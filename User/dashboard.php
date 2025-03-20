<?php
// Start the session
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get user data
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'Beneficiary';

// Connect to database 
// This is a placeholder - replace with your actual database connection code
$conn = null;
try {
    // $conn = new PDO("mysql:host=localhost;dbname=your_database", "username", "password");
    // $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // echo "Connection failed: " . $e->getMessage();
}

// Fetch notifications (placeholder)
$notifications = [
    ['id' => 1, 'title' => 'Upcoming Health Check', 'message' => 'Your scheduled health check is on June 15, 2025', 'date' => '2025-06-01', 'read' => false],
    ['id' => 2, 'title' => 'Benefit Payment', 'message' => 'Your monthly benefit has been credited to your account', 'date' => '2025-05-25', 'read' => true],
    ['id' => 3, 'title' => 'Document Update Required', 'message' => 'Please update your family information by July 1, 2025', 'date' => '2025-05-20', 'read' => false]
];

// Fetch upcoming events (placeholder)
$events = [
    ['id' => 1, 'title' => 'Family Development Session', 'date' => '2025-06-10', 'location' => 'Barangay Community Center'],
    ['id' => 2, 'title' => 'Health and Nutrition Seminar', 'date' => '2025-06-18', 'location' => 'Municipal Health Office'],
    ['id' => 3, 'title' => 'Educational Support Workshop', 'date' => '2025-06-25', 'location' => 'Elementary School Auditorium']
];

// Fetch compliance status (placeholder)
$compliance = [
    'health' => [
        'status' => 'Compliant',
        'lastCheckup' => '2025-04-15',
        'nextCheckup' => '2025-07-15'
    ],
    'education' => [
        'status' => 'Compliant',
        'attendance' => '95%',
        'childrenEnrolled' => 2
    ],
    'fds' => [
        'status' => 'Compliant',
        'sessionsAttended' => 4,
        'sessionsRequired' => 6,
        'nextSession' => '2025-06-10'
    ]
];

// Fetch benefit history (placeholder)
$benefits = [
    ['month' => 'May 2025', 'healthGrant' => 750, 'educationGrant' => 1500, 'riceSubsidy' => 600, 'total' => 2850, 'status' => 'Received'],
    ['month' => 'April 2025', 'healthGrant' => 750, 'educationGrant' => 1500, 'riceSubsidy' => 600, 'total' => 2850, 'status' => 'Received'],
    ['month' => 'March 2025', 'healthGrant' => 750, 'educationGrant' => 1500, 'riceSubsidy' => 600, 'total' => 2850, 'status' => 'Received']
];

// Count unread notifications
$unreadCount = 0;
foreach ($notifications as $notification) {
    if (!$notification['read']) {
        $unreadCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Base styles */
        body {
            font-family: 'Poppins', sans-serif;
            color: #333;
            background-color: #f8f9fa;
        }

        /* Navbar styles */
        .navbar {
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 15px 0;
        }

        .navbar-brand {
            font-weight: 600;
            font-size: 1.3rem;
            color: #2c3e50;
        }

        .navbar-brand img {
            height: 45px;
            margin-right: 10px;
        }

        .navbar-brand span {
            font-weight: 600;
        }

        .nav-link {
            color: #2c3e50;
            font-weight: 500;
            padding: 10px 15px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }

        .nav-link:hover, .nav-link.active {
            color: #3498db;
            background-color: rgba(52, 152, 219, 0.1);
        }

        .navbar-toggler {
            border: none;
            padding: 0.5rem;
        }

        .navbar-toggler:focus {
            box-shadow: none;
        }

        .btn-login, .btn-register {
            padding: 8px 20px;
            border-radius: 5px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-login {
            background-color: transparent;
            color: #3498db;
            border: 1px solid #3498db;
            margin-right: 10px;
        }

        .btn-login:hover {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .btn-register {
            background-color: #3498db;
            color: white;
            border: 1px solid #3498db;
        }

        .btn-register:hover {
            background-color: #2980b9;
            border-color: #2980b9;
        }

        /* Footer styles */
        .footer {
            background-color: #2c3e50;
            color: #ecf0f1;
            padding: 60px 0 20px;
        }

        .footer-logo img {
            height: 60px;
            margin-bottom: 20px;
        }

        .footer-description {
            margin-bottom: 25px;
            opacity: 0.8;
            font-size: 14px;
            line-height: 1.6;
        }

        .social-icons {
            display: flex;
            margin-bottom: 30px;
        }

        .social-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.1);
            color: #ecf0f1;
            margin-right: 10px;
            transition: all 0.3s ease;
        }

        .social-icon:hover {
            background-color: #3498db;
            color: white;
            transform: translateY(-3px);
        }

        .footer-title {
            color: white;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }

        .footer-title::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 2px;
            background-color: #3498db;
        }

        .footer-links {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li {
            margin-bottom: 10px;
        }

        .footer-links a {
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .footer-links a:hover {
            color: #3498db;
            padding-left: 5px;
        }

        .footer-col {
            margin-bottom: 30px;
        }

        .footer-bottom {
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 20px;
            margin-top: 20px;
            text-align: center;
            font-size: 14px;
            color: #bdc3c7;
        }

        /* Dashboard specific styles */
        .dashboard-container {
            padding: 30px 0;
            background-color: #f8f9fa;
            min-height: calc(100vh - 76px);
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .welcome-message {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .dashboard-subtitle {
            color: #7f8c8d;
            margin-bottom: 20px;
        }

        .dashboard-card {
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 25px;
            transition: transform 0.3s ease;
            height: 100%;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
            margin-bottom: 15px;
        }

        .card-title {
            font-size: 18px;
            font-weight: 600;
            color: #2c3e50;
            margin: 0;
        }

        .card-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            color: white;
        }

        .icon-primary {
            background-color: #3498db;
        }

        .icon-success {
            background-color: #2ecc71;
        }

        .icon-warning {
            background-color: #f39c12;
        }

        .icon-danger {
            background-color: #e74c3c;
        }

        .badge-counter {
            position: absolute;
            top: -5px;
            right: -10px;
            font-size: 11px;
        }

        .notification-item {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .notification-item.unread {
            background-color: #f8f9fa;
        }

        .notification-title {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .notification-message {
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .notification-date {
            font-size: 12px;
            color: #95a5a6;
        }

        .event-item {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }

        .event-item:last-child {
            border-bottom: none;
        }

        .event-date {
            min-width: 60px;
            text-align: center;
            margin-right: 15px;
        }

        .event-day {
            font-size: 20px;
            font-weight: 700;
            color: #e74c3c;
        }

        .event-month {
            font-size: 12px;
            color: #7f8c8d;
            text-transform: uppercase;
        }

        .event-details {
            flex: 1;
        }

        .event-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .event-location {
            font-size: 13px;
            color: #7f8c8d;
        }

        .compliance-item {
            margin-bottom: 15px;
        }

        .compliance-label {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .compliance-status {
            font-weight: 600;
        }

        .status-compliant {
            color: #2ecc71;
        }

        .status-noncompliant {
            color: #e74c3c;
        }

        .progress {
            height: 10px;
            margin-bottom: 5px;
            border-radius: 5px;
        }

        .compliance-details {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #7f8c8d;
        }

        .benefit-table {
            width: 100%;
        }

        .benefit-table th, .benefit-table td {
            padding: 10px;
            font-size: 14px;
        }

        .benefit-table th {
            color: #2c3e50;
            font-weight: 600;
            border-bottom: 2px solid #eee;
        }

        .benefit-table td {
            border-bottom: 1px solid #eee;
        }

        .benefit-table tr:last-child td {
            border-bottom: none;
        }

        .status-pill {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-received {
            background-color: #d5f5e3;
            color: #2ecc71;
        }

        .status-pending {
            background-color: #fef9e7;
            color: #f39c12;
        }

        .profile-overview {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }

        .profile-image {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            overflow: hidden;
            margin-right: 20px;
            background-color: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: 600;
        }

        .profile-info {
            flex: 1;
        }

        .profile-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .profile-role {
            font-size: 14px;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .profile-id {
            font-size: 13px;
            color: #95a5a6;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-top: 20px;
        }

        .action-button {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 15px;
            border-radius: 10px;
            background-color: #fff;
            border: 1px solid #eee;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .action-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .action-icon {
            font-size: 24px;
            margin-bottom: 8px;
            color: #3498db;
        }

        .action-text {
            font-size: 12px;
            color: #2c3e50;
            text-align: center;
        }

        .summary-card {
            padding: 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            color: white;
        }

        .summary-icon {
            font-size: 32px;
            margin-right: 15px;
        }

        .summary-info {
            flex: 1;
        }

        .summary-value {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .summary-label {
            font-size: 14px;
            opacity: 0.8;
        }

        .bg-health {
            background-color: #3498db;
        }

        .bg-education {
            background-color: #2ecc71;
        }

        .bg-fds {
            background-color: #9b59b6;
        }

        .bg-rice {
            background-color: #f39c12;
        }

        /* Dropdown menu styling */
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            padding: 10px 0;
            border-radius: 8px;
        }

        .dropdown-item {
            padding: 8px 20px;
            font-size: 14px;
            color: #2c3e50;
            transition: all 0.2s ease;
        }

        .dropdown-item:hover {
            background-color: rgba(52, 152, 219, 0.1);
            color: #3498db;
        }

        .dropdown-divider {
            margin: 5px 0;
            border-top: 1px solid #eee;
        }

        .dropdown-toggle {
            color: #2c3e50;
            font-weight: 500;
            background-color: transparent;
            border: none;
        }

        .dropdown-toggle:hover, .dropdown-toggle:focus {
            color: #3498db;
            background-color: transparent;
        }

        /* Button styling */
        .btn-outline-primary {
            color: #3498db;
            border-color: #3498db;
        }

        .btn-outline-primary:hover {
            background-color: #3498db;
            color: white;
        }

        .btn-sm {
            padding: 5px 15px;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 576px) {
            .dashboard-header {
                text-align: center;
            }
            
            .profile-overview {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-image {
                margin-right: 0;
                margin-bottom: 15px;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }

            .compliance-details {
                flex-direction: column;
                text-align: center;
            }

            .compliance-details span {
                margin-bottom: 5px;
            }

            .benefit-table {
                font-size: 12px;
            }
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
                <ul class="navbar-nav ms-auto me-lg-4">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">Profile</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="family.php">Family</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="compliance.php">Compliance</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="benefits.php">Benefits</a>
                    </li>
                </ul>
                
                <div class="auth-buttons d-flex">
                    <div class="dropdown">
                        <button class="btn dropdown-toggle" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle me-1"></i> <?php echo htmlspecialchars($username); ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i> Log Out</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="dashboard-container">
        <div class="container">
            <div class="dashboard-header">
                <h1 class="welcome-message">Welcome back, <?php echo htmlspecialchars($username); ?>!</h1>
                <p class="dashboard-subtitle">Here's an overview of your 4P's Program status and activities.</p>
            </div>

            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-4">
                    <!-- Profile Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">Profile Overview</h3>
                            <div class="card-icon icon-primary">
                                <i class="fas fa-user"></i>
                            </div>
                        </div>
                        <div class="profile-overview">
                            <div class="profile-image">
                                <?php echo strtoupper(substr($username, 0, 1)); ?>
                            </div>
                            <div class="profile-info">
                                <div class="profile-name"><?php echo htmlspecialchars($username); ?></div>
                                <div class="profile-role"><?php echo htmlspecialchars($role); ?></div>
                                <div class="profile-id">ID: #<?php echo str_pad($user_id, 5, '0', STR_PAD_LEFT); ?></div>
                            </div>
                        </div>
                        <div class="quick-actions">
                            <a href="profile.php" class="action-button">
                                <i class="fas fa-user-edit action-icon"></i>
                                <span class="action-text">Edit Profile</span>
                            </a>
                            <a href="family.php" class="action-button">
                                <i class="fas fa-users action-icon"></i>
                                <span class="action-text">Family</span>
                            </a>
                            <a href="documents.php" class="action-button">
                                <i class="fas fa-file-alt action-icon"></i>
                                <span class="action-text">Documents</span>
                            </a>
                        </div>
                    </div>

                    <!-- Notifications Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">Notifications</h3>
                            <div class="card-icon icon-warning position-relative">
                                <i class="fas fa-bell"></i>
                                <?php if ($unreadCount > 0): ?>
                                <span class="badge rounded-pill bg-danger badge-counter"><?php echo $unreadCount; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if (empty($notifications)): ?>
                            <p class="text-center py-3 text-muted">No notifications at this time.</p>
                        <?php else: ?>
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item <?php echo $notification['read'] ? '' : 'unread'; ?>">
                                    <div class="notification-title">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                        <?php if (!$notification['read']): ?>
                                            <span class="badge rounded-pill bg-danger ms-2">New</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                    <div class="notification-date"><?php echo date('M d, Y', strtotime($notification['date'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                            <div class="text-center mt-3">
                                <a href="notifications.php" class="btn btn-sm btn-outline-primary">View All Notifications</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Middle Column -->
                <div class="col-lg-4">
                    <!-- Summary Cards -->
                    <div class="row">
                        <div class="col-md-6 col-lg-12">
                            <div class="summary-card bg-health">
                                <div class="summary-icon">
                                    <i class="fas fa-heartbeat"></i>
                                </div>
                                <div class="summary-info">
                                    <div class="summary-value">PHP 750</div>
                                    <div class="summary-label">Health Grant</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-12">
                            <div class="summary-card bg-education">
                                <div class="summary-icon">
                                    <i class="fas fa-graduation-cap"></i>
                                </div>
                                <div class="summary-info">
                                    <div class="summary-value">PHP 1,500</div>
                                    <div class="summary-label">Education Grant</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-12">
                            <div class="summary-card bg-fds">
                                <div class="summary-icon">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="summary-info">
                                    <div class="summary-value">4 of 6</div>
                                    <div class="summary-label">FDS Sessions Attended</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 col-lg-12">
                            <div class="summary-card bg-rice">
                                <div class="summary-icon">
                                    <i class="fas fa-utensils"></i>
                                </div>
                                <div class="summary-info">
                                    <div class="summary-value">PHP 600</div>
                                    <div class="summary-label">Rice Subsidy</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="col-lg-4">
                    <!-- Compliance Status Card -->
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3 class="card-title">Compliance Status</h3>
                            <div class="card-icon icon-success">
                                <i class="fas fa-check-circle"></i>
                            </div>
                        </div>
                        <div class="compliance-item">
                            <div class="compliance-label">
                                <span>Health</span>
                                <span class="compliance-status <?php echo $compliance['health']['status'] === 'Compliant' ? 'status-compliant' : 'status-noncompliant'; ?>">
                                    <?php echo $compliance['health']['status']; ?>
                                </span>
                            </div>