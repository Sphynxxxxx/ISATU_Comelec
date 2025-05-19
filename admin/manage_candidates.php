<?php
session_start();
require_once "../backend/connections/config.php"; 


// Logout functionality
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../admin.php");
    exit();
}

// Get total candidates count
$total_candidates_query = "SELECT COUNT(*) as total FROM candidates";
$total_candidates_result = $conn->query($total_candidates_query);
$total_candidates = $total_candidates_result->fetch_assoc()['total'];

// Get count of candidates by college
$college_counts = [];
$college_query = "SELECT college_code, COUNT(*) as count FROM candidates GROUP BY college_code";
$college_result = $conn->query($college_query);

// Define college names for display
$college_names = [
    'sr' => 'Student Republic',
    'cas' => 'College of Arts and Sciences',
    'cea' => 'College of Engineering and Architecture',
    'coe' => 'College of Education',
    'cit' => 'College of Industrial Technology'
];


// Initialize all colleges with zero count
foreach ($college_names as $code => $name) {
    $college_counts[$code] = 0;
}

// Fill in actual counts from database
if ($college_result->num_rows > 0) {
    while ($row = $college_result->fetch_assoc()) {
        $college_code = strtolower($row['college_code']);
        if (isset($college_counts[$college_code])) {
            $college_counts[$college_code] = $row['count'];
        }
    }
}

// Get recent candidate registrations
$recent_registrations_query = "
    SELECT 
        c.id,
        c.student_id,
        c.name,
        c.college_code,
        c.position,
        c.created_at
    FROM candidates c
    ORDER BY c.created_at DESC
    LIMIT 5
";

$recent_result = $conn->query($recent_registrations_query);
$recent_registrations = [];

if ($recent_result && $recent_result->num_rows > 0) {
    while ($row = $recent_result->fetch_assoc()) {
        $recent_registrations[] = $row;
    }
}

// Function to format time difference
function time_elapsed_string($datetime) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0) {
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    }
    if ($diff->m > 0) {
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    }
    if ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    }
    if ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    }
    if ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    }
    return 'just now';
}

// Current date and time
$current_datetime = date('F d, Y - h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Candidates - ISATU Election System</title>
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
        
        /* Page styles */
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
        
        /* Modern card layout styles */
        .college-cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .college-card-wrapper {
            text-decoration: none;
            color: inherit;
            display: block;
        }

        .college-card {
            background-color: white;
            border-radius: 12px;
            border: none;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            position: relative;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .college-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }

        .college-card-image {
            height: 180px;
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8f9fa;
            padding: 20px;
        }

        .college-card-image img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain; 
            transition: transform 0.3s ease;
        }


        .college-card-image i {
            font-size: 3.5rem;
        }


        .college-card-content {
            padding: 20px;
        }

        .college-badges {
            display: flex;
            gap: 8px;
            margin-bottom: 12px;
        }

        .college-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .sr .college-badge { 
            background-color: rgba(0, 0, 0, 0.1);
            color: #000000;
        }

        .cas .college-badge { 
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }

        .cea .college-badge { 
            background-color: rgba(255, 140, 0, 0.1);
            color: #ff8c00;
        }

        .coe .college-badge { 
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }

        .cit .college-badge { 
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }

        .college-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--isatu-primary);
            margin-bottom: 10px;
        }

        .college-count {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .college-count span {
            font-weight: 600;
            color: var(--isatu-accent);
        }

        .college-card-button {
            background-color: var(--isatu-primary);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            display: inline-block;
            text-align: center;
            text-decoration: none;
            width: fit-content;
            transition: background-color 0.3s;
        }

        .college-card-button:hover {
            background-color: var(--isatu-dark);
        }

        .data-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .college-cards-container {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .college-card-image {
                height: 150px;
            }
        }
        
        .quick-actions {
            display: flex;
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .action-btn {
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 15px 20px;
            text-align: center;
            text-decoration: none;
            color: var(--isatu-primary);
            transition: all 0.3s;
            display: flex;
            align-items: center;
        }
        
        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            border-color: var(--isatu-accent);
            color: var(--isatu-primary);
        }
        
        .action-icon {
            font-size: 1.5rem;
            margin-right: 10px;
            color: var(--isatu-accent);
        }
        
        .action-text {
            font-weight: 600;
        }
        
        .position-card {
            background-color: white;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 15px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.05);
            transition: all 0.2s;
            border-left: 3px solid var(--isatu-accent);
            display: flex;
            align-items: center;
        }
        
        .position-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .position-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(26, 100, 160, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--isatu-accent);
            font-size: 1.2rem;
        }
        
        .position-name {
            font-weight: 600;
            color: var(--isatu-primary);
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
        }
        
        @media (max-width: 768px) {
            .college-card {
                flex-direction: column;
                text-align: center;
            }
            
            .college-icon {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .action-btn {
                flex-direction: column;
                padding: 10px;
            }
            
            .action-icon {
                margin-right: 0;
                margin-bottom: 10px;
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
            <a href="manage_candidates.php" class="nav-link active">
                <i class="bi bi-person-badge"></i>
                <span>Manage Candidates</span>
            </a>
            <a href="election_results.php" class="nav-link">
                <i class="bi bi-bar-chart"></i>
                <span>Election Results</span>
            </a>
            <a href="manage_candidates.php?logout=1" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
        
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-title">
            <i class="bi bi-person-badge"></i> Manage Candidates
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="control/view_all_candidates.php" class="action-btn">
                <i class="bi bi-list-check action-icon"></i>
                <span class="action-text">View All Candidates</span>
            </a>
            <!--<a href="candidate_statistics.php" class="action-btn">
                <i class="bi bi-graph-up action-icon"></i>
                <span class="action-text">Candidate Statistics</span>
            </a>-->
        </div>
        
        <!-- Register Candidate by College Cards -->
        <div class="data-card mb-4">
            <div class="card-header-action">
                <h5 class="card-title">Register Candidate by College</h5>
                <i class="bi bi-building card-header-icon"></i>
            </div>
            
            <div class="college-cards-container">
                <?php foreach ($college_names as $code => $name): ?>
                <a href="control/register_candidate.php?college=<?php echo $code; ?>" class="college-card-wrapper">
                    <div class="college-card <?php echo $code; ?>">
                        <div class="college-card-image">
                            <?php if ($code == 'sr'): ?>
                                <img src="../assets/logo/STUDENT REPUBLIC LOGO.png" alt="SR Image">
                            <?php elseif ($code == 'cas'): ?>
                                <img src="../assets/logo/CASSC.jpg" alt="CAS Image">
                            <?php elseif ($code == 'cea'): ?>
                                <img src="../assets/logo/CEASC.jpg" alt="CEA Image">
                            <?php elseif ($code == 'coe'): ?>
                                <img src="../assets/logo/COESC.jpg" alt="COE Image">
                            <?php elseif ($code == 'cit'): ?>
                                <img src="../assets/logo/CITSC.png" alt="CIT Image">
                            <?php endif; ?>
                        </div>

                        <div class="college-card-content">
                            <div class="college-badges">
                                <div class="college-badge <?php echo $code; ?>">
                                    <?php echo strtoupper($code); ?>
                                </div>
                            </div>
                            <div class="college-name"><?php echo $name; ?></div>
                            <div class="college-count">
                                <span><?php echo $college_counts[$code]; ?></span> Registered Candidates
                            </div>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        
        
        <div class="timestamp">
            <i class="bi bi-clock"></i> Last updated: <?php echo $current_datetime; ?>
        </div>
    </div>
    
    <!-- Position Selection Modals for each college -->
    <?php foreach ($college_names as $code => $name): ?>
    <div class="modal fade" id="collegePositions<?php echo ucfirst($code); ?>" tabindex="-1" aria-labelledby="collegePositionsLabel<?php echo ucfirst($code); ?>" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="collegePositionsLabel<?php echo ucfirst($code); ?>">
                        Select Position for <?php echo $name; ?>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php foreach ($positions[$code] as $position): ?>
                    <a href="control/register_candidate.php?college=<?php echo $code; ?>&position=<?php echo urlencode($position); ?>" class="text-decoration-none">
                        <div class="position-card">
                            <div class="position-icon">
                                <?php 
                                // Assign icons based on position
                                switch($position) {
                                    case 'President':
                                    case 'Governor':
                                        echo '<i class="bi bi-star-fill"></i>';
                                        break;
                                    case 'Vice President':
                                    case 'Vice Governor':
                                        echo '<i class="bi bi-star-half"></i>';
                                        break;
                                    case 'Secretary':
                                        echo '<i class="bi bi-journal-text"></i>';
                                        break;
                                    case 'Treasurer':
                                        echo '<i class="bi bi-cash-coin"></i>';
                                        break;
                                    case 'Auditor':
                                        echo '<i class="bi bi-calculator"></i>';
                                        break;
                                    case 'PIO':
                                        echo '<i class="bi bi-megaphone"></i>';
                                        break;
                                    case 'Senator':
                                    case 'Board Member':
                                        echo '<i class="bi bi-person-badge"></i>';
                                        break;
                                    default:
                                        echo '<i class="bi bi-person"></i>';
                                }
                                ?>
                            </div>
                            <div class="position-name"><?php echo $position; ?></div>
                        </div>
                    </a>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
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
    </script>
</body>
</html>