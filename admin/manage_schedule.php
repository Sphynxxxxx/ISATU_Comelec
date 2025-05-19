<?php
session_start();
require_once "../backend/connections/config.php";

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_time = $_POST['start_date'] . ' ' . $_POST['start_time'];
    $end_time = $_POST['end_date'] . ' ' . $_POST['end_time'];
    
    // Validate dates
    $start_datetime = new DateTime($start_time);
    $end_datetime = new DateTime($end_time);
    $now = new DateTime();
    
    $error = "";
    
    if ($end_datetime <= $start_datetime) {
        $error = "End time must be after start time";
    }
    
    if (empty($error)) {
        // Deactivate all existing schedules
        $deactivate_query = "UPDATE voting_schedule SET is_active = 0 WHERE is_active = 1";
        $conn->query($deactivate_query);
        
        // Create new schedule
        $insert_query = "INSERT INTO voting_schedule (start_time, end_time) VALUES (?, ?)";
        $stmt = $conn->prepare($insert_query);
        $stmt->bind_param("ss", $start_time, $end_time);
        
        if ($stmt->execute()) {
            $success = "Voting schedule has been successfully updated";
        } else {
            $error = "Error updating schedule: " . $conn->error;
        }
        
        $stmt->close();
    }
}

// Get current voting schedule
$schedule_query = "SELECT * FROM voting_schedule WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
$schedule_result = $conn->query($schedule_query);
$voting_schedule = $schedule_result->fetch_assoc();

// Check if voting is currently active
$current_time = date('Y-m-d H:i:s');
$voting_active = false;
$status_message = "";

if ($voting_schedule) {
    if ($current_time >= $voting_schedule['start_time'] && $current_time <= $voting_schedule['end_time']) {
        $voting_active = true;
        $status_message = "<span class='badge bg-success'>Voting is currently ACTIVE</span>";
    } elseif ($current_time < $voting_schedule['start_time']) {
        $status_message = "<span class='badge bg-warning'>Voting is scheduled to start soon</span>";
    } else {
        $status_message = "<span class='badge bg-danger'>Voting period has ended</span>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Voting Schedule - ISATU Election System</title>
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
        
        /* Timezone indicator */
        .timezone-indicator {
            display: inline-block;
            padding: 2px 8px;
            background-color: rgba(12, 59, 93, 0.1);
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 8px;
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
            <a href="election_results.php" class="nav-link">
                <i class="bi bi-bar-chart"></i>
                <span>Election Results</span>
            </a>
            <a href="manage_schedule.php" class="nav-link active">
                <i class="bi bi-calendar-event"></i>
                <span>Voting Schedule</span>
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
            <i class="bi bi-calendar-event"></i> Manage Voting Schedule
            <span class="timezone-indicator">PHT (UTC+8)</span>
        </div>
        
        <?php if (isset($error) && !empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i> <?php echo $error; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success) && !empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i> <?php echo $success; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Current Schedule -->
        <div class="data-card mb-4">
            <div class="card-header-action">
                <h5 class="card-title">Current Voting Schedule</h5>
                <?php echo $status_message; ?>
            </div>
            
            <div class="mt-4">
                <?php if ($voting_schedule): ?>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="fw-bold">Start Time (PHT):</label>
                                <p><?php echo date('F d, Y - h:i A', strtotime($voting_schedule['start_time'])); ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="fw-bold">End Time (PHT):</label>
                                <p><?php echo date('F d, Y - h:i A', strtotime($voting_schedule['end_time'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> Setting a new schedule will deactivate the current one.
                    </div>
                    
                    <!-- Show countdown if schedule exists -->
                    <div class="card bg-light mt-3">
                        <div class="card-body">
                            <h6 class="card-title">
                                <i class="bi bi-hourglass-split me-2"></i> 
                                <?php if ($voting_active): ?>
                                    Time Remaining Until Voting Ends:
                                <?php elseif ($current_time < $voting_schedule['start_time']): ?>
                                    Time Until Voting Begins:
                                <?php else: ?>
                                    Voting Period Has Ended
                                <?php endif; ?>
                            </h6>
                            
                            <?php if ($voting_active || $current_time < $voting_schedule['start_time']): ?>
                                <div class="text-center" id="countdown-display">
                                    <div class="d-inline-block px-3 py-2 bg-white rounded shadow-sm mx-1">
                                        <span id="days" class="fs-4 fw-bold">00</span>
                                        <span class="d-block small">Days</span>
                                    </div>
                                    <div class="d-inline-block px-3 py-2 bg-white rounded shadow-sm mx-1">
                                        <span id="hours" class="fs-4 fw-bold">00</span>
                                        <span class="d-block small">Hours</span>
                                    </div>
                                    <div class="d-inline-block px-3 py-2 bg-white rounded shadow-sm mx-1">
                                        <span id="minutes" class="fs-4 fw-bold">00</span>
                                        <span class="d-block small">Minutes</span>
                                    </div>
                                    <div class="d-inline-block px-3 py-2 bg-white rounded shadow-sm mx-1">
                                        <span id="seconds" class="fs-4 fw-bold">00</span>
                                        <span class="d-block small">Seconds</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i> No active voting schedule found. Set up a new schedule below.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Set New Schedule Form -->
        <div class="data-card">
            <div class="card-header-action">
                <h5 class="card-title">Set New Voting Schedule</h5>
                <i class="bi bi-calendar-plus card-header-icon"></i>
            </div>
            
            
            <form method="POST" action="" class="mt-4">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_date" class="form-label">Start Date</label>
                            <input type="date" class="form-control" id="start_date" name="start_date" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="start_time" class="form-label">Start Time (PHT)</label>
                            <input type="time" class="form-control" id="start_time" name="start_time" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="end_date" class="form-label">End Date</label>
                            <input type="date" class="form-control" id="end_date" name="end_date" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="end_time" class="form-label">End Time (PHT)</label>
                            <input type="time" class="form-control" id="end_time" name="end_time" required>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-end">
                    <a href="admin_dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-clock"></i> Set Voting Schedule
                    </button>
                </div>
            </form>
        </div>
        
        <div class="timestamp">
            <i class="bi bi-clock"></i> Last updated: <?php echo date('F d, Y - h:i A'); ?> <span class="badge bg-secondary">PHT</span>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Set default values for the form
        document.addEventListener('DOMContentLoaded', function() {
            const today = new Date();
            const formatDate = (date) => {
                return date.toISOString().split('T')[0];
            };
            
            document.getElementById('start_date').value = formatDate(today);
            
            // Set default end date to 1 week from today
            const endDate = new Date();
            endDate.setDate(today.getDate() + 7);
            document.getElementById('end_date').value = formatDate(endDate);
            
            // Set default times
            document.getElementById('start_time').value = '08:00';
            document.getElementById('end_time').value = '17:00';
            
            <?php if (($voting_active || $current_time < $voting_schedule['start_time']) && $voting_schedule): ?>
            // Initialize countdown
            const targetDate = new Date("<?php echo $voting_active ? $voting_schedule['end_time'] : $voting_schedule['start_time']; ?>").getTime();
            
            // Update the countdown every second
            const countdownTimer = setInterval(function() {
                // Get current date and time
                const now = new Date().getTime();
                
                // Find the time remaining
                const distance = targetDate - now;
                
                // Time calculations
                const days = Math.floor(distance / (1000 * 60 * 60 * 24));
                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);
                
                // Display the result
                document.getElementById("days").innerHTML = days.toString().padStart(2, '0');
                document.getElementById("hours").innerHTML = hours.toString().padStart(2, '0');
                document.getElementById("minutes").innerHTML = minutes.toString().padStart(2, '0');
                document.getElementById("seconds").innerHTML = seconds.toString().padStart(2, '0');
                
                // If the countdown is finished, refresh the page to update status
                if (distance < 0) {
                    clearInterval(countdownTimer);
                    location.reload();
                }
            }, 1000);
            <?php endif; ?>
        });
    </script>
</body>
</html>