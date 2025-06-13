<?php
require_once "../backend/connections/config.php";
require_once "../backend/connections/database.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$success_message = '';
$error_message = '';
$profile_updated = false;

try {
    $db = new Database();

    // Get user information with barangay name
    $userId = $_SESSION['user_id'];
    $userSql = "SELECT u.*, b.name as barangay_name 
                FROM users u 
                LEFT JOIN barangays b ON u.barangay = b.barangay_id 
                WHERE u.user_id = ?";
    $user = $db->fetchOne($userSql, [$userId]) ?? [];

    // Get all barangays for dropdown if needed
    $barangays = $db->fetchAll("SELECT barangay_id, name FROM barangays ORDER BY name");

    $defaultValues = [
        'firstname' => $_SESSION['firstname'] ?? 'User',
        'middle_initial' => $_SESSION['middle_initial'] ?? '',
        'lastname' => $_SESSION['lastname'] ?? '',
        'email' => $_SESSION['email'] ?? '',
        'phone_number' => '',
        'barangay' => '',
        'barangay_name' => '',
        'date_of_birth' => date('Y-m-d', strtotime('-30 years')),
        'gender' => 'male',
        'civil_status' => 'single',
        'region' => '',
        'province' => '',
        'city' => '',
        'household_members' => 1,
        'dependants' => 0,
        'family_head' => 'Self',
        'occupation' => '',
        'household_income' => 0,
        'income_source' => '',
        'valid_id_path' => '',
        'proof_of_residency_path' => '',
        'profile_image' => '',
        'role' => $_SESSION['role'] ?? 'resident',
        'created_at' => date('Y-m-d H:i:s'),
        'account_status' => 'active'
    ];
    
    $user = $user ?: [];
    
    foreach ($defaultValues as $key => $value) {
        if (!isset($user[$key])) {
            $user[$key] = $value;
        }
    }

    
    // Process form submission for profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $filename = $_FILES['profile_picture']['name'];
            $filetype = pathinfo($filename, PATHINFO_EXTENSION);
            if (in_array(strtolower($filetype), $allowed)) {
                $newFilename = 'profile_' . $userId . '_' . time() . '.' . $filetype;
                $uploadPath = 'uploads/profiles/' . $newFilename;
                if (!file_exists('uploads/profiles/')) {
                    mkdir('uploads/profiles/', 0777, true);
                }
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadPath)) {
                    $updateProfilePicSql = "UPDATE users SET profile_image = ? WHERE user_id = ?";
                    $db->execute($updateProfilePicSql, [$uploadPath, $userId]);
                    
                    $success_message = 'Profile picture updated successfully';
                    $profile_updated = true;
                    
                    // Refresh user data after update
                    $user = $db->fetchOne($userSql, [$userId]);
                    
                    // Reapply default values for any missing fields
                    foreach ($defaultValues as $key => $value) {
                        if (!isset($user[$key])) {
                            $user[$key] = $value;
                        }
                    }
                }
            }
        }
        // Check if this is a general profile update
        elseif (isset($_POST['update_profile'])) {
            $firstname = trim($_POST['firstname'] ?? '');
            $middleinitial = trim($_POST['middle_initial'] ?? '');
            $lastname = trim($_POST['lastname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone_number = trim($_POST['phone_number'] ?? '');
            $barangay = trim($_POST['barangay'] ?? '');
            $date_of_birth = $_POST['date_of_birth'] ?? '';
            $gender = $_POST['gender'] ?? 'male';
            $civil_status = $_POST['civil_status'] ?? 'single';
            $region = trim($_POST['region'] ?? '');
            $province = trim($_POST['province'] ?? '');
            $city = trim($_POST['city'] ?? '');
            $household_members = (int)($_POST['household_members'] ?? 1);
            $dependants = (int)($_POST['dependants'] ?? 0);
            $family_head = trim($_POST['family_head'] ?? '');
            $occupation = trim($_POST['occupation'] ?? '');
            $household_income = (float)($_POST['household_income'] ?? 0);
            $income_source = trim($_POST['income_source'] ?? '');

            if (empty($firstname)) {
                $error_message = 'First name is required';
            } elseif (empty($middleinitial)) {
                $error_message = 'Middle initial is required';
            } elseif (empty($lastname)) {
                $error_message = 'Last name is required';
            } elseif (empty($email)) {
                $error_message = 'Email is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error_message = 'Invalid email format';
            } else {
                // Check if email exists for other users
                $checkEmailSql = "SELECT user_id FROM users WHERE email = ? AND user_id != ?";
                $existingUser = $db->fetchOne($checkEmailSql, [$email, $userId]);
                if ($existingUser) {
                    $error_message = 'Email address is already in use by another account';
                } else {
                    // Update user information
                    $updateUserSql = "UPDATE users SET 
                        firstname = ?, 
                        middle_initial = ?, 
                        lastname = ?, 
                        email = ?, 
                        phone_number = ?, 
                        barangay = ?,
                        date_of_birth = ?,
                        gender = ?,
                        civil_status = ?,
                        region = ?,
                        province = ?,
                        city = ?,
                        household_members = ?,
                        dependants = ?,
                        family_head = ?,
                        occupation = ?,
                        household_income = ?,
                        income_source = ?,
                        updated_at = NOW() 
                        WHERE user_id = ?";
                    $db->execute($updateUserSql, [
                        $firstname, 
                        $middleinitial, 
                        $lastname, 
                        $email, 
                        $phone_number, 
                        $barangay,
                        $date_of_birth,
                        $gender,
                        $civil_status,
                        $region,
                        $province,
                        $city,
                        $household_members,
                        $dependants,
                        $family_head,
                        $occupation,
                        $household_income,
                        $income_source,
                        $userId
                    ]);

                    // Update session information
                    $_SESSION['firstname'] = $firstname;
                    $_SESSION['middle_initial'] = $middleinitial;
                    $_SESSION['lastname'] = $lastname;
                    $_SESSION['email'] = $email;

                    if (isset($_FILES['valid_id']) && $_FILES['valid_id']['error'] == 0) {
                        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                        $filename = $_FILES['valid_id']['name'];
                        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
                        if (in_array(strtolower($filetype), $allowed)) {
                            $newFilename = 'valid_id_' . $userId . '_' . time() . '.' . $filetype;
                            $uploadPath = 'uploads/ids/' . $newFilename;
                            if (!file_exists('uploads/ids/')) {
                                mkdir('uploads/ids/', 0777, true);
                            }
                            if (move_uploaded_file($_FILES['valid_id']['tmp_name'], $uploadPath)) {
                                $updateIdSql = "UPDATE users SET valid_id_path = ? WHERE user_id = ?";
                                $db->execute($updateIdSql, [$uploadPath, $userId]);
                            }
                        }
                    }

                    // Handle proof of residency upload
                    if (isset($_FILES['proof_of_residency']) && $_FILES['proof_of_residency']['error'] == 0) {
                        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
                        $filename = $_FILES['proof_of_residency']['name'];
                        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
                        if (in_array(strtolower($filetype), $allowed)) {
                            $newFilename = 'residency_' . $userId . '_' . time() . '.' . $filetype;
                            $uploadPath = 'uploads/residency/' . $newFilename;
                            if (!file_exists('uploads/residency/')) {
                                mkdir('uploads/residency/', 0777, true);
                            }
                            if (move_uploaded_file($_FILES['proof_of_residency']['tmp_name'], $uploadPath)) {
                                $updateProofSql = "UPDATE users SET proof_of_residency_path = ? WHERE user_id = ?";
                                $db->execute($updateProofSql, [$uploadPath, $userId]);
                            }
                        }
                    }

                    if (empty($success_message)) {
                        $success_message = 'Profile information updated successfully';
                    }
                    $profile_updated = true;

                    $user = $db->fetchOne($userSql, [$userId]);
                    
                    foreach ($defaultValues as $key => $value) {
                        if (!isset($user[$key])) {
                            $user[$key] = $value;
                        }
                    }
                }
            }
        }
    }



    // Process password change
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'All password fields are required';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'New password and confirmation do not match';
        } elseif (strlen($new_password) < 8) {
            $error_message = 'New password must be at least 8 characters long';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error_message = 'Current password is incorrect';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $updatePasswordSql = "UPDATE users SET password = ? WHERE user_id = ?";
            $db->execute($updatePasswordSql, [$hashed_password, $userId]);

            $success_message = 'Password changed successfully';
        }
    }

    $db->closeConnection();
} catch (Exception $e) {
    error_log("Profile Error: " . $e->getMessage());
    $error_message = 'An error occurred while loading your profile. Please try again later.';
}

$displayName = $user['firstname'] . ' ' . ($user['middle_initial'] ? $user['middle_initial'] . '. ' : '') . ($user['lastname'] ?? '');
$displayRole = ucfirst($user['role'] ?? $_SESSION['role'] ?? 'Resident');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --dswd-blue: #0033a0;
            --dswd-red: #ce1126;
            --sidebar-width: 280px;
            --header-height: 56px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            overflow-x: hidden;
            width: 100%;
            min-height: 100vh;
        }

        /* Header styling */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--header-height);
            z-index: 1030;
            background-color: var(--dswd-blue);
        }

        .navbar-brand img {
            height: 40px;
            max-width: 100%;
        }

        .navbar-brand span {
            font-size: 1.2rem;
            color: #fff;
            margin-left: 10px;
            white-space: nowrap;
        }

        /* Sidebar styling */
        .sidebar {
            width: var(--sidebar-width);
            background-color: #fff;
            border-right: 1px solid #dee2e6;
            height: calc(100vh - var(--header-height));
            position: fixed;
            top: var(--header-height);
            left: 0;
            overflow-y: auto;
            z-index: 1020;
            padding-top: 1.5rem;
            box-shadow: 2px 0 5px rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .sidebar .nav-link {
            color: #212529;
            padding: 0.8rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 15px;
            border-radius: 0;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover, 
        .sidebar .nav-link.active {
            background-color: rgba(0, 51, 160, 0.1);
            color: var(--dswd-blue);
        }

        .sidebar .nav-link i {
            font-size: 1.3rem;
            width: 24px;
            text-align: center;
        }

        /* Main content area */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            padding-top: calc(var(--header-height) + 1rem);
            width: calc(100% - var(--sidebar-width));
            min-height: 100vh;
            transition: margin-left 0.3s ease, width 0.3s ease;
        }

        /* User profile section */
        .profile-section {
            text-align: center;
            padding: 1.5rem 1rem;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 1.5rem;
        }

        .profile-image {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--dswd-blue);
            margin: 0 auto 0.8rem;
        }

        .user-name {
            font-weight: 600;
            margin-bottom: 0.2rem;
            word-break: break-word;
            font-size: clamp(1rem, 2.5vw, 1.2rem);
        }

        .user-type {
            display: inline-block;
            background-color: #e9ecef;
            border-radius: 20px;
            padding: 0.25rem 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        /* Profile content styling */
        .profile-header {
            background-color: #fff;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            position: relative;
        }
        
        .profile-card {
            background-color: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            height: auto;
        }
        
        .profile-card h5 {
            color: var(--dswd-blue);
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .profile-image-container {
            width: 150px;
            height: 150px;
            position: relative;
        }
        .profile-image-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--dswd-blue);
            padding: 3px;
            background-color: #fff;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .form-label {
            font-weight: 500;
            color: #555;
        }
        
        .btn-primary {
            background-color: var(--dswd-blue);
            border-color: var(--dswd-blue);
        }
        
        .btn-primary:hover {
            background-color: #002680;
            border-color: #002680;
        }
        
        .btn-outline-primary {
            color: var(--dswd-blue);
            border-color: var(--dswd-blue);
        }
        
        .btn-outline-primary:hover {
            background-color: var(--dswd-blue);
            border-color: var(--dswd-blue);
            color: #fff;
        }

        /* Status indicators */
        .status-badge {
            font-size: 0.8rem;
            font-weight: 500;
            padding: 0.35rem 0.75rem;
            border-radius: 30px;
        }

        .status-active {
            background-color: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #a5d6a7;
        }

        .status-pending {
            background-color: #fff3e0;
            color: #e65100;
            border: 1px solid #ffcc80;
        }

        .status-suspended {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
        }

        .status-deactivated {
            background-color: #eceff1;
            color: #546e7a;
            border: 1px solid #cfd8dc;
        }

        /* Document preview */
        .document-preview {
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
            background-color: #f8f9fa;
        }

        .document-preview img {
            max-width: 100%;
            height: auto;
            max-height: 200px;
            display: block;
            margin: 0 auto;
        }

        .document-preview .document-placeholder {
            width: 100%;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            color: #6c757d;
        }

        .document-placeholder i {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        /* Notification badge */
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            transform: translate(25%, -25%);
        }

        /* Sidebar toggle button */
        .sidebar-toggle {
            display: none;
            background: transparent;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            margin-right: 10px;
            padding: 5px;
            z-index: 1040;
        }

        /* Responsive styles */
        @media (max-width: 1200px) {
            .main-content {
                padding: calc(var(--header-height) + 1rem) 1.5rem 1.5rem;
            }
        }

        @media (max-width: 992px) {
            :root {
                --sidebar-width: 250px;
            }
            
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
            }
            
            .sidebar-toggle {
                display: block;
            }
            
            /* Add overlay when sidebar is shown on mobile */
            .sidebar-overlay {
                display: none;
                position: fixed;
                top: var(--header-height);
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1010;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: calc(var(--header-height) + 1rem) 1rem 1rem;
            }
            
            .profile-header {
                padding: 20px;
            }
            
            .profile-card {
                padding: 20px;
            }
            
            .navbar-brand span {
                font-size: 1rem;
            }
            
            .navbar-brand img {
                height: 35px;
            }
        }

        @media (max-width: 576px) {
            .profile-image {
                width: 70px;
                height: 70px;
            }
            
            .user-name {
                font-size: 1rem;
            }
            
            .user-type {
                font-size: 0.7rem;
                padding: 0.2rem 0.8rem;
            }
            
            .navbar-brand span {
                max-width: 150px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        }

        /* Print styles */
        @media print {
            .sidebar, .sidebar-toggle, .navbar {
                display: none;
            }
            
            .main-content {
                margin-left: 0;
                width: 100%;
                padding: 0;
            }
            
            .profile-card {
                box-shadow: none;
                border: 1px solid #dee2e6;
            }
            
            body {
                background-color: white;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <button class="btn btn-primary sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="assets/pngwing.com (7).png" alt="DSWD Logo">
                <span class="ms-3 text-white">4P's Profiling System</span>
            </a>
            
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <a class="text-white dropdown-toggle text-decoration-none" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-5 me-1"></i>
                        <span class="d-none d-md-inline-block"><?php echo $displayName; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="control/logout.php"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar and Main Content Container -->
    <div class="container-fluid p-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <div class="sidebar" id="sidebar">
                <!-- User Profile Section -->
                <div class="profile-section">
                    <img src="<?php echo !empty($user['profile_image']) ? $user['profile_image'] : 
                        (!empty($user['valid_id_path']) ? $user['valid_id_path'] : 'assets/images/profile-placeholder.png'); ?>" 
                        alt="Profile Image" class="profile-image">
                    <h5 class="user-name"><?php echo $displayName; ?></h5>
                    <span class="user-type"><?php echo $displayRole; ?></span>
                </div>

                <!-- Navigation Menu -->
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="profile.php">
                            <i class="bi bi-person"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="activities.php">
                            <i class="bi bi-calendar2-check"></i> Activities
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="members.php">
                            <i class="bi bi-people"></i> Members
                        </a>
                    </li>
                    <?php if ($user['role'] == 'resident'): ?>
                    <?php endif; ?>
                    <?php if ($user['role'] == 'admin' || $user['role'] == 'staff'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="residents.php">
                            <i class="bi bi-people"></i> Residents
                        </a>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="bi bi-chat-left-text"></i> Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="calendar.php">
                            <i class="bi bi-calendar3"></i> Calendar
                        </a>
                    </li>
                    <?php if ($user['role'] == 'admin' || $user['role'] == 'staff'): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="bi bi-file-earmark-text"></i> Reports
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Page Title -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="bi bi-person-circle me-2"></i> My Profile
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active" aria-current="page">Profile</li>
                        </ol>
                    </nav>
                </div>
                
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>
                
                <!-- Profile Header -->
                <div class="profile-header mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h4 class="mb-1"><?php echo htmlspecialchars($displayName); ?></h4>
                            <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?>  <?php echo htmlspecialchars($user['phone_number']); ?></p>
                            <div class="d-flex align-items-center mb-3">
                                <span class="status-badge <?php 
                                    $status = $user['account_status'];
                                    if ($status == 'active') echo 'status-active';
                                    elseif ($status == 'pending') echo 'status-pending';
                                    elseif ($status == 'suspended') echo 'status-suspended';
                                    else echo 'status-deactivated';
                                ?>">
                                    <i class="bi bi-circle-fill me-1 small"></i>
                                    <?php echo ucfirst($user['account_status']); ?>
                                </span>
                            </div>
                            <p class="mb-0">
                            <small class="text-muted">Registered: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></small>
                                <?php if (!empty($user['last_login'])): ?>
                                <small class="text-muted ms-3">Last Login: <?php echo date('F j, Y, g:i a', strtotime($user['last_login'])); ?></small>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div class="col-md-4 text-md-end mt-3 mt-md-0">
                            <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                                <i class="bi bi-key me-1"></i> Change Password
                            </button>
                            <?php if ($user['role'] == 'resident'): ?>
                            <!--<a href="benefits.php" class="btn btn-primary">
                                <i class="bi bi-cash-coin me-1"></i> View Benefits
                            </a>-->
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-6">
                        <!-- Profile Picture Section -->
                        <div class="profile-card mb-4">
                            <h5><i class="bi bi-person-badge me-2"></i>Profile Picture</h5>
                            <form method="post" action="" enctype="multipart/form-data" class="text-center">
                                <input type="hidden" name="update_profile" value="1">
                                <div class="mb-3">
                                    <div class="profile-image-container mx-auto mb-3">
                                        <img src="<?php echo !empty($user['profile_image']) ? $user['profile_image'] : 
                                            (!empty($user['valid_id_path']) ? $user['valid_id_path'] : 'assets/images/profile-placeholder.png'); ?>" 
                                            alt="Profile Image" class="profile-image-large">
                                    </div>
                                    <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/jpeg,image/png,image/jpg">
                                    <div class="form-text">Upload JPEG or PNG image (max 5MB)</div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload me-2"></i>Update Profile Picture
                                </button>
                            </form>
                        </div>

                        <!-- Personal Information -->
                        <div class="profile-card">
                            <h5><i class="bi bi-person me-2"></i>Personal Information</h5>
                            <form method="post" action="" id="profile-form" enctype="multipart/form-data">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="row">
                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="firstname" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="firstname" name="firstname" value="<?php echo htmlspecialchars($user['firstname']); ?>" required>
                                        </div>
                                        <div class="col-md-2 mb-3">
                                            <label for="middle_initial" class="form-label">M.I.</label>
                                            <input type="text" class="form-control" id="middle_initial" name="middle_initial" value="<?php echo htmlspecialchars($user['middle_initial'] ?? ''); ?>" maxlength="1">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label for="lastname" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="lastname" name="lastname" value="<?php echo htmlspecialchars($user['lastname']); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email Address</label>
                                        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="date_of_birth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="date_of_birth" name="date_of_birth" value="<?php echo htmlspecialchars($user['date_of_birth']); ?>" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="phone_number" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($user['phone_number']); ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="gender" class="form-label">Gender</label>
                                        <select class="form-select" id="gender" name="gender" required>
                                            <option value="male" <?php echo ($user['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                            <option value="female" <?php echo ($user['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                            <option value="other" <?php echo ($user['gender'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="civil_status" class="form-label">Civil Status</label>
                                        <select class="form-select" id="civil_status" name="civil_status" required>
                                            <option value="single" <?php echo ($user['civil_status'] == 'single') ? 'selected' : ''; ?>>Single</option>
                                            <option value="married" <?php echo ($user['civil_status'] == 'married') ? 'selected' : ''; ?>>Married</option>
                                            <option value="widowed" <?php echo ($user['civil_status'] == 'widowed') ? 'selected' : ''; ?>>Widowed</option>
                                            <option value="separated" <?php echo ($user['civil_status'] == 'separated') ? 'selected' : ''; ?>>Separated</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="barangay" class="form-label">Barangay</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($user['barangay_name'] ?? ''); ?>" readonly>
                                    <input type="hidden" name="barangay" value="<?php echo htmlspecialchars($user['barangay'] ?? ''); ?>">
                                </div>
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="region" class="form-label">Region</label>
                                        <input type="text" class="form-control" id="region" name="region" value="<?php echo htmlspecialchars($user['region']); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="province" class="form-label">Province</label>
                                        <input type="text" class="form-control" id="province" name="province" value="<?php echo htmlspecialchars($user['province']); ?>">
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="city" class="form-label">City</label>
                                        <input type="text" class="form-control" id="city" name="city" value="<?php echo htmlspecialchars($user['city']); ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="household_members" class="form-label">Household Members</label>
                                        <input type="number" class="form-control" id="household_members" name="household_members" value="<?php echo $user['household_members']; ?>" min="1" required>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="dependants" class="form-label">Dependants</label>
                                        <input type="number" class="form-control" id="dependants" name="dependants" value="<?php echo $user['dependants']; ?>" min="0" required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="family_head" class="form-label">Family Head</label>
                                    <input type="text" class="form-control" id="family_head" name="family_head" value="<?php echo htmlspecialchars($user['family_head']); ?>" required>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="occupation" class="form-label">Occupation</label>
                                        <input type="text" class="form-control" id="occupation" name="occupation" value="<?php echo htmlspecialchars($user['occupation']); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="household_income" class="form-label">Household Income</label>
                                        <input type="number" step="0.01" class="form-control" id="household_income" name="household_income" value="<?php echo $user['household_income']; ?>" min="0">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="income_source" class="form-label">Income Source</label>
                                    <input type="text" class="form-control" id="income_source" name="income_source" value="<?php echo htmlspecialchars($user['income_source']); ?>">
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-save me-2"></i>Save Changes
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- ID Documents Upload -->
                    <div class="col-lg-6">
                        <div class="profile-card">
                            <h5><i class="bi bi-file-earmark-text me-2"></i>ID Documents</h5>
                            <form method="post" action="" enctype="multipart/form-data">
                                <input type="hidden" name="update_profile" value="1">
                                <div class="mb-3 document-preview">
                                    <label for="valid_id" class="form-label">Valid ID</label>
                                    <?php if (!empty($user['valid_id_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($user['valid_id_path']); ?>" target="_blank">
                                        <img src="<?php echo htmlspecialchars($user['valid_id_path']); ?>" alt="Valid ID">
                                    </a>
                                    <?php else: ?>
                                    <div class="document-placeholder">
                                        <i class="bi bi-file-earmark"></i>
                                        <span>No Valid ID Uploaded</span>
                                    </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control mt-2" id="valid_id" name="valid_id" accept="image/*,application/pdf">
                                </div>
                                <div class="mb-3 document-preview">
                                    <label for="proof_of_residency" class="form-label">Proof of Residency</label>
                                    <?php if (!empty($user['proof_of_residency_path'])): ?>
                                    <a href="<?php echo htmlspecialchars($user['proof_of_residency_path']); ?>" target="_blank">
                                        <img src="<?php echo htmlspecialchars($user['proof_of_residency_path']); ?>" alt="Proof of Residency">
                                    </a>
                                    <?php else: ?>
                                    <div class="document-placeholder">
                                        <i class="bi bi-file-earmark"></i>
                                        <span>No Proof of Residency Uploaded</span>
                                    </div>
                                    <?php endif; ?>
                                    <input type="file" class="form-control mt-2" id="proof_of_residency" name="proof_of_residency" accept="image/*,application/pdf">
                                </div>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-outline-primary">
                                        <i class="bi bi-upload me-2"></i>Upload Documents
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <input type="hidden" name="change_password" value="1">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <div class="form-text">Password must be at least 8 characters long.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-key me-2"></i>Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function () {
                    sidebar.classList.toggle('show');
                });
            }

            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function (event) {
                if (window.innerWidth < 992) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggle = sidebarToggle.contains(event.target);
                    if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('show')) {
                        sidebar.classList.remove('show');
                    }
                }
            });

            // Password confirmation validation
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            function validatePassword() {
                if (newPassword.value !== confirmPassword.value) {
                    confirmPassword.setCustomValidity("Passwords don't match");
                } else {
                    confirmPassword.setCustomValidity('');
                }
            }
            if (newPassword && confirmPassword) {
                newPassword.addEventListener('change', validatePassword);
                confirmPassword.addEventListener('keyup', validatePassword);
            }
        });
    </script>
</body>
</html>