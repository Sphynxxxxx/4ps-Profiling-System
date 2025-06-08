<?php
require_once "../backend/connections/config.php";
require_once "../backend/connections/database.php";
require_once "../backend/connections/helpers.php";

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
    
    // Get current user's barangay
    $userBarangayId = null;
    if (isset($user['barangay'])) {
        $userBarangayId = intval($user['barangay']);
    } else {
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

    // Count total beneficiaries
    $beneficiaryCountSql = "SELECT COUNT(*) as total FROM beneficiaries WHERE parent_leader_id = ?";
    $beneficiaryResult = $db->fetchOne($beneficiaryCountSql, [$userId]);
    $totalBeneficiaries = $beneficiaryResult['total'] ?? 0;
        
    // Count active programs (activities that are currently ongoing)
    if ($userRole == 'admin' || $userRole == 'staff') {
        // Admin and staff see all active programs
        $activeProgramsSql = "SELECT COUNT(*) as total FROM activities 
                            WHERE CURDATE() BETWEEN start_date AND end_date";
        $programsResult = $db->fetchOne($activeProgramsSql);
    } else {
        // Regular users only see active programs in their barangay
        $activeProgramsSql = "SELECT COUNT(*) as total FROM activities 
                            WHERE CURDATE() BETWEEN start_date AND end_date 
                            AND barangay_id = ?";
        $programsResult = $db->fetchOne($activeProgramsSql, [$userBarangayId]);
    }
    $activePrograms = $programsResult['total'] ?? 0;
    
    // Calculate compliance rate instead of hardcoding it
    $complianceRate = calculateComplianceRate($db, $userRole, $userBarangayId);
    
    // Get completed and missed activities for pie chart
    $activityStats = getActivityStatistics($db, $userId, $userRole, $userBarangayId);
    
    // Activity filtering
    $activityType = isset($_GET['type']) ? $_GET['type'] : null;
    $dateFilter = isset($_GET['date']) ? $_GET['date'] : null;
    $selectedBarangayId = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : $userBarangayId;
    $searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
    
    // Get activities with filters
    $activitiesSql = "SELECT a.*, b.name as barangay_name, u.firstname, u.lastname 
                     FROM activities a 
                     LEFT JOIN barangays b ON a.barangay_id = b.barangay_id
                     LEFT JOIN users u ON a.created_by = u.user_id
                     WHERE 1=1";
    
    $params = [];
    
    // Apply filters
    if ($activityType) {
        $activitiesSql .= " AND a.activity_type = ?";
        $params[] = $activityType;
    }
    
    if ($dateFilter) {
        if ($dateFilter === 'upcoming') {
            $activitiesSql .= " AND a.start_date >= CURDATE()";
        } elseif ($dateFilter === 'past') {
            $activitiesSql .= " AND a.end_date < CURDATE()";
        } elseif ($dateFilter === 'active') {
            $activitiesSql .= " AND CURDATE() BETWEEN a.start_date AND a.end_date";
        }
    }
    
    if ($selectedBarangayId) {
        $activitiesSql .= " AND a.barangay_id = ?";
        $params[] = $selectedBarangayId;
    }
    
    if ($searchTerm) {
        $activitiesSql .= " AND (a.title LIKE ? OR a.description LIKE ? OR b.name LIKE ?)";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
        $params[] = "%$searchTerm%";
    }
    
    $activitiesSql .= " ORDER BY a.created_at DESC";
    
    $activities = $db->fetchAll($activitiesSql, $params);
    
    // Fetch available barangays for the dropdown
    $barangays = [];
    $query = "SELECT barangay_id, name FROM barangays ORDER BY name";
    $barangays = $db->fetchAll($query);
    
} catch (Exception $e) {
    // Log error
    error_log("Dashboard Error: " . $e->getMessage());
    
    $user = [
        'firstname' => $_SESSION['firstname'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'resident',
        'profile_image' => 'assets/images/profile-placeholder.png'
    ];
    
    $activities = [];
    $userBarangayId = null;
    $selectedBarangayId = null;
    $barangays = [];
    $totalBeneficiaries = 0;
    $activePrograms = 0;
    $complianceRate = 0; // Default to 0 instead of 85
    $activityStats = ['completed' => 0, 'missed' => 0];
}

// Get recent activities with a new database connection if previous one was closed
try {
    if (!isset($db) || !$db->isConnected()) {
        $db = new Database();
    }
    
    // Get only the 3 newest activities
    $recentActivitiesSql = "SELECT a.*, b.name as barangay_name, u.firstname, u.lastname 
                        FROM activities a 
                        LEFT JOIN barangays b ON a.barangay_id = b.barangay_id
                        LEFT JOIN users u ON a.created_by = u.user_id
                        WHERE 1=1";
    
    $recentActivitiesParams = [];
    
    // Apply barangay filter for regular users
    if ($userRole != 'admin' && $userRole != 'staff' && $userBarangayId) {
        $recentActivitiesSql .= " AND a.barangay_id = ?";
        $recentActivitiesParams[] = $userBarangayId;
    }
    
    $recentActivitiesSql .= " ORDER BY a.created_at DESC LIMIT 3";
    
    $recentActivities = $db->fetchAll($recentActivitiesSql, $recentActivitiesParams);
    
    if (isset($db)) {
        $db->closeConnection();
    }
} catch (Exception $e) {
    error_log("Error fetching recent activities: " . $e->getMessage());
    $recentActivities = [];
    
    if (isset($db)) {
        $db->closeConnection();
    }
}

/**
 * Calculate compliance rate based on beneficiary attendance
 * @param Database $db Database connection
 * @param string $userRole User's role
 * @param int|null $barangayId Barangay ID to filter by
 * @return float Compliance rate percentage
 */
function calculateComplianceRate($db, $userRole, $barangayId = null) {
    try {
        // Get total beneficiaries count
        $totalSql = "SELECT COUNT(*) as total FROM beneficiaries";
        $totalParams = [];
        
        // Apply barangay filter for non-admin/staff users
        if ($userRole != 'admin' && $userRole != 'staff' && $barangayId) {
            $totalSql .= " WHERE barangay_id = ?";
            $totalParams[] = $barangayId;
        }
        
        $totalResult = $db->fetchOne($totalSql, $totalParams);
        $totalBeneficiaries = $totalResult['total'] ?? 0;
        
        // If no beneficiaries, return 0% compliance
        if ($totalBeneficiaries == 0) {
            return 0;
        }
        

        $compliantSql = "SELECT COUNT(DISTINCT b.beneficiary_id) as compliant 
                         FROM beneficiaries b
                         JOIN activity_participants ap ON b.user_id = ap.user_id
                         JOIN activities a ON ap.activity_id = a.activity_id
                         WHERE ap.attended = 1 
                         AND a.activity_type IN ('family_development_session', 'health_check', 'education', 'community_meeting')
                         AND ap.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
        
        $compliantParams = [];
        
        // Apply barangay filter for non-admin/staff users
        if ($userRole != 'admin' && $userRole != 'staff' && $barangayId) {
            $compliantSql .= " AND b.barangay_id = ?";
            $compliantParams[] = $barangayId;
        }
        
        $compliantResult = $db->fetchOne($compliantSql, $compliantParams);
        $compliantBeneficiaries = $compliantResult['compliant'] ?? 0;
        
        // Calculate compliance rate as percentage
        $complianceRate = round(($compliantBeneficiaries / $totalBeneficiaries) * 100, 1);
        
        return $complianceRate;
        
    } catch (Exception $e) {
        error_log("Error calculating compliance rate: " . $e->getMessage());
        return 0; // Return 0% on error
    }
}

/**
 * Get activity statistics for completed and missed activities
 * @param Database $db Database connection
 * @param int $userId Current user ID
 * @param string $userRole User's role  
 * @param int|null $barangayId Barangay ID to filter by
 * @return array Contains completed and missed counts
 */
function getActivityStatistics($db, $userId, $userRole, $barangayId = null) {
    try {
        // For admin/staff, count all activities
        // For regular users, count their personal participation
        
        if ($userRole == 'admin' || $userRole == 'staff') {
            // Count all completed activities
            $completedSql = "SELECT COUNT(DISTINCT a.activity_id) as count 
                           FROM activities a
                           WHERE a.end_date < CURDATE()
                           AND EXISTS (
                               SELECT 1 FROM activity_participants ap 
                               WHERE ap.activity_id = a.activity_id 
                               AND ap.attended = 1
                           )";
            
            // Count all missed activities (past activities with no attendance)
            $missedSql = "SELECT COUNT(DISTINCT a.activity_id) as count
                        FROM activities a
                        WHERE a.end_date < CURDATE()
                        AND NOT EXISTS (
                            SELECT 1 FROM activity_participants ap
                            WHERE ap.activity_id = a.activity_id
                            AND ap.attended = 1
                        )";
                        
            $completedResult = $db->fetchOne($completedSql);
            $missedResult = $db->fetchOne($missedSql);
            
        } else {
            // For regular users, count their personal attendance
            $completedSql = "SELECT COUNT(*) as count
                           FROM activity_participants ap
                           JOIN activities a ON ap.activity_id = a.activity_id
                           WHERE ap.user_id = ?
                           AND ap.attended = 1
                           AND a.end_date < CURDATE()";
            
            $missedSql = "SELECT COUNT(*) as count
                        FROM activity_participants ap
                        JOIN activities a ON ap.activity_id = a.activity_id
                        WHERE ap.user_id = ?
                        AND ap.attended = 0
                        AND a.end_date < CURDATE()";
            
            $completedResult = $db->fetchOne($completedSql, [$userId]);
            $missedResult = $db->fetchOne($missedSql, [$userId]);
        }
        
        return [
            'completed' => $completedResult['count'] ?? 0,
            'missed' => $missedResult['count'] ?? 0
        ];
        
    } catch (Exception $e) {
        error_log("Error getting activity statistics: " . $e->getMessage());
        return ['completed' => 0, 'missed' => 0];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activities | DSWD 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
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

        .filter-bar {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .no-activities {
            min-height: 300px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
        
        .chart-container {
            position: relative;
            height: 250px;
            width: 100%;
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
                        <!--<i class="bi bi-bell-fill fs-5"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                            <?php echo count($activities); ?>
                        </span>-->
                    </a>
                </div>
                <div class="dropdown">
                    <a class="text-white dropdown-toggle text-decoration-none" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-5 me-1"></i>
                        <span class="d-none d-md-inline-block"><?php echo $user['firstname']; ?></span>
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
                </ul>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="container-fluid pb-5">
                    <!-- Page Title -->
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
                                        <a href="control/register_beneficiary.php" class="btn btn-sm btn-outline-primary bi bi-plus-lg me-2"> Register Beneficiaries</a>
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
                                            <h6 class="card-subtitle mb-1 text-muted">Active Activities</h6>
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

                    <!-- Activity Cards and Pie Chart Row -->
                    <div class="row mb-4">
                        <!-- Recent Activities Column -->
                        <div class="col-lg-8">
                            <div class="card border h-100">
                                <div class="card-body p-3">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <div class="d-flex align-items-center">
                                            <h5 class="mb-0">
                                                <i class="bi bi-calendar2-check me-2 text-primary"></i> Recent View Activities
                                            </h5>
                                        </div>
                                        <a href="activities.php" class="btn btn-sm btn-outline-primary">View All Activities</a>
                                    </div>
                                    
                                    <?php 
                                    // Get only the 3 newest activities
                                    $recentActivitiesSql = "SELECT a.*, b.name as barangay_name, u.firstname, u.lastname 
                                                        FROM activities a 
                                                        LEFT JOIN barangays b ON a.barangay_id = b.barangay_id
                                                        LEFT JOIN users u ON a.created_by = u.user_id
                                                        WHERE 1=1";
                                    
                                    $recentActivitiesParams = [];
                                    
                                    // Apply barangay filter for regular users
                                    if ($userRole != 'admin' && $userRole != 'staff' && $userBarangayId) {
                                        $recentActivitiesSql .= " AND a.barangay_id = ?";
                                        $recentActivitiesParams[] = $userBarangayId;
                                    }
                                    
                                    $recentActivitiesSql .= " ORDER BY a.created_at DESC LIMIT 3";
                                    
                                    $recentActivities = $db->fetchAll($recentActivitiesSql, $recentActivitiesParams);
                                    
                                    if (count($recentActivities) > 0):
                                    ?>
                                    <div class="row">
                                        <?php foreach ($recentActivities as $activity): 
                                            $activityType = formatActivityType($activity['activity_type']);
                                            $activityStatus = getActivityStatus($activity['start_date'], $activity['end_date']);
                                        ?>
                                        <div class="col-lg-12 mb-3">
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
                                               <div class="card-footer bg-white border-top-0 d-flex justify-content-between align-items-center">
                                                   <a href="control/view_activity.php?id=<?php echo $activity['activity_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                       View Details
                                                   </a>
                                               </div>
                                           </div>
                                       </div>
                                       <?php endforeach; ?>
                                   </div>
                                   <?php else: ?>
                                   <div class="text-center py-5">
                                       <div class="d-inline-block p-3 border rounded mb-3">
                                           <i class="bi bi-calendar-x fs-1 text-muted"></i>
                                       </div>
                                       <p class="text-muted mb-0">No recent activities found</p>
                                   </div>
                                   <?php endif; ?>
                               </div>
                           </div>
                       </div>

                       <!-- Activity Statistics Pie Chart Column -->
                       <div class="col-lg-4">
                           <div class="card border h-100">
                               <div class="card-body p-3">
                                   <h5 class="mb-3">
                                       <i class="bi bi-pie-chart me-2 text-info"></i> Activity Statistics
                                   </h5>
                                   <div class="chart-container">
                                       <canvas id="activityChart"></canvas>
                                   </div>
                                   <div class="mt-3">
                                       <div class="d-flex justify-content-between mb-2">
                                           <div class="d-flex align-items-center">
                                               <div style="width: 12px; height: 12px; background-color: #198754; border-radius: 2px;" class="me-2"></div>
                                               <small>Completed Activities</small>
                                           </div>
                                           <small class="fw-bold"><?php echo $activityStats['completed']; ?></small>
                                       </div>
                                       <div class="d-flex justify-content-between">
                                           <div class="d-flex align-items-center">
                                               <div style="width: 12px; height: 12px; background-color: #dc3545; border-radius: 2px;" class="me-2"></div>
                                               <small>Missed Activities</small>
                                           </div>
                                           <small class="fw-bold"><?php echo $activityStats['missed']; ?></small>
                                       </div>
                                   </div>
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
                                           <a href="calendar.php" class="card text-center h-100 text-decoration-none border-danger">
                                               <div class="card-body p-3">
                                                   <div class="rounded-circle bg-danger bg-opacity-10 p-3 d-inline-flex mb-3">
                                                       <i class="bi bi-calendar-event fs-3 text-danger"></i>
                                                   </div>
                                                   <h6 class="card-title text-danger">View Events</h6>
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
       });

       // Initialize Pie Chart
       document.addEventListener('DOMContentLoaded', function() {
           const ctx = document.getElementById('activityChart').getContext('2d');
           const completedCount = <?php echo $activityStats['completed']; ?>;
           const missedCount = <?php echo $activityStats['missed']; ?>;
           
           new Chart(ctx, {
               type: 'pie',
               data: {
                   labels: ['Completed', 'Missed'],
                   datasets: [{
                       data: [completedCount, missedCount],
                       backgroundColor: [
                           'rgba(25, 135, 84, 0.8)', // Green for completed
                           'rgba(220, 53, 69, 0.8)'  // Red for missed
                       ],
                       borderColor: [
                           'rgba(25, 135, 84, 1)',
                           'rgba(220, 53, 69, 1)'
                       ],
                       borderWidth: 1
                   }]
               },
               options: {
                   responsive: true,
                   maintainAspectRatio: false,
                   plugins: {
                       legend: {
                           position: 'bottom',
                           labels: {
                               padding: 20
                           }
                       },
                       tooltip: {
                           callbacks: {
                               label: function(context) {
                                   const total = completedCount + missedCount;
                                   const percentage = total > 0 ? ((context.raw / total) * 100).toFixed(1) : 0;
                                   return `${context.label}: ${context.raw} (${percentage}%)`;
                               }
                           }
                       }
                   }
               }
           });
       });
   </script>
</body>
</html>