<?php
session_start();
require_once "../backend/connections/config.php";
require_once "../backend/connections/database.php";

$selected_barangay_id = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : null;

if ($selected_barangay_id) {
    $_SESSION['default_barangay_id'] = $selected_barangay_id;
} elseif (isset($_SESSION['default_barangay_id'])) {
    $selected_barangay_id = $_SESSION['default_barangay_id'];
}

try {
    $db = new Database();
    
    // Fetch current barangay details if a specific barangay is selected
    $barangayQuery = "SELECT name, captain_name FROM barangays WHERE barangay_id = ?";
    $current_barangay = $db->fetchOne($barangayQuery, [$selected_barangay_id]);
    
    // Get total parent leaders count
    $totalParentLeadersQuery = $selected_barangay_id 
    ? "SELECT COUNT(*) as total FROM users WHERE role = 'resident' AND account_status = 'active' AND barangay = ?" 
    : "SELECT COUNT(*) as total FROM users WHERE role = 'resident' AND account_status = 'active'";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $result = $params ? $db->fetchOne($totalParentLeadersQuery, $params) : $db->fetchOne($totalParentLeadersQuery);
    $total_parent_leaders = $result ? $result['total'] : 0;
    
    // Pending Verifications - MODIFIED: Filter by barangay if selected
    $pendingVerificationsQuery = $selected_barangay_id
        ? "SELECT COUNT(*) as pending FROM users 
           WHERE account_status = 'pending' AND role = 'resident' AND barangay = ?"
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
    
    // Pending Users - MODIFIED: Filter by barangay using the 'barangay' column in users table
    try {
        $pendingUsersQuery = $selected_barangay_id
            ? "SELECT user_id, firstname, lastname, email, created_at 
               FROM users 
               WHERE account_status = 'pending' AND role = 'resident' AND barangay = ?
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
    
    // Recent Activities - MODIFIED: Filter by barangay
    try {
        $activitiesQuery = $selected_barangay_id
            ? "SELECT a.activity_type, a.description, a.created_at, 
               CONCAT(u.firstname, ' ', u.lastname) as user_name 
               FROM activity_logs a 
               LEFT JOIN users u ON a.user_id = u.user_id 
               WHERE u.barangay = ? OR a.description LIKE ?
               ORDER BY a.created_at DESC LIMIT 7"
            : "SELECT a.activity_type, a.description, a.created_at, 
               CONCAT(u.firstname, ' ', u.lastname) as user_name 
               FROM activity_logs a 
               LEFT JOIN users u ON a.user_id = u.user_id 
               ORDER BY a.created_at DESC LIMIT 7";
        
        // Use parameter binding for barangay ID and also search in activity description
        $barangay_name = '';
        if ($selected_barangay_id && $current_barangay) {
            $barangay_name = $current_barangay['name'];
        }
        
        $params = $selected_barangay_id ? [$selected_barangay_id, "%$barangay_name%"] : [];
        $activities = $params ? $db->fetchAll($activitiesQuery, $params) : $db->fetchAll($activitiesQuery);
    } catch (Exception $e) {
        $activities = [];
    }
    
    // Close database connection
    //$db->closeConnection();
    
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
    <title><?php echo $selected_barangay_id && $current_barangay ? 'Barangay ' . htmlspecialchars($current_barangay['name']) : 'All Barangays'; ?> - Admin Dashboard</title>
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
                <a class="nav-link active <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" href="admin_dashboard.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'verification' ? 'active' : ''; ?>" href="participant_verification.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-person-check"></i> Parent Leader Verification
                    <?php if($pending_verifications > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $pending_verifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'parent_leaders' ? 'active' : ''; ?>" href="parent_leaders.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-people"></i> List of Parent Leaders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'beneficiaries' ? 'active' : ''; ?>" href="beneficiaries.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-people"></i> Beneficiaries
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'activities' ? 'active' : ''; ?>" href="add_activities.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-arrow-repeat"></i> Activities
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'calendar' ? 'active' : ''; ?>" href="calendar.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-calendar3"></i> Calendar
                    <?php if($upcoming_events > 0): ?>
                    <span class="badge bg-primary rounded-pill ms-auto"><?php echo $upcoming_events; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'messages' ? 'active' : ''; ?>" href="messages.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-chat-left-text"></i> Messages
                    <?php if($unread_messages > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <!--<li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reports' ? 'active' : ''; ?>" href="reports.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i> Reports
                </a>
            </li>-->
            <li class="nav-item">
                <a class="nav-link" href="../admin.php">
                    <i class="bi bi-box-arrow-right"></i> Back
                </a>
            </li>
        </ul>
        
        <!-- Barangay Selector -->
        <?php if (!empty($barangays)): ?>
            <div class="mt-4 p-3 bg-light rounded">
                <h6 class="mb-2"><i class="bi bi-geo-alt me-2"></i>Select Barangay</h6>
                <form action="" method="GET" id="barangayForm">
                    <select class="form-select form-select-sm" name="barangay_id" id="barangay_id" onchange="document.getElementById('barangayForm').submit();">
                        <?php foreach($barangays as $barangay): ?>
                        <option value="<?php echo $barangay['barangay_id']; ?>" 
                            <?php echo (($selected_barangay_id == $barangay['barangay_id']) ? 'selected' : ''); ?>>
                            <?php echo htmlspecialchars($barangay['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($_GET['parent_leader_id'])): ?>
                    <input type="hidden" name="parent_leader_id" value="<?php echo intval($_GET['parent_leader_id']); ?>">
                    <?php endif; ?>
                    <?php if (isset($_GET['search'])): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($_GET['search']); ?>">
                    <?php endif; ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
    
    
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Barangay Header if specific barangay is selected -->
        <?php if($selected_barangay_id && $current_barangay): ?>
        <div class="alert alert-success mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-building fs-1 me-3"></i>
                <div>
                    <h4 class="alert-heading">Barangay <?php echo htmlspecialchars($current_barangay['name']); ?> Dashboard</h4>
                    <p class="mb-0">Barangay Captain: <?php echo htmlspecialchars($current_barangay['captain_name'] ?: 'Not Assigned'); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
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
                        <h3><?php echo number_format($total_parent_leaders); ?></h3>
                        <p>Total Parent Leaders</p>
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
                    <a href="add_activities.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" class="quick-btn">
                        <i class="bi bi-arrow-repeat"></i> Add Activities
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="generate_report.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" class="quick-btn">
                        <i class="bi bi-file-earmark-text"></i> Generate Reports
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="tabsadd_event.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" class="quick-btn">
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
        
        <!-- Pending Verifications Section - Only show if there are pending verifications -->
        <?php if(!empty($pending_users)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="dashboard-card">
                    <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                        <h3><i class="bi bi-person-check me-2"></i>Pending Verifications 
                            <?php if($selected_barangay_id && $current_barangay): ?>
                            for Barangay <?php echo htmlspecialchars($current_barangay['name']); ?>
                            <?php endif; ?>
                        </h3>
                        <a href="participant_verification.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-outline-light btn-sm">View All</a>
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
                                            <a href="participant_verification.php?id=<?php echo $user['user_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-sm btn-primary">
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
        <?php elseif($selected_barangay_id && $current_barangay): ?>
        <div class="alert alert-info mb-4">
            <h5><i class="bi bi-info-circle me-2"></i>No Pending Verifications</h5>
            <p>There are currently no pending verifications for Barangay <?php echo htmlspecialchars($current_barangay['name']); ?>.</p>
        </div>
        <?php endif; ?>
        
        
        <div class="row mt-4">
            <!-- Recent Activities -->
            <div class="col-lg-6 mb-4">
                <div class="activities-container">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2><i class="bi bi-activity me-2"></i>Recent Activities
                            <?php if($selected_barangay_id && $current_barangay): ?>
                            in <?php echo htmlspecialchars($current_barangay['name']); ?>
                            <?php endif; ?>
                        </h2>
                        <a href="activity_logs.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-outline-primary btn-sm">View All</a>
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
                                    <div class="text"><strong>Administrator</strong> <?php echo htmlspecialchars($activity['description']); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Activities Overview -->
            <div class="col-lg-6 mb-4">
                <div class="dashboard-card">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h3><i class="bi bi-calendar-event me-2"></i>Activities Overview
                            <?php if($selected_barangay_id && $current_barangay): ?>
                            for <?php echo htmlspecialchars($current_barangay['name']); ?>
                            <?php endif; ?>
                        </h3>
                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="modal" data-bs-target="#allActivitiesModal">
                            <i class="bi bi-list-ul me-1"></i> View All
                        </button>
                    </div>
                    <div class="card-body">
                        <?php 
                        // Fetch limited activities for the card (5 most recent)
                        try {
                            $db = new Database();
                            
                            $recentActivitiesQuery = $selected_barangay_id
                                ? "SELECT a.activity_id, a.title, a.activity_type, a.start_date, a.end_date 
                                FROM activities a
                                WHERE a.barangay_id = ? 
                                ORDER BY a.start_date DESC 
                                LIMIT 5"
                                : "SELECT a.activity_id, a.title, a.activity_type, a.start_date, a.end_date 
                                FROM activities a
                                ORDER BY a.start_date DESC 
                                LIMIT 5";
                            
                            $params = $selected_barangay_id ? [$selected_barangay_id] : [];
                            $recent_activities = $db->fetchAll($recentActivitiesQuery, $params);
                        } catch (Exception $e) {
                            $recent_activities = [];
                        }
                        ?>
                        
                        <?php if(empty($recent_activities)): ?>
                            <div class="text-center py-4">
                                <i class="bi bi-calendar-x fs-1 text-muted"></i>
                                <p class="mt-2 text-muted">
                                    <?php echo $selected_barangay_id 
                                        ? "No activities found for this barangay." 
                                        : "No recent activities found."; 
                                    ?>
                                </p>
                            </div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach($recent_activities as $activity): ?>
                                    <a href="tabs/view_activity.php?id=<?php echo $activity['activity_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" 
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="my-0"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                            <small class="text-muted">
                                                <?php 
                                                $activity_types = [
                                                    'health_check' => 'Health Check',
                                                    'education' => 'Education',
                                                    'family_development_session' => 'Family Development Session',
                                                    'community_meeting' => 'Community Meeting',
                                                    'other' => 'Other Activity'
                                                ];
                                                echo $activity_types[$activity['activity_type']] ?? $activity['activity_type'];
                                                ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-primary rounded-pill">
                                            <?php 
                                            if ($activity['start_date'] && $activity['end_date'] && $activity['start_date'] != $activity['end_date']) {
                                                echo date('M d', strtotime($activity['start_date'])) . ' - ' . 
                                                    date('M d', strtotime($activity['end_date']));
                                            } elseif ($activity['start_date']) {
                                                echo date('M d', strtotime($activity['start_date']));
                                            } else {
                                                echo 'No Date';
                                            }
                                            ?>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer">
                        <a href="add_activities.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" 
                        class="btn btn-primary w-100">
                            <i class="bi bi-plus-circle me-2"></i>Create New Activity
                        </a>
                    </div>
                </div>
            </div>

            <!-- All Activities Modal -->
            <div class="modal fade" id="allActivitiesModal" tabindex="-1" aria-labelledby="allActivitiesModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="allActivitiesModalLabel">
                                <i class="bi bi-calendar-event me-2"></i>All Activities
                                <?php if($selected_barangay_id && $current_barangay): ?>
                                - Barangay <?php echo htmlspecialchars($current_barangay['name']); ?>
                                <?php endif; ?>
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <?php 
                            // Fetch all activities for the modal
                            try {
                                $allActivitiesQuery = $selected_barangay_id
                                    ? "SELECT a.activity_id, a.title, a.activity_type, a.start_date, a.end_date, a.description,
                                            b.name as barangay_name
                                    FROM activities a
                                    LEFT JOIN barangays b ON a.barangay_id = b.barangay_id
                                    WHERE a.barangay_id = ? 
                                    ORDER BY a.start_date DESC"
                                    : "SELECT a.activity_id, a.title, a.activity_type, a.start_date, a.end_date, a.description,
                                            b.name as barangay_name
                                    FROM activities a
                                    LEFT JOIN barangays b ON a.barangay_id = b.barangay_id
                                    ORDER BY a.start_date DESC";
                                
                                $params = $selected_barangay_id ? [$selected_barangay_id] : [];
                                $all_activities = $db->fetchAll($allActivitiesQuery, $params);
                            } catch (Exception $e) {
                                $all_activities = [];
                            }
                            ?>
                            
                            <?php if(empty($all_activities)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-calendar-x fs-1 text-muted"></i>
                                    <p class="mt-2 text-muted">
                                        <?php echo $selected_barangay_id 
                                            ? "No activities found for this barangay." 
                                            : "No activities found."; 
                                        ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach($all_activities as $activity): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                                    <small class="text-muted">
                                                        <?php 
                                                        echo $activity_types[$activity['activity_type']] ?? $activity['activity_type'];
                                                        if (!$selected_barangay_id && !empty($activity['barangay_name'])) {
                                                            echo ' • ' . htmlspecialchars($activity['barangay_name']);
                                                        }
                                                        ?>
                                                    </small>
                                                </div>
                                                <span class="badge bg-primary rounded-pill">
                                                    <?php 
                                                    if ($activity['start_date'] && $activity['end_date'] && $activity['start_date'] != $activity['end_date']) {
                                                        echo date('M d, Y', strtotime($activity['start_date'])) . ' - ' . 
                                                            date('M d, Y', strtotime($activity['end_date']));
                                                    } elseif ($activity['start_date']) {
                                                        echo date('M d, Y', strtotime($activity['start_date']));
                                                    } else {
                                                        echo 'No Date';
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                            <?php if(!empty($activity['description'])): ?>
                                                <p class="mb-1"><?php echo nl2br(htmlspecialchars($activity['description'])); ?></p>
                                            <?php endif; ?>
                                            <a href="tabs/view_activity.php?id=<?php echo $activity['activity_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" 
                                            class="btn btn-sm btn-outline-primary mt-2">
                                                <i class="bi bi-eye me-1"></i> View Details
                                            </a>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
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

        document.addEventListener('DOMContentLoaded', function() {
            // Preserve barangay_id when submitting any form that doesn't already have it
            const forms = document.querySelectorAll('form');
            const barangayId = <?php echo $selected_barangay_id ? $selected_barangay_id : 'null'; ?>;
            
            if (barangayId) {
                forms.forEach(form => {
                    // Skip forms that already handle barangay_id
                    if (form.id === 'barangayForm' || form.querySelector('[name="barangay_id"]')) {
                        return;
                    }
                    
                    // Only add to forms that submit to the same site (not external)
                    if (!form.action || form.action.includes(window.location.hostname)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'barangay_id';
                        input.value = barangayId;
                        form.appendChild(input);
                    }
                });
            }
        });
    </script>
</body>
</html>