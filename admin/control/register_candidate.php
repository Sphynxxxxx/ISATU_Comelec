<?php
session_start();
require_once "../../backend/connections/config.php";

// Check if admin is logged in
if(!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit();
}

// Get college parameter from URL
$college_code = isset($_GET['college']) ? $_GET['college'] : '';

// College validation
$valid_colleges = ['sr', 'cas', 'cea', 'coe', 'cit'];
if(!in_array($college_code, $valid_colleges)) {
    // Invalid college code, redirect to manage candidates
    header("Location: manage_candidates.php");
    exit();
}

// Define college names for display
$college_names = [
    'sr' => 'Student Republic',
    'cas' => 'College of Arts and Sciences',
    'cea' => 'College of Engineering and Architecture',
    'coe' => 'College of Education',
    'cit' => 'College of Industrial Technology'
];

// Define positions for each college
$positions = [
    'sr' => ['President', 'Vice President', 'Senator'],
    'cas' => [
        'Governor', 
        'Vice Governor', 
        'Secretary', 
        'Assistant Secretary', 
        'Treasurer', 
        'Assistant Treasurer', 
        'Auditor', 
        'Assistant Auditor', 
        'Business Manager', 
        'Assistant Business Manager', 
        'Public Relation Officer', 
        'Social Media Manager', 
        'Content Manager', 
        'BS Math Representative', 
        'BS Humserve Representative', 
        'BAEL Representative', 
        'BSCD Representative', 
        'BS Bio Representative'
    ],
    'cea' => [], // Will be blank for now
    'coe' => [], // Will be blank for now
    'cit' => []  // Will be blank for now
];

// Define parties
$parties = ['TDA', 'IND'];

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Validate and sanitize input
    $student_id_number = isset($_POST['student_id']) ? trim($_POST['student_id']) : '';
    $name = isset($_POST['name']) ? trim($_POST['name']) : '';
    $position = isset($_POST['position']) ? trim($_POST['position']) : '';
    $party = isset($_POST['party']) ? trim($_POST['party']) : '';
    $platform = isset($_POST['platform']) ? trim($_POST['platform']) : '';
    
    // Validation
    $errors = [];
    
    if (empty($student_id_number)) {
        $errors[] = "Student ID is required";
    }
    
    if (empty($name)) {
        $errors[] = "Candidate name is required";
    }
    
    if (empty($position)) {
        $errors[] = "Position is required";
    }
    
    if ($college_code == 'sr' && empty($party)) {
        $errors[] = "Party list is required for Student Republic candidates";
    }
    
    // Image upload handling
    $photo_path = '';
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed_ext = ['jpg', 'jpeg', 'png'];
        $file_name = $_FILES['photo']['name'];
        $file_size = $_FILES['photo']['size'];
        $file_tmp = $_FILES['photo']['tmp_name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Check extension
        if (!in_array($file_ext, $allowed_ext)) {
            $errors[] = "Extension not allowed, please choose a JPEG or PNG file.";
        }
        
        // Check file size (5MB max)
        if ($file_size > 5242880) {
            $errors[] = "File size must be less than 5 MB";
        }
        
        if (empty($errors)) {
            // Create unique file name to prevent overwriting
            $new_file_name = uniqid('candidate_') . '.' . $file_ext;
            $upload_path = '../../uploads/candidates/' . $new_file_name;
            
            // Create directory if it doesn't exist
            if (!file_exists('../../uploads/candidates/')) {
                mkdir('../../uploads/candidates/', 0777, true);
            }
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $photo_path = $new_file_name;
            } else {
                $errors[] = "Failed to upload image";
            }
        }
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // First check if the student exists in the students table
            $check_student_query = "SELECT id FROM students WHERE student_id = ? AND college_code = ?";
            $stmt = $conn->prepare($check_student_query);
            $stmt->bind_param("ss", $student_id_number, $college_code);
            $stmt->execute();
            $result = $stmt->get_result();
            
            // Get the student_id (primary key from students table)
            if ($result->num_rows > 0) {
                // Student exists, get their ID
                $student = $result->fetch_assoc();
                $student_id = $student['id'];
            } else {
                // Student doesn't exist, insert them first
                $insert_student = "INSERT INTO students (student_id, college_code, created_at) VALUES (?, ?, NOW())";
                $stmt = $conn->prepare($insert_student);
                $stmt->bind_param("ss", $student_id_number, $college_code);
                $stmt->execute();
                
                // Get the newly created student ID
                $student_id = $conn->insert_id;
            }
            
            // Get the position_id from the positions table
            $position_query = "SELECT id FROM positions WHERE name = ?";
            $stmt = $conn->prepare($position_query);
            $stmt->bind_param("s", $position);
            $stmt->execute();
            $position_result = $stmt->get_result();
            
            if ($position_result->num_rows > 0) {
                $position_row = $position_result->fetch_assoc();
                $position_id = $position_row['id'];
            } else {
                // Position doesn't exist, create a new one
                $insert_position = "INSERT INTO positions (name, max_candidates, max_votes, display_order) 
                                   VALUES (?, 1, 1, 0)";
                $stmt = $conn->prepare($insert_position);
                $stmt->bind_param("s", $position);
                $stmt->execute();
                $position_id = $conn->insert_id;
            }
            
            // Get or create the party_id
            $party_id = null;
            if (!empty($party)) {
                $party_query = "SELECT id FROM parties WHERE name = ?";
                $stmt = $conn->prepare($party_query);
                $stmt->bind_param("s", $party);
                $stmt->execute();
                $party_result = $stmt->get_result();
                
                if ($party_result->num_rows > 0) {
                    $party_row = $party_result->fetch_assoc();
                    $party_id = $party_row['id'];
                } else {
                    // Party doesn't exist, create a new one
                    $insert_party = "INSERT INTO parties (name, description) VALUES (?, ?)";
                    $description = $party . " Party";
                    $stmt = $conn->prepare($insert_party);
                    $stmt->bind_param("ss", $party, $description);
                    $stmt->execute();
                    $party_id = $conn->insert_id;
                }
            }
            
            // Now insert into the candidates table using the student's ID
            $insert_candidate = "INSERT INTO candidates (student_id, name, college_code, position, party, position_id, party_id, platform, photo_url, created_at) 
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
            
            // Now insert the candidate with all the required fields
            $stmt = $conn->prepare($insert_candidate);
            $stmt->bind_param("issssiiss", $student_id, $name, $college_code, $position, $party, $position_id, $party_id, $platform, $photo_path);
            
            if ($stmt->execute()) {
                // Success message
                $success_message = "Candidate registered successfully!";
                
                // Redirect after a delay
                header("refresh:2;url=../manage_candidates.php");
            } else {
                $errors[] = "Error registering candidate: " . $conn->error;
            }
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}

// Current date and time
$current_datetime = date('F d, Y - h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Candidate - <?php echo $college_names[$college_code]; ?> - ISATU Election System</title>
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
        
        .college-badge {
            font-size: 0.9rem;
            padding: 5px 10px;
            border-radius: 20px;
            margin-left: 15px;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .sr-badge {
            background-color: rgba(0, 0, 0, 0.1);
            color: #000000;
        }
        
        .cas-badge {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .cea-badge {
            background-color: rgba(255, 140, 0, 0.1);
            color: #ff8c00;
        }
        
        .coe-badge {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .cit-badge {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        
        .form-card {
            background-color: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .form-subtitle {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--isatu-primary);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 500;
            color: var(--isatu-primary);
        }
        
        .form-control:focus {
            border-color: var(--isatu-accent);
            box-shadow: 0 0 0 0.25rem rgba(26, 100, 160, 0.25);
        }
        
        .btn-isatu {
            background-color: var(--isatu-primary);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .btn-isatu:hover {
            background-color: var(--isatu-dark);
            color: white;
            transform: translateY(-2px);
        }
        
        .img-preview {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin-top: 10px;
            display: none;
        }
        
        .timestamp {
            font-size: 0.9rem;
            color: #6c757d;
            margin-top: 30px;
            text-align: right;
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
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
            <a href="../manage_students.php" class="nav-link">
                <i class="bi bi-people"></i>
                <span>Manage Students</span>
            </a>
            <a href="../manage_candidates.php" class="nav-link active">
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
            <a href="../register_candidate.php?logout=1" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
        
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-title">
            <i class="bi bi-person-plus"></i> Register Candidate
            <span class="college-badge <?php echo $college_code; ?>-badge">
                <?php echo $college_names[$college_code]; ?>
            </span>
        </div>
        
        <?php if(isset($errors) && !empty($errors)): ?>
            <div class="alert alert-danger">
                <strong><i class="bi bi-exclamation-triangle"></i> Error:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if(isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle"></i> <?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <div class="form-card">
            <h5 class="form-subtitle">Candidate Information</h5>
            
            <form action="register_candidate.php?college=<?php echo $college_code; ?>" method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="student_id" class="form-label">Student ID</label>
                            <input type="text" class="form-control" id="student_id" name="student_id" placeholder="e.g. 2022-1234" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name" placeholder="e.g. Juan Dela Cruz" required>
                        </div>
                    </div>
                    
                    <div class="<?php echo $college_code == 'sr' ? 'col-md-6' : 'col-md-12'; ?>">
                        <div class="form-group">
                            <label for="position" class="form-label">Position</label>
                            <select class="form-select" id="position" name="position" required>
                                <option value="">Select Position</option>
                                <?php foreach($positions[$college_code] as $position): ?>
                                    <option value="<?php echo $position; ?>"><?php echo $position; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <?php if($college_code == 'sr'): ?>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="party" class="form-label">Party List</label>
                            <select class="form-select" id="party" name="party" required>
                                <option value="">Select Party</option>
                                <?php foreach($parties as $party): ?>
                                    <option value="<?php echo $party; ?>"><?php echo $party; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="party" value="INDEPENDENT">
                    <?php endif; ?>
                    
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="platform" class="form-label">Platform/Agenda (Optional)</label>
                            <textarea class="form-control" id="platform" name="platform" rows="4" placeholder="Enter candidate's platform or agenda"></textarea>
                        </div>
                    </div>
                    
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="photo" class="form-label">Candidate Photo</label>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/jpeg, image/png">
                            <small class="text-muted">Upload a clear portrait photo. Max size: 5MB. Formats: JPG, PNG.</small>
                            <img id="photoPreview" class="img-preview mt-2" src="#" alt="Preview">
                        </div>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between mt-4">
                    <a href="../manage_candidates.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Candidates
                    </a>
                    <button type="submit" class="btn btn-isatu">
                        <i class="bi bi-person-plus"></i> Register Candidate
                    </button>
                </div>
            </form>
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
        
        // Image preview
        document.getElementById('photo').addEventListener('change', function(e) {
            const preview = document.getElementById('photoPreview');
            const file = e.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    </script>
</body>
</html>