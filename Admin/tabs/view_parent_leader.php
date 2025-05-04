<?php
session_start();
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";

// Get parent leader ID from URL parameter
$parent_leader_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Get the selected barangay ID from URL parameter or session
$default_barangay_id = isset($_SESSION['default_barangay_id']) ? intval($_SESSION['default_barangay_id']) : null;
$selected_barangay_id = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : $default_barangay_id;

if ($parent_leader_id <= 0) {
    // Invalid parent leader ID
    header("Location: parent_leaders.php" . ($selected_barangay_id ? "?barangay_id=$selected_barangay_id" : ""));
    exit();
}

try {
    $db = new Database();
    
    $current_barangay = null;
    if ($selected_barangay_id) {
        $barangayQuery = "SELECT name, captain_name FROM barangays WHERE barangay_id = ?";
        $current_barangay = $db->fetchOne($barangayQuery, [$selected_barangay_id]);
        
        if (!$current_barangay) {
            $selected_barangay_id = null;
            unset($_SESSION['default_barangay_id']);
        }
    }
    
    // Fetch parent leader details
    $parent_leader_query = "
        SELECT 
            u.user_id,
            u.firstname,
            u.lastname,
            u.email,
            u.phone_number,
            u.date_of_birth,
            u.gender,
            u.civil_status,
            u.region,
            u.province,
            u.city,
            u.household_members,
            u.dependants,
            u.family_head,
            u.occupation,
            u.household_income,
            u.income_source,
            u.created_at,
            u.updated_at,
            u.last_login,
            u.account_status,
            u.profile_image,
            u.valid_id_path,
            u.proof_of_residency_path,
            b.name as barangay_name,
            b.barangay_id,
            b.captain_name as barangay_captain,
            COUNT(DISTINCT ben.beneficiary_id) as beneficiaries_count,
            (SELECT MAX(created_at) FROM activity_logs WHERE activity_type = 'verification' AND description LIKE CONCAT('%', u.user_id, '%')) as verification_date,
            (SELECT description FROM activity_logs WHERE activity_type = 'verification' AND description LIKE CONCAT('%', u.user_id, '%') ORDER BY created_at DESC LIMIT 1) as verification_details
        FROM users u
        LEFT JOIN barangays b ON u.barangay = b.barangay_id
        LEFT JOIN beneficiaries ben ON u.user_id = ben.parent_leader_id
        WHERE u.user_id = ? AND u.role = 'resident'
        GROUP BY u.user_id
    ";
    
    $parent_leader = $db->fetchOne($parent_leader_query, [$parent_leader_id]);
    
    if (!$parent_leader) {
        // Parent leader not found
        header("Location: parent_leaders.php" . ($selected_barangay_id ? "?barangay_id=$selected_barangay_id" : ""));
        exit();
    }
    
    // Fetch beneficiaries under this parent leader
    $beneficiaries_query = "
        SELECT 
            b.beneficiary_id,
            b.firstname,
            b.lastname,
            b.age,
            b.year_level,
            b.phone_number,
            b.household_size,
            b.created_at,
            bg.name as barangay_name
        FROM beneficiaries b
        LEFT JOIN barangays bg ON b.barangay_id = bg.barangay_id
        WHERE b.parent_leader_id = ?
        ORDER BY b.lastname, b.firstname
        LIMIT 10
    ";
    
    $recent_beneficiaries = $db->fetchAll($beneficiaries_query, [$parent_leader_id]);
    
    // Fetch activity logs related to this parent leader
    $activity_logs_query = "
        SELECT 
            log_id,
            activity_type,
            description,
            created_at
        FROM activity_logs
        WHERE description LIKE CONCAT('%', ?, '%')
        ORDER BY created_at DESC
        LIMIT 15
    ";
    
    $activity_logs = $db->fetchAll($activity_logs_query, [$parent_leader_id]);
    
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
    
    // Note: Don't close the database connection here as we might need it later
    
} catch (Exception $e) {
    // Log the error
    error_log("View Parent Leader Error: " . $e->getMessage());
    
    // Set a user-friendly error message
    $error_message = "An error occurred while processing your request. Please try again later.";
    
    // We'll handle this in the HTML below
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Parent Leader: <?php echo isset($parent_leader) ? htmlspecialchars($parent_leader['firstname'] . ' ' . $parent_leader['lastname']) : 'Not Found'; ?> - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../css/admin.css" rel="stylesheet">
    <style>
        .profile-header {
            position: relative;
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .profile-cover {
            height: 180px;
            background: linear-gradient(135deg, #0056b3 0%, #0033a0 100%);
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            border: 5px solid white;
            background-color: #e9ecef;
            position: absolute;
            left: 50px;
            top: 100px;
            overflow: hidden;
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar .default-avatar {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            font-size: 64px;
            color: #6c757d;
        }
        
        .profile-details {
            padding-left: 220px;
            padding-bottom: 1.5rem;
            padding-top: 1.5rem;
        }
        
        .profile-actions {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        
        .info-card {
            margin-bottom: 1.5rem;
            border-left: 4px solid #0d6efd;
            transition: all 0.3s ease;
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
        }
        
        .beneficiary-card {
            transition: all 0.3s ease;
            border-left: 4px solid #198754;
        }
        
        .beneficiary-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
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
        
        .activity-log {
            position: relative;
            padding-left: 30px;
            padding-bottom: 20px;
            border-left: 2px solid #dee2e6;
            margin-left: 15px;
        }
        
        .activity-log:last-child {
            border-left: 2px solid transparent;
        }
        
        .activity-log::before {
            content: "";
            position: absolute;
            left: -10px;
            top: 0;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: #fff;
            border: 2px solid #0d6efd;
        }
        
        .activity-log.verification::before {
            background-color: #28a745;
            border-color: #28a745;
        }
        
        .activity-log.rejection::before {
            background-color: #dc3545;
            border-color: #dc3545;
        }
        
        .activity-log.beneficiary::before {
            background-color: #fd7e14;
            border-color: #fd7e14;
        }
        
        .activity-log.login::before {
            background-color: #17a2b8;
            border-color: #17a2b8;
        }
        
        .activity-log.deletion::before {
            background-color: #dc3545;
            border-color: #dc3545;
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
        
        @media (max-width: 767px) {
            .profile-avatar {
                left: 50%;
                transform: translateX(-50%);
                top: 80px;
                width: 120px;
                height: 120px;
            }
            
            .profile-details {
                padding-left: 1rem;
                padding-right: 1rem;
                padding-top: 80px;
                text-align: center;
            }
            
            .profile-actions {
                position: relative;
                top: auto;
                right: auto;
                display: flex;
                justify-content: center;
                margin-top: 1rem;
            }
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
            <img src="../../User/assets/pngwing.com (7).png" alt="DSWD Logo">
            <h1>4P's Profiling System - View Parent Leader</h1>
        </div>
    </header>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="../admin_dashboard.php">
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
                <a class="nav-link" href="../beneficiaries.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?><?php echo isset($parent_leader) ? '&parent_leader_id='.$parent_leader['user_id'] : ''; ?>">
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
                    <option value="">All Barangays</option>
                    <?php foreach($barangays as $barangay): ?>
                    <option value="<?php echo $barangay['barangay_id']; ?>" 
                        <?php echo (($selected_barangay_id == $barangay['barangay_id']) ? 'selected' : ''); ?>>
                        <?php echo htmlspecialchars($barangay['name']); ?> 
                        (<?php echo $barangay['users_count']; ?> Parent Leaders)
                    </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="id" value="<?php echo $parent_leader_id; ?>">
            </form>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $error_message; ?>
        </div>
        <?php elseif (isset($parent_leader)): ?>
        
        <!-- Navigation Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="admin_dashboard.php">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="parent_leaders.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">Parent Leaders</a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo htmlspecialchars($parent_leader['firstname'] . ' ' . $parent_leader['lastname']); ?></li>
            </ol>
        </nav>
        
        <!-- Profile Header Section -->
        <div class="profile-header">
            <div class="profile-cover"></div>
            <div class="profile-avatar">
                <?php if (!empty($parent_leader['profile_image']) && file_exists($parent_leader['profile_image'])): ?>
                <img src="<?php echo $parent_leader['profile_image']; ?>" alt="Profile Photo">
                <?php else: ?>
                <div class="default-avatar">
                    <i class="bi bi-person"></i>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="profile-details">
                <h2><?php echo htmlspecialchars($parent_leader['firstname'] . ' ' . $parent_leader['lastname']); ?></h2>
                
                <div class="status-badge 
                    <?php 
                    switch($parent_leader['account_status']) {
                        case 'active': echo 'status-active'; break;
                        case 'pending': echo 'status-pending'; break;
                        case 'suspended': echo 'status-suspended'; break;
                        case 'deactivated': echo 'status-deactivated'; break;
                        default: echo 'status-pending';
                    }
                    ?>">
                    <i class="bi 
                    <?php 
                    switch($parent_leader['account_status']) {
                        case 'active': echo 'bi-check-circle-fill'; break;
                        case 'pending': echo 'bi-hourglass-split'; break;
                        case 'suspended': echo 'bi-exclamation-triangle-fill'; break;
                        case 'deactivated': echo 'bi-x-circle-fill'; break;
                        default: echo 'bi-question-circle-fill';
                    }
                    ?> me-1"></i> 
                    <?php 
                    switch($parent_leader['account_status']) {
                        case 'active': echo 'Approved'; break;
                        case 'pending': echo 'Pending Verification'; break;
                        case 'suspended': echo 'Suspended'; break;
                        case 'deactivated': echo 'Deactivated'; break;
                        default: echo 'Unknown Status';
                    }
                    ?>
                </div>
                
                <div class="profile-meta d-flex flex-wrap mt-2">
                    <div class="me-4 mb-2">
                        <i class="bi bi-envelope me-1 text-muted"></i> <?php echo htmlspecialchars($parent_leader['email']); ?>
                    </div>
                    <div class="me-4 mb-2">
                        <i class="bi bi-telephone me-1 text-muted"></i> <?php echo htmlspecialchars($parent_leader['phone_number']); ?>
                    </div>
                    <div class="me-4 mb-2">
                        <i class="bi bi-geo-alt me-1 text-muted"></i> <?php echo htmlspecialchars($parent_leader['barangay_name'] ?? 'No Barangay Assigned'); ?>
                    </div>
                    <div class="me-4 mb-2">
                        <i class="bi bi-people me-1 text-muted"></i> <?php echo $parent_leader['beneficiaries_count']; ?> Beneficiaries
                    </div>
                </div>
            </div>
            
            <div class="profile-actions">
                <div class="btn-group">
                    <a href="edit_parent_leader.php?id=<?php echo $parent_leader['user_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Edit
                    </a>
                    <button type="button" class="btn btn-primary dropdown-toggle dropdown-toggle-split" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="send_message.php?recipient=<?php echo $parent_leader['user_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>">
                                <i class="bi bi-chat-dots me-2"></i> Send Message
                            </a>
                        </li>
                        <?php if ($parent_leader['account_status'] === 'active'): ?>
                        <li>
                            <a class="dropdown-item text-warning" href="change_status.php?id=<?php echo $parent_leader['user_id']; ?>&status=suspended<?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" onclick="return confirm('Are you sure you want to suspend this parent leader?');">
                                <i class="bi bi-pause-circle me-2"></i> Suspend Account
                            </a>
                        </li>
                        <?php elseif ($parent_leader['account_status'] === 'suspended'): ?>
                        <li>
                            <a class="dropdown-item text-success" href="change_status.php?id=<?php echo $parent_leader['user_id']; ?>&status=active<?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>">
                                <i class="bi bi-play-circle me-2"></i> Reactivate Account
                            </a>
                        </li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="delete_parent_leader.php?id=<?php echo $parent_leader['user_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" onclick="return confirm('Are you sure you want to delete this parent leader? This action cannot be undone.');">
                                <i class="bi bi-trash me-2"></i> Delete Parent Leader
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Personal Information Column -->
            <div class="col-lg-4">
                <!-- Personal Information Card -->
                <div class="card info-card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Personal Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th scope="row" width="40%">Full Name</th>
                                <td><?php echo htmlspecialchars($parent_leader['firstname'] . ' ' . $parent_leader['lastname']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Date of Birth</th>
                                <td><?php echo date('F d, Y', strtotime($parent_leader['date_of_birth'])); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Age</th>
                                <td>
                                    <?php 
                                    $birth_date = new DateTime($parent_leader['date_of_birth']);
                                    $today = new DateTime();
                                    $age = $birth_date->diff($today)->y;
                                    echo $age . ' years';
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Gender</th>
                                <td><?php echo ucfirst(strtolower($parent_leader['gender'])); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Civil Status</th>
                                <td><?php echo ucfirst(strtolower($parent_leader['civil_status'])); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Occupation</th>
                                <td><?php echo htmlspecialchars($parent_leader['occupation']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Contact Information Card -->
                <div class="card info-card mb-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="bi bi-telephone-fill me-2"></i>Contact Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th scope="row" width="40%">Email</th>
                                <td><?php echo htmlspecialchars($parent_leader['email']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Phone</th>
                                <td><?php echo htmlspecialchars($parent_leader['phone_number']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Barangay</th>
                                <td><?php echo htmlspecialchars($parent_leader['barangay_name'] ?? 'Not Assigned'); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">City</th>
                                <td><?php echo htmlspecialchars($parent_leader['city']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Province</th>
                                <td><?php echo htmlspecialchars($parent_leader['province']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Region</th>
                                <td><?php echo htmlspecialchars($parent_leader['region']); ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Family Information Card -->
                <div class="card info-card mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Family Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th scope="row" width="40%">Family Head</th>
                                <td><?php echo htmlspecialchars($parent_leader['family_head']); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Household Members</th>
                                <td><?php echo $parent_leader['household_members']; ?> people</td>
                            </tr>
                            <tr>
                                <th scope="row">Dependants</th>
                                <td><?php echo $parent_leader['dependants']; ?> people</td>
                            </tr>
                            <tr>
                                <th scope="row">Monthly Income</th>
                                <td>₱<?php echo number_format($parent_leader['household_income'], 2); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Income Source</th>
                                <td>
                                    <?php 
                                    $income_source = $parent_leader['income_source'];
                                    
                                    // Format income source for display
                                    switch($income_source) {
                                        case 'EMPLOYMENT':
                                            echo 'Employment';
                                            break;
                                        case 'SMALL_BUSINESS':
                                            echo 'Small Business';
                                            break;
                                        case 'GOVERNMENT_ASSISTANCE':
                                            echo 'Government Assistance';
                                            break;
                                        default:
                                            if (strpos($income_source, 'OTHERS:') !== false) {
                                                echo substr($income_source, 8); // Remove "OTHERS: " prefix
                                            } else {
                                                echo htmlspecialchars($income_source);
                                            }
                                    }
                                    ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <!-- Account Information Card -->
                <div class="card info-card mb-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-info-circle-fill me-2"></i>Account Information</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-borderless">
                            <tr>
                                <th scope="row" width="40%">Account Status</th>
                                <td>
                                    <span class="status-badge 
                                        <?php 
                                        switch($parent_leader['account_status']) {
                                            case 'active': echo 'status-active'; break;
                                            case 'pending': echo 'status-pending'; break;
                                            case 'suspended': echo 'status-suspended'; break;
                                            case 'deactivated': echo 'status-deactivated'; break;
                                            default: echo 'status-pending';
                                        }
                                        ?>">
                                        <?php 
                                        switch($parent_leader['account_status']) {
                                            case 'active': echo 'Approved'; break;
                                            case 'pending': echo 'Pending Verification'; break;
                                            case 'suspended': echo 'Suspended'; break;
                                            case 'deactivated': echo 'Deactivated'; break;
                                            default: echo 'Unknown Status';
                                        }
                                        ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Joined On</th>
                                <td><?php echo date('F d, Y', strtotime($parent_leader['created_at'])); ?></td>
                            </tr>
                            <tr>
                                <th scope="row">Approved On</th>
                                <td>
                                    <?php 
                                    if (!empty($parent_leader['verification_date'])) {
                                        echo date('F d, Y', strtotime($parent_leader['verification_date']));
                                    } else {
                                        echo 'Not yet approved';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Last Login</th>
                                <td>
                                    <?php 
                                    if (!empty($parent_leader['last_login'])) {
                                        echo date('F d, Y g:i a', strtotime($parent_leader['last_login']));
                                    } else {
                                        echo 'Never logged in';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Beneficiaries</th>
                                <td>
                                    <a href="../beneficiaries.php?parent_leader_id=<?php echo $parent_leader['user_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-sm btn-outline-primary">
                                        <?php echo $parent_leader['beneficiaries_count']; ?> Beneficiaries <i class="bi bi-arrow-right-short"></i>
                                    </a>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Beneficiaries and Documents Column -->
            <div class="col-lg-8">
                <!-- Document Verification Card -->
                <div class="card info-card mb-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-file-earmark-text me-2"></i>Verification Documents</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <h6 class="mb-2">Valid ID</h6>
                                <?php 
                                $valid_id_path = $parent_leader['valid_id_path'];
                                // Check if the path needs adjustment (if it's a relative path)
                                if (!empty($valid_id_path) && substr($valid_id_path, 0, 1) !== '/' && !preg_match('/^[a-zA-Z]:\\\/', $valid_id_path)) {
                                    $valid_id_path = '../../User/' . $valid_id_path;
                                }
                                
                                if (!empty($valid_id_path) && file_exists($valid_id_path)): 
                                ?>
                                <div class="text-center">
                                    <?php
                                    $file_extension = pathinfo($valid_id_path, PATHINFO_EXTENSION);
                                    if (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])):
                                    ?>
                                    <img src="<?php echo $valid_id_path; ?>" class="img-fluid rounded" style="max-height: 200px;" alt="Valid ID">
                                    <?php else: ?>
                                    <a href="<?php echo $valid_id_path; ?>" class="btn btn-outline-primary" target="_blank">
                                        <i class="bi bi-file-earmark-pdf me-2"></i> View Document
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Valid ID document not found
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <h6 class="mb-2">Proof of Residency</h6>
                                <?php 
                                $residency_path = $parent_leader['proof_of_residency_path'];
                                if (!empty($residency_path) && substr($residency_path, 0, 1) !== '/' && !preg_match('/^[a-zA-Z]:\\\/', $residency_path)) {
                                    $residency_path = '../../User/' . $residency_path;
                                }
                                
                                if (!empty($residency_path) && file_exists($residency_path)): 
                                ?>
                                <div class="text-center">
                                    <?php
                                    $file_extension = pathinfo($residency_path, PATHINFO_EXTENSION);
                                    if (in_array(strtolower($file_extension), ['jpg', 'jpeg', 'png', 'gif'])):
                                    ?>
                                    <img src="<?php echo $residency_path; ?>" class="img-fluid rounded" style="max-height: 200px;" alt="Proof of Residency">
                                    <?php else: ?>
                                    <a href="<?php echo $residency_path; ?>" class="btn btn-outline-primary" target="_blank">
                                        <i class="bi bi-file-earmark-pdf me-2"></i> View Document
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle-fill me-2"></i> Proof of residency document not found
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mt-3">
                            <h6>Verification Details</h6>
                            <?php if (!empty($parent_leader['verification_details'])): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle-fill me-2"></i> <?php echo htmlspecialchars($parent_leader['verification_details']); ?>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-secondary">
                                <i class="bi bi-question-circle-fill me-2"></i> No verification details available
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Beneficiaries Card -->
                <div class="card info-card mb-4">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-people-fill me-2"></i>Recent Beneficiaries</h5>
                        <a href="beneficiaries.php?parent_leader_id=<?php echo $parent_leader['user_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-sm btn-outline-light">
                            View All <i class="bi bi-arrow-right-short"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_beneficiaries)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i> This parent leader has no registered beneficiaries yet.
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($recent_beneficiaries as $beneficiary): ?>
                            <div class="col-md-6 mb-3">
                                <div class="card beneficiary-card h-100 position-relative">
                                    <div class="household-size" title="Household size">
                                        <?php echo $beneficiary['household_size']; ?>
                                    </div>
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <?php echo htmlspecialchars($beneficiary['firstname'] . ' ' . $beneficiary['lastname']); ?>
                                        </h5>
                                        <p class="card-text">
                                            <i class="bi bi-calendar-fill me-1 text-muted"></i> Age: <?php echo htmlspecialchars($beneficiary['age']); ?><br>
                                            <i class="bi bi-telephone-fill me-1 text-muted"></i> <?php echo htmlspecialchars($beneficiary['phone_number']); ?><br>
                                            <i class="bi bi-geo-alt-fill me-1 text-muted"></i> <?php echo htmlspecialchars($beneficiary['barangay_name']); ?>
                                        </p>
                                        
                                        <div class="badge bg-light text-dark mb-2">
                                            <i class="bi bi-book-fill me-1"></i> <?php echo htmlspecialchars($beneficiary['year_level']); ?>
                                        </div>
                                        
                                        <p class="text-muted small">
                                            <i class="bi bi-clock-history me-1"></i> Added: 
                                            <?php echo date('M d, Y', strtotime($beneficiary['created_at'])); ?>
                                        </p>
                                    </div>
                                    <!--<div class="card-footer bg-transparent">
                                        <a href="view_beneficiary.php?id=<?php echo $beneficiary['beneficiary_id']; ?>&parent_leader_id=<?php echo $parent_leader['user_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-sm btn-outline-success w-100">
                                            <i class="bi bi-eye me-1"></i> View Details
                                        </a>
                                    </div>-->
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        
                        <!--<div class="mt-3 text-center">
                            <a href="add_beneficiary.php?parent_leader_id=<?php echo $parent_leader['user_id']; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-success">
                                <i class="bi bi-person-plus-fill me-2"></i> Add New Beneficiary for this Parent Leader
                            </a>
                        </div> -->
                    </div>
                </div>
                
                <!-- Activity Logs Card 
                <div class="card info-card mb-4">
                    <div class="card-header bg-purple text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Activity Logs</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($activity_logs)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle-fill me-2"></i> No activity logs found for this parent leader.
                        </div>
                        <?php else: ?>
                        <div class="timeline">
                            <?php foreach ($activity_logs as $log): ?>
                            <div class="activity-log 
                                <?php 
                                if (strpos($log['description'], 'Approved') !== false) {
                                    echo 'verification';
                                } elseif (strpos($log['description'], 'Rejected') !== false) {
                                    echo 'rejection';
                                } elseif (strpos($log['description'], 'beneficiary') !== false) {
                                    echo 'beneficiary';
                                } elseif (strpos($log['description'], 'login') !== false) {
                                    echo 'login';
                                } elseif (strpos($log['description'], 'Deleted') !== false) {
                                    echo 'deletion';
                                }
                                ?>">
                                <h6 class="mb-1"><?php echo htmlspecialchars($log['description']); ?></h6>
                                <p class="text-muted small mb-0">
                                    <i class="bi bi-clock me-1"></i> <?php echo date('F d, Y g:i a', strtotime($log['created_at'])); ?>
                                </p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div> -->
            </div>
        </div>
        
        <?php else: ?>
        <!-- Parent Leader Not Found -->
        <div class="alert alert-danger" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> Parent leader not found.
        </div>
        <div class="text-center mt-4">
            <a href="parent_leaders.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-primary">
                <i class="bi bi-arrow-left me-2"></i> Back to Parent Leaders
            </a>
        </div>
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
            
            // Image preview modal
            const previewLinks = document.querySelectorAll('[data-bs-toggle="modal"]');
            previewLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const targetModal = document.getElementById(this.getAttribute('data-bs-target').substring(1));
                    const modalImg = targetModal.querySelector('img');
                    modalImg.src = this.getAttribute('data-img-src');
                });
            });
        });
    </script>
    
    <?php
    // Now close the database connection at the end of the script
    if (isset($db) && $db instanceof Database) {
        $db->closeConnection();
    }
    ?>
</body>
</html>