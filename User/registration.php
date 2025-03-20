<?php
require_once "../backend/connections/config.php";
require_once "../backend/connections/database.php";
require_once "../vendor/autoload.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$email = $password = $confirmPassword = "";
$fullName = $dateOfBirth = $gender = $civilStatus = "";
$phoneNumber = $emailAddress = $address = $region = $province = $city = "";
$householdMembers = $dependants = $familyHead = $occupation = "";
$householdIncome = $incomeSource = $otherIncomeSource = "";
$verificationCode = "";
$errors = [];

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Account Credentials
    if (empty($_POST["email_account"])) {
        $errors["email_account"] = "Email is required";
    } else {
        $email = test_input($_POST["email_account"]);
        // Check if email is valid
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors["email_account"] = "Invalid email format";
        }
    }
    
    // Validate password
    if (empty($_POST["password"])) {
        $errors["password"] = "Password is required";
    } else {
        $password = $_POST["password"];
        // Check password strength
        if (strlen($password) < 8) {
            $errors["password"] = "Password must be at least 8 characters";
        }
    }
    
    // Validate confirm password
    if (empty($_POST["confirm_password"])) {
        $errors["confirm_password"] = "Please confirm your password";
    } else {
        $confirmPassword = $_POST["confirm_password"];
        // Check if passwords match
        if ($password !== $confirmPassword) {
            $errors["confirm_password"] = "Passwords do not match";
        }
    }
    
    // Personal Information
    if (empty($_POST["fullname"])) {
        $errors["fullname"] = "Full name is required";
    } else {
        $fullName = test_input($_POST["fullname"]);
    }
    
    if (empty($_POST["dob"])) {
        $errors["dob"] = "Date of birth is required";
    } else {
        $dateOfBirth = test_input($_POST["dob"]);
        // Check if date is valid and not in the future
        $birthdayDate = new DateTime($dateOfBirth);
        $today = new DateTime();
        if ($birthdayDate > $today) {
            $errors["dob"] = "Date of birth cannot be in the future";
        }
    }
    
    if (empty($_POST["gender"])) {
        $errors["gender"] = "Gender is required";
    } else {
        $gender = test_input($_POST["gender"]);
        // Convert gender to lowercase to match database enum values
        $gender = strtolower($gender);
    }
    
    if (empty($_POST["civil_status"])) {
        $errors["civil_status"] = "Civil status is required";
    } else {
        $civilStatus = test_input($_POST["civil_status"]);
    }
    
    // Contact Information
    if (empty($_POST["phone"])) {
        $errors["phone"] = "Phone number is required";
    } else {
        $phoneNumber = test_input($_POST["phone"]);
        // Simple validation for phone number
        if (!preg_match("/^[0-9]{10,15}$/", $phoneNumber)) {
            $errors["phone"] = "Invalid phone number format";
        }
    }
    
    if (empty($_POST["email"])) {
        $errors["email"] = "Email address is required";
    } else {
        $emailAddress = test_input($_POST["email"]);
        // Check if email is valid
        if (!filter_var($emailAddress, FILTER_VALIDATE_EMAIL)) {
            $errors["email"] = "Invalid email format";
        }
    }
    
    if (empty($_POST["address"])) {
        $errors["address"] = "Address is required";
    } else {
        $address = test_input($_POST["address"]);
    }
    
    if (empty($_POST["region"])) {
        $errors["region"] = "Region is required";
    } else {
        $region = test_input($_POST["region"]);
    }
    
    if (empty($_POST["province"])) {
        $errors["province"] = "Province is required";
    } else {
        $province = test_input($_POST["province"]);
    }
    
    if (empty($_POST["city"])) {
        $errors["city"] = "City is required";
    } else {
        $city = test_input($_POST["city"]);
    }
    
    // Family Information
    if (empty($_POST["household_members"])) {
        $errors["household_members"] = "Number of household members is required";
    } else {
        $householdMembers = test_input($_POST["household_members"]);
        if (!is_numeric($householdMembers) || $householdMembers < 1) {
            $errors["household_members"] = "Please enter a valid number";
        }
    }
    
    if (empty($_POST["dependants"])) {
        $errors["dependants"] = "Number of dependants is required";
    } else {
        $dependants = test_input($_POST["dependants"]);
        if (!is_numeric($dependants) || $dependants < 0) {
            $errors["dependants"] = "Please enter a valid number";
        }
    }
    
    if (empty($_POST["head_of_family"])) {
        $errors["head_of_family"] = "Head of the family is required";
    } else {
        $familyHead = test_input($_POST["head_of_family"]);
    }
    
    if (empty($_POST["occupation"])) {
        $errors["occupation"] = "Occupation is required";
    } else {
        $occupation = test_input($_POST["occupation"]);
    }
    
    // Socio-Economic Status
    if (empty($_POST["household_income"])) {
        $errors["household_income"] = "Household income is required";
    } else {
        $householdIncome = test_input($_POST["household_income"]);
        if (!is_numeric(str_replace(",", "", $householdIncome))) {
            $errors["household_income"] = "Please enter a valid amount";
        }
    }
    
    if (empty($_POST["income_source"])) {
        $errors["income_source"] = "Income source is required";
    } else {
        $incomeSource = test_input($_POST["income_source"]);
        
        if ($incomeSource == "OTHERS" && empty($_POST["other_income_source"])) {
            $errors["other_income_source"] = "Please specify other income source";
        } else if ($incomeSource == "OTHERS") {
            $otherIncomeSource = test_input($_POST["other_income_source"]);
        }
    }
    
    // Validate ID upload
    if (empty($_FILES["valid_id"]["name"])) {
        $errors["valid_id"] = "Valid ID upload is required";
    } else {
        // Check file type and size
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES["valid_id"]["type"], $allowedTypes)) {
            $errors["valid_id"] = "Only JPG, PNG, and PDF files are allowed";
        }
        
        if ($_FILES["valid_id"]["size"] > $maxFileSize) {
            $errors["valid_id"] = "File size should not exceed 5MB";
        }
    }
    
    // Validate proof of residency
    if (empty($_FILES["proof_of_residency"]["name"])) {
        $errors["proof_of_residency"] = "Proof of residency is required";
    } else {
        // Check file type and size
        $allowedTypes = ['image/jpeg', 'image/png', 'application/pdf'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES["proof_of_residency"]["type"], $allowedTypes)) {
            $errors["proof_of_residency"] = "Only JPG, PNG, and PDF files are allowed";
        }
        
        if ($_FILES["proof_of_residency"]["size"] > $maxFileSize) {
            $errors["proof_of_residency"] = "File size should not exceed 5MB";
        }
    }
    
    // Validate verification code 
    if (empty($_POST["verification_code"])) {
        $errors["verification_code"] = "Verification code is required";
    } else {
        $verificationCode = test_input($_POST["verification_code"]);
        
        // Check for client-side verification first 
        $clientSideVerified = true; 
        
        // Now check server-side for security
        if (!isset($_SESSION['verification_code']) || 
            !isset($_SESSION['verification_email']) || 
            !isset($_SESSION['verification_expires'])) {
            $errors["verification_code"] = "No verification code found. Please request a new code.";
            $clientSideVerified = false;
        } else if ($_SESSION['verification_code'] != $verificationCode) {
            $errors["verification_code"] = "Invalid verification code.";
            $clientSideVerified = false;
        } else if ($_SESSION['verification_email'] != $email) {
            $errors["verification_code"] = "Email address does not match the one used for verification.";
            $clientSideVerified = false;
        } else if (time() > $_SESSION['verification_expires']) {
            $errors["verification_code"] = "Verification code has expired. Please request a new one.";
            $clientSideVerified = false;
        }
        
        if (!$clientSideVerified) {
            // Clear expired or invalid verification data
            unset($_SESSION['verification_code']);
            unset($_SESSION['verification_email']);
            unset($_SESSION['verification_expires']);
        }
    }
    
    // If no errors, proceed with registration
    if (empty($errors)) {
        try {
            $db = new Database();
            
            // Add better error handling for database connectivity
            try {
                $testQuery = $db->fetchOne("SELECT 1 as test");
                if (!$testQuery) {
                    throw new Exception("Database connection test failed");
                }
            } catch (Exception $e) {
                $errors["database"] = "Database connection error: " . $e->getMessage();
                error_log("Database connection error: " . $e->getMessage());
                throw $e; // Re-throw to be caught by outer try-catch
            }
            
            $checkEmailSql = "SELECT user_id FROM users WHERE email = ?";
            $result = $db->fetchOne($checkEmailSql, [$email]);
            
            if ($result) {
                $errors["email_account"] = "Email already registered";
            } else {
                $db->beginTransaction();
                
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Handle ID file upload
                $targetDirId = "uploads/ids/";
                $targetDirProof = "uploads/residency/";
                
                if (!file_exists($targetDirId)) {
                    mkdir($targetDirId, 0777, true);
                }
                
                // Generate unique filename for ID
                $fileExtensionId = pathinfo($_FILES["valid_id"]["name"], PATHINFO_EXTENSION);
                $newFileNameId = uniqid('ID_') . "." . $fileExtensionId;
                $targetFileId = $targetDirId . $newFileNameId;
                
                // Handle proof of residency file upload
                $targetDirProof = "uploads/residency/";
                
                if (!file_exists($targetDirProof)) {
                    mkdir($targetDirProof, 0777, true);
                }
                
                // Generate unique filename for proof of residency
                $fileExtensionProof = pathinfo($_FILES["proof_of_residency"]["name"], PATHINFO_EXTENSION);
                $newFileNameProof = uniqid('PROOF_') . "." . $fileExtensionProof;
                $targetFileProof = $targetDirProof . $newFileNameProof;
                
                // Move uploaded files
                $uploadSuccess = true;
                
                if (!move_uploaded_file($_FILES["valid_id"]["tmp_name"], $targetFileId)) {
                    $uploadSuccess = false;
                    $errors["valid_id"] = "Error uploading ID. Please try again.";
                }
                
                if (!move_uploaded_file($_FILES["proof_of_residency"]["tmp_name"], $targetFileProof)) {
                    $uploadSuccess = false;
                    $errors["proof_of_residency"] = "Error uploading proof of residency. Please try again.";
                }
                
                if ($uploadSuccess) {
                    // Format income source if it's OTHERS
                    $finalIncomeSource = $incomeSource;
                    if ($incomeSource == "OTHERS" && !empty($otherIncomeSource)) {
                        $finalIncomeSource = "OTHERS: " . $otherIncomeSource;
                    }
                    
                    $insertSql = "INSERT INTO users (email, password, full_name, date_of_birth, gender, civil_status, 
                                 phone_number, address, region, province, city, household_members, dependants, 
                                 family_head, occupation, household_income, income_source, valid_id_path, proof_of_residency_path) 
                                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $params = [
                        $email,
                        $hashedPassword,
                        $fullName,
                        $dateOfBirth,
                        $gender,
                        $civilStatus,
                        $phoneNumber,
                        $address,
                        $region,
                        $province,
                        $city,
                        $householdMembers,
                        $dependants,
                        $familyHead,
                        $occupation,
                        $householdIncome,
                        $finalIncomeSource,
                        $targetFileId,
                        $targetFileProof
                    ];
                    
                    // For debugging
                    error_log("About to insert user with params: " . print_r($params, true));
                    
                    $userId = $db->insert($insertSql, $params);
                    
                    if ($userId) {
                        // REMOVED: Activity logs section
                        
                        // Clear verification code from session
                        unset($_SESSION['verification_code']);
                        unset($_SESSION['verification_email']);
                        unset($_SESSION['verification_expires']);
                        
                        // Commit transaction
                        $db->commit();
                        
                        // Set a success message in session
                        $_SESSION['success_msg'] = "Registration successful! Welcome to the 4P's Profiling System.";
                        
                        // Redirect to login page
                        header("Location: login.php");
                        exit();
                    } else {
                        $db->rollback();
                        $errors["database"] = "Registration failed. Please try again.";
                    }
                } else {
                    $db->rollback();
                }
            }
            
            $db->closeConnection();
            
        } catch (Exception $e) {
            if (isset($db) && $db->inTransaction()) {
                $db->rollback();
            }
            
            $errors["database"] = "An error occurred during registration: " . $e->getMessage();
            error_log("Registration Error: " . $e->getMessage());
        }
    }
}

function test_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

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
    <title>Registration - 4P's Profiling System</title>
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
            <a class="navbar-brand d-flex align-items-center" href="index.php">
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

    <!-- Registration Form -->
    <div class="container">
        <div class="registration-container">
            <h1 class="registration-title">Registration</h1>
            
            <?php if(!empty($success_msg)): ?>
                <div class="alert alert-success"><?php echo $success_msg; ?></div>
            <?php endif; ?>
            
            <?php if(!empty($errors["database"])): ?>
                <div class="alert alert-danger"><?php echo $errors["database"]; ?></div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <!-- Personal Information Section -->
                        <div class="form-section">
                            <h2 class="section-title">Personal Information</h2>
                            
                            <div class="mb-3">
                                <label for="fullname" class="form-label">Full Name:</label>
                                <input type="text" class="form-control <?php echo (!empty($errors['fullname'])) ? 'is-invalid' : ''; ?>" id="fullname" name="fullname" value="<?php echo $fullName; ?>">
                                <?php if(!empty($errors['fullname'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['fullname']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="dob" class="form-label">Date of Birth:</label>
                                <input type="date" class="form-control <?php echo (!empty($errors['dob'])) ? 'is-invalid' : ''; ?>" id="dob" name="dob" value="<?php echo $dateOfBirth; ?>">
                                <?php if(!empty($errors['dob'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['dob']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="gender" class="form-label">Gender:</label>
                                <select class="form-select <?php echo (!empty($errors['gender'])) ? 'is-invalid' : ''; ?>" id="gender" name="gender">
                                    <option value="" selected disabled>Select Gender</option>
                                    <option value="MALE" <?php echo ($gender == "MALE") ? 'selected' : ''; ?>>Male</option>
                                    <option value="FEMALE" <?php echo ($gender == "FEMALE") ? 'selected' : ''; ?>>Female</option>
                                    <option value="OTHER" <?php echo ($gender == "OTHER") ? 'selected' : ''; ?>>Other</option>
                                </select>
                                <?php if(!empty($errors['gender'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['gender']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="civil_status" class="form-label">Civil Status:</label>
                                <select class="form-select <?php echo (!empty($errors['civil_status'])) ? 'is-invalid' : ''; ?>" id="civil_status" name="civil_status">
                                    <option value="" selected disabled>Select Civil Status</option>
                                    <option value="SINGLE" <?php echo ($civilStatus == "SINGLE") ? 'selected' : ''; ?>>Single</option>
                                    <option value="MARRIED" <?php echo ($civilStatus == "MARRIED") ? 'selected' : ''; ?>>Married</option>
                                    <option value="WIDOWED" <?php echo ($civilStatus == "WIDOWED") ? 'selected' : ''; ?>>Widowed</option>
                                    <option value="DIVORCED" <?php echo ($civilStatus == "DIVORCED") ? 'selected' : ''; ?>>Divorced</option>
                                    <option value="SEPARATED" <?php echo ($civilStatus == "SEPARATED") ? 'selected' : ''; ?>>Separated</option>
                                </select>
                                <?php if(!empty($errors['civil_status'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['civil_status']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Contact Details Section -->
                        <div class="form-section">
                            <h2 class="section-title">Contact Details</h2>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label">Phone Number:</label>
                                <input type="tel" class="form-control <?php echo (!empty($errors['phone'])) ? 'is-invalid' : ''; ?>" id="phone" name="phone" value="<?php echo $phoneNumber; ?>">
                                <?php if(!empty($errors['phone'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['phone']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address:</label>
                                <input type="email" class="form-control <?php echo (!empty($errors['email'])) ? 'is-invalid' : ''; ?>" id="email" name="email" value="<?php echo $emailAddress; ?>">
                                <?php if(!empty($errors['email'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['email']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="address" class="form-label">Address:</label>
                                <input type="text" class="form-control <?php echo (!empty($errors['address'])) ? 'is-invalid' : ''; ?>" id="address" name="address" value="<?php echo $address; ?>">
                                <?php if(!empty($errors['address'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['address']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="region" class="form-label">Region:</label>
                                    <input type="text" class="form-control <?php echo (!empty($errors['region'])) ? 'is-invalid' : ''; ?>" id="region" name="region" value="<?php echo $region; ?>">
                                    <?php if(!empty($errors['region'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['region']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="province" class="form-label">Province:</label>
                                    <input type="text" class="form-control <?php echo (!empty($errors['province'])) ? 'is-invalid' : ''; ?>" id="province" name="province" value="<?php echo $province; ?>">
                                    <?php if(!empty($errors['province'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['province']; ?></div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="col-md-4 mb-3">
                                    <label for="city" class="form-label">City:</label>
                                    <input type="text" class="form-control <?php echo (!empty($errors['city'])) ? 'is-invalid' : ''; ?>" id="city" name="city" value="<?php echo $city; ?>">
                                    <?php if(!empty($errors['city'])): ?>
                                        <div class="invalid-feedback"><?php echo $errors['city']; ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Family Information Section -->
                        <div class="form-section">
                            <h2 class="section-title">Family Information</h2>
                            
                            <div class="mb-3">
                                <label for="household_members" class="form-label">Number of Household Members:</label>
                                <input type="number" class="form-control <?php echo (!empty($errors['household_members'])) ? 'is-invalid' : ''; ?>" id="household_members" name="household_members" value="<?php echo $householdMembers; ?>" min="1">
                                <?php if(!empty($errors['household_members'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['household_members']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="dependants" class="form-label">Number of Dependants (Below 18):</label>
                                <input type="number" class="form-control <?php echo (!empty($errors['dependants'])) ? 'is-invalid' : ''; ?>" id="dependants" name="dependants" value="<?php echo $dependants; ?>" min="0">
                                <?php if(!empty($errors['dependants'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['dependants']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="head_of_family" class="form-label">Head of the Family:</label>
                                <input type="text" class="form-control <?php echo (!empty($errors['head_of_family'])) ? 'is-invalid' : ''; ?>" id="head_of_family" name="head_of_family" value="<?php echo $familyHead; ?>">
                                <?php if(!empty($errors['head_of_family'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['head_of_family']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="occupation" class="form-label">Occupation:</label>
                                <input type="text" class="form-control <?php echo (!empty($errors['occupation'])) ? 'is-invalid' : ''; ?>" id="occupation" name="occupation" value="<?php echo $occupation; ?>">
                                <?php if(!empty($errors['occupation'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['occupation']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Socio-Economic Status Section -->
                        <div class="form-section">
                            <h2 class="section-title">Socio-Economic Status</h2>
                            
                            <div class="mb-3">
                                <label for="household_income" class="form-label">Household Income (Monthly):</label>
                                <input type="number" class="form-control <?php echo (!empty($errors['household_income'])) ? 'is-invalid' : ''; ?>" id="household_income" name="household_income" value="<?php echo $householdIncome; ?>" min="0" step="0.01">
                                <?php if(!empty($errors['household_income'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['household_income']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Source of Income:</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="income_source" id="employment" value="EMPLOYMENT" <?php echo ($incomeSource == "EMPLOYMENT") ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="employment">
                                        Employment
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="income_source" id="small_business" value="SMALL_BUSINESS" <?php echo ($incomeSource == "SMALL_BUSINESS") ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="small_business">
                                        Small Business
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="income_source" id="government_assistance" value="GOVERNMENT_ASSISTANCE" <?php echo ($incomeSource == "GOVERNMENT_ASSISTANCE") ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="government_assistance">
                                        Government Assistance
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="income_source" id="others" value="OTHERS" <?php echo ($incomeSource == "OTHERS") ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="others">
                                        Others
                                    </label>
                                </div>
                                <?php if(!empty($errors['income_source'])): ?>
                                    <div class="text-danger"><?php echo $errors['income_source']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3" id="other_income_source_div" style="display: <?php echo ($incomeSource == 'OTHERS') ? 'block' : 'none'; ?>;">
                                <label for="other_income_source" class="form-label">Please specify:</label>
                                <input type="text" class="form-control <?php echo (!empty($errors['other_income_source'])) ? 'is-invalid' : ''; ?>" id="other_income_source" name="other_income_source" value="<?php echo $otherIncomeSource; ?>">
                                <?php if(!empty($errors['other_income_source'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['other_income_source']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <!-- Supporting Documents Section -->
                        <div class="form-section">
                            <h2 class="section-title">Supporting Documents</h2>
                            
                            <div class="mb-4">
                                <label class="form-label">Valid ID:</label>
                                <div class="upload-box" onclick="document.getElementById('valid_id').click()">
                                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                    <div class="upload-text">Click to upload or drag and drop</div>
                                    <div class="upload-text">PNG, JPG, PDF (Max. 5MB)</div>
                                </div>
                                <input type="file" id="valid_id" name="valid_id" class="form-control d-none" accept=".png,.jpg,.jpeg,.pdf">
                                <div id="valid_id_name" class="form-text"></div>
                                <?php if(!empty($errors['valid_id'])): ?>
                                    <div class="text-danger mt-2"><?php echo $errors['valid_id']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label">Proof of Residency:</label>
                                <div class="upload-box" onclick="document.getElementById('proof_of_residency').click()">
                                    <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                    <div class="upload-text">Click to upload or drag and drop</div>
                                    <div class="upload-text">PNG, JPG, PDF (Max. 5MB)</div>
                                </div>
                                <input type="file" id="proof_of_residency" name="proof_of_residency" class="form-control d-none" accept=".png,.jpg,.jpeg,.pdf">
                                <div id="proof_of_residency_name" class="form-text"></div>
                                <?php if(!empty($errors['proof_of_residency'])): ?>
                                    <div class="text-danger mt-2"><?php echo $errors['proof_of_residency']; ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Account Credentials Section -->
                        <div class="form-section">
                            <h2 class="section-title">Account Credentials</h2>
                            
                            <div class="mb-3">
                                <label for="email_account" class="form-label">Email:</label>
                                <input type="email" class="form-control <?php echo (!empty($errors['email_account'])) ? 'is-invalid' : ''; ?>" id="email_account" name="email_account" value="<?php echo $email; ?>">
                                <?php if(!empty($errors['email_account'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['email_account']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">Password:</label>
                                <input type="password" class="form-control <?php echo (!empty($errors['password'])) ? 'is-invalid' : ''; ?>" id="password" name="password">
                                <?php if(!empty($errors['password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['password']; ?></div>
                                <?php endif; ?>
                                <div class="form-text">Password must be at least 8 characters long and include letters, numbers, and special characters.</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password:</label>
                                <input type="password" class="form-control <?php echo (!empty($errors['confirm_password'])) ? 'is-invalid' : ''; ?>" id="confirm_password" name="confirm_password">
                                <?php if(!empty($errors['confirm_password'])): ?>
                                    <div class="invalid-feedback"><?php echo $errors['confirm_password']; ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <label for="verification_code" class="form-label">Verification Code:</label>
                                <div class="input-group">
                                    <input type="text" class="form-control verification-code-input <?php echo (!empty($errors['verification_code'])) ? 'is-invalid' : ''; ?> <?php echo empty($_SESSION['verification_code']) ? 'verification-disabled' : ''; ?>" id="verification_code" name="verification_code" value="<?php echo $verificationCode; ?>">
                                    <button type="button" class="btn btn-send-code" id="sendCodeBtn">Send Code</button>
                                    <span class="loader" id="verificationLoader" style="display: none;"></span>
                                </div>
                                <?php if(!empty($errors['verification_code'])): ?>
                                    <div class="invalid-feedback d-block"><?php echo $errors['verification_code']; ?></div>
                                <?php endif; ?>
                                <div id="verificationSuccess" class="verification-status verification-success">
                                    <i class="fas fa-check-circle"></i> Verification code sent successfully
                                </div>
                                <div id="verificationError" class="verification-status verification-error">
                                    <i class="fas fa-exclamation-circle"></i> Failed to send verification code
                                </div>
                                <a href="#" class="resend-code d-none" id="resendCode">Resend code</a>
                            </div>
                        </div>
                        
                        <!-- Terms and Conditions Section -->
                        <div class="form-section">
                            <h2 class="section-title">Terms and Conditions</h2>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="terms_agreement" name="terms_agreement" required>
                                    <label class="form-check-label" for="terms_agreement">
                                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms and Conditions</a> and <a href="#" data-bs-toggle="modal" data-bs-target="#privacyModal">Privacy Policy</a>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="data_consent" name="data_consent" required>
                                    <label class="form-check-label" for="data_consent">
                                        I consent to the collection and processing of my personal information for the purpose of program qualification assessment and service delivery.
                                    </label>
                                </div>
                            </div>
                            <button type="submit" class="btn-submit">SUBMIT</button>
                        </div>
                    </div>
                </div>
                
                
            </form>
        </div>
    </div>
    
    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">Terms and Conditions</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h4>4P's Profiling System Terms of Service</h4>
                    <p>Last Updated: March 21, 2025</p>
                    
                    <h5>1. Acceptance of Terms</h5>
                    <p>By creating an account and using the 4P's Profiling System, you agree to be bound by these Terms and Conditions. If you do not agree to these terms, please do not use this system.</p>
                    
                    <h5>2. Registration and Account Security</h5>
                    <p>When you register for an account, you agree to provide accurate, current, and complete information. You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account.</p>
                    
                    <h5>3. Eligibility Requirements</h5>
                    <p>Registration in this system does not automatically qualify you for the 4P's program. Your application will be evaluated based on the program's eligibility criteria and validation processes.</p>
                    
                    <h5>4. Use of Information</h5>
                    <p>Information provided during registration will be used for processing your application, program implementation, monitoring, and evaluation purposes in accordance with our Privacy Policy.</p>
                    
                    <h5>5. User Obligations</h5>
                    <p>You agree to:</p>
                    <ul>
                        <li>Provide truthful and accurate information</li>
                        <li>Update your information when necessary</li>
                        <li>Comply with all applicable laws and regulations</li>
                        <li>Not use the system for any fraudulent or illegal purposes</li>
                    </ul>
                    
                    <h5>6. Termination</h5>
                    <p>We reserve the right to suspend or terminate your account if we determine, in our sole discretion, that you have violated these Terms or have provided false information.</p>
                    
                    <h5>7. Changes to Terms</h5>
                    <p>We may modify these Terms at any time. Your continued use of the system following any changes constitutes your acceptance of the revised Terms.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Privacy Policy Modal -->
    <div class="modal fade" id="privacyModal" tabindex="-1" aria-labelledby="privacyModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="privacyModalLabel">Privacy Policy</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h4>4P's Profiling System Privacy Policy</h4>
                    <p>Last Updated: March 21, 2025</p>
                    
                    <h5>1. Information We Collect</h5>
                    <p>We collect personal information including but not limited to:</p>
                    <ul>
                        <li>Personal details (name, date of birth, gender, civil status)</li>
                        <li>Contact information (address, phone number, email)</li>
                        <li>Family information (household composition, dependents)</li>
                        <li>Socio-economic data (income, employment status)</li>
                        <li>Supporting documents (ID, proof of residency)</li>
                    </ul>
                    
                    <h5>2. How We Use Your Information</h5>
                    <p>Your information is used for:</p>
                    <ul>
                        <li>Processing and evaluating program applications</li>
                        <li>Program implementation and service delivery</li>
                        <li>Monitoring and evaluation of program effectiveness</li>
                        <li>Statistical analysis and reporting (in anonymized form)</li>
                        <li>Communication regarding your application or benefits</li>
                    </ul>
                    
                    <h5>3. Information Sharing</h5>
                    <p>We may share your information with:</p>
                    <ul>
                        <li>Government agencies involved in program implementation</li>
                        <li>Third-party service providers who assist in program delivery</li>
                        <li>Other entities as required by law or regulation</li>
                    </ul>
                    
                    <h5>4. Data Security</h5>
                    <p>We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction.</p>
                    
                    <h5>5. Data Retention</h5>
                    <p>We retain your personal information for as long as necessary to fulfill the purposes outlined in this Privacy Policy, unless a longer retention period is required by law.</p>
                    
                    <h5>6. Your Rights</h5>
                    <p>You have the right to:</p>
                    <ul>
                        <li>Access your personal information</li>
                        <li>Correct inaccurate or incomplete information</li>
                        <li>Request deletion of your data (subject to legal requirements)</li>
                        <li>Object to or restrict certain processing activities</li>
                    </ul>
                    
                    <h5>7. Contact Information</h5>
                    <p>For questions or concerns about this Privacy Policy or our data practices, please contact our Data Protection Officer at privacy@4ps-system.gov.ph.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Handle file upload visual feedback
        document.getElementById('valid_id').addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'No file selected';
            document.getElementById('valid_id_name').textContent = fileName;
        });
        
        document.getElementById('proof_of_residency').addEventListener('change', function() {
            const fileName = this.files[0]?.name || 'No file selected';
            document.getElementById('proof_of_residency_name').textContent = fileName;
        });
        
        // Add drag and drop functionality to upload boxes
        ['valid_id', 'proof_of_residency'].forEach(function(id) {
            const uploadBox = document.querySelector(`.upload-box[onclick="document.getElementById('${id}').click()"]`);
            
            uploadBox.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.style.backgroundColor = 'rgba(0, 86, 179, 0.15)';
            });
            
            uploadBox.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.style.backgroundColor = 'rgba(0, 86, 179, 0.05)';
            });
            
            uploadBox.addEventListener('drop', function(e) {
                e.preventDefault();
                this.style.backgroundColor = 'rgba(0, 86, 179, 0.05)';
                
                const fileInput = document.getElementById(id);
                fileInput.files = e.dataTransfer.files;
                
                // Trigger change event
                const event = new Event('change', { bubbles: true });
                fileInput.dispatchEvent(event);
            });
        });
        
        // Handle "Others" income source toggle
        document.querySelectorAll('input[name="income_source"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (this.value === 'OTHERS') {
                    document.getElementById('other_income_source_div').style.display = 'block';
                } else {
                    document.getElementById('other_income_source_div').style.display = 'none';
                }
            });
        });
        
        // Password strength validation
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            
            // Basic password strength criteria
            const hasMinLength = password.length >= 8;
            const hasLetter = /[a-zA-Z]/.test(password);
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            
            // Create or update feedback text
            let feedbackElement = document.getElementById('password-strength-feedback');
            if (!feedbackElement) {
                feedbackElement = document.createElement('div');
                feedbackElement.id = 'password-strength-feedback';
                feedbackElement.className = 'form-text mt-2';
                this.parentNode.appendChild(feedbackElement);
            }
            
            // Update feedback based on password strength
            if (password.length === 0) {
                feedbackElement.textContent = '';
            } else if (hasMinLength && hasLetter && hasNumber && hasSpecial) {
                feedbackElement.textContent = 'Strong password';
                feedbackElement.className = 'form-text mt-2 text-success';
            } else if (hasMinLength && (hasLetter && hasNumber || hasLetter && hasSpecial || hasNumber && hasSpecial)) {
                feedbackElement.textContent = 'Moderate password';
                feedbackElement.className = 'form-text mt-2 text-warning';
            } else {
                feedbackElement.textContent = 'Weak password - please include letters, numbers, and special characters';
                feedbackElement.className = 'form-text mt-2 text-danger';
            }
        });
        
        // Confirm password validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            // Create or update feedback text
            let feedbackElement = document.getElementById('confirm-password-feedback');
            if (!feedbackElement) {
                feedbackElement = document.createElement('div');
                feedbackElement.id = 'confirm-password-feedback';
                feedbackElement.className = 'form-text mt-2';
                this.parentNode.appendChild(feedbackElement);
            }
            
            // Update feedback based on password match
            if (confirmPassword.length === 0) {
                feedbackElement.textContent = '';
            } else if (password === confirmPassword) {
                feedbackElement.textContent = 'Passwords match';
                feedbackElement.className = 'form-text mt-2 text-success';
            } else {
                feedbackElement.textContent = 'Passwords do not match';
                feedbackElement.className = 'form-text mt-2 text-danger';
            }
        });
        
        // Email verification code handling
        document.getElementById('sendCodeBtn').addEventListener('click', function() {
            const emailInput = document.getElementById('email_account');
            const email = emailInput.value.trim();
            
            // Validate email
            if (email === '') {
                alert('Please enter your email address first.');
                emailInput.focus();
                return;
            }
            
            if (!/^\S+@\S+\.\S+$/.test(email)) {
                alert('Please enter a valid email address.');
                emailInput.focus();
                return;
            }
            
            // Show loader
            this.disabled = true;
            document.getElementById('verificationLoader').style.display = 'inline-block';
            document.getElementById('verificationSuccess').style.display = 'none';
            document.getElementById('verificationError').style.display = 'none';
            
            // Generate a random verification code
            const code = Math.floor(100000 + Math.random() * 900000).toString();
            
            // Send verification code to email using AJAX
            fetch('../backend/connections/send_email_verification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    code: code
                }),
            })
            .then(response => response.json())
            .then(data => {
                // Hide loader
                document.getElementById('verificationLoader').style.display = 'none';
                
                if (data.success) {
                    // Store verification code in session
                    saveVerificationCodeToSession(email, code);
                    
                    // Show success message
                    document.getElementById('verificationSuccess').style.display = 'block';
                    
                    // Enable verification code input
                    document.querySelector('.verification-code-input').classList.remove('verification-disabled');
                    
                    // Start countdown timer for code expiration
                    startVerificationCountdown();
                    
                    // Show resend button after 30 seconds
                    setTimeout(() => {
                        document.getElementById('resendCode').classList.remove('d-none');
                    }, 30000);
                } else {
                    // Show error message
                    document.getElementById('verificationError').style.display = 'block';
                    document.getElementById('verificationError').textContent = data.error || 'Failed to send verification code. Please try again.';
                    document.getElementById('sendCodeBtn').disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('verificationLoader').style.display = 'none';
                document.getElementById('verificationError').style.display = 'block';
                document.getElementById('verificationError').textContent = 'Network error. Please check your connection and try again.';
                document.getElementById('sendCodeBtn').disabled = false;
            });
        });
        
        // Save verification code to session
        function saveVerificationCodeToSession(email, code) {
            fetch('../backend/connections/save_verification.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    email: email,
                    code: code
                }),
            })
            .then(response => response.json())
            .catch(error => {
                console.error('Error saving verification code:', error);
            });
        }
        
        // Start countdown timer for verification code expiration
        function startVerificationCountdown() {
            // Create countdown element if it doesn't exist
            let countdownElement = document.getElementById('verification-countdown');
            if (!countdownElement) {
                countdownElement = document.createElement('div');
                countdownElement.id = 'verification-countdown';
                countdownElement.className = 'verification-status text-muted';
                document.getElementById('verification_code').parentNode.appendChild(countdownElement);
            }
            
            // Set expiration time (30 minutes from now)
            const expirationTime = Date.now() + (30 * 60 * 1000);
            
            // Display countdown
            countdownElement.style.display = 'block';
            
            // Update countdown every second
            const countdownInterval = setInterval(() => {
                const currentTime = Date.now();
                const timeLeft = Math.round((expirationTime - currentTime) / 1000);
                
                if (timeLeft <= 0) {
                    // Code expired
                    clearInterval(countdownInterval);
                    countdownElement.textContent = 'Verification code has expired. Please request a new one.';
                    countdownElement.className = 'verification-status text-danger';
                    
                    // Re-enable the send code button
                    document.getElementById('sendCodeBtn').disabled = false;
                } else {
                    // Format and display remaining time
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    countdownElement.textContent = `Code expires in ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
                    countdownElement.className = 'verification-status text-muted';
                }
            }, 1000);
        }
        
        // Resend verification code
        document.getElementById('resendCode').addEventListener('click', function(e) {
            e.preventDefault();
            document.getElementById('sendCodeBtn').disabled = false;
            document.getElementById('sendCodeBtn').click();
        });
        
        // Form submission validation
        document.querySelector('form').addEventListener('submit', function(e) {
            // Check if passwords match
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please check and try again.');
                document.getElementById('confirm_password').focus();
                return;
            }
            
            // Check verification code
            const verificationCode = document.getElementById('verification_code').value.trim();
            if (verificationCode === '') {
                e.preventDefault();
                alert('Please enter the verification code sent to your email.');
                document.getElementById('verification_code').focus();
                return;
            }
            
            // Check terms agreement
            if (!document.getElementById('terms_agreement').checked) {
                e.preventDefault();
                alert('You must agree to the Terms and Conditions to register.');
                document.getElementById('terms_agreement').focus();
                return;
            }
            
            // Check data consent
            if (!document.getElementById('data_consent').checked) {
                e.preventDefault();
                alert('You must provide consent for data processing to register.');
                document.getElementById('data_consent').focus();
                return;
            }
        });
    </script>
</body>
</html>
                