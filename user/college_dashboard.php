<?php
session_start();
require_once "../backend/connections/config.php"; 

// Check if a college was selected
if (!isset($_SESSION['selected_college'])) {
    header("Location: ../index.php");
    exit();
}

$college_code = $_SESSION['selected_college'];

// College names for display
$college_names = [
    'sr' => 'Student Republic',
    'cas' => 'College of Arts and Sciences',
    'cea' => 'College of Engineering and Architecture',
    'coe' => 'College of Education',
    'cit' => 'College of Industrial Technology'
];

// Process student ID verification
$error_message = '';
$success_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['student_id'])) {
    $student_id = trim($_POST['student_id']);
    
    if (empty($student_id)) {
        $error_message = "Please enter your student ID";
    } else {
        // Check if student exists in the selected college
        $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ? AND college_code = ?");
        $stmt->bind_param("ss", $student_id, $college_code);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            // Student exists, save student data to session
            $student = $result->fetch_assoc();
            $_SESSION['student_id'] = $student['id'];
            $_SESSION['student_number'] = $student_id;
            
            // Check if student has already voted
            $voted = false;
            if ($college_code == 'sr') {
                // Check Student Republic voting record
                $check_stmt = $conn->prepare("
                    SELECT v.* FROM votes v 
                    JOIN students s ON v.student_id = s.id 
                    WHERE s.student_id = ? AND s.college_code = 'sr'
                ");
                $check_stmt->bind_param("s", $student_id);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    $voted = true;
                }
                $check_stmt->close();
            } else {
                // Check college-specific voting record
                $check_stmt = $conn->prepare("
                    SELECT v.* FROM votes v 
                    JOIN students s ON v.student_id = s.id 
                    WHERE s.student_id = ? AND s.college_code = ?
                ");
                $check_stmt->bind_param("ss", $student_id, $college_code);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    $voted = true;
                }
                $check_stmt->close();
            }
            
            if ($voted) {
                $error_message = "You have already voted for this election in " . $college_names[$college_code];
            } else {
                // Redirect to voting page
                header("Location: control/voting.php");
                exit();
            }
        } else {
            $error_message = "Student ID not found in " . $college_names[$college_code];
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Verification - ISATU Voting System</title>
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
            max-width: 1000px;
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
        
        .verification-card {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            padding: 35px;
            margin-bottom: 30px;
            border-left: 6px solid var(--isatu-primary);
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
        
        .btn-verify {
            background-color: var(--isatu-primary);
            border-color: var(--isatu-primary);
            color: white;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 40px;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            font-size: 1.1rem;
            text-transform: uppercase;
        }
        
        .btn-verify:hover, .btn-verify:focus {
            background-color: var(--isatu-dark);
            border-color: var(--isatu-dark);
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(12, 59, 93, 0.35);
        }
        
        .btn-back {
            background-color: #6c757d;
            border-color: #6c757d;
            color: white;
            padding: 12px 30px;
            font-weight: 600;
            border-radius: 40px;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            font-size: 1.1rem;
        }
        
        .btn-back:hover, .btn-back:focus {
            background-color: #5a6268;
            border-color: #5a6268;
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .form-control {
            padding: 12px;
            border-radius: 8px;
            font-size: 1.1rem;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--isatu-primary);
            font-size: 1.1rem;
        }
        
        .student-id-form {
            max-width: 500px;
            margin: 0 auto;
        }
        
        .alert-warning {
            background-color: rgba(242, 192, 29, 0.2);
            border-color: var(--isatu-secondary);
            color: #856404;
            border-radius: 10px;
        }
        
        .alert-danger {
            border-radius: 10px;
        }
        
        .isatu-logo {
            max-width: 80px;
            margin-right: 25px;
        }

        .button-container {
            gap: 10px;
        }

        @media (max-width: 576px) {
            .button-container {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-back, .btn-verify {
                width: 100%;
                margin-bottom: 10px;
            }
        }
        
        @media (max-width: 767px) {
            .isatu-header {
                padding: 20px;
                text-align: center;
            }
            
            .isatu-header div {
                margin-top: 10px;
            }
            
            .verification-card {
                padding: 25px;
            }
            
            .isatu-header .d-flex.align-items-center {
                flex-direction: column;
            }
            
            .isatu-logo {
                margin: 0 auto 15px auto;
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
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="isatu-header d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center">
                <img src="../assets/logo/ISAT-U-logo-2.png" alt="ISATU Logo" class="isatu-logo">
                <div>
                    <h1 class="mb-0">Iloilo Science and Technology University Voting System</h1>
                    <p class="text-muted mb-0 fs-5">Iloilo Science and Technology University</p>
                </div>
            </div>
        </div>
        
        <div class="verification-card">
            <div class="text-center mb-4">
                <div class="college-badge p-3">
                    <?php
                    // College logos mapping
                    $college_logos = [
                        'sr' => '../assets/logo/STUDENT REPUBLIC LOGO.png',
                        'cas' => '../assets/logo/CASSC.jpg',
                        'cea' => '../assets/logo/CEASC.jpg',
                        'coe' => '../assets/logo/COESC.jpg',
                        'cit' => '../assets/logo/CITSC.png'
                    ];
                    
                    // Default to ISATU logo if no specific college logo is found
                    $logo_path = isset($college_logos[$college_code]) ? $college_logos[$college_code] : 'uploads/ISAT-U-logo-2.png';
                    ?>
                    <img src="<?php echo $logo_path; ?>" alt="<?php echo $college_names[$college_code]; ?> Logo" class="college-logo mb-2">
                    <div><?php echo $college_names[$college_code]; ?></div>
                </div>
                <h2>Student ID Verification</h2>
                <p class="text-muted">Please enter your student ID to proceed with voting</p>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <div class="alert alert-warning" role="alert">
                <i class="bi bi-info-circle-fill me-2"></i> Make sure you are registered in <strong><?php echo $college_names[$college_code]; ?></strong> to vote in this election.
            </div>
            
            <form method="POST" action="" class="student-id-form mt-4">
                <div class="mb-4">
                    <label for="student_id" class="form-label">Student ID Number</label>
                    <input type="text" class="form-control" id="student_id" name="student_id" placeholder="e.g. 2021-1234-A" required>
                    <div class="form-text">Enter your student ID in the format: YYYY-NNNN-A</div>
                </div>
                
                <div class="d-flex justify-content-between mt-4 flex-wrap button-container">
                    <a href="../index.php" class="btn btn-back mb-3 mb-md-0">
                        <i class="bi bi-arrow-left me-2"></i> Back
                    </a>
                    <button type="submit" class="btn btn-verify">
                        <i class="bi bi-shield-check me-2"></i> Verify & Continue
                    </button>
                </div>
            </form>
        </div>
        
        <div class="text-center mt-4 mb-4">
            <div class="footer-text">
                <div>
                    <i class="bi bi-building me-2"></i> © 2025 Iloilo Science and Technology University
                </div>
                <div>
                    </i>Developed by Larry Denver Biaco
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>