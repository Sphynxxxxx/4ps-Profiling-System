<?php
require_once "../backend/connections/config.php";
require_once "../backend/connections/database.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define a debug mode flag - set to false in production
$debug_mode = true;

// Initialize variables
$email = $password = "";
$errors = [];

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check for GET parameters first (logout message, etc.)
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = $_GET['message'];
    $messageType = $_GET['type'];
    
    if ($messageType == 'success') {
        $success_msg = $message;
    } else if ($messageType == 'error') {
        $errors["login"] = $message;
    }
}

// Check if user is already logged in - this should be reliable
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // Check if this is a fresh login (from a POST) or just a page refresh
    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        header("Location: dashboard.php");
        exit();
    }
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
            $sql = "SELECT user_id, email, password, firstname, lastname, role, account_status FROM users WHERE email = ?";
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
                    $_SESSION['firstname'] = $user['firstname'];
                    $_SESSION['middle_initial'] = $user['middle_initial'];
                    $_SESSION['lastname'] = $user['lastname'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_activity'] = time(); // Add this for session timeout tracking
                    
                    try {
                        // Update last login timestamp
                        $updateSql = "UPDATE users SET last_login = NOW() WHERE user_id = ?";
                        $result = $db->execute($updateSql, [$user['user_id']]);
                        
                        if ($debug_mode) {
                            error_log("Update last_login result: " . ($result ? "Success" : "Failed"));
                        }
                        
                        // Redirect based on role
                        if ($user['role'] == 'admin') {
                            header("Location: ../admin/dashboard.php");
                        } else {
                            header("Location: dashboard.php");
                        }
                        exit();
                    } catch (Exception $e) {
                        // This will only catch errors in the update part
                        if ($debug_mode) {
                            $errors["login"] = "Error after successful login: " . $e->getMessage();
                            error_log("Login process error: " . $e->getMessage());
                        } else {
                            $errors["login"] = "An error occurred during login. Please try again later.";
                            error_log("Login process error: " . $e->getMessage());
                        }
                        
                        // Clear the session since we couldn't complete the login process
                        session_unset();
                        session_destroy();
                    }
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
            // Show detailed error message in debug mode, generic message otherwise
            if ($debug_mode) {
                $errors["login"] = "Login error: " . $e->getMessage();
            } else {
                $errors["login"] = "An error occurred. Please try again later.";
            }
            error_log("Login Error: " . $e->getMessage());
            
            // Make sure to close the connection in case of an error
            if (isset($db)) {
                $db->closeConnection();
            }
        }
    }
}

function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check for success message from registration or other sources
$success_msg = isset($_SESSION['success_msg']) ? $_SESSION['success_msg'] : '';
if (!empty($success_msg)) {
    unset($_SESSION['success_msg']);
}

// Get message from query parameters (e.g., from logout.php)
if (isset($_GET['message']) && isset($_GET['type']) && $_GET['type'] == 'success') {
    $success_msg = $_GET['message'];
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
    <link href="css/login_register.css" rel="stylesheet">

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
                
                <!--<a href="forgot_password.php" class="forgotten-password">Forgot Password?</a>-->
                
                <div class="register-link">
                    Don't have an account? <a href="registration.php">Register Now</a>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>