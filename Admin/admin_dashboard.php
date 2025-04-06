<?php
require_once "../backend/connections/config.php";
require_once "../backend/connections/database.php";

$selected_barangay_id = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : null;

try {
    $db = new Database();
    
    // Fetch current barangay details if a specific barangay is selected
    $current_barangay = null;
    if ($selected_barangay_id) {
        $barangayQuery = "SELECT name, captain_name FROM barangays WHERE barangay_id = ?";
        $current_barangay = $db->fetchOne($barangayQuery, [$selected_barangay_id]);
    }
    
    // Fetch statistics from database
    $totalBeneficiariesQuery = $selected_barangay_id 
        ? "SELECT COUNT(*) as total FROM beneficiaries WHERE barangay_id = ?" 
        : "SELECT COUNT(*) as total FROM beneficiaries";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $result = $params ? $db->fetchOne($totalBeneficiariesQuery, $params) : $db->fetchOne($totalBeneficiariesQuery);
    $total_beneficiaries = $result ? $result['total'] : 0;
    
    // Pending Verifications (filtered by barangay if selected)
    $pendingVerificationsQuery = $selected_barangay_id
        ? "SELECT COUNT(*) as pending FROM users 
           WHERE account_status = 'pending' AND role = 'resident' AND barangay_id = ?"
        : "SELECT COUNT(*) as pending FROM users 
           WHERE account_status = 'pending' AND role = 'resident'";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $result = $params ? $db->fetchOne($pendingVerificationsQuery, $params) : $db->fetchOne($pendingVerificationsQuery);
    $pending_verifications = $result ? $result['pending'] : 0;
    
    // Upcoming Events
    try {
        $upcomingEventsQuery = $selected_barangay_id
            ? "SELECT COUNT(*) as upcoming FROM events 
               WHERE event_date >= CURDATE() AND barangay_id = ?"
            : "SELECT COUNT(*) as upcoming FROM events WHERE event_date >= CURDATE()";
        $params = $selected_barangay_id ? [$selected_barangay_id] : [];
        $result = $params ? $db->fetchOne($upcomingEventsQuery, $params) : $db->fetchOne($upcomingEventsQuery);
        $upcoming_events = $result ? $result['upcoming'] : 0;
    } catch (Exception $e) {
        $upcoming_events = 0;
    }
    
    // Unread Messages
    try {
        $unreadMessagesQuery = $selected_barangay_id
            ? "SELECT COUNT(*) as unread FROM messages 
               WHERE status = 'unread' AND (sender_barangay_id = ? OR receiver_barangay_id = ?)"
            : "SELECT COUNT(*) as unread FROM messages WHERE status = 'unread'";
        $params = $selected_barangay_id ? [$selected_barangay_id, $selected_barangay_id] : [];
        $result = $params ? $db->fetchOne($unreadMessagesQuery, $params) : $db->fetchOne($unreadMessagesQuery);
        $unread_messages = $result ? $result['unread'] : 0;
    } catch (Exception $e) {
        $unread_messages = 0;
    }
    
    // Active and Inactive Beneficiaries
    try {
        $activeBeneficiariesQuery = $selected_barangay_id
            ? "SELECT COUNT(*) as active FROM beneficiaries 
               WHERE status = 'active' AND barangay_id = ?"
            : "SELECT COUNT(*) as active FROM beneficiaries WHERE status = 'active'";
        $params = $selected_barangay_id ? [$selected_barangay_id] : [];
        $result = $params ? $db->fetchOne($activeBeneficiariesQuery, $params) : $db->fetchOne($activeBeneficiariesQuery);
        $active_beneficiaries = $result ? $result['active'] : 0;
        
        $inactiveBeneficiariesQuery = $selected_barangay_id
            ? "SELECT COUNT(*) as inactive FROM beneficiaries 
               WHERE status = 'inactive' AND barangay_id = ?"
            : "SELECT COUNT(*) as inactive FROM beneficiaries WHERE status = 'inactive'";
        $params = $selected_barangay_id ? [$selected_barangay_id] : [];
        $result = $params ? $db->fetchOne($inactiveBeneficiariesQuery, $params) : $db->fetchOne($inactiveBeneficiariesQuery);
        $inactive_beneficiaries = $result ? $result['inactive'] : 0;
    } catch (Exception $e) {
        $active_beneficiaries = $total_beneficiaries;
        $inactive_beneficiaries = 0;
    }
    
    // Compliance Rates
    try {
        $complianceBaseQuery = $selected_barangay_id
            ? "FROM compliance_records cr
               JOIN beneficiaries ben ON cr.beneficiary_id = ben.beneficiary_id
               WHERE ben.barangay_id = ?"
            : "FROM compliance_records";
        
        $params = $selected_barangay_id ? [$selected_barangay_id] : [];
        
        $healthComplianceQuery = "SELECT ROUND(AVG(health_compliance) * 100, 0) as rate " . $complianceBaseQuery;
        $result = $params ? $db->fetchOne($healthComplianceQuery, $params) : $db->fetchOne($healthComplianceQuery);
        $health_compliance = $result ? $result['rate'] : 0;
        
        $educationComplianceQuery = "SELECT ROUND(AVG(education_compliance) * 100, 0) as rate " . $complianceBaseQuery;
        $result = $params ? $db->fetchOne($educationComplianceQuery, $params) : $db->fetchOne($educationComplianceQuery);
        $education_compliance = $result ? $result['rate'] : 0;
        
        $fdsComplianceQuery = "SELECT ROUND(AVG(fds_compliance) * 100, 0) as rate " . $complianceBaseQuery;
        $result = $params ? $db->fetchOne($fdsComplianceQuery, $params) : $db->fetchOne($fdsComplianceQuery);
        $fds_compliance = $result ? $result['rate'] : 0;
        
        $overallComplianceQuery = "SELECT ROUND(AVG((health_compliance + education_compliance + fds_compliance) / 3) * 100, 0) as rate " . $complianceBaseQuery;
        $result = $params ? $db->fetchOne($overallComplianceQuery, $params) : $db->fetchOne($overallComplianceQuery);
        $overall_compliance = $result ? $result['rate'] : 0;
    } catch (Exception $e) {
        $health_compliance = 0;
        $education_compliance = 0;
        $fds_compliance = 0;
        $overall_compliance = 0;
    }
    
    // Barangay Information
    try {
        $barangaysQuery = "SELECT b.barangay_id, b.name, 
                        COALESCE(b.captain_name, 'No Captain Assigned') as captain_name, 
                        COUNT(ben.beneficiary_id) as total_beneficiaries, 
                        b.image_path,
                        CASE WHEN b.barangay_id = ? THEN 1 ELSE 0 END as is_selected
                        FROM barangays b 
                        LEFT JOIN beneficiaries ben ON b.barangay_id = ben.barangay_id 
                        GROUP BY b.barangay_id
                        ORDER BY is_selected DESC, total_beneficiaries DESC";
        $params = [$selected_barangay_id ?? 0];
        $barangays = $db->fetchAll($barangaysQuery, $params);
    } catch (Exception $e) {
        $barangays = [];
    }
    
    // Pending Users
    try {
        $pendingUsersQuery = $selected_barangay_id
            ? "SELECT user_id, firstname, lastname, email, created_at 
               FROM users 
               WHERE account_status = 'pending' AND role = 'resident' AND barangay_id = ?
               ORDER BY created_at DESC LIMIT 5"
            : "SELECT user_id, firstname, lastname, email, created_at 
               FROM users 
               WHERE account_status = 'pending' AND role = 'resident' 
               ORDER BY created_at DESC LIMIT 5";
        $params = $selected_barangay_id ? [$selected_barangay_id] : [];
        $pending_users = $params ? $db->fetchAll($pendingUsersQuery, $params) : $db->fetchAll($pendingUsersQuery);
    } catch (Exception $e) {
        $pending_users = [];
    }
    
    // System Version and Backup
    try {
        $systemVersionQuery = "SELECT value FROM system_settings WHERE setting_name = 'version'";
        $result = $db->fetchOne($systemVersionQuery);
        $system_version = $result ? $result['value'] : 'v1.0.0';
        
        $lastBackupQuery = "SELECT value FROM system_settings WHERE setting_name = 'last_backup'";
        $result = $db->fetchOne($lastBackupQuery);
        $last_backup = $result ? $result['value'] : 'Never';
    } catch (Exception $e) {
        $system_version = 'v1.0.0';
        $last_backup = 'Never';
    }
    
    // Database Size
    try {
        $databaseSizeQuery = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024 / 1024, 1) as size 
                            FROM information_schema.TABLES WHERE table_schema = '" . DB_NAME . "'";
        $result = $db->fetchOne($databaseSizeQuery);
        $database_size = $result ? $result['size'] : 0;
    } catch (Exception $e) {
        $database_size = 0;
    }
    
    // Recent Activities
    try {
        $activitiesQuery = $selected_barangay_id
            ? "SELECT a.activity_type, a.description, a.created_at, 
               CONCAT(u.firstname, ' ', u.lastname) as user_name 
               FROM activity_logs a 
               LEFT JOIN users u ON a.user_id = u.user_id 
               LEFT JOIN beneficiaries ben ON u.user_id = ben.user_id
               WHERE ben.barangay_id = ?
               ORDER BY a.created_at DESC LIMIT 7"
            : "SELECT a.activity_type, a.description, a.created_at, 
               CONCAT(u.firstname, ' ', u.lastname) as user_name 
               FROM activity_logs a 
               LEFT JOIN users u ON a.user_id = u.user_id 
               ORDER BY a.created_at DESC LIMIT 7";
        $params = $selected_barangay_id ? [$selected_barangay_id] : [];
        $activities = $params ? $db->fetchAll($activitiesQuery, $params) : $db->fetchAll($activitiesQuery);
    } catch (Exception $e) {
        $activities = [];
    }
    
    // Close database connection
    $db->closeConnection();
    
} catch (Exception $e) {
    // Handle database errors
    error_log("Admin Dashboard Error: " . $e->getMessage());
    
    // Set default values in case of errors
    $total_beneficiaries = 0;
    $pending_verifications = 0;
    $upcoming_events = 0;
    $unread_messages = 0;
    $active_beneficiaries = 0;
    $inactive_beneficiaries = 0;
    $health_compliance = 0;
    $education_compliance = 0;
    $fds_compliance = 0;
    $overall_compliance = 0;
    $system_version = 'v1.0.0';
    $database_size = 0;
    $last_backup = 'Never';
    $barangays = [];
    $activities = [];
    $pending_users = [];
    $current_barangay = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="menu-toggle" id="menuToggle">
        <i class="bi bi-list"></i>
    </button>
    
    <!-- Header -->
    <header class="page-header">
        <div class="logo-container">
            <img src="../User/assets/pngwing.com (7).png" alt="DSWD Logo">
            <h1>4P's Profiling System ADMIN DASHBOARD</h1>
        </div>
    </header>
    
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="admin.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="participant_verification.php">
                    <i class="bi bi-person-check"></i> Participant Verification
                    <?php if($pending_verifications > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $pending_verifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="beneficiaries.php">
                    <i class="bi bi-people"></i> Beneficiaries
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="calendar.php">
                    <i class="bi bi-calendar3"></i> Calendar
                    <?php if($upcoming_events > 0): ?>
                    <span class="badge bg-primary rounded-pill ms-auto"><?php echo $upcoming_events; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="messages.php">
                    <i class="bi bi-chat-left-text"></i> Messages
                    <?php if($unread_messages > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php">
                    <i class="bi bi-file-earmark-text"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
                    <i class="bi bi-gear"></i> System Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    
    
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Welcome Message -->
        <div class="alert alert-primary mb-4" role="alert">
            <h4 class="alert-heading"><i class="bi bi-info-circle me-2"></i>System Summary</h4>
            <p>You have <strong><?php echo $pending_verifications; ?></strong> pending verifications, <strong><?php echo $upcoming_events; ?></strong> upcoming events, and <strong><?php echo $unread_messages; ?></strong> unread messages.</p>
            <hr>
            <p class="mb-0">Today is <?php echo date("l, F j, Y"); ?></p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-container">
            <div class="row">
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <div class="icon">
                            <i class="bi bi-people-fill"></i>
                        </div>
                        <h3><?php echo number_format($total_beneficiaries); ?></h3>
                        <p>Total Beneficiaries</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <div class="icon">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                        <h3><?php echo $pending_verifications; ?></h3>
                        <p>Pending Verifications</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <div class="icon">
                            <i class="bi bi-calendar-event"></i>
                        </div>
                        <h3><?php echo $upcoming_events; ?></h3>
                        <p>Upcoming Events</p>
                    </div>
                </div>
                <div class="col-md-3 col-6 mb-4">
                    <div class="stat-card">
                        <div class="icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <h3><?php echo $unread_messages; ?></h3>
                        <p>Unread Messages</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Access -->
        <div class="quick-access mb-5">
            <div class="row">
                <div class="col-md-3 col-6">
                    <a href="add_beneficiary.php" class="quick-btn">
                        <i class="bi bi-person-plus"></i> Add Beneficiary
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="generate_report.php" class="quick-btn">
                        <i class="bi bi-file-earmark-text"></i> Generate Reports
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="add_event.php" class="quick-btn">
                        <i class="bi bi-calendar-plus"></i> Schedule Event
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="system_backup.php" class="quick-btn">
                        <i class="bi bi-cloud-arrow-up"></i> Backup System
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Pending Verifications Section -->
        <?php if(!empty($pending_users)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                        <h3><i class="bi bi-person-check me-2"></i>Pending Verifications</h3>
                        <a href="participant_verification.php" class="btn btn-outline-light btn-sm">View All</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Submitted On</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pending_users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['user_id']; ?></td>
                                        <td><?php echo htmlspecialchars(ucwords($user['firstname'] . ' ' . $user['lastname'])); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <a href="participant_verification.php?id=<?php echo $user['user_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="bi bi-eye"></i> Review
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Barangay Cards -->
        <h2 class="mb-4 text-center text-primary">Manage Barangay Beneficiaries</h2>
        <div class="row">
            <?php if(empty($barangays)): ?>
                <div class="col-12 text-center">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i> No barangay data available. Please add barangays to the system.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($barangays as $barangay): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="feature-card">
                            <div class="feature-card <?php echo ($barangay['barangay_id'] == $selected_barangay_id) ? 'border border-primary' : ''; ?>">
                            <div class="card-img-container">
                                <img src="<?php echo !empty($barangay['image_path']) ? $barangay['image_path'] : 'assets/images/barangay-default.jpg'; ?>" alt="<?php echo htmlspecialchars($barangay['name']); ?>">
                            </div>
                            <div class="card-content">
                                <h3><?php echo htmlspecialchars(strtoupper($barangay['name'])); ?></h3>
                                <p class="caption"><?php echo htmlspecialchars($barangay['captain_name']); ?></p>
                                <p>Total beneficiaries: <?php echo number_format($barangay['total_beneficiaries']); ?></p>
                                <div class="d-grid gap-2">
                                    <a href="barangay_details.php?id=<?php echo $barangay['barangay_id']; ?>" class="btn btn-primary">Manage Beneficiaries</a>
                                    <a href="barangay_reports.php?id=<?php echo $barangay['barangay_id']; ?>" class="btn btn-outline-secondary">View Reports</a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        
        <div class="row mt-4">
            <!-- Recent Activities -->
            <div class="col-lg-6 mb-4">
                <div class="activities-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2><i class="bi bi-activity me-2"></i>Recent Activities</h2>
                        <a href="activity_logs.php" class="btn btn-outline-primary btn-sm">View All</a>
                    </div>
                    <div class="activity-list">
                        <?php if(empty($activities)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-exclamation-circle fs-1 text-muted"></i>
                                <p class="mt-2 text-muted">No recent activities found.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach($activities as $activity): ?>
                                <div class="activity-item">
                                    <div class="time"><?php echo date('F j, Y, g:i a', strtotime($activity['created_at'])); ?></div>
                                    <div class="text"><strong><?php echo htmlspecialchars($activity['user_name'] ?? 'System'); ?></strong> <?php echo htmlspecialchars($activity['description']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- System Summary -->
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-header bg-primary text-white">
                        <h3><i class="bi bi-info-circle me-2"></i>System Overview</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-people me-2"></i>Beneficiary Statistics</h5>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Total Registered
                                                <span class="badge bg-primary rounded-pill"><?php echo number_format($total_beneficiaries); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Active Beneficiaries
                                                <span class="badge bg-success rounded-pill"><?php echo number_format($active_beneficiaries); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Pending Approval
                                                <span class="badge bg-warning rounded-pill"><?php echo number_format($pending_verifications); ?></span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Inactive
                                                <span class="badge bg-danger rounded-pill"><?php echo number_format($inactive_beneficiaries); ?></span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-calendar-check me-2"></i>Program Compliance</h5>
                                        <ul class="list-group list-group-flush">
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Health Check Compliance
                                                <span class="badge bg-<?php echo $health_compliance >= 90 ? 'success' : ($health_compliance >= 75 ? 'primary' : ($health_compliance >= 60 ? 'warning' : 'danger')); ?> rounded-pill"><?php echo $health_compliance; ?>%</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Education Attendance
                                                <span class="badge bg-<?php echo $education_compliance >= 90 ? 'success' : ($education_compliance >= 75 ? 'primary' : ($education_compliance >= 60 ? 'warning' : 'danger')); ?> rounded-pill"><?php echo $education_compliance; ?>%</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                FDS Attendance
                                                <span class="badge bg-<?php echo $fds_compliance >= 90 ? 'success' : ($fds_compliance >= 75 ? 'primary' : ($fds_compliance >= 60 ? 'warning' : 'danger')); ?> rounded-pill"><?php echo $fds_compliance; ?>%</span>
                                            </li>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                Overall Compliance
                                                <span class="badge bg-<?php echo $overall_compliance >= 90 ? 'success' : ($overall_compliance >= 75 ? 'primary' : ($overall_compliance >= 60 ? 'warning' : 'danger')); ?> rounded-pill"><?php echo $overall_compliance; ?>%</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-12 mt-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title"><i class="bi bi-graph-up me-2"></i>System Status</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Database Size
                                                        <span class="badge bg-info rounded-pill"><?php echo $database_size; ?> GB</span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Last Backup
                                                        <span class="badge bg-success rounded-pill"><?php echo $last_backup; ?></span>
                                                    </li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <ul class="list-group list-group-flush">
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        System Version
                                                        <span class="badge bg-primary rounded-pill"><?php echo $system_version; ?></span>
                                                    </li>
                                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                                        Server Status
                                                        <span class="badge bg-success rounded-pill">Online</span>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer">
                        <a href="system_reports.php" class="btn btn-primary">View Detailed System Reports</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <footer class="footer">
        <div class="container">
            <p>&copy; <?php echo date("Y"); ?> Department of Social Welfare and Development - 4P's Profiling System</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile menu toggle
            const menuToggle = document.getElementById('menuToggle');
            const sidebar = document.getElementById('sidebar');
            
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('show');
            });
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 992) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggle = menuToggle.contains(event.target);
                    
                    if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('show')) {
                        sidebar.classList.remove('show');
                    }
                }
            });
            
            // Handle window resize
            window.addEventListener('resize', function() {
                if (window.innerWidth >= 992) {
                    sidebar.classList.remove('show');
                }
            });
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html>