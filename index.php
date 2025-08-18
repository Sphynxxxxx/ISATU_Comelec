<?php
session_start();
require_once "backend/connections/config.php"; 


date_default_timezone_set('Asia/Manila');

// Process form submission if any
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['college'])) {
    $_SESSION['selected_college'] = $_POST['college'];
    header("Location: user/college_dashboard.php");
    exit();
}

// Get voting schedule from database
$schedule_query = "SELECT * FROM voting_schedule WHERE is_active = 1 ORDER BY id DESC LIMIT 1";
$schedule_result = $conn->query($schedule_query);
$voting_schedule = $schedule_result->fetch_assoc();

// Determine voting status
$current_time = date('Y-m-d H:i:s');
$voting_status = "";
$countdown_date = "";
$is_voting_active = false;
$heading_text = "";
$status_class = "";

if ($voting_schedule) {
    $start_time = $voting_schedule['start_time'];
    $end_time = $voting_schedule['end_time'];
    
    if ($current_time < $start_time) {
        // Voting not started yet
        $voting_status = "Voting will start soon";
        $countdown_date = $start_time;
        $heading_text = "Voting Starts In:";
        $status_class = "upcoming";
    } elseif ($current_time >= $start_time && $current_time <= $end_time) {
        // Voting is active
        $voting_status = "Voting is OPEN";
        $countdown_date = $end_time;
        $heading_text = "Voting Ends In:";
        $is_voting_active = true;
        $status_class = "active";
    } else {
        // Voting ended
        $voting_status = "Voting has ENDED";
        $countdown_date = null;
        $heading_text = "Voting Closed On:";
        $status_class = "ended";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISATU College Election System</title>
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
        .college-card {
            transition: transform 0.3s, box-shadow 0.3s;
            cursor: pointer;
            height: 300px; 
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            margin-bottom: 35px;
            text-align: center;
            border-radius: 16px;
            position: relative;
            overflow: hidden;
            border: 2px solid #dee2e6; 
            padding: 20px; 
        }
        
        .college-card:hover {
            transform: translateY(-8px); 
            box-shadow: 0 15px 30px rgba(12, 59, 93, 0.25); 
        }
        
        .college-card-disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .college-card-disabled:hover {
            transform: none;
            box-shadow: none;
        }
        
        .card-selected {
            border: 4px solid var(--isatu-secondary); 
            background-color: rgba(242, 192, 29, 0.1);
        }
        
        .card-selected::after {
            content: "✓";
            position: absolute;
            top: 15px;
            right: 15px;
            background-color: var(--isatu-secondary);
            color: var(--isatu-primary);
            width: 40px; 
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
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
            max-width: 1400px;
        }
        
        h1, h2, h3, h4, h5 {
            color: var(--isatu-primary);
        }
        
        h1 {
            font-size: 2.5rem; 
        }
        
        h4 {
            font-size: 1.6rem; 
        }
        
        h5 {
            font-size: 1.25rem; 
        }
        
        .college-logo-container {
            width: 800px; 
            height: auto;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            overflow: hidden;
            background-color: white;
        }
        
        .college-img-container {
            width: auto;
            height: auto;
        }
        
        .img-logo {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
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
        
        .vote-counter {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: var(--isatu-primary);
            padding: 8px 20px; 
            border-radius: 25px; 
            font-size: 1rem; 
            color: white;
            font-weight: 600;
        }
        
        .btn-vote {
            background-color: var(--isatu-primary);
            border-color: var(--isatu-primary);
            color: white;
            padding: 15px 50px;
            font-weight: 600;
            border-radius: 40px; 
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.3s;
            font-size: 1.2rem; 
            margin-top: 20px; 
        }
        
        .btn-vote:hover, .btn-vote:focus {
            background-color: var(--isatu-dark);
            border-color: var(--isatu-dark);
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(12, 59, 93, 0.35); 
        }
        
        .btn-vote:disabled {
            background-color: #6c757d;
            border-color: #6c757d;
            opacity: 0.65;
            transform: none;
            box-shadow: none;
        }
        
        .isatu-logo {
            max-width: 130px; 
            margin-right: 25px;
        }
        
        .student-republic-section {
            background-color: rgba(12, 59, 93, 0.05);
            border-radius: 20px; 
            padding: 35px 30px 15px 30px; 
            margin-bottom: 40px;
            border-left: 6px solid var(--isatu-primary); 
            box-shadow: 0 5px 15px rgba(0,0,0,0.05); 
        }
        
        .colleges-section {
            background-color: white;
            border-radius: 20px; 
            padding: 35px 30px; 
            box-shadow: 0 8px 20px rgba(0,0,0,0.1); 
            margin-bottom: 30px; 
        }
        
        .section-title {
            color: var(--isatu-primary);
            font-weight: 600;
            margin-bottom: 30px; 
            padding-bottom: 12px;
            border-bottom: 3px solid var(--isatu-secondary); 
            display: inline-block;
            font-size: 1.8rem; 
        }
        
        /* Countdown timer styles */
        .voting-status-banner {
            background-color: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 40px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 6px solid var(--isatu-primary);
        }
        
        .voting-status-banner.active {
            border-left: 6px solid #198754;
        }
        
        .voting-status-banner.upcoming {
            border-left: 6px solid #ffc107;
        }
        
        .voting-status-banner.ended {
            border-left: 6px solid #dc3545;
        }
        
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 15px;
            letter-spacing: 1px;
        }
        
        .status-badge.active {
            background-color: rgba(25, 135, 84, 0.1);
            color: #198754;
        }
        
        .status-badge.upcoming {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .status-badge.ended {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .countdown-container {
            display: flex;
            justify-content: center;
            margin-top: 15px;
            gap: 15px;
        }
        
        .countdown-box {
            background-color: var(--isatu-primary);
            color: white;
            border-radius: 10px;
            padding: 15px;
            min-width: 80px;
            box-shadow: 0 4px 10px rgba(12, 59, 93, 0.2);
        }
        
        .countdown-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .countdown-label {
            font-size: 0.8rem;
            text-transform: uppercase;
            opacity: 0.8;
        }
        
        /* Timezone indicator */
        .timezone-badge {
            display: inline-block;
            padding: 2px 8px;
            background-color: rgba(12, 59, 93, 0.1);
            border-radius: 4px;
            font-size: 0.8rem;
            margin-left: 8px;
            color: var(--isatu-primary);
        }
        
        @media (min-width: 992px) {
            .college-card {
                height: 320px;
            }
        }
        
        @media (max-width: 991px) {
            .college-card {
                height: 280px; 
            }
            
            .college-img-container {
                width: 100px;
                height: 100px;
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
            
            .vote-counter {
                position: relative;
                top: 0;
                right: 0;
                margin: 15px auto 0;
                display: inline-block;
            }
            
            .btn-vote {
                padding: 12px 40px;
                font-size: 1.1rem;
            }
            
            .section-title {
                font-size: 1.5rem;
            }

            .isatu-header .d-flex.align-items-center.flex-wrap {
                flex-direction: column !important;
                justify-content: center !important;
                width: 100%;
            }
            
            .isatu-logo {
                display: block !important;
                margin: 0 auto 15px auto !important;
                max-width: 110px !important;
            }
            
            /* Center the text content */
            .isatu-header div {
                text-align: center;
                width: 100%;
            }
            
            .countdown-container {
                flex-wrap: wrap;
            }
            
            .countdown-box {
                min-width: 70px;
                padding: 10px;
            }
            
            .countdown-value {
                font-size: 1.7rem;
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

        .logo-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 0.5rem; 
            padding: 1rem;
        }

        .isatu-logo {
            max-width: 100%;
            height: auto;
            width: 150px;
        }

        @media (max-width: 576px) {
            .isatu-logo {
                width: 100px;
            }
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="isatu-header d-flex align-items-center justify-content-between flex-wrap">
            <div class="d-flex align-items-center flex-wrap">
                <!-- Container for images -->
                <div class="logo-container">
                    <img src="assets/logo/ISAT-U-logo-2.png" alt="ISATU Logo" class="isatu-logo d-block">
                    <img src="assets/logo/isatu_comelec.png" alt="COMELEC Logo" class="isatu-logo d-block">
                </div>

                <div>
                    <h1 class="mb-0">Student Republic Commission on Elections</h1>
                    <p class="text-muted mb-0 fs-5">Iloilo Science and Technology University</p>
                </div>
                
            </div>
        </div>
        
        <!-- Voting Status Banner with Countdown Timer -->
        <?php if (isset($voting_schedule)): ?>
        <div class="voting-status-banner <?php echo $status_class; ?>">
            <div class="status-badge <?php echo $status_class; ?>">
                <?php echo $voting_status; ?>
            </div>
            <h4><?php echo $heading_text; ?></h4>
            
            <?php if ($countdown_date): ?>
            <div class="countdown-container" id="countdown">
                <div class="countdown-box">
                    <div class="countdown-value" id="days">00</div>
                    <div class="countdown-label">Days</div>
                </div>
                <div class="countdown-box">
                    <div class="countdown-value" id="hours">00</div>
                    <div class="countdown-label">Hours</div>
                </div>
                <div class="countdown-box">
                    <div class="countdown-value" id="minutes">00</div>
                    <div class="countdown-label">Minutes</div>
                </div>
                <div class="countdown-box">
                    <div class="countdown-value" id="seconds">00</div>
                    <div class="countdown-label">Seconds</div>
                </div>
            </div>
            <div class="mt-3 text-muted">
                <?php if ($is_voting_active): ?>
                Please select your college to cast your vote before the deadline.
                <?php else: ?>
                Voting will be available once the election period begins.
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="text-muted mt-3">
                <p>Voting period ended on <?php echo date('F d, Y - h:i A', strtotime($voting_schedule['end_time'])); ?></p>
                <p>Please contact the administration for any inquiries.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="voting-status-banner">
            <div class="status-badge">No Active Election</div>
            <h4>No Voting Schedule Available</h4>
            <p class="text-muted mt-3">There is currently no active election schedule. Please check back later or contact the administration for more information.</p>
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="collegeForm">
            <input type="hidden" name="college" id="selectedCollege" value="">
            
            <div class="student-republic-section mb-5">
                <h4 class="section-title">Student Republic</h4>
                <div class="row justify-content-center">
                    <div class="col-lg-8 col-md-10">
                        <div class="college-card border bg-white shadow-sm p-4 <?php echo (!$is_voting_active) ? 'college-card-disabled' : ''; ?>" 
                            onclick="<?php echo ($is_voting_active) ? 'selectCollege(\'sr\')' : ''; ?>">
                            <div class="college-logo-container">
                                <img src="assets/logo/STUDENT REPUBLIC LOGO.png" alt="Student Republic Logo" class="img-logo sr-img-logo">
                            </div>
                            <h4>Student Republic</h4>
                            <p class="text-muted mb-0 fs-5">ISAT U Student Republic - Iloilo City</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- 4 Colleges Section -->
            <div class="colleges-section">
                <h4 class="section-title">Colleges</h4>
                <div class="row">
                    <!-- College of Art and Sciences -->
                    <div class="col-md-6 col-lg-3">
                        <div class="college-card border bg-white shadow-sm p-3 <?php echo (!$is_voting_active) ? 'college-card-disabled' : ''; ?>" 
                            onclick="<?php echo ($is_voting_active) ? 'selectCollege(\'cas\')' : ''; ?>">
                            <div class="college-logo-container college-img-container">
                                <img src="assets/logo/CASSC.jpg" alt="College of Arts and Sciences Logo" class="img-logo">
                            </div>
                            <h5>College of Art and Sciences</h5>
                            <p class="text-muted mb-0 fs-6">CASSC</p>
                        </div>
                    </div>
                    
                    <!-- College of Engineering and Architecture -->
                    <div class="col-md-6 col-lg-3">
                        <div class="college-card border bg-white shadow-sm p-3 <?php echo (!$is_voting_active) ? 'college-card-disabled' : ''; ?>" 
                            onclick="<?php echo ($is_voting_active) ? 'selectCollege(\'cea\')' : ''; ?>">
                            <div class="college-logo-container college-img-container">
                                <img src="assets/logo/CEASC.jpg" alt="College of Engineering and Architecture Logo" class="img-logo">
                            </div>
                            <h5>College of Engineering and Architecture</h5>
                            <p class="text-muted mb-0 fs-6">CEASC</p>
                        </div>
                    </div>
                    
                    <!-- College of Education -->
                    <div class="col-md-6 col-lg-3">
                        <div class="college-card border bg-white shadow-sm p-3 <?php echo (!$is_voting_active) ? 'college-card-disabled' : ''; ?>" 
                            onclick="<?php echo ($is_voting_active) ? 'selectCollege(\'coe\')' : ''; ?>">
                            <div class="college-logo-container college-img-container">
                                <img src="assets/logo/COESC.jpg" alt="College of Education Logo" class="img-logo">
                            </div>
                            <h5>College of Education</h5>
                            <p class="text-muted mb-0 fs-6">ED GUILD</p>
                        </div>
                    </div>
                    
                    <!-- College of Industrial Technology -->
                    <div class="col-md-6 col-lg-3">
                        <div class="college-card border bg-white shadow-sm p-3 <?php echo (!$is_voting_active) ? 'college-card-disabled' : ''; ?>" 
                            onclick="<?php echo ($is_voting_active) ? 'selectCollege(\'cit\')' : ''; ?>">
                            <div class="college-logo-container college-img-container">
                                <img src="assets/logo/CITSC.png" alt="College of Industrial Technology Logo" class="img-logo">
                            </div>
                            <h5>College of Industrial Technology</h5>
                            <p class="text-muted mb-0 fs-6">OIT</p>
                        </div>
                    </div>

                    <!-- College of Computing and Informatics -->
                    <div class="col-md-6 col-lg-3">
                        <div class="college-card border bg-white shadow-sm p-3 <?php echo (!$is_voting_active) ? 'college-card-disabled' : ''; ?>" 
                            onclick="<?php echo ($is_voting_active) ? 'selectCollege(\'cci\')' : ''; ?>">
                            <div class="college-logo-container college-img-container">
                                <img src="assets/logo/CCI.png" alt="College of Computing and Informatics" class="img-logo">
                            </div>
                            <h5>College of Computing and Informatics</h5>
                            <p class="text-muted mb-0 fs-6">CCISC</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5 mb-5">
                <button type="submit" class="btn btn-vote btn-lg" id="submitBtn" disabled>
                    <i class="bi bi-check2-circle me-2"></i> Cast Your Vote
                </button>
                <?php if (!$is_voting_active && $voting_schedule): ?>
                <div class="text-muted mt-3">
                    <i class="bi bi-info-circle"></i> 
                    <?php if ($current_time < $start_time): ?>
                        Voting is not yet available. Please wait until the voting period begins.
                    <?php else: ?>
                        Voting period has ended. Thank you for your participation.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </form>
        
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
    
    <script>
        // College selection function
        function selectCollege(collegeCode) {
            // Remove selection from all cards
            document.querySelectorAll('.college-card').forEach(card => {
                card.classList.remove('card-selected');
            });
            
            // Add selection to clicked card
            event.currentTarget.classList.add('card-selected');
            
            // Set the hidden input value
            document.getElementById('selectedCollege').value = collegeCode;
            
            // Enable the submit button
            <?php if ($is_voting_active): ?>
            document.getElementById('submitBtn').disabled = false;
            <?php endif; ?>
            
            // Scroll to the button on mobile for better UX
            if (window.innerWidth < 768) {
                document.getElementById('submitBtn').scrollIntoView({behavior: 'smooth'});
            }
        }
        
        // Countdown timer function
        <?php if ($countdown_date): ?>
        const countdownDate = new Date("<?php echo $countdown_date; ?>").getTime();
        
        // Update the countdown every 1 second
        const countdown = setInterval(function() {
            // Get current date and time
            const now = new Date().getTime();
            
            // Find the time remaining between now and the countdown date
            const distance = countdownDate - now;
            
            // Time calculations for days, hours, minutes and seconds
            const days = Math.floor(distance / (1000 * 60 * 60 * 24));
            const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
            const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
            const seconds = Math.floor((distance % (1000 * 60)) / 1000);
            
            // Display the result in the corresponding elements
            document.getElementById("days").innerHTML = days.toString().padStart(2, '0');
            document.getElementById("hours").innerHTML = hours.toString().padStart(2, '0');
            document.getElementById("minutes").innerHTML = minutes.toString().padStart(2, '0');
            document.getElementById("seconds").innerHTML = seconds.toString().padStart(2, '0');
            
            // If the countdown is finished, disable the voting functionality and reload
            if (distance < 0) {
                clearInterval(countdown);
                
                // Disable all college cards
                document.querySelectorAll('.college-card').forEach(card => {
                    card.classList.add('college-card-disabled');
                    card.onclick = null;
                });
                
                // Disable the submit button
                document.getElementById('submitBtn').disabled = true;
                
                // Show message
                const messageDiv = document.createElement('div');
                messageDiv.className = 'alert alert-warning mt-3';
                messageDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i> Voting period has ended. The page will refresh momentarily.';
                document.getElementById('submitBtn').insertAdjacentElement('afterend', messageDiv);
                
                // Reload the page after a short delay
                setTimeout(function() {
                    location.reload();
                }, 3000);
            }
        }, 1000);
        <?php endif; ?>
    </script>
</body>
</html>