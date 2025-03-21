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
    $completedSql = "SELECT COUNT(*) as count FROM activities WHERE user_id = ? AND status = 'completed'";
    $completedResult = $db->fetchOne($completedSql, [$userId]);
    $completedCount = $completedResult ? $completedResult['count'] : 0;
    
    $missedSql = "SELECT COUNT(*) as count FROM activities WHERE user_id = ? AND status = 'missed'";
    $missedResult = $db->fetchOne($missedSql, [$userId]);
    $missedCount = $missedResult ? $missedResult['count'] : 0;
    
    $newActivitiesSql = "SELECT COUNT(*) as count FROM activities WHERE user_id = ? AND status = 'pending' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
    $newActivitiesResult = $db->fetchOne($newActivitiesSql, [$userId]);
    $newActivitiesCount = $newActivitiesResult ? $newActivitiesResult['count'] : 0;
    
    // Get recent activities
    $recentActivitiesSql = "SELECT * FROM activities WHERE user_id = ? AND status = 'pending' ORDER BY due_date ASC LIMIT 3";
    $recentActivities = $db->fetchAll($recentActivitiesSql, [$userId]);
    
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
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'beneficiary',
        'profile_image' => 'assets/images/profile-placeholder.png'
    ];
    
    $completedCount = 0;
    $missedCount = 0;
    $newActivitiesCount = 0;
    $recentActivities = [];
    $updates = [];
    $events = [];
    
    // Close database connection if it exists
    if (isset($db)) {
        $db->closeConnection();
    }
}

// Determine display name and role for the sidebar
$displayName = $user['full_name'] ?? $_SESSION['full_name'] ?? 'User';
$displayRole = strtoupper($user['role'] ?? $_SESSION['role'] ?? 'BENEFICIARY');
//$profileImage = !empty($user['profile_image']) ? $user['profile_image'] : 
                //(!empty($user['valid_id_path']) ? $user['valid_id_path'] : 'assets/images/profile-placeholder.png');

// Total beneficiaries (for admin dashboard)
$totalBeneficiaries = 0;
$activePrograms = 0;
$complianceRate = 0;
$pendingVerifications = 0;

if (isset($user['role']) && $user['role'] == 'admin') {
    try {
        $db = new Database();
        
        // Get total beneficiaries
        $totalSql = "SELECT COUNT(*) as count FROM beneficiaries";
        $totalResult = $db->fetchOne($totalSql);
        $totalBeneficiaries = $totalResult ? $totalResult['count'] : 0;
        
        // Get active programs
        $programsSql = "SELECT COUNT(*) as count FROM programs WHERE status = 'active'";
        $programsResult = $db->fetchOne($programsSql);
        $activePrograms = $programsResult ? $programsResult['count'] : 0;
        
        // Get compliance rate
        $complianceSql = "SELECT 
                            (COUNT(CASE WHEN status = 'completed' THEN 1 END) * 100.0 / COUNT(*)) as rate 
                          FROM activities";
        $complianceResult = $db->fetchOne($complianceSql);
        $complianceRate = $complianceResult ? round($complianceResult['rate']) : 0;
        
        // Get pending verifications
        $verificationsSql = "SELECT COUNT(*) as count FROM verifications WHERE status = 'pending'";
        $verificationsResult = $db->fetchOne($verificationsSql);
        $pendingVerifications = $verificationsResult ? $verificationsResult['count'] : 0;
        
        $db->closeConnection();
    } catch (Exception $e) {
        error_log("Admin Dashboard Error: " . $e->getMessage());
        
        if (isset($db)) {
            $db->closeConnection();
        }
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
                <!-- User Profile Section 
                <div class="profile-section">
                    <img src="image_fetch.php" alt="Profile Image" class="profile-image">
                    <h5 class="user-name"><?php echo $displayName; ?></h5>
                    <span class="user-type"><?php echo $displayRole; ?></span>
                </div>-->
                
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
                        <div>
                            <button class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-download me-1"></i> Export
                            </button>
                            <button class="btn btn-primary btn-sm ms-2">
                                <i class="bi bi-plus-lg me-1"></i> New Record
                            </button>
                        </div>
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

                        <div class="col-md-3 mb-4">
                            <div class="card dashboard-card bg-white h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="text-muted mb-1">Pending Verifications</h6>
                                            <h2 class="mb-0 fw-bold"><?php echo $pendingVerifications; ?></h2>
                                        </div>
                                        <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                            <i class="bi bi-hourglass-split text-warning fs-3"></i>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <!--<span class="badge bg-danger">-18% <i class="bi bi-arrow-down"></i></span>
                                        <span class="text-muted ms-2">Reduced backlog</span>-->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activities Sections -->
                    <div class="row mb-4">
                        <!-- New Activities Section -->
                        <div class="col-md-6 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header d-flex justify-content-between align-items-center py-3">
                                    <h5 class="mb-0">New Added Activities</h5>
                                    <span class="badge bg-primary rounded-pill"><?php echo $newActivitiesCount; ?></span>
                                </div>
                                <div class="card-body">
                                    <div class="list-group list-group-flush">
                                        <?php if (empty($recentActivities)): ?>
                                        <div class="text-center text-muted py-4">
                                            <i class="bi bi-calendar-check fs-1"></i>
                                            <p class="mt-2">No new activities at the moment.</p>
                                        </div>
                                        <?php else: ?>
                                            <?php foreach ($recentActivities as $activity): ?>
                                            <div class="list-group-item border-0 px-0 d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                                    <p class="text-muted mb-0 small">
                                                        <?php 
                                                        $due_date = new DateTime($activity['due_date']);
                                                        $now = new DateTime();
                                                        $diff = $now->diff($due_date);
                                                        echo "Due in " . $diff->days . " days";
                                                        ?>
                                                    </p>
                                                </div>
                                                <span class="badge bg-<?php 
                                                    $category = strtolower($activity['category']);
                                                    if ($category == 'education') echo 'info';
                                                    elseif ($category == 'health') echo 'success';
                                                    elseif ($category == 'financial') echo 'warning';
                                                    else echo 'secondary';
                                                ?> rounded-pill"><?php echo htmlspecialchars($activity['category']); ?></span>
                                            </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="activities.php" class="btn btn-outline-primary">
                                            Click to View All
                                            <i class="bi bi-arrow-right ms-1"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Activity Status Section -->
                        <div class="col-md-6 mb-4">
                            <div class="card dashboard-card">
                                <div class="card-header py-3">
                                    <h5 class="mb-0">Activity Status</h5>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 text-center mb-4">
                                            <h5 class="text-muted mb-3">Completed Activities</h5>
                                            <div class="d-flex align-items-center justify-content-center mb-3">
                                                <div style="width: 120px; height: 120px;" class="rounded-circle d-flex align-items-center justify-content-center border border-success border-3">
                                                    <h1 class="display-4 mb-0 text-success"><?php echo $completedCount; ?></h1>
                                                </div>
                                            </div>
                                            <button class="btn status-button completed-btn">
                                                <i class="bi bi-check-circle me-1"></i> Completed
                                            </button>
                                        </div>
                                        
                                        <div class="col-md-6 text-center mb-4">
                                            <h5 class="text-muted mb-3">Missed Activities</h5>
                                            <div class="d-flex align-items-center justify-content-center mb-3">
                                                <div style="width: 120px; height: 120px;" class="rounded-circle d-flex align-items-center justify-content-center border border-danger border-3">
                                                    <h1 class="display-4 mb-0 text-danger"><?php echo $missedCount; ?></h1>
                                                </div>
                                            </div>
                                            <button class="btn status-button missed-btn">
                                                <i class="bi bi-x-circle me-1"></i> Missed
                                            </button>
                                        </div>
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