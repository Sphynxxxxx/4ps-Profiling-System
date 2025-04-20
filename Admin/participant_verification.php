<?php
session_start();
require_once "../backend/connections/config.php";
require_once "../backend/connections/database.php";


// Initialize variables
$error_message = "";
$success_message = "";
$pending_users = [];

// Get the selected barangay ID from URL parameter
$selected_barangay_id = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : null;

if ($selected_barangay_id) {
    $_SESSION['default_barangay_id'] = $selected_barangay_id;
} elseif (isset($_SESSION['default_barangay_id'])) {
    $selected_barangay_id = $_SESSION['default_barangay_id'];
}

try {
    $db = new Database();
    
    // Fetch current barangay details if a specific barangay is selected
    $current_barangay = null;
    if ($selected_barangay_id) {
        $barangayQuery = "SELECT name, captain_name FROM barangays WHERE barangay_id = ?";
        $current_barangay = $db->fetchOne($barangayQuery, [$selected_barangay_id]);
    }
    
    // Handle verification actions if form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action']) && isset($_POST['user_id'])) {
            $user_id = $_POST['user_id'];
            $action = $_POST['action'];
            
            if ($action === 'approve') {
                // Update user status to active
                $updateQuery = "UPDATE users SET account_status = 'active' WHERE user_id = ?";
                $db->execute($updateQuery, [$user_id]);
                
                // Check if user exists
                $userQuery = "SELECT * FROM users WHERE user_id = ?";
                $user = $db->fetchOne($userQuery, [$user_id]);
                
                if ($user) {
                    // Log activity
                    $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, created_at) 
                                VALUES (?, 'verification', ?, NOW())";
                    $db->execute($logQuery, [$_SESSION['admin_id'] ?? 1, "Approved user ID {$user_id} (not added to beneficiaries)"]);
                    
                    $success_message = "User has been approved.";
                }
        
            } elseif ($action === 'reject') {
                // Update user status to deactivated
                $updateQuery = "UPDATE users SET account_status = 'deactivated' WHERE user_id = ?";
                $db->execute($updateQuery, [$user_id]);
                
                // Log activity
                $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, created_at) 
                            VALUES (?, 'verification', ?, NOW())";
                $db->execute($logQuery, [$_SESSION['admin_id'] ?? 1, "Rejected user ID {$user_id} verification"]);
                
                $success_message = "User application has been rejected.";
            } elseif ($action === 'delete') {
                // First delete from beneficiaries if exists
                $deleteBeneficiaryQuery = "DELETE FROM beneficiaries WHERE user_id = ?";
                $db->execute($deleteBeneficiaryQuery, [$user_id]);
                
                // Then delete the user
                $deleteUserQuery = "DELETE FROM users WHERE user_id = ?";
                $db->execute($deleteUserQuery, [$user_id]);
                
                // Log activity
                $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, created_at) 
                            VALUES (?, 'deletion', ?, NOW())";
                $db->execute($logQuery, [$_SESSION['admin_id'] ?? 1, "Deleted user ID {$user_id}"]);
                
                $success_message = "User has been deleted successfully.";
                
                // Refresh the page to update the lists - maintain barangay filter if set
                $redirect = "participant_verification.php";
                if ($selected_barangay_id) {
                    $redirect .= "?barangay_id=" . $selected_barangay_id;
                }
                header("Location: $redirect");
                exit();
            }
        }
    }
    
    // Get pending verification count for sidebar - filter by barangay if selected
    $pendingVerificationsQuery = $selected_barangay_id 
        ? "SELECT COUNT(*) as pending FROM users WHERE account_status = 'pending' AND role = 'resident' AND barangay = ?"
        : "SELECT COUNT(*) as pending FROM users WHERE account_status = 'pending' AND role = 'resident'";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $result = $params ? $db->fetchOne($pendingVerificationsQuery, $params) : $db->fetchOne($pendingVerificationsQuery);
    $pending_verifications = $result ? $result['pending'] : 0;
    
    // Get other counts for the sidebar - events might be filtered by barangay if applicable
    $upcomingEventsQuery = "SELECT COUNT(*) as upcoming FROM events WHERE event_date >= CURDATE()";
    $result = $db->fetchOne($upcomingEventsQuery);
    $upcoming_events = $result ? $result['upcoming'] : 0;
    
    $unreadMessagesQuery = "SELECT COUNT(*) as unread FROM messages WHERE status = 'unread'";
    $result = $db->fetchOne($unreadMessagesQuery);
    $unread_messages = $result ? $result['unread'] : 0;

    // Get approved users count for sidebar - filter by barangay if selected
    $approvedUsersCountQuery = $selected_barangay_id
        ? "SELECT COUNT(*) as approved FROM users WHERE account_status = 'active' AND role = 'resident' AND barangay = ?"
        : "SELECT COUNT(*) as approved FROM users WHERE account_status = 'active' AND role = 'resident'";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $result = $params ? $db->fetchOne($approvedUsersCountQuery, $params) : $db->fetchOne($approvedUsersCountQuery);
    $approved_users_count = $result ? $result['approved'] : 0;

    // Get rejected users count for sidebar - filter by barangay if selected
    $rejectedUsersCountQuery = $selected_barangay_id
        ? "SELECT COUNT(*) as rejected FROM users WHERE account_status = 'deactivated' AND role = 'resident' AND barangay = ?"
        : "SELECT COUNT(*) as rejected FROM users WHERE account_status = 'deactivated' AND role = 'resident'";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $result = $params ? $db->fetchOne($rejectedUsersCountQuery, $params) : $db->fetchOne($rejectedUsersCountQuery);
    $rejected_users_count = $result ? $result['rejected'] : 0;
    
    // Get all pending users - filter by barangay if selected
    $pendingUsersQuery = $selected_barangay_id
        ? "SELECT u.*, b.name as barangay_name 
           FROM users u 
           LEFT JOIN barangays b ON u.barangay = b.barangay_id 
           WHERE u.account_status = 'pending' AND u.role = 'resident' AND u.barangay = ?
           ORDER BY u.created_at DESC"
        : "SELECT u.*, b.name as barangay_name 
           FROM users u 
           LEFT JOIN barangays b ON u.barangay = b.barangay_id 
           WHERE u.account_status = 'pending' AND u.role = 'resident'
           ORDER BY u.created_at DESC";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $pending_users = $params ? $db->fetchAll($pendingUsersQuery, $params) : $db->fetchAll($pendingUsersQuery);

    // Add these queries to fetch approved and rejected users - filter by barangay if selected
    $approvedUsersQuery = $selected_barangay_id
        ? "SELECT u.*, b.name as barangay_name 
           FROM users u 
           LEFT JOIN barangays b ON u.barangay = b.barangay_id 
           WHERE u.account_status = 'active' AND u.role = 'resident' AND u.barangay = ?
           ORDER BY u.created_at DESC"
        : "SELECT u.*, b.name as barangay_name 
           FROM users u 
           LEFT JOIN barangays b ON u.barangay = b.barangay_id 
           WHERE u.account_status = 'active' AND u.role = 'resident'
           ORDER BY u.created_at DESC";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $approved_users = $params ? $db->fetchAll($approvedUsersQuery, $params) : $db->fetchAll($approvedUsersQuery);

    $rejectedUsersQuery = $selected_barangay_id
        ? "SELECT u.*, b.name as barangay_name 
           FROM users u 
           LEFT JOIN barangays b ON u.barangay = b.barangay_id 
           WHERE u.account_status = 'deactivated' AND u.role = 'resident' AND u.barangay = ?
           ORDER BY u.created_at DESC"
        : "SELECT u.*, b.name as barangay_name 
           FROM users u 
           LEFT JOIN barangays b ON u.barangay = b.barangay_id 
           WHERE u.account_status = 'deactivated' AND u.role = 'resident'
           ORDER BY u.created_at DESC";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $rejected_users = $params ? $db->fetchAll($rejectedUsersQuery, $params) : $db->fetchAll($rejectedUsersQuery);
    
    // Fetch all barangays for the filter dropdown
    $barangaysQuery = "
        SELECT 
            b.barangay_id, 
            b.name,
            COALESCE(b.captain_name, 'No Captain Assigned') as captain_name,
            COUNT(DISTINCT u.user_id) as users_count
        FROM barangays b
        LEFT JOIN users u ON b.barangay_id = u.barangay AND u.role = 'resident'
        GROUP BY b.barangay_id
        ORDER BY b.name
    ";
    $barangays = $db->fetchAll($barangaysQuery);
    
    $db->closeConnection();
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    
    $pending_verifications = 0;
    $upcoming_events = 0;
    $unread_messages = 0;
    $approved_users_count = 0;
    $rejected_users_count = 0;
    $pending_users = [];
    $approved_users = [];
    $rejected_users = [];
    $barangays = [];
    $current_barangay = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Leader Verification - 4P's Profiling System</title>
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

    <div class="main-content">
        <!-- Barangay Header if specific barangay is selected -->
        <?php if($selected_barangay_id && $current_barangay): ?>
        <div class="alert alert-success mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-building fs-1 me-3"></i>
                <div>
                    <h4 class="alert-heading">Barangay <?php echo htmlspecialchars($current_barangay['name']); ?> Parent Leaders</h4>
                    <p class="mb-0">Barangay Captain: <?php echo htmlspecialchars($current_barangay['captain_name'] ?: 'Not Assigned'); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" href="admin_dashboard.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active <?php echo $current_page == 'verification' ? 'active' : ''; ?>" href="participant_verification.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
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
                <a class="nav-link <?php echo $current_page == 'settings' ? 'active' : ''; ?>" href="#<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-gear"></i> System Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="../admin.php">
                    <i class="bi bi-box-arrow-right"></i> Back
                </a>
            </li>
        </ul>
        
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
        <!-- Page Title -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-person-check me-2"></i>Parent Leader Verification</h2>
            <div>
                <span class="badge bg-danger"><?php echo $pending_verifications; ?> Pending</span>
            </div>
        </div>
        
        <?php if(!empty($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <?php if(!empty($success_message)): ?>
        <div class="alert alert-success" role="alert">
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>
        
        <!-- Instructions Card -->
        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5><i class="bi bi-info-circle me-2"></i>Verification Guidelines</h5>
            </div>
            <div class="card-body">
                <p>Review each applicant carefully to ensure they meet the following criteria:</p>
                <ul>
                    <li>Household income does not exceed ₱10,000 per month</li>
                    <li>Has at least one dependent child aged 0-18 years</li>
                    <li>Valid ID and proof of residency match the provided information</li>
                    <li>Resident of the covered barangays</li>
                </ul>
                <p class="mb-0 text-danger"><strong>Note:</strong> After approval, the applicant will be officially registered as a 4P's beneficiary and will have access to all program benefits.</p>
            </div>
        </div>
        
        <!-- Verification Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <ul class="nav nav-tabs card-header-tabs" id="verificationTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                            Pending <span class="badge bg-danger ms-1"><?php echo $pending_verifications; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">
                            Approved <span class="badge bg-success ms-1"><?php echo $approved_users_count; ?></span>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="rejected-tab" data-bs-toggle="tab" data-bs-target="#rejected" type="button" role="tab">
                            Rejected <span class="badge bg-danger ms-1"><?php echo $rejected_users_count; ?></span>
                        </button>
                    </li>
                </ul>
                <div class="d-flex align-items-center">
                    <input type="text" id="searchInput" class="form-control form-control-sm me-2" placeholder="Search by name or ID...">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="searchTable()">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="tab-content" id="verificationTabContent">
                    <!-- Pending Tab -->
                    <div class="tab-pane fade show active" id="pending" role="tabpanel">
                        <?php if(count($pending_users) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Date of Birth</th>
                                        <th>Barangay</th>
                                        <th>Household Members</th>
                                        <th>Income</th>
                                        <th>Submitted</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($pending_users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['user_id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2">
                                                    <?php if(!empty($user['profile_image'])): ?>
                                                        <img src="../User/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                                            class="rounded-circle" 
                                                            style="width: 50px; height: 50px; object-fit: cover;" 
                                                            alt="Profile">
                                                    <?php else: ?>
                                                    <div class="avatar-placeholder"><?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars(ucwords($user['firstname'] . ' ' . $user['lastname'])); ?></div>
                                                    <small><?php echo htmlspecialchars($user['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['date_of_birth'])); ?></td>
                                        <td><?php echo htmlspecialchars($user['barangay_name'] ?? 'Unknown'); ?>, <?php echo htmlspecialchars($user['province']); ?> <?php echo htmlspecialchars($user['city']); ?></td>
                                        <td><?php echo $user['household_members']; ?> (<?php echo $user['dependants']; ?> dependants)</td>
                                        <td>₱<?php echo number_format($user['household_income'], 2); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <div class="d-flex">
                                                <button class="btn btn-sm btn-primary me-1" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $user['user_id']; ?>">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <form method="post" class="me-1" onsubmit="return confirm('Are you sure you want to approve this applicant?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="approve">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="bi bi-check-lg"></i>
                                                    </button>
                                                </form>
                                                <form method="post" onsubmit="return confirm('Are you sure you want to reject this applicant?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-x-lg"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="p-4 text-center">
                            <i class="bi bi-check-circle text-success fs-1"></i>
                            <p class="mt-3">No pending verification requests at this time!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Approved Tab -->
                    <div class="tab-pane fade" id="approved" role="tabpanel">
                        <?php if(count($approved_users) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Date Approved</th>
                                        <th>Barangay</th>
                                        <th>Household Members</th>
                                        <th>Income</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($approved_users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['user_id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2">
                                                    <?php if(!empty($user['profile_image'])): ?>
                                                        <img src="../User/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                                            class="rounded-circle" 
                                                            style="width: 50px; height: 50px; object-fit: cover;" 
                                                            alt="Profile">
                                                    <?php else: ?>
                                                        <div class="rounded-circle bg-secondary text-white d-flex align-items-center justify-content-center" 
                                                            style="width: 30px; height: 30px; font-size: 0.75rem;">
                                                            <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>

                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars(ucwords($user['firstname'] . ' ' . $user['lastname'])); ?></div>
                                                    <small><?php echo htmlspecialchars($user['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['updated_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($user['barangay'] . ', ' . $user['city']); ?></td>
                                        <td><?php echo $user['household_members']; ?> (<?php echo $user['dependants']; ?> dependants)</td>
                                        <td>₱<?php echo number_format($user['household_income'], 2); ?></td>
                                        <td><span class="badge bg-success">Approved</span></td>
                                        <td>
                                            <div class="d-flex">
                                                <!-- View Button -->
                                                <button class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $user['user_id']; ?>">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                
                                                <!-- Delete Button -->
                                                <form method="post" onsubmit="return confirm('WARNING: This will permanently delete this user. Continue?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="p-4 text-center">
                            <i class="bi bi-people text-primary fs-1"></i>
                            <p class="mt-3">No approved Parent Leader found!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Rejected Tab -->
                    <div class="tab-pane fade" id="rejected" role="tabpanel">
                        <?php if(count($rejected_users) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Date Rejected</th>
                                        <th>Barangay</th>
                                        <th>Reason</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($rejected_users as $user): ?>
                                    <tr>
                                        <td><?php echo $user['user_id']; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="me-2">
                                                    <?php if(!empty($user['profile_image'])): ?>
                                                        <img src="../User/<?php echo htmlspecialchars($user['profile_image']); ?>" 
                                                            class="rounded-circle" 
                                                            style="width: 50px; height: 50px; object-fit: cover;" 
                                                            alt="Profile">
                                                    <?php else: ?>
                                                    <div class="avatar-placeholder"><?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?></div>
                                                    <?php endif; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-bold"><?php echo htmlspecialchars(ucwords($user['firstname'] . ' ' . $user['lastname'])); ?></div>
                                                    <small><?php echo htmlspecialchars($user['email']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($user['updated_at'])); ?></td>
                                        <td><?php echo htmlspecialchars($user['barangay'] . ', ' . $user['city']); ?></td>
                                        <td><?php echo !empty($user['rejection_reason']) ? htmlspecialchars($user['rejection_reason']) : 'Not specified'; ?></td>
                                        <td><span class="badge bg-danger">Rejected</span></td>
                                        <td>
                                            <div class="d-flex">
                                                <!-- View Button -->
                                                <button class="btn btn-sm btn-primary me-2" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $user['user_id']; ?>">
                                                    <i class="bi bi-eye"></i> View
                                                </button>
                                                
                                                <!-- Delete Button -->
                                                <form method="post" onsubmit="return confirm('WARNING: This will permanently delete this user. Continue?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="p-4 text-center">
                            <i class="bi bi-person-x text-danger fs-1"></i>
                            <p class="mt-3">No rejected Parent Leader found!</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- View User Details Modals for ALL users -->
        <?php 
        // Combine all users into one array
        $all_users = array_merge(
            $pending_users ?? [],
            $approved_users ?? [],
            $rejected_users ?? []
        );

        foreach($all_users as $user): ?>
        <div class="modal fade" id="viewModal<?php echo $user['user_id']; ?>" tabindex="-1" aria-labelledby="viewModalLabel<?php echo $user['user_id']; ?>" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="viewModalLabel<?php echo $user['user_id']; ?>">
                            <i class="bi bi-person-badge me-2"></i>Applicant Details
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body text-center">
                                        <?php if(!empty($user['profile_image'])): ?>
                                            <img src="../User/<?php echo htmlspecialchars($user['profile_image']); ?>" class="img-fluid rounded-circle mb-3" style="width: 50px;" alt="Profile Image">
                                        <?php else: ?>
                                        <div class="avatar-placeholder-lg mb-3">
                                            <?php echo strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1)); ?>
                                        </div>
                                        <?php endif; ?>
                                        <h5 class="card-title"><?php echo htmlspecialchars(ucwords($user['firstname'] . ' ' . $user['lastname'])); ?></h5>
                                        <p class="text-muted"><?php echo htmlspecialchars($user['email']); ?></p>
                                        <p class="text-muted">Application Date: <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-person me-2"></i>Personal Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <small class="text-muted">Date of Birth</small>
                                                <p><?php echo date('F j, Y', strtotime($user['date_of_birth'])); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <small class="text-muted">Gender</small>
                                                <p><?php echo ucfirst(strtolower($user['gender'])); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <small class="text-muted">Civil Status</small>
                                                <p><?php echo ucfirst(strtolower($user['civil_status'])); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <small class="text-muted">Contact Number</small>
                                                <p><?php echo htmlspecialchars($user['phone_number']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-house me-2"></i>Household Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <small class="text-muted">Complete Address</small>
                                                <p><?php echo htmlspecialchars($user['barangay'] . ', ' . $user['city'] . ', ' . $user['province'] . ', ' . $user['region']); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <small class="text-muted">Family Head</small>
                                                <p><?php echo htmlspecialchars($user['family_head']); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <small class="text-muted">Total Household Members</small>
                                                <p><?php echo $user['household_members']; ?></p>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <small class="text-muted">Dependants (Below 18)</small>
                                                <p><?php echo $user['dependants']; ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card mb-3">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-cash-coin me-2"></i>Financial Information</h6>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-6 mb-2">
                                                <small class="text-muted">Occupation</small>
                                                <p><?php echo htmlspecialchars($user['occupation']); ?></p>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <small class="text-muted">Monthly Household Income</small>
                                                <p class="<?php echo $user['household_income'] > 10000 ? 'text-danger' : ''; ?>">
                                                    ₱<?php echo number_format($user['household_income'], 2); ?>
                                                    <?php if($user['household_income'] > 10000): ?>
                                                    <i class="bi bi-exclamation-triangle-fill text-danger" data-bs-toggle="tooltip" title="Income exceeds program threshold"></i>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <small class="text-muted">Income Source</small>
                                                <p><?php echo htmlspecialchars($user['income_source']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-card-checklist me-2"></i>Valid ID</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <img src="../User/<?php echo htmlspecialchars($user['valid_id_path']); ?>" class="img-fluid border" alt="Valid ID">
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-header bg-light">
                                        <h6 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Proof of Residency</h6>
                                    </div>
                                    <div class="card-body text-center">
                                        <img src="../User/<?php echo htmlspecialchars($user['proof_of_residency_path']); ?>" class="img-fluid border" alt="Proof of Residency">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <?php if($user['account_status'] === 'pending'): ?>
                        <form method="post" class="d-inline me-1">
                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle me-1"></i>Approve
                            </button>
                        </form>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                            <input type="hidden" name="action" value="reject">
                            <button type="submit" class="btn btn-danger">
                                <i class="bi bi-x-circle me-1"></i>Reject
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    
        
    <!-- Footer -->
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
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });

            // Initialize tab functionality
            const tabLinks = document.querySelectorAll('#verificationTabs .nav-link');
            tabLinks.forEach(link => {
                link.addEventListener('shown.bs.tab', function(event) {
                    // Clear search when switching tabs
                    document.getElementById('searchInput').value = '';
                    // Show all rows in the newly active tab
                    const targetPane = document.querySelector(event.target.dataset.bsTarget);
                    const table = targetPane.querySelector('table');
                    if (table) {
                        const rows = table.getElementsByTagName('tr');
                        for (let i = 1; i < rows.length; i++) {
                            rows[i].style.display = '';
                        }
                    }
                });
            });
        });
        
        // Enhanced search function that works with tabs
        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            
            // Get the currently active tab content
            const activeTabPane = document.querySelector('#verificationTabContent .tab-pane.active');
            if (!activeTabPane) return;
            
            const table = activeTabPane.querySelector('table');
            if (!table) return;
            
            const rows = table.getElementsByTagName('tr');
            let anyResultsFound = false;
            
            for (let i = 1; i < rows.length; i++) { 
                let found = false;
                const cells = rows[i].getElementsByTagName('td');
                
                for (let j = 0; j < cells.length; j++) {
                    const cellText = cells[j].textContent || cells[j].innerText;
                    
                    if (cellText.toUpperCase().indexOf(filter) > -1) {
                        found = true;
                        anyResultsFound = true;
                        break;
                    }
                }
                
                rows[i].style.display = found ? '' : 'none';
            }
            
            // Show "no results" message if needed
            const noResultsMessage = activeTabPane.querySelector('.no-results-message');
            if (noResultsMessage) {
                noResultsMessage.style.display = anyResultsFound ? 'none' : 'block';
            }
        }

        // Add event listener for Enter key in search input
        document.getElementById('searchInput')?.addEventListener('keyup', function(event) {
            if (event.key === 'Enter') {
                searchTable();
            }
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