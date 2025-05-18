<?php
session_start();
require_once "../../backend/connections/config.php"; 

// Check if admin is logged in
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../../admin.php"); 
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

// Initialize variables
$student_id = '';
$college_code = '';
$error_msg = '';
$success_msg = '';
$student_data = null;

// Check for URL parameters from redirect 
if (isset($_GET['success'])) {
    $success_msg = urldecode($_GET['success']);
}

if (isset($_GET['error'])) {
    $error_msg = urldecode($_GET['error']);
}

// Check if an ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: ../manage_students.php");
    exit();
}

$id = $_GET['id'];

// Fetch student data
$query = "SELECT * FROM students WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: ../manage_students.php");
    exit();
}

$student_data = $result->fetch_assoc();
$student_id = $student_data['student_id'];
$college_code = $student_data['college_code'];
$original_student_id = $student_id;

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
    
    // Check if the new student ID already exists (only if changed)
    if (empty($error_msg) && $student_id !== $original_student_id) {
        $check_query = "SELECT * FROM students WHERE student_id = ? AND college_code = ? AND id != ?";
        $stmt = $conn->prepare($check_query);
        $stmt->bind_param("ssi", $student_id, $college_code, $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $error_msg = "This student ID is already registered in the selected college";
        }
    }
    
    // Update student record if no errors
    if (empty($error_msg)) {
        $conn->begin_transaction();
        try {
            // Update the student record
            $update_query = "UPDATE students SET student_id = ? WHERE id = ?";
            $stmt = $conn->prepare($update_query);
            $stmt->bind_param("si", $student_id, $id);
            $stmt->execute();
            
            // If student ID was changed, update any SR record too
            if ($student_id !== $original_student_id) {
                // Only if this isn't already an SR record
                if ($college_code !== 'sr') {
                    // Find the corresponding SR record
                    $find_sr_query = "SELECT id FROM students WHERE student_id = ? AND college_code = 'sr'";
                    $stmt = $conn->prepare($find_sr_query);
                    $stmt->bind_param("s", $original_student_id);
                    $stmt->execute();
                    $sr_result = $stmt->get_result();
                    
                    if ($sr_result->num_rows > 0) {
                        $sr_record = $sr_result->fetch_assoc();
                        $sr_id = $sr_record['id'];
                        
                        // Update the SR record with the new student ID
                        $update_sr_query = "UPDATE students SET student_id = ? WHERE id = ?";
                        $stmt = $conn->prepare($update_sr_query);
                        $stmt->bind_param("si", $student_id, $sr_id);
                        $stmt->execute();
                    }
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            $success_msg = "Student information successfully updated";
            // Update the original student ID to reflect the changes
            $original_student_id = $student_id;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_msg = "Update failed: " . $e->getMessage();
        }
    }
}

// Get current date and time
$current_datetime = date('F d, Y - h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Student - ISATU Election System</title>
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
            <a href="edit_student.php?logout=1" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
        
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-title">
            <i class="bi bi-pencil-square"></i> Edit Student
        </div>
        
        <!-- Edit Form -->
        <div class="form-card">
            <div class="card-header-action">
                <div class="d-flex align-items-center">
                    <div class="college-icon">
                        <?php if ($college_code == 'sr'): ?>
                            <i class="bi bi-people-fill"></i>
                        <?php elseif ($college_code == 'cas'): ?>
                            <i class="bi bi-book-fill"></i>
                        <?php elseif ($college_code == 'cea'): ?>
                            <i class="bi bi-building-fill"></i>
                        <?php elseif ($college_code == 'coe'): ?>
                            <i class="bi bi-mortarboard-fill"></i>
                        <?php elseif ($college_code == 'cit'): ?>
                            <i class="bi bi-gear-fill"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h5 class="card-title">Edit Student Information</h5>
                        <div class="college-badge <?php echo $college_code; ?>">
                            <?php echo $college_names[$college_code]; ?>
                        </div>
                    </div>
                </div>
                <i class="bi bi-pencil-fill card-header-icon"></i>
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
            
            <form method="post" action="edit_student.php?id=<?php echo $id; ?>">
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
                            <option value="<?php echo $code; ?>" <?php echo ($code == $college_code) ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="college_code" value="<?php echo $college_code; ?>">
                    <div class="form-text text-muted">
                        College cannot be changed. To change college, delete this record and register a new one.
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="register_student.php?college=<?php echo $college_code; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-2"></i> Back to <?php echo $college_names[$college_code]; ?> Students
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save me-2"></i> Save Changes
                    </button>
                </div>
            </form>
            
            <div class="mt-4 pt-3 border-top">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="text-muted mb-1">Created on</h6>
                        <p class="mb-0"><?php echo date('F d, Y - h:i A', strtotime($student_data['created_at'])); ?></p>
                    </div>
                    
                    <a href="register_student.php?college=<?php echo $college_code; ?>&delete_id=<?php echo $id; ?>" 
                       class="btn btn-danger btn-delete">
                        <i class="bi bi-trash me-2"></i> Delete Student
                    </a>
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
        
        // Confirm delete action
        document.querySelector('.btn-delete').addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to delete this student? This action cannot be undone, and will also remove them from the Student Republic.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>