<?php
session_start();
require_once "../../backend/connections/config.php"; 



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
    'cit' => 'College of Industrial Technology',
    'cci' => 'College of Computing and Informatics'
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
    
    // Check if student ID already exists in ANY college (except SR)
    if (empty($error_msg)) {
        // First, check if student exists in the same college
        $check_query = "SELECT college_code FROM students WHERE student_id = ? AND college_code = ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ss", $student_id, $college_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_msg = "This student ID is already registered in " . $college_names[$college_code];
        } else {
            // Then, check if student exists in any other college (excluding SR)
            $check_other_colleges_query = "SELECT college_code FROM students WHERE student_id = ? AND college_code != 'sr'";
            $stmt = $conn->prepare($check_other_colleges_query);
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $existing_college = $row['college_code'];
                $error_msg = "This student ID is already registered in " . $college_names[$existing_college] . 
                             ". A student cannot be registered in multiple colleges.";
            }
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

// Handle delete action
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    // Start transaction
    $conn->begin_transaction();
    try {
        // Get the student_id first to find the SR record if needed
        $get_student_query = "SELECT student_id, college_code FROM students WHERE id = ?";
        $stmt = $conn->prepare($get_student_query);
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $student = $result->fetch_assoc();
            $student_id = $student['student_id'];
            $college_code = $student['college_code'];
            
            // If this is not an SR record, find and delete the corresponding SR record
            if ($college_code != 'sr') {
                // Find SR record with the same student_id
                $find_sr_query = "SELECT id FROM students WHERE student_id = ? AND college_code = 'sr'";
                $stmt = $conn->prepare($find_sr_query);
                $stmt->bind_param("s", $student_id);
                $stmt->execute();
                $sr_result = $stmt->get_result();
                
                if ($sr_result->num_rows > 0) {
                    $sr_record = $sr_result->fetch_assoc();
                    $sr_id = $sr_record['id'];
                    
                    // Delete from student_republic_voters first (to maintain FK constraints)
                    $delete_sr_voter_query = "DELETE FROM student_republic_voters WHERE student_id = ?";
                    $stmt = $conn->prepare($delete_sr_voter_query);
                    $stmt->bind_param("i", $sr_id);
                    $stmt->execute();
                    
                    // Then delete the SR student record
                    $delete_sr_query = "DELETE FROM students WHERE id = ?";
                    $stmt = $conn->prepare($delete_sr_query);
                    $stmt->bind_param("i", $sr_id);
                    $stmt->execute();
                }
            } else {
                // If this is an SR record, delete from student_republic_voters first
                $delete_sr_voter_query = "DELETE FROM student_republic_voters WHERE student_id = ?";
                $stmt = $conn->prepare($delete_sr_voter_query);
                $stmt->bind_param("i", $delete_id);
                $stmt->execute();
            }
        }
        
        // Finally delete the original record that was requested
        $delete_query = "DELETE FROM students WHERE id = ?";
        $stmt = $conn->prepare($delete_query);
        $stmt->bind_param("i", $delete_id);
        $stmt->execute();
        
        // Commit all changes
        $conn->commit();
        
        // Add a success message parameter
        $success = "Student successfully deleted from " . $college_names[$selected_college];
        if ($college_code != 'sr') {
            $success .= " and Student Republic";
        }
        
        header("Location: register_student.php?college=".$selected_college."&success=".urlencode($success));
        exit();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error = "Failed to delete student: " . $e->getMessage();
        header("Location: register_student.php?college=".$selected_college."&error=".urlencode($error));
        exit();
    }
}

// Initialize search variable
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_condition = '';
$search_params = [];
$param_types = 's'; 

// If search term is provided, add it to the query
if (!empty($search_term)) {
    $search_condition = " AND student_id LIKE ?";
    $search_params[] = "%$search_term%";
    $param_types .= 's';
}

// Prepare the base params array with college code
$query_params = [$selected_college];

// Add search params if they exist
if (!empty($search_params)) {
    $query_params = array_merge($query_params, $search_params);
}

// Modify the existing query to include search
$students_query = "SELECT id, student_id, created_at FROM students 
                  WHERE college_code = ?" . $search_condition . " 
                  ORDER BY created_at DESC";
$stmt = $conn->prepare($students_query);

// Dynamically bind parameters
$stmt->bind_param($param_types, ...$query_params);
$stmt->execute();
$result = $stmt->get_result();

// Get current date and time
$current_datetime = date('F d, Y - h:i A');

// Fetch registered students for the selected college
$registered_students = [];
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
        
        .cci {
            background-color: rgba(40, 167, 69, 0.1);
            color: #28a745;
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

        /* Search Bar Styles */
        .input-group {
            height: 38px;
        }

        .input-group .form-control {
            height: 100%;
            border-right: none;
            padding: 0.375rem 0.75rem;
        }

        .input-group .btn {
            height: 100%;
            padding: 0 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-left: none;
        }

        .input-group .btn i {
            font-size: 1rem;
        }

        /* Remove double borders between elements */
        .input-group .form-control:not(:first-child),
        .input-group .btn:not(:first-child) {
            border-left: none;
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
        
        <!-- Search Bar -->
        <div class="d-flex justify-content-end mb-4">
            <form action="register_student.php" method="get" class="w-auto">
                <input type="hidden" name="college" value="<?php echo $selected_college; ?>">
                <div class="input-group" style="width: 300px;">
                    <input type="text" class="form-control" name="search" placeholder="Search student ID..." 
                        value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button class="btn btn-primary" type="submit" style="border-top-left-radius: 0; border-bottom-left-radius: 0;">
                        <i class="bi bi-search"></i>
                    </button>
                    <?php if (isset($_GET['search']) && !empty($_GET['search'])): ?>
                    <a href="register_student.php?college=<?php echo $selected_college; ?>" 
                    class="btn btn-outline-secondary" 
                    style="border-top-right-radius: 0; border-bottom-right-radius: 0;">
                        <i class="bi bi-x"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </form>
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
                        <?php elseif ($selected_college == 'cci'): ?>
                            <i class="bi bi-laptop-fill"></i>
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

        <div class="d-flex justify-content-end mb-3">
            <a href="export_students.php?college=<?php echo $selected_college; ?>" class="btn btn-success">
                <i class="bi bi-file-excel me-2"></i> Export to Excel
            </a>
        </div>
        
        <!-- Registered Students Table -->
        <div class="form-card mt-4">
            <div class="card-header-action">
                <h5 class="card-title">Registered Students in <?php echo $college_names[$selected_college]; ?></h5>
                <i class="bi bi-people-fill card-header-icon"></i>
            </div>
            
            <!-- Search results message -->
            <?php if (!empty($search_term)): ?>
            <div class="alert alert-info">
                <i class="bi bi-info-circle-fill me-2"></i>
                <?php 
                $result_count = count($registered_students);
                if ($result_count > 0): 
                ?>
                    Found <?php echo $result_count; ?> student<?php echo $result_count != 1 ? 's' : ''; ?> matching "<?php echo htmlspecialchars($search_term); ?>"
                <?php else: ?>
                    No students found matching "<?php echo htmlspecialchars($search_term); ?>"
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <?php if (count($registered_students) > 0): ?>
                <div class="table-responsive">
                    <table class="students-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Date Registered</th>
                                <th>Status</th>
                                <th>Actions</th>
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
                                    <td>
                                        <div class="d-flex gap-2">
                                            <!--<a href="edit_student.php?id=<?php echo $student['id']; ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>-->
                                            <a href="register_student.php?college=<?php echo $selected_college; ?>&delete_id=<?php echo $student['id']; ?>" 
                                            class="btn btn-sm btn-outline-danger btn-delete">
                                                <i class="bi bi-trash"></i> Delete
                                            </a>
                                        </div>
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
        // Toggle sidebar functionality (if toggle button exists)
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

        // Confirm delete action
        document.querySelectorAll('.btn-delete').forEach(btn => {
            btn.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to delete this student? This action cannot be undone.')) {
                    e.preventDefault();
                }
            });
        });

    </script>
</body>
</html>