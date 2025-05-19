<?php
session_start();
require_once "../backend/connections/config.php"; 


// Logout functionality
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../admin.php");
    exit();
}

// Get total students count - Exclude candidates (where student_id is not used in candidates table)
$total_students_query = "SELECT COUNT(*) as total FROM students 
                        WHERE NOT EXISTS (
                            SELECT 1 FROM candidates 
                            WHERE candidates.student_id = students.id
                        )";
$total_students_result = $conn->query($total_students_query);
$total_students = $total_students_result->fetch_assoc()['total'];

// Get count of students by college - Exclude candidates
$college_counts = [];
$college_query = "SELECT college_code, COUNT(*) as count 
                 FROM students 
                 WHERE NOT EXISTS (
                     SELECT 1 FROM candidates 
                     WHERE candidates.student_id = students.id
                 )
                 GROUP BY college_code";
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

// Get recent student registrations with combined colleges - Exclude candidates
$recent_registrations_query = "
    SELECT 
        s.student_id,
        GROUP_CONCAT(s.college_code ORDER BY s.college_code SEPARATOR ',') as colleges,
        MAX(s.id) as id,
        MAX(s.created_at) as created_at
    FROM students s
    WHERE NOT EXISTS (
        SELECT 1 FROM candidates c 
        WHERE c.student_id = s.id
    )
    GROUP BY s.student_id
    ORDER BY MAX(s.created_at) DESC
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
    <title>Manage Students - ISATU Election System</title>
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
        
        .college-card {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 5px solid var(--isatu-primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 100%;
        }
        
        .college-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            cursor: pointer;
        }
        
        .college-icon {
            width: 80px;
            height: 80px;
            background-color: rgba(12, 59, 93, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: var(--isatu-primary);
            margin-right: 20px;
        }
        
        .college-info {
            flex-grow: 1;
        }
        
        .college-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--isatu-primary);
            margin-bottom: 8px;
        }
        
        .college-count {
            font-size: 1rem;
            color: #6c757d;
        }
        
        .college-count span {
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--isatu-accent);
        }
        
        .card-header-action {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .card-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--isatu-primary);
            margin-bottom: 0;
        }
        
        .card-header-icon {
            color: var(--isatu-accent);
            font-size: 1.5rem;
        }
        
        .data-card {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .action-btns {
            display: flex;
            gap: 10px;
        }
        
        .timestamp {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 30px;
            text-align: right;
        }
        
        .recent-table {
            width: 100%;
        }
        
        .recent-table th {
            font-weight: 600;
            color: var(--isatu-primary);
            padding: 12px 15px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .recent-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .badge-college {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .sr {
            background-color: rgba(0, 0, 0, 0.1);
            color: #000000;
        }
        
        .cas {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .cea {
            background-color: rgba(255, 140, 0, 0.1);
            color: #ff8c00;
        }
        
        .coe {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .cit {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
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
            <a href="manage_students.php" class="nav-link active">
                <i class="bi bi-people"></i>
                <span>Manage Students</span>
            </a>
            <a href="manage_candidates.php" class="nav-link">
                <i class="bi bi-person-badge"></i>
                <span>Manage Candidates</span>
            </a>
            <a href="election_results.php" class="nav-link">
                <i class="bi bi-bar-chart"></i>
                <span>Election Results</span>
            </a>
            <a href="manage_students.php?logout=1" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
        
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-title">
            <i class="bi bi-people"></i> Manage Students
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="control/register_student.php" class="action-btn">
                <i class="bi bi-person-plus action-icon"></i>
                <span class="action-text">Register New Student</span>
            </a>
            <!--<a href="export_students.php" class="action-btn">
                <i class="bi bi-file-earmark-arrow-down action-icon"></i>
                <span class="action-text">Export Students</span>
            </a>-->
        </div>
        
        <!-- Register Student by College Cards -->
        <div class="data-card mb-4">
            <div class="card-header-action">
                <h5 class="card-title">Register Student by College</h5>
                <i class="bi bi-building card-header-icon"></i>
            </div>
            
            <div class="row mt-3">
                <?php foreach ($college_names as $code => $name): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <a href="control/register_student.php?college=<?php echo $code; ?>" class="text-decoration-none">
                        <div class="college-card">
                            <div class="college-icon">
                                <?php if ($code == 'sr'): ?>
                                    <i class="bi bi-people-fill"></i>
                                <?php elseif ($code == 'cas'): ?>
                                    <i class="bi bi-book-fill"></i>
                                <?php elseif ($code == 'cea'): ?>
                                    <i class="bi bi-building-fill"></i>
                                <?php elseif ($code == 'coe'): ?>
                                    <i class="bi bi-mortarboard-fill"></i>
                                <?php elseif ($code == 'cit'): ?>
                                    <i class="bi bi-gear-fill"></i>
                                <?php endif; ?>
                            </div>
                            <div class="college-info">
                                <div class="college-name"><?php echo $name; ?></div>
                                <div class="college-count">
                                    <span><?php echo $college_counts[$code]; ?></span> Registered Students
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Recent Registrations -->
        <div class="data-card">
            <div class="card-header-action">
                <h5 class="card-title">Recent Student Registrations</h5>
                <i class="bi bi-clock-history card-header-icon"></i>
            </div>
            
            <div class="table-responsive mt-3">
                <table class="recent-table">
                    <thead>
                        <tr>
                            <th>Student ID</th>
                            <th>College</th>
                            <th>Registered</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_registrations)): ?>
                        <tr>
                            <td colspan="4" class="text-center">No recent registrations found</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($recent_registrations as $registration): ?>
                            <tr>
                                <td><?php echo $registration['student_id']; ?></td>
                                <td>
                                    <?php 
                                    $college_codes = explode(',', $registration['colleges']);
                                    foreach ($college_codes as $index => $college_code):
                                        $college_code = strtolower($college_code);
                                    ?>
                                    <span class="badge-college <?php echo $college_code; ?>">
                                        <?php 
                                        echo isset($college_names[$college_code]) ? $college_names[$college_code] : ucfirst($college_code); 
                                        ?>
                                    </span>
                                    <?php if ($index < count($college_codes) - 1) echo " "; ?>
                                    <?php endforeach; ?>
                                </td>
                                <td><?php echo time_elapsed_string($registration['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex justify-content-center mt-4">
                <a href="view_all_students.php" class="btn btn-outline-primary">
                    <i class="bi bi-list"></i> View All Students
                </a>
            </div>
        </div>
        
        <div class="timestamp">
            <i class="bi bi-clock"></i> Last updated: <?php echo $current_datetime; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle sidebar functionality
        const toggleBtn = document.getElementById('toggleBtn');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', function() {
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
        }
        
        // Confirm delete
        const deleteLinks = document.querySelectorAll('.btn-delete');
        deleteLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>