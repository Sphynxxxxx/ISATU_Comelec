<?php
session_start();

// Process form submission if any
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['college'])) {
    $_SESSION['selected_college'] = $_POST['college'];
    header("Location: college_dashboard.php");
    exit();
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
            <div class="d-flex align-items-center flex-wrap">
                <img src="assets/logo/ISAT-U-logo-2.png" alt="ISATU Logo" class="isatu-logo d-block">
                <div>
                    <h1 class="mb-0">Iloilo Science and Technology University Voting System</h1>
                    <p class="text-muted mb-0 fs-5">Iloilo Science and Technology University</p>
                </div>
            </div>
            <div class="vote-counter">
                <i class="bi bi-people-fill me-2"></i> <span id="voterCount">0</span> votes
            </div>
        </div>
        
        <form method="POST" action="" id="collegeForm">
            <input type="hidden" name="college" id="selectedCollege" value="">
            
            <div class="student-republic-section mb-5">
                <h4 class="section-title">Student Republic</h4>
                <div class="row justify-content-center">
                    <div class="col-lg-8 col-md-10">
                        <div class="college-card border bg-white shadow-sm p-4" 
                            onclick="selectCollege('sr')">
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
                <h4 class="section-title">Academic Colleges</h4>
                <div class="row">
                    <!-- College of Art and Sciences -->
                    <div class="col-md-6 col-lg-3">
                        <div class="college-card border bg-white shadow-sm p-3" 
                            onclick="selectCollege('cas')">
                            <div class="college-logo-container college-img-container">
                                <img src="assets/logo/CASSC.jpg" alt="College of Arts and Sciences Logo" class="img-logo">
                            </div>
                            <h5>College of Art and Sciences</h5>
                            <p class="text-muted mb-0 fs-6">CASSC</p>
                        </div>
                    </div>
                    
                    <!-- College of Engineering and Architecture -->
                    <div class="col-md-6 col-lg-3">
                        <div class="college-card border bg-white shadow-sm p-3" 
                            onclick="selectCollege('cea')">
                            <div class="college-logo-container college-img-container">
                                <img src="assets/logo/CEASC.jpg" alt="College of Engineering and Architecture Logo" class="img-logo">
                            </div>
                            <h5>College of Engineering and Architecture</h5>
                            <p class="text-muted mb-0 fs-6">CEASC</p>
                        </div>
                    </div>
                    
                    <!-- College of Education -->
                    <div class="col-md-6 col-lg-3">
                        <div class="college-card border bg-white shadow-sm p-3" 
                            onclick="selectCollege('coe')">
                            <div class="college-logo-container college-img-container">
                                <img src="assets/logo/COESC.jpg" alt="College of Education Logo" class="img-logo">
                            </div>
                            <h5>College of Education</h5>
                            <p class="text-muted mb-0 fs-6">COESC</p>
                        </div>
                    </div>
                    
                    <!-- College of Industrial Technology -->
                    <div class="col-md-6 col-lg-3">
                        <div class="college-card border bg-white shadow-sm p-3" 
                            onclick="selectCollege('cit')">
                            <div class="college-logo-container college-img-container">
                                <img src="assets/logo/CITSC.png" alt="College of Industrial Technology Logo" class="img-logo">
                            </div>
                            <h5>College of Industrial Technology</h5>
                            <p class="text-muted mb-0 fs-6">CITSC</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-5 mb-5">
                <button type="submit" class="btn btn-vote btn-lg" id="submitBtn" disabled>
                    <i class="bi bi-check2-circle me-2"></i> Cast Your Vote
                </button>
            </div>
        </form>
        
        <div class="text-center mt-4 mb-4">
            <div class="footer-text">
                <div>
                    <i class="bi bi-building me-2"></i> © 2025 Iloilo Science and Technology University | Est. 1905
                </div>
                <div>
                    </i> This website was developed by Larry Denver Biaco
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        
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
            document.getElementById('submitBtn').disabled = false;
            
            // Scroll to the button on mobile for better UX
            if (window.innerWidth < 768) {
                document.getElementById('submitBtn').scrollIntoView({behavior: 'smooth'});
            }
        }
    </script>
</body>
</html>