<?php
session_start();
require_once "../backend/connections/config.php"; 

// Logout functionality
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../admin.php");
    exit();
}

// Get election positions from the database
$positions_query = "SELECT * FROM positions ORDER BY display_order";
$positions_result = $conn->query($positions_query);
$positions = [];

if ($positions_result->num_rows > 0) {
    while ($row = $positions_result->fetch_assoc()) {
        $positions[] = $row;
    }
}

// Function to get candidates by position
function getCandidatesByPosition($conn, $position_id) {
    $candidates_query = "SELECT c.*, 
                        c.name as candidate_name,
                        c.photo_url,
                        COUNT(vd.id) as vote_count
                     FROM candidates c
                     LEFT JOIN vote_details vd ON c.id = vd.candidate_id
                     WHERE c.position_id = ?
                     GROUP BY c.id
                     ORDER BY vote_count DESC";
                     
    $stmt = $conn->prepare($candidates_query);
    $stmt->bind_param("i", $position_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $candidates = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $candidates[] = $row;
        }
    }
    
    return $candidates;
}

// Function to get total votes cast for a position
function getTotalVotesByPosition($conn, $position_id) {
    $votes_query = "SELECT COUNT(*) as total 
                    FROM vote_details vd
                    JOIN candidates c ON vd.candidate_id = c.id
                    WHERE c.position_id = ?";
                    
    $stmt = $conn->prepare($votes_query);
    $stmt->bind_param("i", $position_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['total'];
    }
    
    return 0;
}

// Get college abbreviations and names for filtering
$colleges = [
    'all' => 'All Colleges',
    'sr' => 'Student Republic',
    'cas' => 'College of Arts and Sciences',
    'cea' => 'College of Engineering and Architecture',
    'coe' => 'College of Education',
    'cit' => 'College of Industrial Technology'
];

// Handle college filter
$selected_college = isset($_GET['college']) ? $_GET['college'] : 'all';

// Get current date and time
$current_datetime = date('F d, Y - h:i A');

// Function to calculate percentage
function calculatePercentage($votes, $total) {
    if ($total <= 0) return 0;
    return round(($votes / $total) * 100, 1);
}

// Function to get candidate's color based on rank
function getCandidateColor($index) {
    $colors = ['#0c3b5d', '#1a64a0', '#2980b9', '#3498db', '#5dade2', '#85c1e9'];
    return $colors[min($index, count($colors) - 1)];
}

// Function to get candidate image
function getCandidateImage($photo_url) {
    if (!empty($photo_url)) {
        $image_path = "../uploads/candidates/" . $photo_url;
        if (file_exists($image_path)) {
            return $image_path;
        }
    }
    
    return "../assets/images/default-candidate.png";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Election Results - ISATU Election System</title>
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
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
        }
        
        /* Sidebar styles */
        .sidebar {
            background-color: var(--isatu-primary);
            width: 280px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding-top: 20px;
            color: white;
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar-collapsed {
            width: 70px;
        }
        
        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            transition: all 0.3s;
            padding: 20px;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        .main-content-expanded {
            margin-left: 70px;
            width: calc(100% - 70px);
        }
        
        .sidebar-header {
            padding: 0 20px 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .isatu-logo {
            max-width: 100px;
            margin-bottom: 15px;
        }
        
        .logo-text {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            white-space: nowrap;
        }
        
        .logo-subtext {
            font-size: 0.9rem;
            opacity: 0.8;
            margin: 0;
            white-space: nowrap;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.85);
            border-radius: 8px;
            margin: 5px 15px;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-link i {
            margin-right: 15px;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        
        .nav-link span {
            white-space: nowrap;
        }
        
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            background-color: var(--isatu-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-weight: bold;
            color: var(--isatu-primary);
        }
        
        .admin-name {
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .admin-role {
            font-size: 0.75rem;
            opacity: 0.7;
            white-space: nowrap;
        }
        
        .toggle-btn {
            cursor: pointer;
            width: 30px;
            height: 30px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s;
        }
        
        .toggle-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        /* Page header styles */
        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--isatu-primary);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        
        .page-title i {
            margin-right: 12px;
            font-size: 1.6rem;
        }
        
        /* Filter controls */
        .filter-controls {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        /* Results section */
        .position-card {
            background-color: white;
            border-radius: 12px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            width: 400px; /* Fixed smaller width */
            display: inline-block;
            margin-right: 20px;
            vertical-align: top;
        }

        .position-card:nth-child(2n) {
            margin-right: 0;
        }

        .positions-container {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }

        
        .position-header {
            background-color: var(--isatu-primary);
            color: white;
            padding: 15px 20px;
            font-size: 1.3rem;
            font-weight: 600;
        }
        
        .position-body {
            padding: 20px;
        }
        
        /* Candidate cards */
        .candidates-grid {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .candidate-card {
            width: 100%;
            margin-bottom: 0;
            border: 1px solid #e1e1e1;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s;
        }
        
        .candidate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .candidate-image-container {
            height: 200px;
            overflow: hidden;
            position: relative;
        }
        
        .candidate-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .candidate-rank {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--isatu-secondary);
            color: var(--isatu-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .candidate-info {
            padding: 15px;
        }
        
        .candidate-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--isatu-primary);
        }
        
        .candidate-college {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .vote-bar {
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            margin-bottom: 10px;
            overflow: hidden;
        }
        
        .vote-progress {
            height: 100%;
            border-radius: 10px;
            transition: width 1.5s ease-in-out;
        }
        
        .vote-stats {
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }
        
        .vote-count {
            font-weight: 600;
        }
        
        .vote-percentage {
            color: #6c757d;
        }
        
        /* Winner highlight */
        .winner-badge {
            background-color: var(--isatu-secondary);
            color: var(--isatu-primary);
            font-weight: bold;
            padding: 5px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-left: 10px;
            font-size: 0.8rem;
            vertical-align: middle;
        }
        
        /* Results summary */
        .results-summary {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .summary-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--isatu-primary);
            margin-bottom: 15px;
        }
        
        .timestamp {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 30px;
            text-align: right;
        }
        
        /* Export button */
        .export-btn {
            background-color: var(--isatu-primary);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .export-btn:hover {
            background-color: var(--isatu-dark);
        }
        
        /* No candidates message */
        .no-candidates {
            text-align: center;
            padding: 30px;
            color: #6c757d;
            font-style: italic;
        }
        
        /* Position title for all colleges view */
        .position-title {
            color: var(--isatu-primary);
            padding-bottom: 10px;
            border-bottom: 2px solid var(--isatu-secondary);
            margin-bottom: 20px;
            font-weight: 600;
        }

        .nav-tabs .nav-link {
            color: var(--isatu-primary);
            border-radius: 0;
            padding: 10px 15px;
            font-weight: 500;
        }

        .nav-tabs .nav-link.active {
            color: var(--isatu-primary);
            border-color: var(--isatu-primary);
            border-bottom-color: transparent;
            font-weight: 600;
        }

        .nav-tabs .nav-link:hover:not(.active) {
            border-color: transparent;
            background-color: rgba(12, 59, 93, 0.05);
        }

        .position-cards {
            margin-bottom: 40px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                width: 70px;
            }
            
            .main-content {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .sidebar-expanded, .sidebar-collapsed {
                width: 70px;
            }
            
            .main-content-expanded {
                margin-left: 70px;
                width: calc(100% - 70px);
            }
            
            .nav-link span, .admin-name, .admin-role, .logo-text, .logo-subtext {
                display: none;
            }
            
            .toggle-btn {
                display: none;
            }
            
            .sidebar-header {
                padding: 15px 0;
            }
            
            .nav-link {
                justify-content: center;
                padding: 12px;
            }
            
            .nav-link i {
                margin-right: 0;
            }
            
            .admin-info {
                justify-content: center;
            }
            
            .admin-avatar {
                margin-right: 0;
            }
            
            .position-card {
                width: 100%;
                margin-right: 0;
            }
        }
        
        @media (max-width: 768px) {
            .candidates-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../assets/logo/ISAT-U-logo-2.png" alt="ISATU Logo" class="isatu-logo">
            <p class="logo-text">ISATU Admin</p>
            <p class="logo-subtext">Election System</p>
        </div>
        
        <div class="mt-4">
            <a href="admin_dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="manage_students.php" class="nav-link">
                <i class="bi bi-people"></i>
                <span>Manage Students</span>
            </a>
            <a href="manage_candidates.php" class="nav-link">
                <i class="bi bi-person-badge"></i>
                <span>Manage Candidates</span>
            </a>
            <a href="election_results.php" class="nav-link active">
                <i class="bi bi-bar-chart"></i>
                <span>Election Results</span>
            </a>
            <a href="election_results.php?logout=1" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
            <div class="admin-info">
                <div class="admin-avatar">
                    A
                </div>
                <div>
                    <div class="admin-name">Admin</div>
                    <div class="admin-role">Administrator</div>
                </div>
            </div>
            <div class="toggle-btn" id="toggleBtn">
                <i class="bi bi-chevron-left" id="toggleIcon"></i>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-title">
            <i class="bi bi-bar-chart"></i> Election Results
        </div>
        
        <!-- Filter Controls -->
        <div class="filter-controls">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <form method="GET" action="election_results.php" class="d-flex align-items-center">
                        <label for="college" class="me-3">Filter by College:</label>
                        <select name="college" id="college" class="form-select" style="max-width: 300px;" onchange="this.form.submit()">
                            <?php foreach ($colleges as $code => $name): ?>
                                <option value="<?php echo $code; ?>" <?php echo ($selected_college == $code) ? 'selected' : ''; ?>>
                                    <?php echo $name; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                    <a href="control/export_results.php?college=<?php echo $selected_college; ?>" class="export-btn">
                        <i class="bi bi-download me-2"></i>Export Results to Excel
                    </a>
                    <a href="election_results.php?view=all_colleges" class="btn btn-outline-primary ms-2 <?php echo (isset($_GET['view']) && $_GET['view'] == 'all_colleges') ? 'active' : ''; ?>">
                        <i class="bi bi-grid-3x3-gap me-1"></i>View All Colleges
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Results Summary -->
        <div class="results-summary">
            <h4 class="summary-title">
                <i class="bi bi-info-circle me-2"></i>Results Summary
            </h4>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Total Positions</h5>
                            <p class="card-text fs-4 fw-bold text-primary">
                                <?php echo count($positions); ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Total Candidates</h5>
                            <p class="card-text fs-4 fw-bold text-primary">
                                <?php 
                                $total_candidates_query = "SELECT COUNT(*) as total FROM candidates";
                                $total_candidates_result = $conn->query($total_candidates_query);
                                $total_candidates = $total_candidates_result->fetch_assoc()['total'];
                                echo $total_candidates;
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Total Votes Cast</h5>
                            <p class="card-text fs-4 fw-bold text-primary">
                                <?php 
                                $total_votes_query = "SELECT COUNT(*) as total FROM vote_details";
                                $total_votes_result = $conn->query($total_votes_query);
                                $total_votes = $total_votes_result->fetch_assoc()['total'];
                                echo $total_votes;
                                ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Check if we have any candidates at all -->
        <?php
        $any_candidates = false;
        foreach ($positions as $position) {
            $candidates = getCandidatesByPosition($conn, $position['id']);
            if (!empty($candidates)) {
                $any_candidates = true;
                break;
            }
        }
        
        if (!$any_candidates): 
        ?>
        <div class="position-card">
            <div class="position-body">
                <div class="no-candidates">
                    <i class="bi bi-exclamation-circle fs-1 mb-3 text-muted"></i>
                    <h4>No candidates found</h4>
                    <p>There are currently no candidates registered in the system or no votes have been cast yet.</p>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Check if we're in "View All Colleges" mode -->
        <?php
        $view_all_colleges = isset($_GET['view']) && $_GET['view'] == 'all_colleges';

        // If we're showing all colleges view, display a different layout
        if ($view_all_colleges):
        ?>

        <div class="position-cards">
            <?php
            // Define the desired order of positions
            $position_order = ['President', 'Vice President', 'Senator'];
            $processed_positions = [];

            // Process positions in desired order first
            foreach ($position_order as $position_name):
                foreach ($positions as $position):
                    // Skip if this position doesn't match our current desired position
                    if ($position['name'] != $position_name) continue;
                    
                    // Mark as processed
                    $processed_positions[] = $position['id'];
                    
                    // Get candidates for this position
                    $candidates = getCandidatesByPosition($conn, $position['id']);
                    
                    // Skip positions with no candidates
                    if (empty($candidates)) continue;
                    
                    // Get total votes for this position
                    $total_position_votes = getTotalVotesByPosition($conn, $position['id']);
                    
                    // Group candidates by college
                    $candidates_by_college = [];
                    foreach ($candidates as $candidate) {
                        $college_code = strtolower($candidate['college_code']);
                        if (!isset($candidates_by_college[$college_code])) {
                            $candidates_by_college[$college_code] = [];
                        }
                        $candidates_by_college[$college_code][] = $candidate;
                    }
                    
                    // Display position card
                    ?>
                    <div class="mb-5">
                        <h3 class="position-title mb-4"><?php echo $position['name']; ?></h3>
                        
                        <!-- Display tabs for college selection -->
                        <ul class="nav nav-tabs mb-4" id="collegeTab-<?php echo $position['id']; ?>" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="all-tab-<?php echo $position['id']; ?>" data-bs-toggle="tab" 
                                        data-bs-target="#all-content-<?php echo $position['id']; ?>" type="button" role="tab" aria-selected="true">
                                    All Colleges
                                </button>
                            </li>
                            <?php foreach ($colleges as $code => $name): 
                                if ($code == 'all') continue; 
                                $has_candidates = isset($candidates_by_college[$code]) && !empty($candidates_by_college[$code]);
                                if (!$has_candidates) continue;
                            ?>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="<?php echo $code; ?>-tab-<?php echo $position['id']; ?>" data-bs-toggle="tab" 
                                        data-bs-target="#<?php echo $code; ?>-content-<?php echo $position['id']; ?>" type="button" role="tab" aria-selected="false">
                                    <?php echo $name; ?>
                                </button>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        
                        <!-- Tab content -->
                        <div class="tab-content" id="collegeTabContent-<?php echo $position['id']; ?>">
                            <!-- All colleges tab -->
                            <div class="tab-pane fade show active" id="all-content-<?php echo $position['id']; ?>" role="tabpanel">
                                <div class="positions-container">
                                    <?php 
                                    // Display each candidate in their own card
                                    foreach ($candidates as $index => $candidate): 
                                        $vote_percentage = calculatePercentage($candidate['vote_count'], $total_position_votes);
                                        $is_winner = ($index === 0 && $candidate['vote_count'] > 0);
                                    ?>
                                    <div class="position-card">
                                        <div class="position-header">
                                            <?php echo strtoupper($candidate['college_code']); ?>
                                        </div>
                                        <div class="position-body">
                                            <div class="candidates-grid">
                                                <div class="candidate-card">
                                                    <div class="candidate-image-container">
                                                        <img src="<?php echo getCandidateImage($candidate['photo_url']); ?>" alt="<?php echo $candidate['candidate_name']; ?>" class="candidate-image">
                                                        <div class="candidate-rank"><?php echo $index + 1; ?></div>
                                                    </div>
                                                    <div class="candidate-info">
                                                        <div class="candidate-name">
                                                            <?php echo $candidate['candidate_name']; ?>
                                                            <?php if ($is_winner): ?>
                                                                <span class="winner-badge">
                                                                    <i class="bi bi-trophy"></i> Winner
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="candidate-college">
                                                            <?php echo strtoupper($candidate['college_code']); ?>
                                                        </div>
                                                        <div class="vote-bar">
                                                            <div class="vote-progress" style="width: <?php echo $vote_percentage; ?>%; background-color: <?php echo getCandidateColor($index); ?>;"></div>
                                                        </div>
                                                        <div class="vote-stats">
                                                            <div class="vote-count"><?php echo $candidate['vote_count']; ?> votes</div>
                                                            <div class="vote-percentage"><?php echo $vote_percentage; ?>%</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <!-- Individual college tabs -->
                            <?php foreach ($colleges as $code => $name): 
                                if ($code == 'all') continue;
                                $has_candidates = isset($candidates_by_college[$code]) && !empty($candidates_by_college[$code]);
                                if (!$has_candidates) continue;
                            ?>
                            <div class="tab-pane fade" id="<?php echo $code; ?>-content-<?php echo $position['id']; ?>" role="tabpanel">
                                <div class="positions-container">
                                    <?php 
                                    if (isset($candidates_by_college[$code])):
                                        foreach ($candidates_by_college[$code] as $index => $candidate): 
                                            $vote_percentage = calculatePercentage($candidate['vote_count'], $total_position_votes);
                                            $is_winner = ($index === 0 && $candidate['vote_count'] > 0);
                                    ?>
                                    <div class="position-card">
                                        <div class="position-header">
                                            <?php echo strtoupper($candidate['college_code']); ?>
                                        </div>
                                        <div class="position-body">
                                            <div class="candidates-grid">
                                                <div class="candidate-card">
                                                    <div class="candidate-image-container">
                                                        <img src="<?php echo getCandidateImage($candidate['photo_url']); ?>" alt="<?php echo $candidate['candidate_name']; ?>" class="candidate-image">
                                                        <div class="candidate-rank"><?php echo $index + 1; ?></div>
                                                    </div>
                                                    <div class="candidate-info">
                                                        <div class="candidate-name">
                                                            <?php echo $candidate['candidate_name']; ?>
                                                            <?php if ($is_winner): ?>
                                                                <span class="winner-badge">
                                                                    <i class="bi bi-trophy"></i> Winner
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class="candidate-college">
                                                            <?php echo strtoupper($candidate['college_code']); ?>
                                                        </div>
                                                        <div class="vote-bar">
                                                            <div class="vote-progress" style="width: <?php echo $vote_percentage; ?>%; background-color: <?php echo getCandidateColor($index); ?>;"></div>
                                                        </div>
                                                        <div class="vote-stats">
                                                            <div class="vote-count"><?php echo $candidate['vote_count']; ?> votes</div>
                                                            <div class="vote-percentage"><?php echo $vote_percentage; ?>%</div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <?php 
                                        endforeach;
                                    endif;
                                    ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php
                endforeach;
            endforeach;

            foreach ($positions as $position):
                // Skip if this position was already processed
                if (in_array($position['id'], $processed_positions)) continue;
                
                // Get candidates for this position
                $candidates = getCandidatesByPosition($conn, $position['id']);
                
                // Skip positions with no candidates
                if (empty($candidates)) continue;
                
                // Get total votes for this position
                $total_position_votes = getTotalVotesByPosition($conn, $position['id']);
                
                // Group candidates by college
                $candidates_by_college = [];
                foreach ($candidates as $candidate) {
                    $college_code = strtolower($candidate['college_code']);
                    if (!isset($candidates_by_college[$college_code])) {
                        $candidates_by_college[$college_code] = [];
                    }
                    $candidates_by_college[$college_code][] = $candidate;
                }
                
                // Display position card
                ?>
                <div class="mb-5">
                    <h3 class="position-title mb-4"><?php echo $position['name']; ?></h3>
                    
                    <!-- Display tabs for college selection -->
                    <ul class="nav nav-tabs mb-4" id="collegeTab-<?php echo $position['id']; ?>" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="all-tab-<?php echo $position['id']; ?>" data-bs-toggle="tab" 
                                    data-bs-target="#all-content-<?php echo $position['id']; ?>" type="button" role="tab" aria-selected="true">
                                All Colleges
                            </button>
                        </li>
                        <?php foreach ($colleges as $code => $name): 
                            if ($code == 'all') continue; 
                            $has_candidates = isset($candidates_by_college[$code]) && !empty($candidates_by_college[$code]);
                            if (!$has_candidates) continue;
                        ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="<?php echo $code; ?>-tab-<?php echo $position['id']; ?>" data-bs-toggle="tab" 
                                    data-bs-target="#<?php echo $code; ?>-content-<?php echo $position['id']; ?>" type="button" role="tab" aria-selected="false">
                                <?php echo $name; ?>
                            </button>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    
                    <!-- Tab content -->
                    <div class="tab-content" id="collegeTabContent-<?php echo $position['id']; ?>">
                        <!-- All colleges tab -->
                        <div class="tab-pane fade show active" id="all-content-<?php echo $position['id']; ?>" role="tabpanel">
                            <div class="positions-container">
                                <?php 
                                // Display each candidate in their own card
                                foreach ($candidates as $index => $candidate): 
                                    $vote_percentage = calculatePercentage($candidate['vote_count'], $total_position_votes);
                                    $is_winner = ($index === 0 && $candidate['vote_count'] > 0);
                                ?>
                                <div class="position-card">
                                    <div class="position-header">
                                        <?php echo strtoupper($candidate['college_code']); ?>
                                    </div>
                                    <div class="position-body">
                                        <div class="candidates-grid">
                                            <div class="candidate-card">
                                                <div class="candidate-image-container">
                                                    <img src="<?php echo getCandidateImage($candidate['photo_url']); ?>" alt="<?php echo $candidate['candidate_name']; ?>" class="candidate-image">
                                                    <div class="candidate-rank"><?php echo $index + 1; ?></div>
                                                </div>
                                                <div class="candidate-info">
                                                    <div class="candidate-name">
                                                        <?php echo $candidate['candidate_name']; ?>
                                                        <?php if ($is_winner): ?>
                                                            <span class="winner-badge">
                                                                <i class="bi bi-trophy"></i> Winner
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="candidate-college">
                                                        <?php echo strtoupper($candidate['college_code']); ?>
                                                    </div>
                                                    <div class="vote-bar">
                                                        <div class="vote-progress" style="width: <?php echo $vote_percentage; ?>%; background-color: <?php echo getCandidateColor($index); ?>;"></div>
                                                    </div>
                                                    <div class="vote-stats">
                                                        <div class="vote-count"><?php echo $candidate['vote_count']; ?> votes</div>
                                                        <div class="vote-percentage"><?php echo $vote_percentage; ?>%</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <!-- Individual college tabs -->
                        <?php foreach ($colleges as $code => $name): 
                            if ($code == 'all') continue;
                            $has_candidates = isset($candidates_by_college[$code]) && !empty($candidates_by_college[$code]);
                            if (!$has_candidates) continue;
                        ?>
                        <div class="tab-pane fade" id="<?php echo $code; ?>-content-<?php echo $position['id']; ?>" role="tabpanel">
                            <div class="positions-container">
                                <?php 
                                if (isset($candidates_by_college[$code])):
                                    foreach ($candidates_by_college[$code] as $index => $candidate): 
                                        $vote_percentage = calculatePercentage($candidate['vote_count'], $total_position_votes);
                                        $is_winner = ($index === 0 && $candidate['vote_count'] > 0);
                                ?>
                                <div class="position-card">
                                    <div class="position-header">
                                        <?php echo strtoupper($candidate['college_code']); ?>
                                    </div>
                                    <div class="position-body">
                                        <div class="candidates-grid">
                                            <div class="candidate-card">
                                                <div class="candidate-image-container">
                                                    <img src="<?php echo getCandidateImage($candidate['photo_url']); ?>" alt="<?php echo $candidate['candidate_name']; ?>" class="candidate-image">
                                                    <div class="candidate-rank"><?php echo $index + 1; ?></div>
                                                </div>
                                                <div class="candidate-info">
                                                    <div class="candidate-name">
                                                        <?php echo $candidate['candidate_name']; ?>
                                                        <?php if ($is_winner): ?>
                                                            <span class="winner-badge">
                                                                <i class="bi bi-trophy"></i> Winner
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="candidate-college">
                                                        <?php echo strtoupper($candidate['college_code']); ?>
                                                    </div>
                                                    <div class="vote-bar">
                                                        <div class="vote-progress" style="width: <?php echo $vote_percentage; ?>%; background-color: <?php echo getCandidateColor($index); ?>;"></div>
                                                    </div>
                                                    <div class="vote-stats">
                                                        <div class="vote-count"><?php echo $candidate['vote_count']; ?> votes</div>
                                                        <div class="vote-percentage"><?php echo $vote_percentage; ?>%</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php 
                                    endforeach;
                                endif;
                                ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <!-- Original code for filtered view, modified to display each candidate separately -->
        <!-- Position Results -->
        <div class="positions-container">
        <?php
        // Define the desired order of positions
        $position_order = ['President', 'Vice President', 'Senator'];
        $processed_positions = [];

        // Process positions in desired order first
        foreach ($position_order as $position_name):
            foreach ($positions as $position):
                // Skip if this position doesn't match our current desired position
                if ($position['name'] != $position_name) continue;
                
                // Mark as processed
                $processed_positions[] = $position['id'];
                
                // Get candidates for this position
                $candidates = getCandidatesByPosition($conn, $position['id']);
                
                // Skip positions with no candidates
                if (empty($candidates)) continue;
                
                // Get total votes for this position
                $total_position_votes = getTotalVotesByPosition($conn, $position['id']);
                
                // Filter candidates by college if needed
                $filtered_candidates = [];
                foreach ($candidates as $candidate) {
                    if ($selected_college == 'all' || strtolower($candidate['college_code']) == $selected_college) {
                        $filtered_candidates[] = $candidate;
                    }
                }
                
                // Skip if no candidates match the filter
                if (empty($filtered_candidates)) continue;
                
                // Display each candidate in a separate card regardless of position type
                foreach ($filtered_candidates as $index => $candidate):
                    $vote_percentage = calculatePercentage($candidate['vote_count'], $total_position_votes);
                    $is_winner = ($index === 0 && $candidate['vote_count'] > 0);
                    ?>
                    <div class="position-card">
                        <div class="position-header">
                            <?php echo $position['name']; ?>
                        </div>
                        <div class="position-body">
                            <div class="candidates-grid">
                                <div class="candidate-card">
                                    <div class="candidate-image-container">
                                        <img src="<?php echo getCandidateImage($candidate['photo_url']); ?>" alt="<?php echo $candidate['candidate_name']; ?>" class="candidate-image">
                                        <div class="candidate-rank"><?php echo $index + 1; ?></div>
                                    </div>
                                    <div class="candidate-info">
                                        <div class="candidate-name">
                                            <?php echo $candidate['candidate_name']; ?>
                                            <?php if ($is_winner): ?>
                                                <span class="winner-badge">
                                                    <i class="bi bi-trophy"></i> Winner
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="candidate-college">
                                            <?php echo strtoupper($candidate['college_code']); ?>
                                        </div>
                                        <div class="vote-bar">
                                            <div class="vote-progress" style="width: <?php echo $vote_percentage; ?>%; background-color: <?php echo getCandidateColor($index); ?>;"></div>
                                        </div>
                                        <div class="vote-stats">
                                            <div class="vote-count"><?php echo $candidate['vote_count']; ?> votes</div>
                                            <div class="vote-percentage"><?php echo $vote_percentage; ?>%</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php 
                endforeach;
            endforeach;
        endforeach;

        // Now process any remaining positions that weren't in our specific order
        foreach ($positions as $position):
            // Skip if this position was already processed
            if (in_array($position['id'], $processed_positions)) continue;
            
            // Get candidates for this position
            $candidates = getCandidatesByPosition($conn, $position['id']);
            
            // Skip positions with no candidates
            if (empty($candidates)) continue;
            
            // Get total votes for this position
            $total_position_votes = getTotalVotesByPosition($conn, $position['id']);
            
            // Filter candidates by college if needed
            $filtered_candidates = [];
            foreach ($candidates as $candidate) {
                if ($selected_college == 'all' || strtolower($candidate['college_code']) == $selected_college) {
                    $filtered_candidates[] = $candidate;
                }
            }
            
            // Skip if no candidates match the filter
            if (empty($filtered_candidates)) continue;
            
            // Display each candidate in a separate card
            foreach ($filtered_candidates as $index => $candidate):
                $vote_percentage = calculatePercentage($candidate['vote_count'], $total_position_votes);
                $is_winner = ($index === 0 && $candidate['vote_count'] > 0);
                ?>
                <div class="position-card">
                    <div class="position-header">
                        <?php echo $position['name']; ?>
                    </div>
                    <div class="position-body">
                        <div class="candidates-grid">
                            <div class="candidate-card">
                                <div class="candidate-image-container">
                                    <img src="<?php echo getCandidateImage($candidate['photo_url']); ?>" alt="<?php echo $candidate['candidate_name']; ?>" class="candidate-image">
                                    <div class="candidate-rank"><?php echo $index + 1; ?></div>
                                </div>
                                <div class="candidate-info">
                                    <div class="candidate-name">
                                        <?php echo $candidate['candidate_name']; ?>
                                        <?php if ($is_winner): ?>
                                            <span class="winner-badge">
                                                <i class="bi bi-trophy"></i> Winner
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="candidate-college">
                                        <?php echo strtoupper($candidate['college_code']); ?>
                                    </div>
                                    <div class="vote-bar">
                                        <div class="vote-progress" style="width: <?php echo $vote_percentage; ?>%; background-color: <?php echo getCandidateColor($index); ?>;"></div>
                                    </div>
                                    <div class="vote-stats">
                                        <div class="vote-count"><?php echo $candidate['vote_count']; ?> votes</div>
                                        <div class="vote-percentage"><?php echo $vote_percentage; ?>%</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        
        <div class="timestamp">
            <i class="bi bi-clock"></i> Last updated: <?php echo $current_datetime; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Toggle sidebar functionality
        document.getElementById('toggleBtn').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleIcon = document.getElementById('toggleIcon');
            
            sidebar.classList.toggle('sidebar-collapsed');
            mainContent.classList.toggle('main-content-expanded');
            
            if (sidebar.classList.contains('sidebar-collapsed')) {
                toggleIcon.classList.remove('bi-chevron-left');
                toggleIcon.classList.add('bi-chevron-right');
            } else {
                toggleIcon.classList.remove('bi-chevron-right');
                toggleIcon.classList.add('bi-chevron-left');
            }
        });
        
        // Animation for progress bars
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation to vote progress bars
            setTimeout(function() {
                const voteBars = document.querySelectorAll('.vote-progress');
                voteBars.forEach(bar => {
                    const width = bar.style.width;
                    bar.style.width = '0%';
                    setTimeout(() => {
                        bar.style.width = width;
                    }, 100);
                });
            }, 300);
        });
        
        // Function to export results
        function exportResults() {
            window.location.href = "export_results.php?college=<?php echo $selected_college; ?>";
        }
    </script>
</body>
</html>
