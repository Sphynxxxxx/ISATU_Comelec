<?php
session_start();

// Check if vote was successful
if (!isset($_SESSION['vote_success']) || $_SESSION['vote_success'] !== true) {
    header("Location: ../../index.php");
    exit();
}

// Get college information
$college_code = $_SESSION['selected_college'];

// Get the timestamp of when the vote was actually cast
$vote_timestamp = isset($_SESSION['vote_timestamp']) ? $_SESSION['vote_timestamp'] : time();

// Set the timezone to Philippines (PHT)
date_default_timezone_set('Asia/Manila');

// College names for display
$college_names = [
    'sr' => 'Student Republic',
    'cas' => 'College of Arts and Sciences',
    'cea' => 'College of Engineering and Architecture',
    'coe' => 'College of Education',
    'cit' => 'College of Industrial Technology'
];

// Clear session data except for necessary info
$student_number = $_SESSION['student_number'];
$vote_time = date('F j, Y, g:i a', $vote_timestamp); // Format the stored timestamp with PHT
session_unset();
$_SESSION['voted'] = true;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You - ISATU Voting System</title>
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
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .container {
            max-width: 900px;
        }
        
        h1, h2, h3, h4, h5 {
            color: var(--isatu-primary);
        }
        
        .thank-you-card {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 15px 30px rgba(0,0,0,0.15);
            padding: 40px;
            text-align: center;
            border-top: 7px solid var(--isatu-primary);
            border-bottom: 7px solid var(--isatu-secondary);
            margin-bottom: 30px;
        }
        
        .success-icon {
            font-size: 80px;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .receipt-info {
            background-color: rgba(242, 192, 29, 0.1);
            border: 1px solid var(--isatu-secondary);
            border-radius: 10px;
            padding: 20px;
            margin: 30px auto;
            max-width: 400px;
            text-align: left;
        }
        
        .btn-home {
            background-color: var(--isatu-primary);
            border-color: var(--isatu-primary);
            color: white;
            padding: 12px 40px;
            font-weight: 600;
            border-radius: 40px;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            font-size: 1.1rem;
            margin-top: 20px;
        }
        
        .btn-home:hover, .btn-home:focus {
            background-color: var(--isatu-dark);
            border-color: var(--isatu-dark);
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(12, 59, 93, 0.35);
        }
        
        .isatu-logo {
            max-width: 100px;
            margin-bottom: 20px;
        }
        
        .confetti {
            position: absolute;
            width: 10px;
            height: 10px;
            background-color: #ff80ed;
            animation: confetti 5s ease-in-out -2s infinite;
            transform-origin: center;
            z-index: -1;
        }
        
        @keyframes confetti {
            0% { transform: translateY(0) rotateX(0) rotateY(0); }
            100% { transform: translateY(1000px) rotateX(720deg) rotateY(720deg); }
        }
        
        .confetti:nth-child(1) {
            left: 10%;
            animation-delay: 0;
            background-color: #f2c01d;
        }
        
        .confetti:nth-child(2) {
            left: 20%;
            animation-delay: -5s;
            background-color: #0c3b5d;
        }
        
        .confetti:nth-child(3) {
            left: 30%;
            animation-delay: -3s;
            background-color: #1a64a0;
        }
        
        .confetti:nth-child(4) {
            left: 40%;
            animation-delay: -2.5s;
            background-color: #f2c01d;
        }
        
        .confetti:nth-child(5) {
            left: 50%;
            animation-delay: -4s;
            background-color: #0c3b5d;
        }
        
        .confetti:nth-child(6) {
            left: 60%;
            animation-delay: -6s;
            background-color: #1a64a0;
        }
        
        .confetti:nth-child(7) {
            left: 70%;
            animation-delay: -1.5s;
            background-color: #f2c01d;
        }
        
        .confetti:nth-child(8) {
            left: 80%;
            animation-delay: -2s;
            background-color: #0c3b5d;
        }
        
        .confetti:nth-child(9) {
            left: 90%;
            animation-delay: -3.5s;
            background-color: #1a64a0;
        }
        
        .confetti:nth-child(10) {
            left: 100%;
            animation-delay: -2.5s;
            background-color: #f2c01d;
        }
        
        .footer-text {
            color: var(--isatu-primary);
            font-size: 1rem;
            padding: 15px;
            background-color: rgba(255, 255, 255, 0.7);
            border-radius: 30px;
            display: inline-block;
        }
        
        /* Add a watermark style for the receipt */
        .receipt-watermark {
            position: relative;
            overflow: hidden;
        }
        
        .receipt-watermark::after {
            content: "VERIFIED";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 3rem;
            font-weight: bold;
            color: rgba(40, 167, 69, 0.1);
            pointer-events: none;
            z-index: 1;
            white-space: nowrap;
        }
        
        /* Receipt timestamp style */
        .timestamp {
            font-weight: 600;
            color: var(--isatu-primary);
        }
        
        /* Philippine time indicator */
        .timezone-indicator {
            font-size: 0.8rem;
            color: #6c757d;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <!-- Confetti effect -->
    <div class="confetti"></div>
    <div class="confetti"></div>
    <div class="confetti"></div>
    <div class="confetti"></div>
    <div class="confetti"></div>
    <div class="confetti"></div>
    <div class="confetti"></div>
    <div class="confetti"></div>
    <div class="confetti"></div>
    <div class="confetti"></div>
    
    <div class="container">
        <div class="thank-you-card">
            <img src="../../assets/logo/ISAT-U-logo-2.png" alt="ISATU Logo" class="isatu-logo">
            <i class="bi bi-check-circle-fill success-icon"></i>
            <h1>Thank You for Voting!</h1>
            <p class="fs-5 text-muted">Your vote has been successfully recorded.</p>
            
            <div class="receipt-info receipt-watermark">
                <div class="mb-2">
                    <strong>Student ID:</strong> <?php echo $student_number; ?>
                </div>
                <div class="mb-2">
                    <strong>College:</strong> <?php echo $college_names[$college_code]; ?>
                </div>
                <div class="mb-2">
                    <strong>Date & Time:</strong> <span class="timestamp"><?php echo $vote_time; ?></span>
                    <span class="timezone-indicator">(PHT)</span>
                </div>
                <div>
                    <strong>Status:</strong> <span class="text-success">Vote Confirmed</span>
                </div>
            </div>
            
            <p class="mt-3">Your participation in the election process is important to ISATU.</p>
            <p>Results will be published after the election period ends.</p>
            <p><strong>Note:</strong> Take a screenshot and send to your respective Class Mayors.</p>
            
            <a href="../../index.php" class="btn btn-home">
                <i class="bi bi-house-door-fill me-2"></i> Return to Home
            </a>
        </div>
        
        <div class="text-center">
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