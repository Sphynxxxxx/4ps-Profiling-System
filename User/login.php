<?php
require_once "../backend/connections/config.php";
require_once "../backend/connections/database.php";

// Initialize variables
$email = $password = "";
$errors = [];

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate email
    if (empty($_POST["email"])) {
        $errors["email"] = "Email is required";
    } else {
        $email = test_input($_POST["email"]);
        // Check if email is valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors["email"] = "Invalid email format";
        }
    }
    
    // Validate password
    if (empty($_POST["password"])) {
        $errors["password"] = "Password is required";
    } else {
        $password = $_POST["password"];
    }
    
    // Process login if no validation errors
    if (empty($errors)) {
        try {
            $db = new Database();
            
            // Get user by email
            $sql = "SELECT user_id, email, password, full_name, role, account_status FROM users WHERE email = ?";
            $user = $db->fetchOne($sql, [$email]);
            
            if ($user) {
                // Check account status
                if ($user['account_status'] == 'pending') {
                    $errors["login"] = "Your account is pending approval. Please wait for administrator confirmation.";
                } else if ($user['account_status'] == 'suspended') {
                    $errors["login"] = "Your account has been suspended. Please contact the administrator.";
                } else if ($user['account_status'] == 'deactivated') {
                    $errors["login"] = "Your account has been deactivated. Please contact the administrator.";
                } else if (password_verify($password, $user['password'])) {
                    // Login successful
                    // Store user data in session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Update last login timestamp
                    $updateSql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                    $db->execute($updateSql, [$user['user_id']]);
                    
                    // Log activity
                    $logSql = "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                              VALUES (?, ?, ?, ?, ?)";
                    $db->execute($logSql, [
                        $user['user_id'],
                        'LOGIN',
                        'User logged in successfully',
                        $_SERVER['REMOTE_ADDR'],
                        $_SERVER['HTTP_USER_AGENT']
                    ]);
                    
                    // Redirect based on role
                    if ($user['role'] == 'admin') {
                        header("Location: ../admin/dashboard.php");
                    } else {
                        header("Location: dashboard.php");
                    }
                    exit();
                } else {
                    // Password incorrect
                    $errors["login"] = "Invalid email or password";
                }
            } else {
                // User not found
                $errors["login"] = "Invalid email or password";
            }
            
            $db->closeConnection();
            
        } catch (Exception $e) {
            $errors["login"] = "An error occurred. Please try again later.";
            error_log("Login Error: " . $e->getMessage());
        }
    }
}

function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check for success message from registration
$success_msg = isset($_SESSION['success_msg']) ? $_SESSION['success_msg'] : '';
if (!empty($success_msg)) {
    unset($_SESSION['success_msg']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .navbar {
            background-color: #0056b3;
            padding: 15px 0;
        }
        
        .navbar-brand {
            color: #fff;
            font-weight: 600;
            font-size: 1.5rem;
        }
        
        .navbar-brand img {
            height: 40px;
            margin-right: 10px;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.85);
            font-weight: 500;
            margin: 0 10px;
        }
        
        .nav-link:hover {
            color: #fff;
        }
        
        .login-container {
            max-width: 500px;
            margin: 80px auto;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            padding: 40px;
        }
        
        .login-title {
            text-align: center;
            color: #0056b3;
            font-weight: 700;
            margin-bottom: 30px;
        }
        
        .login-icon {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .login-icon i {
            font-size: 3rem;
            color: #0056b3;
            padding: 20px;
            border-radius: 50%;
            background-color: rgba(0, 86, 179, 0.1);
        }
        
        .form-control {
            padding: 12px 15px;
            border-radius: 6px;
            border: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .form-control:focus {
            border-color: #0056b3;
            box-shadow: 0 0 0 0.2rem rgba(0, 86, 179, 0.25);
        }
        
        .btn-login {
            background-color: #0056b3;
            color: #fff;
            font-weight: 600;
            padding: 12px 40px;
            border: none;
            border-radius: 30px;
            font-size: 1.1rem;
            display: block;
            width: 100%;
            margin: 30px 0 20px;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
            background-color: #004494;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .forgotten-password {
            display: block;
            text-align: center;
            color: #0056b3;
            text-decoration: none;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        
        .register-link a {
            color: #0056b3;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../index.php">
                <img src="assets/pngwing.com (7).png" alt="DSWD Logo">
                <span>4P's Profiling System</span>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="services.php">Services</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="contact.php">Contact</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Login Form -->
    <div class="container">
        <div class="login-container">
            <div class="login-icon">
                <i class="fas fa-user-circle"></i>
            </div>
            
            <h1 class="login-title">Login to Your Account</h1>
            
            <?php if(!empty($success_msg)): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            
            <?php if(!empty($errors["login"])): ?>
                <div class="alert alert-danger"><?php echo $errors["login"]; ?></div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control <?php echo (!empty($errors['email'])) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo $email; ?>" placeholder="Enter your email">
                    <?php if(!empty($errors['email'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control <?php echo (!empty($errors['password'])) ? 'is-invalid' : ''; ?>" id="password" name="password" placeholder="Enter your password">
                    <?php if(!empty($errors['password'])): ?>
                        <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn-login">LOGIN</button>
                
                <a href="forgot_password.php" class="forgotten-password">Forgot Password?</a>
                
                <div class="register-link">
                    Don't have an account? <a href="registration.php">Register Now</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>