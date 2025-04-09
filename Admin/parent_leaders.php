<?php
session_start();
require_once "../backend/connections/config.php";
require_once "../backend/connections/database.php";

// Get the selected barangay ID from URL parameter or session
$default_barangay_id = isset($_SESSION['default_barangay_id']) ? intval($_SESSION['default_barangay_id']) : null;
$selected_barangay_id = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : $default_barangay_id;

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
    
    // Build the base query conditions
    $whereConditions = ["u.account_status = 'active' AND u.role = 'resident'"]; // Added role filter
    $queryParams = [];
    
    // Add barangay filter if selected
    if ($selected_barangay_id) {
        $whereConditions[] = "u.barangay = ?";
        $queryParams[] = $selected_barangay_id;
    }
    
    // Add search filter if provided
    if (!empty($search)) {
        $whereConditions[] = "(u.firstname LIKE ? OR u.lastname LIKE ? OR u.email LIKE ?)";
        $queryParams[] = "%$search%";
        $queryParams[] = "%$search%";
        $queryParams[] = "%$search%";
    }
    
    // Combine where conditions
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
    
    // Count total parent leaders for pagination
    $countQuery = "SELECT COUNT(*) as total FROM users u $whereClause";
    
    $totalResult = $db->fetchOne($countQuery, $queryParams);
    $total_parent_leaders = $totalResult ? $totalResult['total'] : 0;
    $total_pages = ceil($total_parent_leaders / $per_page);
    
    // Ensure current page is within bounds
    $page = max(1, min($page, $total_pages));
    
    // Fetch parent leaders with beneficiary counts and approval info
    $parentLeadersQuery = "
        SELECT 
            u.user_id,
            u.firstname,
            u.lastname,
            u.email,
            u.phone_number,
            u.created_at,
            u.updated_at,
            u.last_login,
            u.barangay,
            u.account_status,
            b.name as barangay_name,
            COUNT(ben.beneficiary_id) as beneficiary_count,
            (SELECT description FROM activity_logs 
             WHERE activity_type = 'verification' 
             AND description LIKE CONCAT('%Approved user ID ', u.user_id, '%') 
             ORDER BY created_at DESC LIMIT 1) as approval_info,
            (SELECT created_at FROM activity_logs 
             WHERE activity_type = 'verification' 
             AND description LIKE CONCAT('%Approved user ID ', u.user_id, '%') 
             ORDER BY created_at DESC LIMIT 1) as approval_date
        FROM users u
        LEFT JOIN barangays b ON u.barangay = b.barangay_id
        LEFT JOIN beneficiaries ben ON u.user_id = ben.parent_leader_id
        $whereClause
        GROUP BY u.user_id
        ORDER BY u.lastname, u.firstname
        LIMIT ?, ?
    ";
    
    $paginationParams = array_merge($queryParams, [$offset, $per_page]);
    $parent_leaders = $db->fetchAll($parentLeadersQuery, $paginationParams);
    
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
        ORDER BY users_count DESC, b.name
    ";
    $barangays = $db->fetchAll($barangaysQuery);
    
    // Close database connection
    $db->closeConnection();
    
} catch (Exception $e) {
    // Log the error
    error_log("Parent Leaders Error: " . $e->getMessage());
    
    // Set default values in case of errors
    $parent_leaders = [];
    $total_parent_leaders = 0;
    $total_pages = 0;
    $barangays = [];
    $current_barangay = null;
    
    // Optional: Set a user-friendly error message
    $error_message = "An error occurred while processing your request. Please try again later.";
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $selected_barangay_id && $current_barangay ? 'Barangay ' . htmlspecialchars($current_barangay['name']) . ' - ' : ''; ?>Parent Leaders</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        .leader-card {
            transition: all 0.3s ease;
            border-left: 4px solid #0d6efd;
        }
        
        .leader-card:hover {
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
        
        .beneficiary-count {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #0d6efd;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .last-login {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .status-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 500;
            margin-bottom: 10px;
        }
        
        .status-active {
            background-color: #d1e7dd;
            color: #0f5132;
            border: 1px solid #badbcc;
        }
        
        .status-pending {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .status-suspended {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .status-deactivated {
            background-color: #e2e3e5;
            color: #383d41;
            border: 1px solid #d6d8db;
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
            <h1>4P's Profiling System - Parent Leaders</h1>
        </div>
    </header>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="admin_dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="participant_verification.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-person-check"></i> Parent Leader Verification
                    <?php if(isset($pending_verifications) && $pending_verifications > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $pending_verifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="parent_leaders.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-people"></i> List of Parent Leaders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="beneficiaries.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-people"></i> Beneficiaries
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="calendar.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-calendar3"></i> Calendar
                    <?php if(isset($upcoming_events) && $upcoming_events > 0): ?>
                    <span class="badge bg-primary rounded-pill ms-auto"><?php echo $upcoming_events; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="messages.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-chat-left-text"></i> Messages
                    <?php if(isset($unread_messages) && $unread_messages > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="reports.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php">
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
                        (<?php echo $barangay['users_count']; ?> Parent Leaders)
                    </option>
                    <?php endforeach; ?>
                </select>
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
                    <h4 class="alert-heading">Barangay <?php echo htmlspecialchars($current_barangay['name']); ?> Parent Leaders</h4>
                    <p class="mb-0">Barangay Captain: <?php echo htmlspecialchars($current_barangay['captain_name'] ?: 'Not Assigned'); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="bi bi-people me-2"></i>Parent Leaders</h2>
            <a href="participant_verification.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-primary">
                <i class="bi bi-person-plus"></i> Verify New Leaders
            </a>
        </div>
        
        <!-- Filters and Search -->
        <div class="filters-container">
            <div class="row align-items-end">
                <div class="col-md-6">
                    <form method="GET" action="" class="search-form">
                        <div class="input-group">
                            <input type="text" class="form-control" placeholder="Search by name or email..." name="search" value="<?php echo htmlspecialchars($search); ?>">
                            <?php if($selected_barangay_id): ?>
                            <input type="hidden" name="barangay_id" value="<?php echo $selected_barangay_id; ?>">
                            <?php endif; ?>
                            <button class="btn btn-outline-primary" type="submit">
                                <i class="bi bi-search"></i>
                            </button>
                            <?php if(!empty($search)): ?>
                            <a href="?<?php echo $selected_barangay_id ? 'barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-outline-secondary">
                                <i class="bi bi-x-lg"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div class="col-md-6 text-end">
                    <a href="export_parent_leaders.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>" class="btn btn-outline-success">
                        <i class="bi bi-file-excel me-1"></i> Export to Excel
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Results summary -->
        <div class="mb-3">
            <p>
                Showing <strong><?php echo count($parent_leaders); ?></strong> of 
                <strong><?php echo $total_parent_leaders; ?></strong> parent leaders
                <?php if($selected_barangay_id && $current_barangay): ?>
                in <strong>Barangay <?php echo htmlspecialchars($current_barangay['name']); ?></strong>
                <?php endif; ?>
                <?php if(!empty($search)): ?>
                matching "<strong><?php echo htmlspecialchars($search); ?></strong>"
                <?php endif; ?>
            </p>
        </div>
        
        <?php if(empty($parent_leaders)): ?>
        <!-- Empty state -->
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-people fs-1 text-muted mb-3"></i>
                <h3>No Parent Leaders Found</h3>
                <p class="text-muted">There are currently no parent leaders registered in the system<?php echo $selected_barangay_id && $current_barangay ? ' for Barangay ' . htmlspecialchars($current_barangay['name']) : ''; ?>.</p>
                <div class="mt-3">
                    <a href="participant_verification.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-primary">
                        <i class="bi bi-person-check"></i> Verify Parent Leaders
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <!-- Parent Leaders Grid -->
        <div class="row">
            <?php foreach($parent_leaders as $leader): ?>
            <div class="col-md-4 col-lg-3 mb-4">
                <div class="card leader-card h-100 position-relative">
                    <div class="beneficiary-count" title="Number of beneficiaries under this parent leader">
                        <?php echo $leader['beneficiary_count']; ?>
                    </div>
                    <div class="card-body">
                        <h5 class="card-title">
                            <?php echo htmlspecialchars(ucwords($leader['lastname'] . ', ' . $leader['firstname'])); ?>
                        </h5>
                        <p class="card-text">
                            <i class="bi bi-envelope-fill me-1 text-muted"></i> <?php echo htmlspecialchars($leader['email']); ?><br>
                            <i class="bi bi-telephone-fill me-1 text-muted"></i> <?php echo htmlspecialchars($leader['phone_number']); ?><br>
                            <i class="bi bi-geo-alt-fill me-1 text-muted"></i> <?php echo htmlspecialchars($leader['barangay_name'] ?? 'No Barangay Assigned'); ?>
                        </p>
                        
                        <?php
                        // Status badge with different styles based on account_status
                        $statusClass = '';
                        $statusIcon = '';
                        
                        switch($leader['account_status']) {
                            case 'active':
                                $statusClass = 'status-active';
                                $statusIcon = 'bi-check-circle-fill';
                                $statusText = 'Approved';
                                break;
                            case 'pending':
                                $statusClass = 'status-pending';
                                $statusIcon = 'bi-hourglass-split';
                                $statusText = 'Pending Approval';
                                break;
                            case 'suspended':
                                $statusClass = 'status-suspended';
                                $statusIcon = 'bi-exclamation-triangle-fill';
                                $statusText = 'Suspended';
                                break;
                            case 'deactivated':
                                $statusClass = 'status-deactivated';
                                $statusIcon = 'bi-x-circle-fill';
                                $statusText = 'Deactivated';
                                break;
                            default:
                                $statusClass = 'status-pending';
                                $statusIcon = 'bi-question-circle-fill';
                                $statusText = 'Unknown Status';
                        }
                        ?>
                        
                        <div class="status-badge <?php echo $statusClass; ?>">
                            <i class="bi <?php echo $statusIcon; ?> me-1"></i> <?php echo $statusText; ?>
                            <?php if($leader['account_status'] == 'active' && $leader['approval_date']): ?>
                            <br><small>on <?php echo date('M d, Y', strtotime($leader['approval_date'])); ?></small>
                            <?php endif; ?>
                        </div>
                        
                        <p class="last-login">
                            <i class="bi bi-clock-history me-1"></i> Last login: 
                            <?php echo $leader['last_login'] ? date('M d, Y, g:i a', strtotime($leader['last_login'])) : 'Never'; ?>
                        </p>
                    </div>
                    <div class="card-footer bg-transparent border-top-0">
                        <div class="d-flex justify-content-between">
                            <a href="view_parent_leader.php?id=<?php echo $leader['user_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye"></i> View Details
                            </a>
                            <a href="beneficiaries.php?parent_leader_id=<?php echo $leader['user_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-people"></i> Beneficiaries
                            </a>
                        </div>
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
                    <a class="page-link" href="?page=<?php echo $page-1; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>" aria-label="Previous">
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
                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $page+1; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?><?php echo !empty($search) ? '&search='.$search : ''; ?>" aria-label="Next">
                        <span aria-hidden="true">&raquo;</span>
                    </a>
                </li>
            </ul>
        </nav>
        <?php endif; ?>
        <?php endif; ?>
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
</html>`