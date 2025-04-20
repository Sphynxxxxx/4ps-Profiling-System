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

// Initialize variables
$error_message = '';
$success_message = '';
$members = [];
$total_members = 0;
$search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$barangay_filter = isset($_GET['barangay']) ? intval($_GET['barangay']) : 0;
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sort_dir = isset($_GET['dir']) ? $_GET['dir'] : 'desc';

// Get pagination parameters
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

try {
    $db = new Database();
    
    // Get user information and role
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
    
    // Fetch barangays for the filter dropdown
    $barangaysSql = "SELECT barangay_id, name FROM barangays ORDER BY name";
    $barangays = $db->fetchAll($barangaysSql);

    // Construct the base query
    $membersQuery = "SELECT u.*, b.name as barangay_name, 
                     (SELECT COUNT(*) FROM beneficiaries WHERE parent_leader_id = u.user_id) as beneficiary_count
                     FROM users u 
                     LEFT JOIN barangays b ON u.barangay = b.barangay_id 
                     WHERE 1=1";
    
    $countQuery = "SELECT COUNT(*) as total FROM users u 
                  LEFT JOIN barangays b ON u.barangay = b.barangay_id 
                  WHERE 1=1";
    
    $queryParams = [];
    
    // Add search filter if provided
    if (!empty($search_term)) {
        $search_condition = " AND (u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ? OR b.name LIKE ?)";
        $membersQuery .= $search_condition;
        $countQuery .= $search_condition;
        
        $search_param = '%' . $search_term . '%';
        $queryParams = array_merge($queryParams, [$search_param, $search_param, $search_param, $search_param]);
    }
    
    // Add status filter if provided
    if (!empty($status_filter)) {
        $membersQuery .= " AND u.account_status = ?";
        $countQuery .= " AND u.account_status = ?";
        $queryParams[] = $status_filter;
    }
    
    // Add barangay filter if provided
    if (!empty($barangay_filter)) {
        $membersQuery .= " AND u.barangay = ?";
        $countQuery .= " AND u.barangay = ?";
        $queryParams[] = $barangay_filter;
    }
    
    // For non-admin users, restrict to viewing only members in their barangay
    if ($userRole != 'admin' && $userRole != 'staff' && $userBarangayId) {
        $membersQuery .= " AND u.barangay = ?";
        $countQuery .= " AND u.barangay = ?";
        $queryParams[] = $userBarangayId;
    }
    
    // Add sorting
    $allowed_sort_fields = ['firstname', 'lastname', 'email', 'account_status', 'created_at', 'beneficiary_count'];
    $allowed_sort_directions = ['asc', 'desc'];
    
    if (in_array($sort_by, $allowed_sort_fields) && in_array($sort_dir, $allowed_sort_directions)) {
        $membersQuery .= " ORDER BY " . ($sort_by == 'beneficiary_count' ? 'beneficiary_count' : "u.$sort_by") . " $sort_dir";
    } else {
        $membersQuery .= " ORDER BY u.created_at DESC";
    }
    
    // Create a copy of queryParams for the count query before adding pagination params
    $countQueryParams = $queryParams;
    
    // Add pagination only to the membersQuery
    $membersQuery .= " LIMIT ? OFFSET ?";
    $queryParams[] = $per_page;
    $queryParams[] = $offset;
    
    // Execute queries with their respective parameters
    $total_members_result = $db->fetchOne($countQuery, $countQueryParams);
    $total_members = $total_members_result['total'] ?? 0;
    
    $members = $db->fetchAll($membersQuery, $queryParams);
    
    $total_pages = ceil($total_members / $per_page);
    
    $db->closeConnection();
} catch (Exception $e) {
    $error_message = "Error fetching members: " . $e->getMessage();
    error_log($error_message);
}

// Function to get pagination URL
function getPaginationUrl($page) {
    $params = $_GET;
    $params['page'] = $page;
    return '?' . http_build_query($params);
}

// Function to get sorting URL
function getSortingUrl($field) {
    $params = $_GET;
    if (isset($params['sort']) && $params['sort'] == $field) {
        $params['dir'] = isset($params['dir']) && $params['dir'] == 'asc' ? 'desc' : 'asc';
    } else {
        $params['sort'] = $field;
        $params['dir'] = 'asc';
    }
    return '?' . http_build_query($params);
}

// Status mapping for display
$status_labels = [
    'pending' => '<span class="badge bg-warning text-dark">Pending</span>',
    'active' => '<span class="badge bg-success">Active</span>',
    'suspended' => '<span class="badge bg-danger">Suspended</span>',
    'deactivated' => '<span class="badge bg-secondary">Deactivated</span>'
];

// Role mapping for display
$role_labels = [
    'resident' => '<span class="badge bg-primary">Resident</span>',
    'staff' => '<span class="badge bg-info">Staff</span>',
    'admin' => '<span class="badge bg-dark">Admin</span>'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members | DSWD 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        .member-table th {
            vertical-align: middle;
            white-space: nowrap;
        }
        
        .member-table td {
            vertical-align: middle;
        }
        
        .member-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }
        
        .sort-icon {
            display: inline-block;
            margin-left: 5px;
        }
        
        .filter-bar {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
        }
        
        .member-name {
            font-weight: 500;
        }
        
        .member-email {
            font-size: 0.85rem;
            color: #6c757d;
        }
        
        .beneficiary-count {
            font-weight: 500;
            color: #28a745;
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
                <div class="dropdown">
                    <a class="text-white dropdown-toggle text-decoration-none" href="#" role="button" id="userMenu" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle fs-5 me-1"></i>
                        <span class="d-none d-md-inline-block"><?php echo $user['firstname']; ?></span>
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
                        <a class="nav-link" href="activities.php">
                            <i class="bi bi-calendar2-check"></i> Activities
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="members.php">
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
                        <a class="nav-link" href="settings.php">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </li>
                </ul>
            </div>

            <!-- Main Content -->
            <div class="main-content">
                <div class="container-fluid pb-5">
                    <!-- Page Title and Breadcrumb -->
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-3">
                        <div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-1">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Members</li>
                                </ol>
                            </nav>
                            <h1 class="h3 mb-0 text-gray-800">
                                <i class="bi bi-people me-2"></i> Members
                            </h1>
                        </div>
                        
                        <?php if ($userRole == 'admin' || $userRole == 'staff'): ?>
                        <div class="mt-2 mt-md-0">
                            <a href="control/register_beneficiary.php" class="btn btn-primary">
                                <i class="bi bi-person-plus me-1"></i> Add New Member
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Filters and Search Bar -->
                    <div class="filter-bar">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-lg-4 col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" placeholder="Search by name" name="search" value="<?php echo htmlspecialchars($search_term); ?>">
                                </div>
                            </div>
                            
                            <!--<div class="col-lg-3 col-md-6">
                                <select class="form-select" name="status">
                                    <option value="">All Statuses</option>
                                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="suspended" <?php echo $status_filter == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                    <option value="deactivated" <?php echo $status_filter == 'deactivated' ? 'selected' : ''; ?>>Deactivated</option>
                                </select>
                            </div>-->
                            
                            <?php if ($userRole == 'admin' || $userRole == 'staff'): ?>
                                <div class="col-lg-3 col-md-6">
                                    <select class="form-select" name="barangay">
                                        <option value="">All Barangays</option>
                                        <?php foreach ($barangays as $barangay): ?>
                                            <option value="<?php echo $barangay['barangay_id']; ?>" <?php echo $barangay_filter == $barangay['barangay_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($barangay['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            <?php else: ?>
                                <!-- For non-admin/staff users, use a hidden input to maintain the filter -->
                                <input type="hidden" name="barangay" value="<?php echo $userBarangayId; ?>">
                            <?php endif; ?>
                            
                            <div class="col-lg-2 col-md-6">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-filter me-1"></i> Apply Filters
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Members Table -->
                    <div class="card">
                        <div class="card-header bg-white">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Member List</h5>
                                <span class="text-muted">
                                    Showing <?php echo min(($page - 1) * $per_page + 1, $total_members); ?> - 
                                    <?php echo min($page * $per_page, $total_members); ?> 
                                    of <?php echo $total_members; ?> members
                                </span>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover member-table mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>
                                                <a href="<?php echo getSortingUrl('firstname'); ?>" class="text-dark text-decoration-none">
                                                    Name
                                                    <?php if ($sort_by == 'firstname'): ?>
                                                        <span class="sort-icon">
                                                            <i class="bi bi-arrow-<?php echo $sort_dir == 'asc' ? 'up' : 'down'; ?>"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortingUrl('email'); ?>" class="text-dark text-decoration-none">
                                                    Email
                                                    <?php if ($sort_by == 'email'): ?>
                                                        <span class="sort-icon">
                                                            <i class="bi bi-arrow-<?php echo $sort_dir == 'asc' ? 'up' : 'down'; ?>"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>Barangay</th>
                                            <th>Role</th>
                                            <th>
                                                <a href="<?php echo getSortingUrl('account_status'); ?>" class="text-dark text-decoration-none">
                                                    Status
                                                    <?php if ($sort_by == 'account_status'): ?>
                                                        <span class="sort-icon">
                                                            <i class="bi bi-arrow-<?php echo $sort_dir == 'asc' ? 'up' : 'down'; ?>"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortingUrl('beneficiary_count'); ?>" class="text-dark text-decoration-none">
                                                    Beneficiaries
                                                    <?php if ($sort_by == 'beneficiary_count'): ?>
                                                        <span class="sort-icon">
                                                            <i class="bi bi-arrow-<?php echo $sort_dir == 'asc' ? 'up' : 'down'; ?>"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                            <th>
                                                <a href="<?php echo getSortingUrl('created_at'); ?>" class="text-dark text-decoration-none">
                                                    Joined Date
                                                    <?php if ($sort_by == 'created_at'): ?>
                                                        <span class="sort-icon">
                                                            <i class="bi bi-arrow-<?php echo $sort_dir == 'asc' ? 'up' : 'down'; ?>"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </a>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($members)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-5">
                                                <div class="text-muted">
                                                    <i class="bi bi-people fs-1 d-block mb-3"></i>
                                                    No members found
                                                </div>
                                            </td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($members as $member): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?php echo !empty($member['profile_image']) ? htmlspecialchars($member['profile_image']) : 'assets/images/profile-placeholder.png'; ?>" 
                                                             alt="Profile" class="member-avatar me-2">
                                                        <div>
                                                            <div class="member-name"><?php echo htmlspecialchars($member['firstname'] . ' ' . $member['lastname']); ?></div>
                                                            <div class="member-email"><?php echo htmlspecialchars($member['phone_number']); ?></div>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td><?php echo htmlspecialchars($member['email']); ?></td>
                                                <td><?php echo htmlspecialchars($member['barangay_name'] ?? 'Not assigned'); ?></td>
                                                <td><?php echo $role_labels[$member['role']] ?? 'Unknown'; ?></td>
                                                <td><?php echo $status_labels[$member['account_status']] ?? 'Unknown'; ?></td>
                                                <td>
                                                    <span class="beneficiary-count">
                                                        <?php echo $member['beneficiary_count']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo date('M d, Y', strtotime($member['created_at'])); ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                        <div class="card-footer bg-white">
                            <nav aria-label="Page navigation">
                                <ul class="pagination justify-content-center mb-0">
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo getPaginationUrl(1); ?>" aria-label="First">
                                            <span aria-hidden="true"><i class="bi bi-chevron-double-left"></i></span>
                                        </a>
                                    </li>
                                    <li class="page-item <?php echo ($page <= 1) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo getPaginationUrl($page - 1); ?>" aria-label="Previous">
                                            <span aria-hidden="true"><i class="bi bi-chevron-left"></i></span>
                                        </a>
                                    </li>
                                    
                                    <?php
                                    $start_page = max(1, min($page - 2, $total_pages - 4));
                                    $end_page = min($total_pages, max($page + 2, 5));
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++):
                                    ?>
                                    <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                                        <a class="page-link" href="<?php echo getPaginationUrl($i); ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo getPaginationUrl($page + 1); ?>" aria-label="Next">
                                            <span aria-hidden="true"><i class="bi bi-chevron-right"></i></span>
                                        </a>
                                    </li>
                                    <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="<?php echo getPaginationUrl($total_pages); ?>" aria-label="Last">
                                            <span aria-hidden="true"><i class="bi bi-chevron-double-right"></i></span>
                                        </a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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