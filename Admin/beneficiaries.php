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

// Get selected parent leader ID from URL parameter
$selected_parent_leader_id = isset($_GET['parent_leader_id']) ? intval($_GET['parent_leader_id']) : null;

// Handle search query
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination setup
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    $db = new Database();
    
    // Fetch current barangay details if a specific barangay is selected
    $current_barangay = null;
    if ($selected_barangay_id) {
        $barangayQuery = "SELECT name, captain_name FROM barangays WHERE barangay_id = ?";
        $current_barangay = $db->fetchOne($barangayQuery, [$selected_barangay_id]);
        
        // If barangay doesn't exist, reset to null
        if (!$current_barangay) {
            $selected_barangay_id = null;
            unset($_SESSION['default_barangay_id']);
        }
    }
    
    // Fetch current parent leader details if a specific parent leader is selected
    $current_parent_leader = null;
    if ($selected_parent_leader_id) {
        $parentLeaderQuery = "SELECT firstname, lastname, barangay FROM users WHERE user_id = ? AND role = 'resident'";
        $current_parent_leader = $db->fetchOne($parentLeaderQuery, [$selected_parent_leader_id]);
        
        // If parent leader doesn't exist, reset to null
        if (!$current_parent_leader) {
            $selected_parent_leader_id = null;
        }
    }
    
    // Build the base query conditions
    $whereConditions = ["1=1"]; // Start with a condition that's always true
    $queryParams = [];
    
    // Add barangay filter if selected
    if ($selected_barangay_id) {
        $whereConditions[] = "b.barangay_id = ?";
        $queryParams[] = $selected_barangay_id;
    }
    
    // Add parent leader filter if selected
    if ($selected_parent_leader_id) {
        $whereConditions[] = "b.parent_leader_id = ?";
        $queryParams[] = $selected_parent_leader_id;
    }
    
    // Add search filter if provided
    if (!empty($search)) {
        $whereConditions[] = "(b.firstname LIKE ? OR b.lastname LIKE ? OR bg.name LIKE ?)";
        $queryParams[] = "%$search%";
        $queryParams[] = "%$search%";
        $queryParams[] = "%$search%";
    }
    
    // Combine where conditions
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    // Count total beneficiaries for pagination
    $countQuery = "
        SELECT COUNT(*) as total 
        FROM beneficiaries b
        LEFT JOIN barangays bg ON b.barangay_id = bg.barangay_id
        $whereClause";
    
    $totalResult = $db->fetchOne($countQuery, $queryParams);
    $total_beneficiaries = $totalResult ? $totalResult['total'] : 0;
    $total_pages = ceil($total_beneficiaries / $per_page);
    
    // Ensure current page is within bounds
    $page = max(1, min($page, $total_pages));
    
    // Fetch beneficiaries with related information
    $beneficiariesQuery = "
        SELECT 
            b.beneficiary_id,
            b.user_id,
            b.firstname,
            b.lastname,
            b.age,
            b.year_level,
            b.phone_number,
            b.household_size,
            b.created_at,
            b.updated_at,
            bg.barangay_id,
            bg.name as barangay_name,
            u.firstname as parent_leader_firstname,
            u.lastname as parent_leader_lastname,
            u.user_id as parent_leader_id
        FROM beneficiaries b
        LEFT JOIN barangays bg ON b.barangay_id = bg.barangay_id
        LEFT JOIN users u ON b.parent_leader_id = u.user_id
        $whereClause
        ORDER BY b.lastname, b.firstname
        LIMIT ?, ?
    ";
    
    $paginationParams = array_merge($queryParams, [$offset, $per_page]);
    $beneficiaries = $db->fetchAll($beneficiariesQuery, $paginationParams);
    
    // Fetch all barangays for the filter dropdown
    $barangaysQuery = "
        SELECT 
            b.barangay_id, 
            b.name,
            COALESCE(b.captain_name, 'No Captain Assigned') as captain_name, 
            COUNT(DISTINCT bf.beneficiary_id) as beneficiaries_count
        FROM barangays b
        LEFT JOIN beneficiaries bf ON b.barangay_id = bf.barangay_id
        GROUP BY b.barangay_id
        ORDER BY beneficiaries_count DESC, b.name
    ";
    $barangays = $db->fetchAll($barangaysQuery);
    
    // Fetch all parent leaders for the filter dropdown
    $parentLeadersQuery = "
        SELECT 
            u.user_id,
            u.firstname,
            u.lastname,
            b.name as barangay_name,
            COUNT(bf.beneficiary_id) as beneficiaries_count
        FROM users u
        LEFT JOIN barangays b ON u.barangay = b.barangay_id
        LEFT JOIN beneficiaries bf ON u.user_id = bf.parent_leader_id
        WHERE u.role = 'resident' AND u.account_status = 'active'
        " . ($selected_barangay_id ? " AND u.barangay = " . intval($selected_barangay_id) : "") . "
        GROUP BY u.user_id
        ORDER BY beneficiaries_count DESC, u.lastname, u.firstname
    ";
    $parent_leaders = $db->fetchAll($parentLeadersQuery);
    
    // Note: Don't close the database connection here as we need it later in the page
    // We'll close it at the end of the script
    
} catch (Exception $e) {
    // Log the error
    error_log("Beneficiaries Error: " . $e->getMessage());
    
    // Set default values in case of errors
    $beneficiaries = [];
    $total_beneficiaries = 0;
    $total_pages = 0;
    $barangays = [];
    $parent_leaders = [];
    $current_barangay = null;
    $current_parent_leader = null;
    
    // Optional: Set a user-friendly error message
    $error_message = "An error occurred while processing your request. Please try again later.";
}

try {
    // Get pending verifications count
    $pendingVerificationsQuery = $selected_barangay_id 
        ? "SELECT COUNT(*) as pending FROM users WHERE account_status = 'pending' AND role = 'resident' AND barangay = ?"
        : "SELECT COUNT(*) as pending FROM users WHERE account_status = 'pending' AND role = 'resident'";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $result = $params ? $db->fetchOne($pendingVerificationsQuery, $params) : $db->fetchOne($pendingVerificationsQuery);
    $pending_verifications = $result ? $result['pending'] : 0;
    
    // Get upcoming events count
    $upcomingEventsQuery = $selected_barangay_id
        ? "SELECT COUNT(*) as upcoming FROM events WHERE event_date >= CURDATE() AND barangay_id = ?"
        : "SELECT COUNT(*) as upcoming FROM events WHERE event_date >= CURDATE()";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $result = $params ? $db->fetchOne($upcomingEventsQuery, $params) : $db->fetchOne($upcomingEventsQuery);
    $upcoming_events = $result ? $result['upcoming'] : 0;
    
    // Get unread messages count
    $unreadMessagesQuery = $selected_barangay_id
        ? "SELECT COUNT(*) as unread FROM messages WHERE status = 'unread' AND (sender_barangay_id = ? OR receiver_barangay_id = ?)"
        : "SELECT COUNT(*) as unread FROM messages WHERE status = 'unread'";
    $params = $selected_barangay_id ? [$selected_barangay_id, $selected_barangay_id] : [];
    $result = $params ? $db->fetchOne($unreadMessagesQuery, $params) : $db->fetchOne($unreadMessagesQuery);
    $unread_messages = $result ? $result['unread'] : 0;
} catch (Exception $e) {
    // Set defaults if there's a database error
    $pending_verifications = 0;
    $upcoming_events = 0;
    $unread_messages = 0;
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $selected_barangay_id && $current_barangay ? 'Barangay ' . htmlspecialchars($current_barangay['name']) . ' - ' : ''; ?><?php echo $selected_parent_leader_id && $current_parent_leader ? htmlspecialchars($current_parent_leader['firstname'] . ' ' . $current_parent_leader['lastname']) . '\'s ' : ''; ?>Beneficiaries</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        .beneficiary-card {
            transition: all 0.3s ease;
            border-left: 4px solid #198754;
        }
        
        .beneficiary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .search-form {
            max-width: 400px;
        }
        
        .filters-container {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .household-size {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #198754;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .creation-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .education-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 10px;
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        
        .filter-badges {
            margin-bottom: 15px;
        }
        
        .filter-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            margin-right: 5px;
            margin-bottom: 5px;
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
        }
        
        .filter-badge i {
            margin-left: 5px;
            cursor: pointer;
        }
        
        .filter-badge i:hover {
            color: #dc3545;
        }
    </style>
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
            <h1>4P's Profiling System - Beneficiaries</h1>
        </div>
    </header>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" href="admin_dashboard.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
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
                <a class="nav-link active <?php echo $current_page == 'beneficiaries' ? 'active' : ''; ?>" href="beneficiaries.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
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
                    <h4 class="alert-heading">Barangay <?php echo htmlspecialchars($current_barangay['name']); ?> Beneficiaries</h4>
                    <p class="mb-0">Barangay Captain: <?php echo htmlspecialchars($current_barangay['captain_name'] ?: 'Not Assigned'); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Parent Leader Header if specific parent leader is selected -->
        <?php if($selected_parent_leader_id && $current_parent_leader): ?>
        <div class="alert alert-primary mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-person-circle fs-1 me-3"></i>
                <div>
                    <h4 class="alert-heading">Parent Leader: <?php echo htmlspecialchars($current_parent_leader['firstname'] . ' ' . $current_parent_leader['lastname']); ?>'s Beneficiaries</h4>
                    <?php 
                    // If we have both barangay_id from current_parent_leader and barangay name from current_barangay
                    $parent_leader_barangay = '';
                    if (isset($current_parent_leader['barangay']) && !empty($current_parent_leader['barangay'])) {
                        // Try to get the barangay name
                        $plBarangayQuery = "SELECT name FROM barangays WHERE barangay_id = ?";
                        $plBarangay = $db->fetchOne($plBarangayQuery, [$current_parent_leader['barangay']]);
                        if ($plBarangay) {
                            $parent_leader_barangay = $plBarangay['name'];
                        }
                    }
                    ?>
                    <?php if (!empty($parent_leader_barangay)): ?>
                    <p class="mb-0">Barangay: <?php echo htmlspecialchars($parent_leader_barangay); ?></p>
                    <?php endif; ?>
                </div>
                <div class="ms-auto">
                    <a href="view_parent_leader.php?id=<?php echo $selected_parent_leader_id; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-outline-primary">
                        <i class="bi bi-person"></i> View Parent Leader
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people me-2"></i>Beneficiaries</h2>
        </div>
        
        <!-- Active Filters Display -->
        <?php if($selected_barangay_id || $selected_parent_leader_id || !empty($search)): ?>
        <div class="filter-badges mb-3">
            <span class="fw-bold me-2">Active Filters:</span>
            
            <?php if($selected_barangay_id && $current_barangay): ?>
            <span class="filter-badge">
                <i class="bi bi-geo-alt-fill me-1"></i>
                Barangay: <?php echo htmlspecialchars($current_barangay['name']); ?>
                <a href="?<?php echo $selected_parent_leader_id ? 'parent_leader_id='.$selected_parent_leader_id : ''; ?><?php echo !empty($search) ? ($selected_parent_leader_id ? '&' : '').'search='.$search : ''; ?>">
                    <i class="bi bi-x-circle"></i>
                </a>
            </span>
            <?php endif; ?>
            
            <?php if($selected_parent_leader_id && $current_parent_leader): ?>
            <span class="filter-badge">
                <i class="bi bi-person-fill me-1"></i>
                Parent Leader: <?php echo htmlspecialchars($current_parent_leader['firstname'] . ' ' . $current_parent_leader['lastname']); ?>
                <a href="?<?php echo $selected_barangay_id ? 'barangay_id='.$selected_barangay_id : ''; ?><?php echo !empty($search) ? ($selected_barangay_id ? '&' : '').'search='.$search : ''; ?>">
                    <i class="bi bi-x-circle"></i>
                </a>
            </span>
            <?php endif; ?>
            
            <?php if(!empty($search)): ?>
            <span class="filter-badge">
                <i class="bi bi-search me-1"></i>
                Search: "<?php echo htmlspecialchars($search); ?>"
                <a href="?<?php echo $selected_barangay_id ? 'barangay_id='.$selected_barangay_id : ''; ?><?php echo $selected_parent_leader_id ? ($selected_barangay_id ? '&' : '').'parent_leader_id='.$selected_parent_leader_id : ''; ?>">
                    <i class="bi bi-x-circle"></i>
                </a>
            </span>
            <?php endif; ?>
            
            <a href="beneficiaries.php" class="btn btn-sm btn-outline-secondary ms-2">
                <i class="bi bi-x-lg"></i> Clear All Filters
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Filters and Search -->
        <div class="filters-container">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <form method="GET" action="" class="search-form">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search by name or barangay..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <?php if($selected_barangay_id): ?>
                            <input type="hidden" name="barangay_id" value="<?php echo $selected_barangay_id; ?>">
                            <?php endif; ?>
                            <?php if($selected_parent_leader_id): ?>
                            <input type="hidden" name="parent_leader_id" value="<?php echo $selected_parent_leader_id; ?>">
                            <?php endif; ?>
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                            <?php if(!empty($search)): ?>
                            <a href="?<?php echo $selected_barangay_id ? 'barangay_id='.$selected_barangay_id : ''; ?><?php echo $selected_parent_leader_id ? ($selected_barangay_id ? '&' : '').'parent_leader_id='.$selected_parent_leader_id : ''; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <!--<div class="col-md-6 text-end">
                    <a href="export_beneficiaries.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?><?php echo $selected_parent_leader_id ? ($selected_barangay_id ? '&' : '?').'parent_leader_id='.$selected_parent_leader_id : ''; ?><?php echo !empty($search) ? (($selected_barangay_id || $selected_parent_leader_id) ? '&' : '?').'search='.$search : ''; ?>" class="btn btn-outline-success">
                        <i class="bi bi-file-excel me-1"></i> Export to Excel
                    </a>
                </div>-->
            </div>
        </div>
        
        <!-- Results summary -->
        <div class="mb-3">
            <p>
                Showing <strong><?php echo count($beneficiaries); ?></strong> of 
                <strong><?php echo $total_beneficiaries; ?></strong> beneficiaries
                <?php if($selected_barangay_id && $current_barangay): ?>
                in <strong>Barangay <?php echo htmlspecialchars($current_barangay['name']); ?></strong>
                <?php endif; ?>
                <?php if($selected_parent_leader_id && $current_parent_leader): ?>
                under <strong><?php echo htmlspecialchars($current_parent_leader['firstname'] . ' ' . $current_parent_leader['lastname']); ?></strong>
                <?php endif; ?>
                <?php if(!empty($search)): ?>
                matching "<strong><?php echo htmlspecialchars($search); ?></strong>"
                <?php endif; ?>
            </p>
        </div>
        
        <?php if(empty($beneficiaries)): ?>
        <!-- Empty state -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-people fs-1 text-muted mb-3"></i>
                <h3>No Beneficiaries Found</h3>
                <p class="text-muted">
                    There are currently no beneficiaries 
                    <?php if($selected_barangay_id && $current_barangay): ?>
                    in Barangay <?php echo htmlspecialchars($current_barangay['name']); ?>
                    <?php endif; ?>
                    <?php if($selected_parent_leader_id && $current_parent_leader): ?>
                    under Parent Leader <?php echo htmlspecialchars($current_parent_leader['firstname'] . ' ' . $current_parent_leader['lastname']); ?>
                    <?php endif; ?>
                    <?php if(!empty($search)): ?>
                    matching "<?php echo htmlspecialchars($search); ?>"
                    <?php endif; ?>.
                </p>
                <div class="mt-3">
                    <!--<a href="add_beneficiary.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?><?php echo $selected_parent_leader_id ? ($selected_barangay_id ? '&' : '?').'parent_leader_id='.$selected_parent_leader_id : ''; ?>" class="btn btn-success">
                        <i class="bi bi-person-plus"></i> Add New Beneficiary
                    </a>-->
                    <a href="beneficiaries.php" class="btn btn-outline-secondary ms-2">
                        <i class="bi bi-arrow-repeat"></i> Clear Filters
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Beneficiaries Grid -->
        <div class="row">
            <?php foreach($beneficiaries as $beneficiary): ?>
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card beneficiary-card h-100 position-relative">
                    <div class="household-size" title="Household size">
                        <?php echo $beneficiary['household_size']; ?>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo htmlspecialchars(ucwords($beneficiary['lastname'] . ', ' . $beneficiary['firstname'])); ?>
                        </h5>
                        <p class="card-text">
                            <i class="bi bi-calendar-fill me-1 text-muted"></i> Age: <?php echo htmlspecialchars($beneficiary['age']); ?><br>
                            <i class="bi bi-telephone-fill me-1 text-muted"></i> <?php echo htmlspecialchars($beneficiary['phone_number']); ?><br>
                            <i class="bi bi-geo-alt-fill me-1 text-muted"></i> <?php echo htmlspecialchars($beneficiary['barangay_name'] ?? 'No Barangay Assigned'); ?>
                        </p>
                        
                        <div class="education-badge">
                            <i class="bi bi-book-fill me-1"></i> <?php echo htmlspecialchars($beneficiary['year_level']); ?>
                        </div>
                        
                        <p class="mb-0">
                            <i class="bi bi-person-fill me-1 text-muted"></i> Parent Leader: 
                            <a href="?parent_leader_id=<?php echo $beneficiary['parent_leader_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($beneficiary['parent_leader_firstname'] . ' ' . $beneficiary['parent_leader_lastname']); ?>
                            </a>
                        </p>
                        
                        <p class="creation-date">
                            <i class="bi bi-clock-history me-1"></i> Added: 
                            <?php echo date('M d, Y', strtotime($beneficiary['created_at'])); ?>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0">
                        <!--<div class="d-flex justify-content-between">
                            <a href="view_beneficiary.php?id=<?php echo $beneficiary['beneficiary_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?><?php echo $selected_parent_leader_id ? '&parent_leader_id='.$selected_parent_leader_id : ''; ?>" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-eye"></i> View Details
                            </a>
                            <a href="edit_beneficiary.php?id=<?php echo $beneficiary['beneficiary_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?><?php echo $selected_parent_leader_id ? '&parent_leader_id='.$selected_parent_leader_id : ''; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-pencil"></i> Edit
                            </a>
                        </div>-->
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if($total_pages > 1): ?>
        <nav aria-label="Page navigation" class="mt-4">
            <ul class="pagination justify-content-center">
                <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?><?php echo $selected_parent_leader_id ? '&parent_leader_id='.$selected_parent_leader_id : ''; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>" aria-label="Previous">
                        <span aria-hidden="true">&laquo;</span>
                    </a>
                </li>
                
                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $start_page + 4);
                if ($end_page - $start_page < 4 && $total_pages > 5) {
                    $start_page = max(1, $end_page - 4);
                }
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                <li class="page-item <?php echo ($page == $i) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?><?php echo $selected_parent_leader_id ? '&parent_leader_id='.$selected_parent_leader_id : ''; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?><?php echo $selected_parent_leader_id ? '&parent_leader_id='.$selected_parent_leader_id : ''; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mt-4">
            <div class="col-md-4 mb-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Total Beneficiaries</h6>
                                <h2 class="mb-0"><?php echo $total_beneficiaries; ?></h2>
                            </div>
                            <i class="bi bi-people-fill fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php
            // Calculate average age
            $total_age = 0;
            $count = count($beneficiaries);
            if ($count > 0) {
                foreach ($beneficiaries as $beneficiary) {
                    $total_age += $beneficiary['age'];
                }
                $average_age = round($total_age / $count, 1);
            } else {
                $average_age = 0;
            }
            ?>
            
            <div class="col-md-4 mb-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Average Age</h6>
                                <h2 class="mb-0"><?php echo $average_age; ?> years</h2>
                            </div>
                            <i class="bi bi-calendar-fill fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php
            // Calculate total household members
            $total_household = 0;
            if ($count > 0) {
                foreach ($beneficiaries as $beneficiary) {
                    $total_household += $beneficiary['household_size'];
                }
            }
            ?>
            
            <div class="col-md-4 mb-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Total Household Members</h6>
                                <h2 class="mb-0"><?php echo $total_household; ?></h2>
                            </div>
                            <i class="bi bi-house-fill fs-1"></i>
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
    
    <?php
    if (isset($db) && $db instanceof Database) {
        $db->closeConnection();
    }
    ?>
</body>
</html>