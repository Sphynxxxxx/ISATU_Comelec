<?php
session_start();
require_once "../../backend/connections/config.php"; 

// Check if admin is logged in
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Logout functionality
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../admin.php");
    exit();
}

// Define college names and codes for display and validation
$college_names = [
    'sr' => 'Student Republic',
    'cas' => 'College of Arts and Sciences',
    'cea' => 'College of Engineering and Architecture',
    'coe' => 'College of Education',
    'cit' => 'College of Industrial Technology'
];

// Set default college from URL parameter, or default to 'sr'
$selected_college = isset($_GET['college']) && array_key_exists($_GET['college'], $college_names) 
    ? $_GET['college'] 
    : 'sr';

// Initialize variables
$student_id = '';
$error_msg = '';
$success_msg = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate inputs
    $student_id = trim($_POST['student_id']);
    $college_code = trim($_POST['college_code']);
    
    // Validate student ID
    if (empty($student_id)) {
        $error_msg = "Student ID is required";
    } elseif (strlen($student_id) > 20) {
        $error_msg = "Student ID cannot exceed 20 characters";
    }
    
    // Validate college code
    if (empty($college_code) || !array_key_exists($college_code, $college_names)) {
        $error_msg = "Invalid college selected";
    }
    
    // Check if student ID already exists in the same college
    if (empty($error_msg)) {
        $check_query = "SELECT * FROM students WHERE student_id = ? AND college_code = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ss", $student_id, $college_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_msg = "This student ID is already registered in the selected college";
        }
    }
    
    // Insert new student if no errors
    if (empty($error_msg)) {
        $conn->begin_transaction();
        try {
            // Insert student record
            $insert_query = "INSERT INTO students (student_id, college_code, created_at) VALUES (?, ?, NOW())";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("ss", $student_id, $college_code);
            $stmt->execute();
            
            // Get the new student ID for SR registration
            $new_student_id = $conn->insert_id;
            
            // Register in Student Republic too (if not already registering for SR)
            if ($college_code != 'sr') {
                // First check if already registered in SR
                $check_sr_query = "SELECT * FROM students WHERE student_id = ? AND college_code = 'sr'";
                $stmt = $conn->prepare($check_sr_query);
                $stmt->bind_param("s", $student_id);
                $stmt->execute();
                $sr_result = $stmt->get_result();
                
                if ($sr_result->num_rows == 0) {
                    // Insert into SR if not already there
                    $insert_sr_query = "INSERT INTO students (student_id, college_code, created_at) VALUES (?, 'sr', NOW())";
                    $stmt = $conn->prepare($insert_sr_query);
                    $stmt->bind_param("s", $student_id);
                    $stmt->execute();
                    
                    // Get the student republic ID
                    $sr_student_id = $conn->insert_id;
                    
                    // Insert into student_republic_voters table
                    $insert_sr_voter_query = "INSERT INTO student_republic_voters (student_id, registered_at) VALUES (?, NOW())";
                    $stmt = $conn->prepare($insert_sr_voter_query);
                    $stmt->bind_param("i", $sr_student_id);
                    $stmt->execute();
                } else {
                    // If already in SR, get that ID to register as SR voter if not already
                    $sr_row = $sr_result->fetch_assoc();
                    $sr_student_id = $sr_row['id'];
                    
                    // Check if already an SR voter
                    $check_sr_voter_query = "SELECT * FROM student_republic_voters WHERE student_id = ?";
                    $stmt = $conn->prepare($check_sr_voter_query);
                    $stmt->bind_param("i", $sr_student_id);
                    $stmt->execute();
                    $sr_voter_result = $stmt->get_result();
                    
                    if ($sr_voter_result->num_rows == 0) {
                        // Register as SR voter
                        $insert_sr_voter_query = "INSERT INTO student_republic_voters (student_id, registered_at) VALUES (?, NOW())";
                        $stmt = $conn->prepare($insert_sr_voter_query);
                        $stmt->bind_param("i", $sr_student_id);
                        $stmt->execute();
                    }
                }
            } else {
                // If registering directly to SR, add them as SR voter too
                $insert_sr_voter_query = "INSERT INTO student_republic_voters (student_id, registered_at) VALUES (?, NOW())";
                $stmt = $conn->prepare($insert_sr_voter_query);
                $stmt->bind_param("i", $new_student_id);
                $stmt->execute();
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_msg = "Student successfully registered in " . $college_names[$college_code];
            if ($college_code != 'sr') {
                $success_msg .= " and Student Republic";
            }
            
            // Reset form
            $student_id = '';
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_msg = "Registration failed: " . $e->getMessage();
        }
    }
}

// Get current date and time
$current_datetime = date('F d, Y - h:i A');

// Fetch registered students for the selected college
$registered_students = [];
$students_query = "SELECT id, student_id, created_at FROM students WHERE college_code = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($students_query);
$stmt->bind_param("s", $selected_college);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $registered_students[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Student - ISATU Election System</title>
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
        
        .form-card {
            background-color: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .card-header-action {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .card-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--isatu-primary);
            margin-bottom: 0;
        }
        
        .card-header-icon {
            color: var(--isatu-accent);
            font-size: 1.8rem;
        }
        
        .college-icon {
            width: 60px;
            height: 60px;
            background-color: rgba(12, 59, 93, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--isatu-primary);
            margin-right: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--isatu-primary);
            margin-bottom: 8px;
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        
        .form-control:focus {
            border-color: var(--isatu-accent);
            box-shadow: 0 0 0 0.25rem rgba(26, 100, 160, 0.25);
        }
        
        .form-select {
            padding: 12px 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            margin-bottom: 20px;
        }
        
        .form-select:focus {
            border-color: var(--isatu-accent);
            box-shadow: 0 0 0 0.25rem rgba(26, 100, 160, 0.25);
        }
        
        .btn-primary {
            background-color: var(--isatu-primary);
            border-color: var(--isatu-primary);
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover, .btn-primary:focus {
            background-color: var(--isatu-dark);
            border-color: var(--isatu-dark);
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .btn-outline-secondary {
            color: #6c757d;
            border-color: #6c757d;
            padding: 12px 25px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-outline-secondary:hover, .btn-outline-secondary:focus {
            background-color: #6c757d;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        
        .alert {
            border-radius: 8px;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        
        .college-badge {
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-block;
            margin-bottom: 20px;
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
        
        .timestamp {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 30px;
            text-align: right;
        }
        
        /* Registered students table styles */
        .students-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .students-table th {
            background-color: var(--isatu-primary);
            color: white;
            padding: 12px 15px;
            text-align: left;
        }
        
        .students-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .students-table tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        
        .students-table tr:hover {
            background-color: #e9ecef;
        }
        
        .badge {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        
        /* Responsive table */
        .table-responsive {
            overflow-x: auto;
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
            .form-card {
                padding: 20px;
            }
            
            .students-table th, 
            .students-table td {
                padding: 8px 10px;
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../../assets/logo/ISAT-U-logo-2.png" alt="ISATU Logo" class="isatu-logo">
            <p class="logo-text">ISATU Admin</p>
            <p class="logo-subtext">Election System</p>
        </div>
        
        <div class="mt-4">
            <a href="../admin_dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="../manage_students.php" class="nav-link active">
                <i class="bi bi-people"></i>
                <span>Manage Students</span>
            </a>
            <a href="../manage_candidates.php" class="nav-link">
                <i class="bi bi-person-badge"></i>
                <span>Manage Candidates</span>
            </a>
            <a href="../election_results.php" class="nav-link">
                <i class="bi bi-bar-chart"></i>
                <span>Election Results</span>
            </a>
            <a href="../election_settings.php" class="nav-link">
                <i class="bi bi-gear"></i>
                <span>Election Settings</span>
            </a>
            <a href="../register_student.php?logout=1" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
        
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-title">
            <i class="bi bi-person-plus"></i> Register Student
        </div>
        
        <!-- Registration Form -->
        <div class="form-card">
            <div class="card-header-action">
                <div class="d-flex align-items-center">
                    <div class="college-icon">
                        <?php if ($selected_college == 'sr'): ?>
                            <i class="bi bi-people-fill"></i>
                        <?php elseif ($selected_college == 'cas'): ?>
                            <i class="bi bi-book-fill"></i>
                        <?php elseif ($selected_college == 'cea'): ?>
                            <i class="bi bi-building-fill"></i>
                        <?php elseif ($selected_college == 'coe'): ?>
                            <i class="bi bi-mortarboard-fill"></i>
                        <?php elseif ($selected_college == 'cit'): ?>
                            <i class="bi bi-gear-fill"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h5 class="card-title">Register New Student</h5>
                        <div class="college-badge <?php echo $selected_college; ?>">
                            <?php echo $college_names[$selected_college]; ?>
                        </div>
                    </div>
                </div>
                <i class="bi bi-person-plus-fill card-header-icon"></i>
            </div>
            
            <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_msg; ?>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_msg; ?>
            </div>
            <?php endif; ?>
            
            <form method="post" action="register_student.php?college=<?php echo $selected_college; ?>">
                <div class="mb-4">
                    <label for="student_id" class="form-label required-field">Student ID Number</label>
                    <input type="text" class="form-control" id="student_id" name="student_id" 
                           value="<?php echo htmlspecialchars($student_id); ?>" required 
                           placeholder="Enter student ID number (e.g., 2023-1234-A)">
                    <div class="form-text text-muted">Format example: 2023-1234-A</div>
                </div>
                
                <div class="mb-4">
                    <label for="college_code" class="form-label required-field">College</label>
                    <select class="form-select" id="college_code" name="college_code" required disabled>
                        <?php foreach ($college_names as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo ($code == $selected_college) ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="college_code" value="<?php echo $selected_college; ?>">
                    <div class="form-text text-muted">
                        <?php if ($selected_college != 'sr'): ?>
                        Student will also be registered in Student Republic automatically.
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="../manage_students.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i> Back to Students
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-person-plus me-2"></i> Register Student
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Registered Students Table -->
        <div class="form-card mt-4">
            <div class="card-header-action">
                <h5 class="card-title">Registered Students in <?php echo $college_names[$selected_college]; ?></h5>
                <i class="bi bi-people-fill card-header-icon"></i>
            </div>
            
            <?php if (count($registered_students) > 0): ?>
                <div class="table-responsive">
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Date Registered</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($registered_students as $index => $student): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($student['student_id']); ?></td>
                                    <td><?php echo date('M d, Y h:i A', strtotime($student['created_at'])); ?></td>
                                    <td>
                                        <span class="badge bg-success">Registered</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="bi bi-info-circle-fill me-2"></i> No students registered yet for <?php echo $college_names[$selected_college]; ?>.
                </div>
            <?php endif; ?>
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

    </script>
</body>
</html>