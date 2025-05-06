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
    
    // Get user's role
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
    
    // Close database connection
    $db->closeConnection();
    
} catch (Exception $e) {
    // Log error
    error_log("Activities Panel Error: " . $e->getMessage());
    
    // Set default values in case of database error
    $user = [
        'firstname' => $_SESSION['firstname'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'resident',
        'profile_image' => 'assets/images/profile-placeholder.png'
    ];
    
    $activities = [];
    $userBarangayId = null;
    $selectedBarangayId = null;
    $barangays = [];
    
    // Close database connection if it exists
    if (isset($db)) {
        $db->closeConnection();
    }
}

    // Determine if the activity is for the user's barangay
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
    <title>Activities | DSWD 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/dashboard.css">
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="bi bi-person"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="activities.php">
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h3 mb-0 text-gray-800">
                                <i class="bi bi-calendar2-check me-2"></i> Activities Panel
                            </h1>
                            <?php if ($userBarangayId): ?>
                            <p class="text-muted mb-0">
                                Showing activities for your barangay: 
                                <strong>
                                <?php 
                                    $barangayName = 'Unknown';
                                    foreach ($barangays as $barangay) {
                                        if ($barangay['barangay_id'] == $userBarangayId) {
                                            $barangayName = htmlspecialchars($barangay['name']);
                                            break;
                                        }
                                    }
                                    echo $barangayName;
                                ?>
                                </strong>
                            </p>
                            <?php else: ?>
                            <p class="text-muted mb-0 text-danger">
                                <i class="bi bi-exclamation-triangle-fill me-1"></i>
                                Warning: You don't have a barangay assigned. Please update your profile.
                            </p>
                            <?php endif; ?>
                        </div>
                        <?php if ($userRole == 'admin' || $userRole == 'staff'): ?>
                        <a href="create_activity.php" class="btn btn-primary">
                            <i class="bi bi-plus-lg me-2"></i> Create Activity
                        </a>
                        <?php endif; ?>
                    </div>

                    <!-- Filter Bar -->
                    <div class="filter-bar shadow-sm">
                        <form method="GET" action="" class="row g-3 align-items-end">
                            <div class="col-lg-4 col-md-6">
                                <label for="search" class="form-label">Search</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Search activities..." value="<?php echo htmlspecialchars($searchTerm ?? ''); ?>">
                                </div>
                            </div>
                            
                            <div class="col-lg-2 col-md-6">
                                <label for="typeFilter" class="form-label">Activity Type</label>
                                <select class="form-select" id="typeFilter" name="type">
                                    <option value="">All Types</option>
                                    <option value="health_check" <?php echo ($activityType == 'health_check') ? 'selected' : ''; ?>>Health Check</option>
                                    <option value="education" <?php echo ($activityType == 'education') ? 'selected' : ''; ?>>Education</option>
                                    <option value="family_development_session" <?php echo ($activityType == 'family_development_session') ? 'selected' : ''; ?>>Family Development Session</option>
                                    <option value="community_meeting" <?php echo ($activityType == 'community_meeting') ? 'selected' : ''; ?>>Community Meeting</option>
                                    <option value="other" <?php echo ($activityType == 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            
                            <div class="col-lg-2 col-md-6">
                                <label for="dateFilter" class="form-label">Date Status</label>
                                <select class="form-select" id="dateFilter" name="date">
                                    <option value="">All Dates</option>
                                    <option value="upcoming" <?php echo ($dateFilter == 'upcoming') ? 'selected' : ''; ?>>Upcoming</option>
                                    <option value="active" <?php echo ($dateFilter == 'active') ? 'selected' : ''; ?>>Currently Active</option>
                                    <option value="past" <?php echo ($dateFilter == 'past') ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </div>
                            
                            <?php if ($userRole == 'admin' || $userRole == 'staff'): ?>
                            <!-- Only admin and staff can see the barangay filter -->
                            <div class="col-lg-2 col-md-6">
                                <label for="barangayFilter" class="form-label">Barangay</label>
                                <select class="form-select" id="barangayFilter" name="barangay_id">
                                    <option value="">All Barangays</option>
                                    <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['barangay_id']; ?>" <?php echo ($selectedBarangayId == $barangay['barangay_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($barangay['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <?php else: ?>
                            <!-- Regular users only see activities from their barangay -->
                            <div class="col-lg-2 col-md-6">
                                <label for="userBarangay" class="form-label">Barangay</label>
                                <input type="text" class="form-control" id="userBarangay" readonly 
                                    value="<?php 
                                        $barangayName = 'Your Barangay';
                                        foreach ($barangays as $barangay) {
                                            if ($barangay['barangay_id'] == $userBarangayId) {
                                                $barangayName = htmlspecialchars($barangay['name']);
                                                break;
                                            }
                                        }
                                        echo $barangayName;
                                    ?>">
                                <input type="hidden" name="barangay_id" value="<?php echo $userBarangayId; ?>">
                            </div>
                            <?php endif; ?>
                            
                            <div class="col-lg-2 col-md-12 text-end">
                                <button type="submit" class="btn btn-primary me-2 w-100">
                                    <i class="bi bi-filter me-2"></i> Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Activity Cards -->
                    <?php if (count($activities) > 0): ?>
                    <div class="row">
                        <?php foreach ($activities as $activity): ?>
                        <?php 
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
                                    <a href="control/view_activity.php?id=<?php echo $activity['activity_id']; ?>" class="btn btn-sm btn-outline-primary">
                                        View Details
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="card shadow-sm">
                        <div class="card-body no-activities">
                            <i class="bi bi-calendar2-x fs-1 mb-3"></i>
                            <h5>No Activities Found</h5>
                            <p class="text-muted">There are no activities that match your filter criteria.</p>
                            <?php if ($userRole == 'admin' || $userRole == 'staff'): ?>
                            <a href="create_activity.php" class="btn btn-primary mt-2">
                                <i class="bi bi-plus-lg me-2"></i> Create New Activity
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
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
    </script>
</body>
</html>