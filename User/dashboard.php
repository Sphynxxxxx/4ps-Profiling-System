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

try {
    $db = new Database();
    
    // Get user information
    $userId = $_SESSION['user_id'];
    $userSql = "SELECT * FROM users WHERE user_id = ?";
    $user = $db->fetchOne($userSql, [$userId]);
    
    // Get beneficiary information if applicable
    $beneficiary = null;
    if ($user && $user['role'] == 'beneficiary') {
        $beneficiarySql = "SELECT * FROM beneficiaries WHERE user_id = ?";
        $beneficiary = $db->fetchOne($beneficiarySql, [$userId]);
    }
    
    // Get activity counts
    $completedSql = "SELECT COUNT(*) as count FROM activities WHERE created_by = ? AND end_date < CURDATE()";
    $completedResult = $db->fetchOne($completedSql, [$userId]);
    $completedCount = $completedResult ? $completedResult['count'] : 0;

    $activesSql = "SELECT COUNT(*) as count FROM activities WHERE created_by = ? AND start_date <= CURDATE() AND end_date >= CURDATE()";
    $activesResult = $db->fetchOne($activesSql, [$userId]);
    $activesCount = $activesResult ? $activesResult['count'] : 0;

    $upcomingSql = "SELECT COUNT(*) as count FROM activities WHERE created_by = ? AND start_date > CURDATE()";
    $upcomingResult = $db->fetchOne($upcomingSql, [$userId]);
    $upcomingCount = $upcomingResult ? $upcomingResult['count'] : 0;
    
    // Get current user's barangay
    $userBarangayId = null;
    if (isset($user['barangay'])) {
        // If barangay is stored as ID
        $userBarangayId = intval($user['barangay']);
    } else {
        // Attempt to get barangay ID from beneficiaries table if user is a beneficiary
        try {
            $beneficiaryQuery = "SELECT barangay_id FROM beneficiaries WHERE user_id = ?";
            $beneficiaryResult = $db->fetchOne($beneficiaryQuery, [$userId]);
            if ($beneficiaryResult) {
                $userBarangayId = $beneficiaryResult['barangay_id'];
            }
        } catch (Exception $e) {
            error_log("Error fetching user's barangay: " . $e->getMessage());
        }
    }

    // Override with URL parameter if present (for filtering)
    $selected_barangay_id = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : $userBarangayId;

    // Fetch available barangays for the dropdown
    $barangays = [];
    $query = "SELECT barangay_id, name FROM barangays ORDER BY name";
    $barangays = $db->fetchAll($query);

    // Get all activities with proper barangay filtering
    $recentActivitiesSql = "SELECT a.*, b.name as barangay_name, u.firstname, u.lastname 
            FROM activities a 
            LEFT JOIN barangays b ON a.barangay_id = b.barangay_id
            LEFT JOIN users u ON a.created_by = u.user_id";

    // If a barangay is selected or user has a barangay, filter activities by it
    if ($selected_barangay_id) {
        $recentActivitiesSql .= " WHERE a.barangay_id = ? ORDER BY a.created_at DESC";
        $recentActivities = $db->fetchAll($recentActivitiesSql, [$selected_barangay_id]);
    } else {
        // Otherwise, show all activities
        $recentActivitiesSql .= " ORDER BY a.created_at DESC";
        $recentActivities = $db->fetchAll($recentActivitiesSql);
    }

    // Helper function to format activity type for display
    function formatActivityType($type) {
        switch ($type) {
            case 'health_check':
                return ['name' => 'Health Check', 'class' => 'success'];
            case 'education':
                return ['name' => 'Education', 'class' => 'info'];
            case 'family_development_session':
                return ['name' => 'Family Development Session', 'class' => 'warning'];
            case 'community_meeting':
                return ['name' => 'Community Meeting', 'class' => 'primary'];
            case 'other':
                return ['name' => 'Other', 'class' => 'secondary'];
            default:
                return ['name' => ucfirst(str_replace('_', ' ', $type)), 'class' => 'secondary'];
        }
    }
    
    // Get recent updates
    $updatesSql = "SELECT * FROM updates ORDER BY created_at DESC LIMIT 3";
    $updates = $db->fetchAll($updatesSql);
    
    // Get upcoming events
    $eventsSql = "SELECT * FROM events WHERE event_date >= CURDATE() ORDER BY event_date ASC LIMIT 3";
    $events = $db->fetchAll($eventsSql);
    
    // Close database connection
    $db->closeConnection();
    
} catch (Exception $e) {
    // Log error
    error_log("Dashboard Error: " . $e->getMessage());
    
    // Set default values in case of database error
    $user = [
        'firstname' => $_SESSION['firstname'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'beneficiary',
        'profile_image' => 'assets/images/profile-placeholder.png'
    ];
    
    $completedCount = 0;
    $activesCount = 0;     
    $upcomingCount = 0;    
    $recentActivities = [];
    $updates = [];
    $events = [];
    $userBarangayId = null;
    $selected_barangay_id = null;
    $barangays = [];
    
    // Close database connection if it exists
    if (isset($db)) {
        $db->closeConnection();
    }
}

// Determine display name and role for the sidebar
$displayName = $user['firstname'] ?? $_SESSION['firstname'] ?? 'User';
$displayRole = strtoupper($user['role'] ?? $_SESSION['role'] ?? 'BENEFICIARY');

$totalBeneficiaries = 0;
$activePrograms = 0;
$complianceRate = 0;
$pendingVerifications = 0;

try {
    $db = new Database();
    
    // Updated query to count ALL beneficiaries from the beneficiaries table
    $totalSql = "SELECT COUNT(*) as count FROM beneficiaries";
    $totalResult = $db->fetchOne($totalSql);
    $totalBeneficiaries = $totalResult ? $totalResult['count'] : 0;
    
    // The rest of your queries remain the same
    $programsSql = "SELECT COUNT(*) as count FROM programs WHERE status = 'active'";
    $programsResult = $db->fetchOne($programsSql);
    $activePrograms = $programsResult ? $programsResult['count'] : 0;
    
    // Get compliance rate
    $complianceSql = "SELECT 
                    (COUNT(CASE WHEN end_date < CURDATE() THEN 1 END) * 100.0 / COUNT(*)) as rate 
                    FROM activities";
    $complianceResult = $db->fetchOne($complianceSql);
    $complianceRate = $complianceResult ? round($complianceResult['rate']) : 0;
    
    // Get pending verifications
    $verificationsSql = "SELECT COUNT(*) as count FROM users WHERE account_status = 'pending'";
    $verificationsResult = $db->fetchOne($verificationsSql);
    $pendingVerifications = $verificationsResult ? $verificationsResult['count'] : 0;
    
    $db->closeConnection();
} catch (Exception $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    
    if (isset($db)) {
        $db->closeConnection();
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DSWD 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/dashboard.css">
    
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
                <div class="position-relative me-3">
                    <a href="#" class="text-white">
                        <i class="bi bi-bell-fill fs-5"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                            <?php echo count($recentActivities); ?>
                        </span>
                    </a>
                </div>
                <div class="dropdown">
                    <a class="text-white dropdown-toggle text-decoration-none" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-5 me-1"></i>
                        <span class="d-none d-md-inline-block"><?php echo $displayName; ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenu">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
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
                <!-- Navigation Menu -->
                <ul class="nav flex-column">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
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
                    <li class="nav-item">
                        <a class="nav-link" href="reports.php">
                            <i class="bi bi-file-earmark-text"></i> Reports
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="settings.php">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="container-fluid">
                    <!-- Page Title -->
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="bi bi-grid-1x2 me-2"></i> Dashboard
                        </h1>
                    </div>

                    <!-- Statistics Cards Row -->
                    <div class="row mb-4">
                        <div class="col-md-3 mb-4">
                            <div class="card dashboard-card bg-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">Total Beneficiaries</h6>
                                            <h2 class="mb-0 fw-bold"><?php echo number_format($totalBeneficiaries); ?></h2>
                                        </div>
                                        <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                            <i class="bi bi-people text-primary fs-3"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <!--<span class="badge bg-success">+12% <i class="bi bi-arrow-up"></i></span>
                                        <span class="text-muted ms-2">Since last month</span>-->
                                    </div>
                                    <div class="mt-4">
                                        <a href="control/register_beneficiary.php" class="btn btn-primary w-100">
                                            <i class="bi bi-plus-lg me-2"></i> Register Beneficiaries
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-4">
                            <div class="card dashboard-card bg-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">Active Programs</h6>
                                            <h2 class="mb-0 fw-bold"><?php echo $activePrograms; ?></h2>
                                        </div>
                                        <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                            <i class="bi bi-journal-check text-success fs-3"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <!--<span class="badge bg-success">+3 <i class="bi bi-arrow-up"></i></span>
                                        <span class="text-muted ms-2">New this quarter</span>-->
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-3 mb-4">
                            <div class="card dashboard-card bg-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">Compliance Rate</h6>
                                            <h2 class="mb-0 fw-bold"><?php echo $complianceRate; ?>%</h2>
                                        </div>
                                        <div class="rounded-circle bg-info bg-opacity-10 p-3">
                                            <i class="bi bi-check2-circle text-info fs-3"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <!--<span class="badge bg-success">+5% <i class="bi bi-arrow-up"></i></span>
                                        <span class="text-muted ms-2">Above target</span>-->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card dashboard-card">
                                <div class="card-header d-flex justify-content-between align-items-center py-3">
                                    <h5 class="mb-0">Recent Activities</h5>
                                    <a href="activities.php" class="btn btn-sm btn-outline-primary">View All Activities</a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <thead class="bg-light">
                                                <tr>
                                                    <th class="ps-4">Activity</th>
                                                    <th>Type</th>
                                                    <th>Barangay</th>
                                                    <th>Date</th>
                                                    <th>Status</th>
                                                    <th class="text-end pe-4">Action</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php if (empty($recentActivities)): ?>
                                                <tr>
                                                    <td colspan="6" class="text-center py-4 text-muted">
                                                        <i class="bi bi-calendar2-x fs-1 d-block mb-2"></i>
                                                        No activities available for your barangay.
                                                    </td>
                                                </tr>
                                                <?php else: ?>
                                                    <?php 
                                                    // Display only the 5 most recent activities
                                                    $displayLimit = 5;
                                                    $activitiesToShow = array_slice($recentActivities, 0, $displayLimit);
                                                    
                                                    foreach ($activitiesToShow as $activity): 
                                                        $activityType = formatActivityType($activity['activity_type']);
                                                        
                                                        // Determine activity status
                                                        $today = date('Y-m-d');
                                                        $startDate = date('Y-m-d', strtotime($activity['start_date']));
                                                        $endDate = date('Y-m-d', strtotime($activity['end_date']));
                                                        
                                                        if ($today < $startDate) {
                                                            $status = ['label' => 'Upcoming', 'class' => 'info'];
                                                        } elseif ($today > $endDate) {
                                                            $status = ['label' => 'Completed', 'class' => 'secondary'];
                                                        } else {
                                                            $status = ['label' => 'Active', 'class' => 'success'];
                                                        }
                                                    ?>
                                                    <tr>
                                                        <td class="ps-4">
                                                            <div class="d-flex align-items-center">
                                                                <div class="activity-icon-sm bg-<?php echo $activityType['class']; ?> bg-opacity-10 text-<?php echo $activityType['class']; ?> rounded me-3 p-2">
                                                                    <i class="bi bi-<?php 
                                                                        switch ($activity['activity_type']) {
                                                                            case 'health_check': echo 'heart-pulse'; break;
                                                                            case 'education': echo 'book'; break;
                                                                            case 'family_development_session': echo 'people'; break;
                                                                            case 'community_meeting': echo 'chat-square-text'; break;
                                                                            default: echo 'grid'; break;
                                                                        }
                                                                    ?>"></i>
                                                                </div>
                                                                <div>
                                                                    <h6 class="mb-0"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                                                    <small class="text-muted">
                                                                        <?php 
                                                                        $desc = htmlspecialchars($activity['description'] ?? '');
                                                                        echo (strlen($desc) > 60) ? substr($desc, 0, 60) . '...' : $desc;
                                                                        ?>
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $activityType['class']; ?> bg-opacity-10 text-<?php echo $activityType['class']; ?> px-3 py-2">
                                                                <?php echo $activityType['name']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <span class="text-secondary">
                                                                <?php echo !empty($activity['barangay_name']) ? htmlspecialchars($activity['barangay_name']) : 'All Barangays'; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex flex-column">
                                                                <span class="small text-nowrap">
                                                                    <i class="bi bi-calendar3 me-1"></i>
                                                                    <?php echo date('M d, Y', strtotime($activity['start_date'])); ?>
                                                                </span>
                                                                <?php if ($activity['start_date'] != $activity['end_date']): ?>
                                                                <span class="small text-muted text-nowrap">
                                                                    <i class="bi bi-arrow-right me-1"></i>
                                                                    <?php echo date('M d, Y', strtotime($activity['end_date'])); ?>
                                                                </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $status['class']; ?> bg-opacity-10 text-<?php echo $status['class']; ?> px-3 py-2">
                                                                <?php echo $status['label']; ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-end pe-4">
                                                            <a href="view_activity.php?id=<?php echo $activity['activity_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                Details
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                    
                                                    <?php if (count($recentActivities) > $displayLimit): ?>
                                                    <tr>
                                                        <td colspan="6" class="text-center py-3 bg-light">
                                                            <a href="activities.php" class="text-decoration-none">
                                                                View <?php echo count($recentActivities) - $displayLimit; ?> more activities <i class="bi bi-arrow-right ms-1"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Updates & Calendar Row -->
                    <div class="row">
                        <!-- Recent Updates -->
                        <div class="col-md-7 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header d-flex justify-content-between align-items-center py-3">
                                    <h5 class="mb-0">Recent Updates</h5>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="updateFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                            Filter
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="updateFilterDropdown">
                                            <li><a class="dropdown-item" href="#">All Updates</a></li>
                                            <li><a class="dropdown-item" href="#">Program Updates</a></li>
                                            <li><a class="dropdown-item" href="#">System Updates</a></li>
                                        </ul>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <?php if (empty($updates)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="bi bi-info-circle fs-1"></i>
                                            <p class="mt-2">No updates available.</p>
                                        </div>
                                        <?php else: ?>
                                            <?php foreach ($updates as $update): ?>
                                            <div class="list-group-item border-start-0 border-end-0 py-3 px-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="flex-shrink-0 me-3">
                                                        <div class="bg-<?php 
                                                            $type = strtolower($update['type'] ?? 'info');
                                                            if ($type == 'financial') echo 'info';
                                                            elseif ($type == 'education') echo 'success';
                                                            elseif ($type == 'announcement') echo 'warning';
                                                            else echo 'secondary';
                                                        ?> rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                                                            <i class="bi bi-<?php 
                                                                $type = strtolower($update['type'] ?? 'info');
                                                                if ($type == 'financial') echo 'cash-coin';
                                                                elseif ($type == 'education') echo 'book';
                                                                elseif ($type == 'announcement') echo 'megaphone';
                                                                else echo 'info-circle';
                                                            ?> text-white fs-4"></i>
                                                        </div>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($update['title']); ?></h6>
                                                            <span class="text-muted small">
                                                                <?php 
                                                                $created = new DateTime($update['created_at']);
                                                                $now = new DateTime();
                                                                $diff = $now->diff($created);
                                                                
                                                                if ($diff->d == 0) {
                                                                    if ($diff->h == 0) {
                                                                        echo $diff->i . ' minutes ago';
                                                                    } else {
                                                                        echo $diff->h . ' hours ago';
                                                                    }
                                                                } elseif ($diff->d == 1) {
                                                                    echo 'Yesterday';
                                                                } else {
                                                                    echo $diff->d . ' days ago';
                                                                }
                                                                ?>
                                                            </span>
                                                        </div>
                                                        <p class="mb-0 text-muted"><?php echo htmlspecialchars($update['content']); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="card-footer bg-white text-center py-3">
                                    <a href="updates.php" class="text-decoration-none">View All Updates <i class="bi bi-arrow-right ms-1"></i></a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Upcoming Calendar Events -->
                        <div class="col-md-5 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header d-flex justify-content-between align-items-center py-3">
                                    <h5 class="mb-0">Upcoming Events</h5>
                                    <a href="calendar.php" class="btn btn-sm btn-outline-primary">Full Calendar</a>
                                </div>
                                <div class="card-body p-0">
                                    <div class="list-group list-group-flush">
                                        <?php if (empty($events)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="bi bi-calendar fs-1"></i>
                                            <p class="mt-2">No upcoming events.</p>
                                        </div>
                                        <?php else: ?>
                                            <?php foreach ($events as $event): ?>
                                            <div class="list-group-item border-start-0 border-end-0 py-3 px-4">
                                                <div class="d-flex">
                                                    <div class="me-3 text-center" style="min-width: 60px;">
                                                        <?php 
                                                        $event_date = new DateTime($event['event_date']);
                                                        $now = new DateTime();
                                                        $text_class = ($event_date < $now) ? 'text-muted' : (($event_date->format('Y-m-d') == $now->format('Y-m-d')) ? 'text-primary' : 'text-danger');
                                                        ?>
                                                        <h5 class="mb-0 <?php echo $text_class; ?>"><?php echo $event_date->format('d'); ?></h5>
                                                        <small class="text-muted"><?php echo $event_date->format('M'); ?></small>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1"><?php echo htmlspecialchars($event['title']); ?></h6>
                                                        <p class="mb-0 text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('g:i A', strtotime($event['event_time'])); ?> - <?php echo date('g:i A', strtotime($event['end_time'])); ?></p>
                                                        <p class="mb-0 text-muted small"><i class="bi bi-geo-alt me-1"></i> <?php echo htmlspecialchars($event['location']); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.querySelector('.sidebar-toggle');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('show');
                });
            }
            
            // Close sidebar when clicking outside on mobile
            document.addEventListener('click', function(event) {
                if (window.innerWidth < 992) {
                    const isClickInsideSidebar = sidebar.contains(event.target);
                    const isClickOnToggle = sidebarToggle.contains(event.target);
                    
                    if (!isClickInsideSidebar && !isClickOnToggle && sidebar.classList.contains('show')) {
                        sidebar.classList.remove('show');
                    }
                }
            });
        });
    </script>
</body>
</html>