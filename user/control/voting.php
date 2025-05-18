<?php
session_start();
require_once "../../backend/connections/config.php"; 

// Check if student is logged in
if (!isset($_SESSION['student_id']) || !isset($_SESSION['selected_college'])) {
    header("Location: index.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$student_number = $_SESSION['student_number'];
$college_code = $_SESSION['selected_college'];

// College names for display
$college_names = [
    'sr' => 'Student Republic',
    'cas' => 'College of Arts and Sciences',
    'cea' => 'College of Engineering and Architecture',
    'coe' => 'College of Education',
    'cit' => 'College of Industrial Technology'
];

// Position hierarchy mapping (lower number = higher position)
$position_hierarchy = [
    'governor' => 1,
    'vice governor' => 2,
    'secretary' => 3,
    'assistant secretary' => 4,
    'treasurer' => 5,
    'assistant treasurer' => 6,
    'auditor' => 7,
    'assistant auditor' => 8,
    'business manager' => 9,
    'assistant business manager' => 10,
    'public relation officer' => 11,
    'social media manager' => 12,
    'content manager' => 13,
    'bs math representative' => 14,
    'bs humserve representative' => 15,
    'bael representative' => 16,
    'bscd representative' => 17,
    'bs bio representative' => 18,
    // SR positions
    'president' => 1,
    'vice president' => 2,
    'senator' => 3
    // Add other positions as needed
];

// Get candidates for the specific college and their positions
$positions = [];
$candidates = [];

// Query to get positions that have candidates for this college
// Modified to ensure correct hierarchical order - using ASC for display_order where lower numbers = higher rank
$position_query = "SELECT DISTINCT p.* 
                  FROM positions p 
                  JOIN candidates c ON p.id = c.position_id 
                  WHERE c.college_code = ? 
                  ORDER BY p.display_order ASC";
                  
$stmt = $conn->prepare($position_query);
$stmt->bind_param("s", $college_code);
$stmt->execute();
$position_result = $stmt->get_result();

while ($position = $position_result->fetch_assoc()) {
    // Set max_votes to 12 for the Senators position
    if (strtolower($position['name']) === 'senator' || strtolower($position['name']) === 'senators') {
        $position['max_votes'] = 12;
    }
    $positions[$position['id']] = $position;
    
    // Add hierarchical value for custom sorting
    $position_name_lower = strtolower($position['name']);
    $positions[$position['id']]['hierarchy_value'] = isset($position_hierarchy[$position_name_lower]) 
        ? $position_hierarchy[$position_name_lower] 
        : 999; // Default to a high number for unknown positions
}
$stmt->close();

// Get candidates for the specific college
$candidate_query = "SELECT c.*, p.name as party_name, po.name as position_name 
                    FROM candidates c 
                    LEFT JOIN parties p ON c.party_id = p.id 
                    LEFT JOIN positions po ON c.position_id = po.id 
                    WHERE c.college_code = ? 
                    ORDER BY po.display_order ASC, c.id ASC";
$stmt = $conn->prepare($candidate_query);
$stmt->bind_param("s", $college_code);
$stmt->execute();
$candidate_result = $stmt->get_result();

while ($candidate = $candidate_result->fetch_assoc()) {
    if (!isset($candidates[$candidate['position_id']])) {
        $candidates[$candidate['position_id']] = [];
    }
    $candidates[$candidate['position_id']][] = $candidate;
}
$stmt->close();

// Custom sorting of positions by hierarchy (lower number = higher position)
uasort($positions, function($a, $b) {
    return $a['hierarchy_value'] - $b['hierarchy_value'];
});

// Process voting form submission
$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['submit_vote'])) {
    // Get active election ID
    $election_id = 1; // Default to 1 if no active election setting is found
    $election_query = "SELECT id FROM election_settings WHERE is_active = 1 LIMIT 1";
    $election_result = $conn->query($election_query);
    if ($election_result && $election_result->num_rows > 0) {
        $election_row = $election_result->fetch_assoc();
        $election_id = $election_row['id'];
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Create vote record
        $create_vote_stmt = $conn->prepare("INSERT INTO votes (student_id, election_id, created_at) VALUES (?, ?, NOW())");
        $create_vote_stmt->bind_param("ii", $student_id, $election_id);
        $create_vote_stmt->execute();
        $vote_id = $conn->insert_id;
        $create_vote_stmt->close();
        
        // Insert vote details for each position
        foreach ($positions as $position) {
            $position_id = $position['id'];
            $max_votes = $position['max_votes'];
            
            // Check if this position has candidates and if user voted
            if (isset($_POST['vote_' . $position_id]) && !empty($_POST['vote_' . $position_id])) {
                $voted_candidates = $_POST['vote_' . $position_id];
                
                // Convert to array if single vote
                if (!is_array($voted_candidates)) {
                    $voted_candidates = [$voted_candidates];
                }
                
                // Check if number of votes exceeds max allowed
                if (count($voted_candidates) > $max_votes) {
                    throw new Exception("Too many candidates selected for " . $position['name']);
                }
                
                // Record each vote
                foreach ($voted_candidates as $candidate_id) {
                    $vote_detail_stmt = $conn->prepare("INSERT INTO vote_details (vote_id, position_id, candidate_id) VALUES (?, ?, ?)");
                    $vote_detail_stmt->bind_param("iii", $vote_id, $position_id, $candidate_id);
                    $vote_detail_stmt->execute();
                    $vote_detail_stmt->close();
                }
            }
        }
        
        // Commit transaction
        $conn->commit();
        
        // Set success message and redirect to a thank you page
        $_SESSION['vote_success'] = true;
        header("Location: thank_you.php");
        exit();
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error_message = "Error: " . $e->getMessage();
    }
}

// Function to check if position is for senators
function isSenatorsPosition($position) {
    return strtolower($position['name']) === 'senator' || strtolower($position['name']) === 'senators';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vote - <?php echo $college_names[$college_code]; ?> - ISATU Voting System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --isatu-primary: #0c3b5d;    /* ISATU navy blue */
            --isatu-secondary: #f2c01d;  /* ISATU gold/yellow */
            --isatu-accent: #1a64a0;     /* ISATU lighter blue */
            --isatu-light: #e8f1f8;      /* Light blue background */
            --isatu-dark: #092c48;       /* Darker blue */
        }
        
        body {
            background-color: #f8f9fa;
            background-image: linear-gradient(120deg, #f8f9fa 0%, var(--isatu-light) 100%);
            padding-top: 40px; 
            padding-bottom: 40px; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh; 
        }
        
        .container {
            max-width: 1200px;
        }
        
        h1, h2, h3, h4, h5 {
            color: var(--isatu-primary);
        }
        
        .isatu-header {
            background-color: #fff;
            padding: 30px; 
            border-radius: 16px; 
            box-shadow: 0 8px 20px rgba(0,0,0,0.15); 
            margin-bottom: 40px; 
            position: relative;
            overflow: hidden;
            border-top: 7px solid var(--isatu-primary); 
            border-bottom: 7px solid var(--isatu-secondary);
        }
        
        .voting-card {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            padding: 35px;
            margin-bottom: 30px;
            border-left: 6px solid var(--isatu-primary);
        }
        
        .position-section {
            background-color: rgba(12, 59, 93, 0.05);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border-left: 4px solid var(--isatu-accent);
        }
        
        .position-title {
            color: var(--isatu-primary);
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--isatu-secondary);
            margin-bottom: 20px;
        }
        
        .candidate-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 10px;
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid #eaeaea;
            position: relative;
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        
        .candidate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .candidate-card.selected {
            border: 2px solid var(--isatu-secondary);
            background-color: rgba(242, 192, 29, 0.05);
        }
        
        .candidate-card.selected::after {
            content: "✓";
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--isatu-secondary);
            color: var(--isatu-primary);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .candidate-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 50%;
            border: 3px solid var(--isatu-primary);
            margin: 0 auto;
        }
        
        .candidate-info {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
        }
        
        .candidate-name {
            font-weight: 600;
            color: var(--isatu-primary);
            font-size: 1.3rem;
            margin-bottom: 5px;
        }
        
        .candidate-party {
            display: inline-block;
            background-color: var(--isatu-accent);
            color: white;
            padding: 3px 10px;
            border-radius: 15px;
            font-size: 0.85rem;
            margin-bottom: 10px;
        }
        
        .candidate-platform {
            color: #666;
            font-size: 0.95rem;
        }
        
        .college-badge {
            color: var(--isatu-primary);
            font-size: 3rem;
            border-radius: 15px;
            font-weight: 600;
            display: inline-block;
            text-align: center;
        }
        
        .college-logo {
            height: 150px;
            width: auto;
            max-width: 100%;
            display: block;
            margin: 0 auto 10px;
        }
        
        .student-info {
            background-color: rgba(242, 192, 29, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border: 1px solid var(--isatu-secondary);
        }
        
        .btn-submit-vote {
            background-color: var(--isatu-primary);
            border-color: var(--isatu-primary);
            color: white;
            padding: 15px 40px;
            font-weight: 600;
            border-radius: 40px;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            font-size: 1.2rem;
            text-transform: uppercase;
        }
        
        .btn-submit-vote:hover, .btn-submit-vote:focus {
            background-color: var(--isatu-dark);
            border-color: var(--isatu-dark);
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(12, 59, 93, 0.35);
        }
        
        .alert-voting-info {
            background-color: rgba(12, 59, 93, 0.1);
            border-color: var(--isatu-primary);
            color: var(--isatu-primary);
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .isatu-logo {
            max-width: 120px;
            margin-right: 25px;
        }
        
        .no-candidates {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            color: #6c757d;
        }
        
        .form-check-input:checked {
            background-color: var(--isatu-primary);
            border-color: var(--isatu-primary);
        }
        
        /* Senator selection styles */
        .senator-selection-summary {
            background-color: rgba(242, 192, 29, 0.1);
            padding: 10px 15px;
            border-radius: 10px;
            margin-top: 20px;
            border: 1px solid var(--isatu-secondary);
        }
        
        .senator-chip {
            display: inline-block;
            background-color: var(--isatu-primary);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            margin: 2px;
            font-size: 0.9rem;
        }
        
        .senator-chip .remove-senator {
            cursor: pointer;
            margin-left: 5px;
        }
        
        @media (max-width: 767px) {
            .isatu-header {
                padding: 20px;
                text-align: center;
            }
            
            .isatu-header div {
                margin-top: 10px;
            }
            
            .voting-card {
                padding: 25px;
            }
            
            .isatu-header .d-flex.align-items-center {
                flex-direction: column;
            }
            
            .isatu-logo {
                margin: 0 auto 15px auto;
            }
            
            .candidate-card {
                padding: 15px;
            }
        }
        
        /* Footer styles */
        .footer-text {
            color: var(--isatu-primary);
            font-size: 1rem;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: 30px;
            display: inline-block;
            margin-top: 30px;
        }
        
        .no-positions {
            text-align: center;
            padding: 50px 20px;
            background-color: rgba(12, 59, 93, 0.05);
            border-radius: 15px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="isatu-header d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center">
                <img src="../../assets/logo/ISAT-U-logo-2.png" alt="ISATU Logo" class="isatu-logo">
                <div>
                    <h1 class="mb-0">ISATU Voting System</h1>
                    <p class="text-muted mb-0 fs-5">Iloilo Science and Technology University</p>
                </div>
            </div>
        </div>
        
        <div class="voting-card">
            <div class="text-center mb-4">
                <div class="college-badge p-3">
                    <?php
                    // College logos mapping
                    $college_logos = [
                        'sr' => '../../assets/logo/STUDENT REPUBLIC LOGO.png',
                        'cas' => '../../assets/logo/CASSC.jpg',
                        'cea' => '../../assets/logo/CEASC.jpg',
                        'coe' => '../../assets/logo/COESC.jpg',
                        'cit' => '../../assets/logo/CITSC.png'
                    ];
                    
                    // Default to ISATU logo if no specific college logo is found
                    $logo_path = isset($college_logos[$college_code]) ? $college_logos[$college_code] : 'uploads/ISAT-U-logo-2.png';
                    ?>
                    <img src="<?php echo $logo_path; ?>" alt="<?php echo $college_names[$college_code]; ?> Logo" class="college-logo mb-2">
                    <div><?php echo $college_names[$college_code]; ?></div>
                </div>
                <h2>Election Ballot</h2>
                <p class="text-muted">Select your candidates for each position</p>
            </div>
            
            <div class="student-info">
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Student ID:</strong> <?php echo $student_number; ?></p>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <p class="mb-1"><strong>College:</strong> <?php echo $college_names[$college_code]; ?></p>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-voting-info" role="alert">
                <h5 class="alert-heading"><i class="bi bi-info-circle-fill me-2"></i> Voting Instructions</h5>
                <p>Select your preferred candidate(s) for each position. For Senators, you can select up to 12 candidates.</p>
                <p class="mb-0">Once you submit your vote, it cannot be changed.</p>
            </div>
            
            <form method="POST" action="" id="votingForm">
                <?php if (empty($positions)): ?>
                    <div class="no-positions">
                        <i class="bi bi-calendar-x fs-1 text-muted mb-3"></i>
                        <h4>No Active Elections</h4>
                        <p class="text-muted">There are currently no active positions for voting in this college.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($positions as $position): ?>
                        <div class="position-section">
                            <h3 class="position-title">
                                <?php echo $position['name']; ?>
                                <?php if ($position['max_votes'] > 1 && !isSenatorsPosition($position)): ?>
                                    <span class="badge bg-secondary ms-2">Select up to <?php echo $position['max_votes']; ?></span>
                                <?php endif; ?>
                            </h3>
                            
                            <?php if (isset($candidates[$position['id']]) && !empty($candidates[$position['id']])): ?>
                                <?php if (isSenatorsPosition($position)): ?>
                                    <!-- Senator Position - Show all candidates naturally with selection limit of 12 -->
                                    <div class="senator-candidates">
                                        
                                        <div class="row">
                                            <?php foreach ($candidates[$position['id']] as $candidate): ?>
                                                <div class="col-md-6 col-lg-4 mb-4">
                                                    <div class="candidate-card rounded-4 shadow-sm h-100">
                                                        <div class="text-center mb-3">
                                                            <?php if (!empty($candidate['photo_url'])): ?>
                                                                <img src="../../uploads/candidates/<?php echo $candidate['photo_url']; ?>" alt="<?php echo $candidate['name']; ?>" class="candidate-image mb-3">
                                                            <?php else: ?>
                                                                <img src="uploads/placeholder.png" alt="No Photo" class="candidate-image mb-3">
                                                            <?php endif; ?>
                                                            
                                                            <div class="candidate-info">
                                                                <h4 class="candidate-name"><?php echo $candidate['name']; ?></h4>
                                                                <?php if (!empty($candidate['party_name'])): ?>
                                                                    <span class="candidate-party"><?php echo $candidate['party_name']; ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                        
                                                        <?php if (!empty($candidate['platform'])): ?>
                                                            <div class="candidate-platform mb-3">
                                                                <strong>Platform:</strong> <?php echo $candidate['platform']; ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        
                                                        <div class="form-check mt-auto text-center">
                                                            <input class="form-check-input senator-checkbox position-<?php echo $position['id']; ?>" 
                                                                type="checkbox" 
                                                                name="vote_<?php echo $position['id']; ?>[]" 
                                                                value="<?php echo $candidate['id']; ?>" 
                                                                id="candidate_<?php echo $candidate['id']; ?>"
                                                                data-name="<?php echo $candidate['name']; ?>">
                                                            <label class="form-check-label" for="candidate_<?php echo $candidate['id']; ?>">
                                                                Vote for this Senator
                                                            </label>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <!-- Regular position display - Show all candidates -->
                                    <div class="row">
                                        <?php foreach ($candidates[$position['id']] as $candidate): ?>
                                            <div class="col-md-6 col-lg-4 mb-4">
                                                <div class="candidate-card rounded-4 shadow-sm h-100">
                                                    <div class="text-center mb-3">
                                                        <?php if (!empty($candidate['photo_url'])): ?>
                                                            <img src="../../uploads/candidates/<?php echo $candidate['photo_url']; ?>" alt="<?php echo $candidate['name']; ?>" class="candidate-image mb-3">
                                                        <?php else: ?>
                                                            <img src="uploads/placeholder.png" alt="No Photo" class="candidate-image mb-3">
                                                        <?php endif; ?>
                                                        
                                                        <div class="candidate-info">
                                                            <h4 class="candidate-name"><?php echo $candidate['name']; ?></h4>
                                                            <?php if (!empty($candidate['party_name'])): ?>
                                                                <span class="candidate-party"><?php echo $candidate['party_name']; ?></span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    
                                                    <?php if (!empty($candidate['platform'])): ?>
                                                        <div class="candidate-platform mb-3">
                                                            <strong>Platform:</strong> <?php echo $candidate['platform']; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <div class="form-check mt-auto text-center">
                                                        <?php if ($position['max_votes'] > 1): ?>
                                                            <input class="form-check-input position-<?php echo $position['id']; ?>" type="checkbox" name="vote_<?php echo $position['id']; ?>[]" value="<?php echo $candidate['id']; ?>" id="candidate_<?php echo $candidate['id']; ?>">
                                                        <?php else: ?>
                                                            <input class="form-check-input position-<?php echo $position['id']; ?>" type="radio" name="vote_<?php echo $position['id']; ?>" value="<?php echo $candidate['id']; ?>" id="candidate_<?php echo $candidate['id']; ?>">
                                                        <?php endif; ?>
                                                        <label class="form-check-label" for="candidate_<?php echo $candidate['id']; ?>">
                                                            Vote
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="no-candidates">
                                    <i class="bi bi-person-x fs-4 mb-2"></i>
                                    <p>No candidates available for this position.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="text-center mt-5">
                        <p class="text-muted mb-3">Please review your selections carefully before submitting.</p>
                        <button type="submit" name="submit_vote" class="btn btn-submit-vote">
                            <i class="bi bi-check2-circle me-2"></i> Submit My Vote
                        </button>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="text-center">
            <div class="footer-text">
                <div>
                    <i class="bi bi-building me-2"></i> © 2025 Iloilo Science and Technology University
                </div>
                <div>
                    </i> This website was developed by Larry Denver Biaco
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Regular candidate card click handling
            const candidateCards = document.querySelectorAll('.candidate-card:not(.senator-candidates .candidate-card)');
            candidateCards.forEach(card => {
                card.addEventListener('click', function() {
                    const input = this.querySelector('input[type="radio"], input[type="checkbox"]');
                    if (input) {
                        if (input.type === 'checkbox') {
                            input.checked = !input.checked;
                            this.classList.toggle('selected', input.checked);
                            
                            // Check for max votes
                            const positionClass = input.className.split(' ')
                                .find(cls => cls.startsWith('position-'));
                            if (positionClass) {
                                const positionId = positionClass.split('-')[1];
                                const maxVotes = parseInt(this.closest('.position-section')
                                    .querySelector('.badge')?.textContent.match(/\d+/) || 1);
                                const selectedCount = document.querySelectorAll(`.${positionClass}:checked`).length;
                                
                                if (selectedCount > maxVotes && input.checked) {
                                    input.checked = false;
                                    this.classList.remove('selected');
                                    alert(`You can only select up to ${maxVotes} candidates for this position.`);
                                }
                            }
                        } else {
                            input.checked = true;
                            
                            // Remove selected class from other cards in the same position
                            const positionClass = input.className.split(' ')
                                .find(cls => cls.startsWith('position-'));
                            if (positionClass) {
                                document.querySelectorAll(`.${positionClass}`).forEach(otherInput => {
                                    otherInput.closest('.candidate-card').classList.remove('selected');
                                });
                            }
                            
                            // Add selected class to this card
                            this.classList.add('selected');
                        }
                    }
                });
            });
            
            // Initialize selected state for checked inputs
            const checkedInputs = document.querySelectorAll('input[type="checkbox"]:checked:not(.senator-checkbox), input[type="radio"]:checked');
            checkedInputs.forEach(input => {
                input.closest('.candidate-card')?.classList.add('selected');
            });
            
            // Senator selection handling
            const senatorCheckboxes = document.querySelectorAll('.senator-checkbox');
            const selectedSenatorsContainer = document.getElementById('selected-senators-container');
            const selectedSenatorCount = document.getElementById('selected-senator-count');
            const noSenatorsMessage = document.getElementById('no-senators-message');
            
            // Initialize senator selection tracking
            if (senatorCheckboxes.length > 0) {
                const MAX_SENATORS = 12;
                let selectedSenators = [];
                
                // Update the selected senators display
                const updateSelectedSenatorsDisplay = () => {
                    selectedSenatorCount.textContent = selectedSenators.length;
                    
                    if (selectedSenators.length === 0) {
                        noSenatorsMessage.style.display = 'block';
                        selectedSenatorsContainer.querySelectorAll('.senator-chip').forEach(chip => chip.remove());
                    } else {
                        noSenatorsMessage.style.display = 'none';
                        
                        // Clear existing chips
                        selectedSenatorsContainer.querySelectorAll('.senator-chip').forEach(chip => chip.remove());
                        
                        // Create new chips
                        selectedSenators.forEach(senator => {
                            const chip = document.createElement('span');
                            chip.className = 'senator-chip';
                            chip.innerHTML = `${senator.name} <span class="remove-senator" data-id="${senator.id}">✕</span>`;
                            selectedSenatorsContainer.appendChild(chip);
                        });
                        
                        // Add event listeners to remove buttons
                        selectedSenatorsContainer.querySelectorAll('.remove-senator').forEach(removeBtn => {
                            removeBtn.addEventListener('click', (e) => {
                                const senatorId = e.target.getAttribute('data-id');
                                const checkbox = document.getElementById(senatorId);
                                if (checkbox) {
                                    checkbox.checked = false;
                                    checkbox.closest('.candidate-card')?.classList.remove('selected');
                                    
                                    // Remove from selected senators array
                                    selectedSenators = selectedSenators.filter(senator => senator.id !== senatorId);
                                    updateSelectedSenatorsDisplay();
                                }
                            });
                        });
                    }
                };
                
                // Handle checkbox changes
                senatorCheckboxes.forEach(checkbox => {
                    // Click on senator card should toggle checkbox
                    checkbox.closest('.candidate-card').addEventListener('click', function(e) {
                        // Skip if the click is on the checkbox itself
                        if (e.target !== checkbox) {
                            const isChecked = !checkbox.checked;
                            
                            // Check if we're trying to add and already at max
                            if (isChecked && selectedSenators.length >= MAX_SENATORS) {
                                alert(`You can only select up to ${MAX_SENATORS} senators.`);
                                return;
                            }
                            
                            checkbox.checked = isChecked;
                            this.classList.toggle('selected', isChecked);
                            
                            const senatorId = checkbox.id;
                            const senatorName = checkbox.getAttribute('data-name');
                            
                            if (isChecked) {
                                selectedSenators.push({ id: senatorId, name: senatorName });
                            } else {
                                selectedSenators = selectedSenators.filter(senator => senator.id !== senatorId);
                            }
                            
                            updateSelectedSenatorsDisplay();
                        }
                    });
                    
                    // Direct checkbox change
                    checkbox.addEventListener('change', function() {
                        const senatorId = this.id;
                        const senatorName = this.getAttribute('data-name');
                        const isChecked = this.checked;
                        
                        if (isChecked) {
                            // Check if we're trying to add and already at max
                            if (selectedSenators.length >= MAX_SENATORS) {
                                this.checked = false;
                                alert(`You can only select up to ${MAX_SENATORS} senators.`);
                                return;
                            }
                            
                            selectedSenators.push({ id: senatorId, name: senatorName });
                            this.closest('.candidate-card').classList.add('selected');
                        } else {
                            selectedSenators = selectedSenators.filter(senator => senator.id !== senatorId);
                            this.closest('.candidate-card').classList.remove('selected');
                        }
                        
                        updateSelectedSenatorsDisplay();
                    });
                    
                    // Check initial state
                    if (checkbox.checked) {
                        const senatorId = checkbox.id;
                        const senatorName = checkbox.getAttribute('data-name');
                        selectedSenators.push({ id: senatorId, name: senatorName });
                        checkbox.closest('.candidate-card').classList.add('selected');
                    }
                });
                
                // Initialize the display
                updateSelectedSenatorsDisplay();
                
                // Form submission validation
                document.getElementById('votingForm').addEventListener('submit', function(e) {
                    const senatorPosition = document.querySelector('.senator-candidates');
                    if (senatorPosition) {
                        if (selectedSenators.length === 0) {
                            const confirmNoSenators = confirm("You haven't selected any senators. Are you sure you want to continue without voting for senators?");
                            if (!confirmNoSenators) {
                                e.preventDefault();
                            }
                        }
                    }
                });
            }
        });
    </script>
</body>
</html>