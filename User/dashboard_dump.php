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

    $userRole = $user['role'] ?? 'resident';

    
    
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

    $activitiesSql = "SELECT a.*, b.name as barangay_name, u.firstname, u.lastname 
                     FROM activities a 
                     LEFT JOIN barangays b ON a.barangay_id = b.barangay_id
                     LEFT JOIN users u ON a.created_by = u.user_id
                     WHERE 1=1";
    
    $params = [];
    
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
    $recentActivitiesSql .= " WHERE a.barangay_id = ?";
    }

    $recentActivitiesSql .= " ORDER BY a.created_at DESC LIMIT 3";

    // Execute query with parameters if needed
    $recentActivities = $selected_barangay_id 
    ? $db->fetchAll($recentActivitiesSql, [$selected_barangay_id])
    : $db->fetchAll($recentActivitiesSql);

    // Debug output (remove after testing)
    error_log("Activities Query: " . $recentActivitiesSql);
    error_log("Selected Barangay ID: " . ($selected_barangay_id ?? 'NULL'));
    error_log("Activities Found: " . count($recentActivities));

    
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
    
    $userRole = $user['role'] ?? $_SESSION['role'] ?? 'beneficiary';
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


function isUserBarangayActivity($activityBarangayId, $userBarangayId) {
    if (!$userBarangayId) {
        return false;
    }
    return $activityBarangayId == $userBarangayId;
}

    // Helper function to format activity type for display
function formatActivityType($type) {
    switch ($type) {
        case 'health_check':
            return ['name' => 'Health Check', 'class' => 'success', 'icon' => 'heart-pulse'];
        case 'education':
            return ['name' => 'Education', 'class' => 'info', 'icon' => 'book'];
        case 'family_development_session':
            return ['name' => 'Family Development Session', 'class' => 'warning', 'icon' => 'people'];
        case 'community_meeting':
            return ['name' => 'Community Meeting', 'class' => 'primary', 'icon' => 'chat-square-text'];
        case 'other':
            return ['name' => 'Other', 'class' => 'secondary', 'icon' => 'grid'];
        default:
            return ['name' => ucfirst(str_replace('_', ' ', $type)), 'class' => 'secondary', 'icon' => 'grid'];
    }
}

// Helper function to determine activity status
function getActivityStatus($startDate, $endDate) {
    $today = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime($startDate));
    $endDate = date('Y-m-d', strtotime($endDate));
    
    if ($today < $startDate) {
        return ['status' => 'upcoming', 'class' => 'info', 'label' => 'Upcoming'];
    } elseif ($today > $endDate) {
        return ['status' => 'completed', 'class' => 'secondary', 'label' => 'Completed'];
    } else {
        return ['status' => 'active', 'class' => 'success', 'label' => 'Active'];
    }
}

// Helper function to format date
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | DSWD 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        /* Activity card styles to match activities.php */
        .activity-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
            height: 100%;
        }
        
        .activity-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .activity-card .card-header {
            border-bottom: none;
            padding: 15px 20px;
        }
        
        .activity-type-badge {
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .activity-status-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .activity-date {
            display: inline-flex;
            align-items: center;
            color: #6c757d;
            font-size: 0.9rem;
            margin-right: 15px;
        }
        
        .activity-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            margin-right: 15px;
        }
        
        .activity-attachments {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        
        .attachment-icon {
            display: inline-flex;
            align-items: center;
            padding: 5px 10px;
            background-color: #f8f9fa;
            border-radius: 5px;
            color: #6c757d;
            font-size: 0.9rem;
            text-decoration: none;
        }
        
        .attachment-icon:hover {
            background-color: #e9ecef;
            color: #495057;
        }
        
        /* Dashboard specific styles */
        .stat-card {
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 1.8rem;
        }
        
        .welcome-card {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            border-radius: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-pattern {
            position: absolute;
            top: 0;
            right: 0;
            width: 250px;
            height: 100%;
            opacity: 0.1;
            background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNjAiIGhlaWdodD0iNjAiIHZpZXdCb3g9IjAgMCA2MCA2MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48ZyBmaWxsPSJ3aGl0ZSIgZmlsbC1ydWxlPSJldmVub2RkIj48cGF0aCBkPSJNMzYgMzRoLTJ2LTRoMnY0em0wLTl2NGgtMnYtNGgyem0tNSAxN3YtM2gxMHYzSDMxem0wLTEwaDEwdjNoLTEwdi0zem0wLTZoMTB2M2gtMTB2LTN6Ii8+PC9nPjwvc3ZnPg==');
            background-size: 60px 60px;
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
                <div class="container-fluid pb-5">
                    <!-- Page Title and Welcome Card -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="welcome-card p-4 shadow-sm">
                                <div class="welcome-pattern"></div>
                                <div class="row align-items-center">
                                    <div class="col-md-7">
                                        <h1 class="h3 mb-2">Welcome back, <?php echo $user['firstname']; ?>!</h1>
                                        <p class="mb-0">Dashboard Overview | <?php echo date('l, F d, Y'); ?></p>
                                    </div>
                                    <div class="col-md-5 d-none d-md-block text-end">
                                        <h4>DSWD 4P's Profiling System</h4>
                                        <p class="mb-0">Pantawid Pamilyang Pilipino Program</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Statistics Cards -->
                    <div class="row mb-4">
                        <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                            <div class="card stat-card shadow-sm h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                                            <i class="bi bi-people-fill"></i>
                                        </div>
                                        <div>
                                            <h6 class="card-subtitle mb-1 text-muted">Total Beneficiaries</h6>
                                            <h2 class="card-title mb-0"><?php echo number_format($totalBeneficiaries); ?></h2>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <a href="control/register_beneficiary.php" class="btn btn-sm btn-outline-primary">View All</a>
                                    </div>
                                    <div class="mt-3">
                                        <a href="control/register_beneficiary.php.php" class="btn btn-sm btn-outline-primary bi bi-plus-lg me-2"> Register Beneficiaries</a>
                                    </div>
                                    
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                            <div class="card stat-card shadow-sm h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                                            <i class="bi bi-journal-check"></i>
                                        </div>
                                        <div>
                                            <h6 class="card-subtitle mb-1 text-muted">Active Programs</h6>
                                            <h2 class="card-title mb-0"><?php echo number_format($activePrograms); ?></h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4 col-md-6">
                            <div class="card stat-card shadow-sm h-100">
                                <div class="card-body p-4">
                                    <div class="d-flex align-items-center">
                                        <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                                            <i class="bi bi-check2-circle"></i>
                                        </div>
                                        <div>
                                            <h6 class="card-subtitle mb-1 text-muted">Compliance Rate</h6>
                                            <h2 class="card-title mb-0"><?php echo $complianceRate; ?>%</h2>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activities Section Modified -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-header bg-white">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0">
                                            <i class="bi bi-calendar2-check me-2 text-primary"></i> Recent View Activities
                                        </h5>
                                        <div class="d-flex">
                                            <?php if (!empty($barangays)): ?>
                                            <form method="get" class="me-2">
                                                <select name="barangay_id" class="form-select form-select-sm" onchange="this.form.submit()">
                                                    <option value="">All Barangays</option>
                                                    <?php foreach ($barangays as $barangay): ?>
                                                    <option value="<?php echo $barangay['barangay_id']; ?>" 
                                                        <?php echo ($selected_barangay_id == $barangay['barangay_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($barangay['name']); ?>
                                                    </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </form>
                                            <?php endif; ?>
                                            <a href="activities.php" class="btn btn-sm btn-outline-primary">View All Activities</a>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <?php if (count($recentActivities) > 0): ?>
                                    <div class="row">
                                        <?php 
                                        // Display up to 3 most recent activities
                                        $displayLimit = 3;
                                        $activitiesToShow = array_slice($recentActivities, 0, $displayLimit);
                                        
                                        foreach ($activitiesToShow as $activity): 
                                            $activityType = formatActivityType($activity['activity_type']);
                                            $activityStatus = getActivityStatus($activity['start_date'], $activity['end_date']);
                                            $attachments = json_decode($activity['attachments'], true) ?? [];
                                        ?>
                                        <div class="col-xl-4 col-lg-6 mb-4">
                                            <div class="card activity-card shadow-sm h-100">
                                                <div class="card-header d-flex align-items-center py-3 bg-white">
                                                    <div class="activity-icon bg-<?php echo $activityType['class']; ?> bg-opacity-10">
                                                        <i class="bi bi-<?php echo $activityType['icon']; ?> text-<?php echo $activityType['class']; ?> fs-4"></i>
                                                    </div>
                                                    <div>
                                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($activity['title']); ?></h5>
                                                        <span class="activity-type-badge bg-<?php echo $activityType['class']; ?> bg-opacity-10 text-<?php echo $activityType['class']; ?>">
                                                            <?php echo $activityType['name']; ?>
                                                        </span>
                                                    </div>
                                                    <span class="activity-status-badge bg-<?php echo $activityStatus['class']; ?> bg-opacity-10 text-<?php echo $activityStatus['class']; ?>">
                                                        <?php echo $activityStatus['label']; ?>
                                                    </span>
                                                </div>
                                                <div class="card-body">
                                                    <p class="card-text">
                                                        <?php 
                                                        $desc = htmlspecialchars($activity['description'] ?? 'No description available.');
                                                        echo (strlen($desc) > 150) ? substr($desc, 0, 150) . '...' : $desc;
                                                        ?>
                                                    </p>
                                                    
                                                    <div class="d-flex flex-wrap align-items-center mt-3 mb-2">
                                                        <div class="activity-date me-3">
                                                            <i class="bi bi-calendar3 me-1"></i>
                                                            <?php echo formatDate($activity['start_date']); ?> - <?php echo formatDate($activity['end_date']); ?>
                                                        </div>
                                                        
                                                        <?php if ($activity['barangay_name']): ?>
                                                        <div class="activity-date">
                                                            <i class="bi bi-geo-alt me-1"></i>
                                                            <?php echo htmlspecialchars($activity['barangay_name']); ?>
                                                        </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <?php if (!empty($attachments)): ?>
                                                    <div class="activity-attachments">
                                                        <?php if (isset($attachments['documents']) && count($attachments['documents']) > 0): ?>
                                                            <?php foreach ($attachments['documents'] as $document): ?>
                                                            <a href="uploads/activity_documents/<?php echo $document; ?>" class="attachment-icon" target="_blank">
                                                                <i class="bi bi-file-earmark-pdf me-1"></i> Document
                                                            </a>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                        
                                                        <?php if (isset($attachments['images']) && count($attachments['images']) > 0): ?>
                                                            <?php foreach ($attachments['images'] as $image): ?>
                                                            <a href="uploads/activity_images/<?php echo $image; ?>" class="attachment-icon" target="_blank">
                                                                <i class="bi bi-image me-1"></i> Image
                                                            </a>
                                                            <?php endforeach; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        Created by: <?php echo htmlspecialchars($activity['firstname'] . ' ' . $activity['lastname']); ?>
                                                    </small>
                                                    <a href="control/view_activity.php?id=<?php echo $activity['activity_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                        View Details
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php else: ?>
                                    <div class="text-center p-4">
                                        <i class="bi bi-calendar2-x fs-1 text-muted mb-3"></i>
                                        <p>No recent activities found</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="row">
                        <div class="col-12">
                            <div class="card shadow-sm">
                                <div class="card-header bg-white">
                                    <h5 class="mb-0">
                                        <i class="bi bi-lightning-charge me-2 text-warning"></i> Quick Actions
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-lg-3 col-md-6">
                                            <a href="activities.php" class="card text-center h-100 text-decoration-none border-primary">
                                                <div class="card-body p-3">
                                                    <div class="rounded-circle bg-primary bg-opacity-10 p-3 d-inline-flex mb-3">
                                                        <i class="bi bi-calendar2-check fs-3 text-primary"></i>
                                                    </div>
                                                    <h6 class="card-title text-primary">View Activities</h6>
                                                </div>
                                            </a>
                                        </div>
                                        
                                        <div class="col-lg-3 col-md-6">
                                            <a href="members.php" class="card text-center h-100 text-decoration-none border-success">
                                                <div class="card-body p-3">
                                                    <div class="rounded-circle bg-success bg-opacity-10 p-3 d-inline-flex mb-3">
                                                        <i class="bi bi-people fs-3 text-success"></i>
                                                    </div>
                                                    <h6 class="card-title text-success">View Members</h6>
                                                </div>
                                            </a>
                                        </div>
                                        
                                        <div class="col-lg-3 col-md-6">
                                            <a href="profile.php" class="card text-center h-100 text-decoration-none border-info">
                                                <div class="card-body p-3">
                                                    <div class="rounded-circle bg-info bg-opacity-10 p-3 d-inline-flex mb-3">
                                                        <i class="bi bi-person-badge fs-3 text-info"></i>
                                                    </div>
                                                    <h6 class="card-title text-info">Update Profile</h6>
                                                </div>
                                            </a>
                                        </div>
                                        
                                        <div class="col-lg-3 col-md-6">
                                            <a href="reports.php" class="card text-center h-100 text-decoration-none border-warning">
                                                <div class="card-body p-3">
                                                    <div class="rounded-circle bg-warning bg-opacity-10 p-3 d-inline-flex mb-3">
                                                        <i class="bi bi-file-earmark-text fs-3 text-warning"></i>
                                                    </div>
                                                    <h6 class="card-title text-warning">View Reports</h6>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-light mt-auto py-3 border-top">
        <div class="container-fluid">
            <div class="row">
                <div class="col-md-6 text-center text-md-start">
                    <span class="text-muted">&copy; <?php echo date('Y'); ?> DSWD 4P's Profiling System</span>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <span class="text-muted">Pantawid Pamilyang Pilipino Program</span>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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

            // Handle barangay filter form submission
            document.querySelectorAll('select[name="barangay_id"]').forEach(select => {
                select.addEventListener('change', function() {
                    // Remove existing barangay_id parameter if present
                    const url = new URL(window.location.href);
                    url.searchParams.delete('barangay_id');
                    
                    // Add new barangay_id if selected
                    if (this.value) {
                        url.searchParams.append('barangay_id', this.value);
                    }
                    
                    window.location.href = url.toString();
                });
            });
        });
    </script>
</body>
</html>