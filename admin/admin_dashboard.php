<?php
session_start();
require_once "../backend/connections/config.php"; 

// Logout functionality
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../admin.php");
    exit();
}

// Get total students count
$total_students_query = "SELECT COUNT(*) as total FROM students";
$total_students_result = $conn->query($total_students_query);
$total_students = $total_students_result->fetch_assoc()['total'];

// Get total votes count
$total_votes_query = "SELECT COUNT(*) as total FROM votes";
$total_votes_result = $conn->query($total_votes_query);
$total_votes = $total_votes_result->fetch_assoc()['total'];

// Calculate participation rate
$participation_rate = ($total_students > 0) ? round(($total_votes / $total_students) * 100, 1) : 0;

// Get votes by college
$college_votes = [];
$college_query = "SELECT 
    college_code, 
    COUNT(*) as vote_count 
    FROM votes 
    JOIN students ON votes.student_id = students.id 
    GROUP BY college_code";

$college_result = $conn->query($college_query);

// Initialize all colleges with zero votes
$college_votes = [
    'sr' => 0, 
    'cas' => 0, 
    'cea' => 0, 
    'coe' => 0, 
    'cit' => 0
];

// Fill in actual votes from database
if ($college_result->num_rows > 0) {
    while ($row = $college_result->fetch_assoc()) {
        $college_code = strtolower($row['college_code']);
        $college_votes[$college_code] = $row['vote_count'];
    }
}

// Get recent activity
$recent_activity_query = "
    (SELECT 
        'vote' as activity_type, 
        CONCAT(students.student_id, ' (', UPPER(students.college_code), ')') as description,
        votes.created_at as activity_time
    FROM votes
    JOIN students ON votes.student_id = students.id
    ORDER BY votes.created_at DESC
    LIMIT 5)
    
    UNION
    
    (SELECT 
        'register' as activity_type,
        CONCAT('New candidate registered: ', c.student_id, ' (', UPPER(s.college_code), ')') as description,
        c.created_at as activity_time
    FROM candidates c
    JOIN students s ON c.student_id = s.id
    ORDER BY c.created_at DESC
    LIMIT 5)
    
    UNION
    
    (SELECT 
        'update' as activity_type,
        CONCAT('Student updated: ', student_id, ' (', UPPER(college_code), ')') as description,
        updated_at as activity_time
    FROM students
    WHERE updated_at IS NOT NULL
    ORDER BY updated_at DESC
    LIMIT 5)
    
    UNION
    
    (SELECT 
        'register' as activity_type,
        CONCAT('New student registered: ', student_id, ' (', UPPER(college_code), ')') as description,
        created_at as activity_time
    FROM students
    ORDER BY created_at DESC
    LIMIT 5)
    
    ORDER BY activity_time DESC
    LIMIT 5
";

$recent_activity_result = $conn->query($recent_activity_query);
$recent_activities = [];

if ($recent_activity_result && $recent_activity_result->num_rows > 0) {
    while ($row = $recent_activity_result->fetch_assoc()) {
        $recent_activities[] = $row;
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

// Get current date and time
$current_datetime = date('F d, Y - h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ISATU Election System</title>
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
        
        /* Dashboard styles */
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
        
        .stat-card {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border-left: 5px solid var(--isatu-primary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
        }
        
        .stat-title {
            font-size: 1rem;
            color: #6c757d;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--isatu-primary);
            margin-bottom: 0;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            background-color: rgba(12, 59, 93, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: var(--isatu-primary);
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
        
        .progress {
            height: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        
        .progress-bar {
            background-color: var(--isatu-primary);
        }
        
        .college-progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .quick-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-top: 15px;
        }
        
        .action-btn {
            flex: 1;
            min-width: 200px;
            background-color: white;
            border: 1px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            color: var(--isatu-primary);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0,0,0,0.1);
            border-color: var(--isatu-accent);
            color: var(--isatu-primary);
        }
        
        .action-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
            color: var(--isatu-accent);
        }
        
        .action-text {
            font-size: 1.1rem;
            font-weight: 600;
        }
        
        .action-subtext {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .timestamp {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 30px;
            text-align: right;
        }
        
        /* Recent activity table */
        .recent-activity {
            margin-top: 15px;
        }
        
        .activity-table {
            width: 100%;
        }
        
        .activity-table th {
            font-weight: 600;
            color: var(--isatu-primary);
            padding: 12px 15px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .activity-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
        }
        
        .activity-table tr:last-child td {
            border-bottom: none;
        }
        
        .activity-table tr:hover {
            background-color: rgba(12, 59, 93, 0.02);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            margin-right: 10px;
        }
        
        .activity-icon.vote {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .activity-icon.register {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        
        .activity-icon.update {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .activity-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .badge-vote {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .badge-register {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        
        .badge-update {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
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
            .stat-card, .data-card {
                padding: 15px;
            }
            
            .action-btn {
                min-width: 100%;
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
            <a href="admin_dashboard.php" class="nav-link active">
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
            <a href="election_results.php" class="nav-link">
                <i class="bi bi-bar-chart"></i>
                <span>Election Results</span>
            </a>
            <a href="election_settings.php" class="nav-link">
                <i class="bi bi-gear"></i>
                <span>Election Settings</span>
            </a>
            <a href="admin_dashboard.php?logout=1" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-title">
            <i class="bi bi-speedometer2"></i> Dashboard
        </div>
        
        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-title">Total Students</div>
                            <div class="stat-value"><?php echo $total_students; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-title">Total Votes Cast</div>
                            <div class="stat-value"><?php echo $total_votes; ?></div>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-check2-square"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="stat-title">Participation Rate</div>
                            <div class="stat-value"><?php echo $participation_rate; ?>%</div>
                        </div>
                        <div class="stat-icon">
                            <i class="bi bi-graph-up"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        

        
        <!-- Two Column Layout for charts and activity -->
        <div class="row">
            <!-- Vote Distribution -->
            <div class="col-lg-6">
                <div class="data-card">
                    <div class="card-header-action">
                        <h5 class="card-title">Vote Distribution by College</h5>
                        <i class="bi bi-pie-chart card-header-icon"></i>
                    </div>
                    
                    <div class="mt-4">
                        <?php
                        // Define college names for display
                        $college_names = [
                            'sr' => 'Student Republic',
                            'cas' => 'College of Arts and Sciences',
                            'cea' => 'College of Engineering and Architecture',
                            'coe' => 'College of Education',
                            'cit' => 'College of Industrial Technology'
                        ];
                        
                        // Display progress bars for each college
                        foreach ($college_votes as $college_code => $votes) {
                            $percentage = ($total_votes > 0) ? round(($votes / $total_votes) * 100, 1) : 0;
                            $college_name = isset($college_names[$college_code]) ? $college_names[$college_code] : ucfirst($college_code);
                        ?>
                        <div class="college-progress-label">
                            <span><?php echo $college_name; ?></span>
                            <span><?php echo $votes; ?> votes</span>
                        </div>
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" 
                                style="width: <?php echo $percentage; ?>%" 
                                aria-valuenow="<?php echo $percentage; ?>" 
                                aria-valuemin="0" aria-valuemax="100">
                                <?php echo $percentage; ?>%
                            </div>
                        </div>
                        <?php } ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="col-lg-6">
                <div class="data-card">
                    <div class="card-header-action">
                        <h5 class="card-title">Recent Activity</h5>
                        <i class="bi bi-activity card-header-icon"></i>
                    </div>
                    
                    <div class="recent-activity">
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_activities)): ?>
                                <tr>
                                    <td colspan="3" class="text-center">No recent activity found</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                    <tr>
                                        <td>
                                            <span class="activity-badge badge-<?php echo $activity['activity_type']; ?>">
                                                <?php echo ucfirst($activity['activity_type']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo $activity['description']; ?></td>
                                        <td><?php echo time_elapsed_string($activity['activity_time']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
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