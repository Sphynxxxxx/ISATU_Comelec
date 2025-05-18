<?php
session_start();

// Static admin credentials
$admin_username = "admin";
$admin_password = "isatu2025";

// Check if admin is already logged in
if(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: admin/admin_dashboard.php");
    exit();
}

// Process login form submission
$error_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Validate credentials
    if ($username === $admin_username && $password === $admin_password) {
        // Set session variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        
        // Redirect to admin dashboard
        header("Location: admin.php");
        exit();
    } else {
        $error_message = "Invalid username or password. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - ISATU College Election System</title>
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
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            max-width: 450px;
            width: 100%;
            padding: 20px;
        }
        
        .login-card {
            background-color: white;
            border-radius: 16px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
            padding: 30px;
            border-top: 7px solid var(--isatu-primary);
            border-bottom: 7px solid var(--isatu-secondary);
        }
        
        .isatu-logo {
            max-width: 130px;
            margin-bottom: 20px;
        }
        
        h1, h2, h3, h4, h5 {
            color: var(--isatu-primary);
        }
        
        .btn-login {
            background-color: var(--isatu-primary);
            border-color: var(--isatu-primary);
            color: white;
            padding: 12px 20px;
            font-weight: 600;
            border-radius: 40px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            transition: all 0.3s;
            width: 100%;
            margin-top: 15px;
        }
        
        .btn-login:hover, .btn-login:focus {
            background-color: var(--isatu-dark);
            border-color: var(--isatu-dark);
            transform: scale(1.03);
            box-shadow: 0 8px 25px rgba(12, 59, 93, 0.35);
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 10px;
            border: 1px solid #ccc;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: var(--isatu-primary);
            box-shadow: 0 0 0 0.2rem rgba(12, 59, 93, 0.25);
        }
        
        .form-label {
            font-weight: 500;
            color: var(--isatu-dark);
        }
        
        .login-icon {
            font-size: 4rem;
            color: var(--isatu-primary);
            margin-bottom: 15px;
        }
        
        .back-link {
            color: var(--isatu-primary);
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
            font-weight: 500;
        }
        
        .back-link:hover {
            color: var(--isatu-accent);
            text-decoration: underline;
        }
        
        .footer-text {
            color: var(--isatu-primary);
            font-size: 0.9rem;
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="text-center mb-4">
            <img src="assets/logo/ISAT-U-logo-2.png" alt="ISATU Logo" class="isatu-logo">
        </div>
        
        <div class="login-card">
            <div class="text-center">
                <div class="login-icon">
                    <i class="bi bi-shield-lock"></i>
                </div>
                <h3 class="mb-4">Admin Login</h3>
                <p class="text-muted mb-4">Please enter your credentials to access the admin panel.</p>
            </div>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" class="form-control" id="username" name="username" required placeholder="Enter username">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-key"></i></span>
                        <input type="password" class="form-control" id="password" name="password" required placeholder="Enter password">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Log In
                </button>
            </form>
            
            <div class="text-center">
                <a href="index.php" class="back-link"><i class="bi bi-arrow-left me-1"></i> Back to Voting System</a>
            </div>
        </div>
        
        <div class="text-center mt-4 mb-4">
            <div class="footer-text">
                <div>
                    <i class="bi bi-building me-2"></i> © 2025 Iloilo Science and Technology University
                </div>
                <div>
                    </i> This website was developed by Larry Denver Biaco
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>