<?php
require_once "backend/connections/config.php";
require_once "backend/connections/database.php";

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['barangay_id'])) {
    $barangayId = intval($_POST['barangay_id']);
    
    // Update the user's barangay field
    $updateQuery = "UPDATE users SET barangay = ? WHERE user_id = ?";
    $db->execute($updateQuery, [$barangayId, $userId]);
}

// Initialize variables
$message = '';
$messageType = '';
$selected_barangay_id = isset($_SESSION['selected_barangay_id']) ? intval($_SESSION['selected_barangay_id']) : 
                        (isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : null);

// Handle barangay registration form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register_barangay'])) {
    $name = trim($_POST['barangay_name']);
    $captain_name = trim($_POST['captain_name']);
    $image_path = null;
    
    // Check if name is provided
    if (empty($name)) {
        $message = "Barangay name is required!";
        $messageType = "danger";
    } else {
        try {
            $db = new Database();
            
            // Check if barangay already exists
            $checkQuery = "SELECT COUNT(*) as count FROM barangays WHERE name = ?";
            $result = $db->fetchOne($checkQuery, [$name]);
            
            if ($result && $result['count'] > 0) {
                $message = "A barangay with this name already exists!";
                $messageType = "danger";
            } else {
                // Handle image upload if provided
                if (isset($_FILES['barangay_image']) && $_FILES['barangay_image']['error'] == 0) {
                    $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                    $filename = $_FILES['barangay_image']['name'];
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    
                    if (in_array(strtolower($ext), $allowed)) {
                        $upload_dir = 'assets/images/barangays/';
                        
                        // Create directory if it doesn't exist
                        if (!file_exists($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $new_filename = 'barangay_' . time() . '.' . $ext;
                        $destination = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($_FILES['barangay_image']['tmp_name'], $destination)) {
                            $image_path = $destination;
                        }
                    }
                }
                
                // Insert new barangay
                $insertQuery = "INSERT INTO barangays (name, captain_name, image_path, created_at) VALUES (?, ?, ?, NOW())";
                $barangayId = $db->insert($insertQuery, [$name, $captain_name, $image_path]);
                
                // Log activity
                $activityQuery = "INSERT INTO activity_logs (user_id, activity_type, description, created_at) 
                                  VALUES (?, 'barangay_added', ?, NOW())";
                $db->execute($activityQuery, [$_SESSION['user_id'] ?? 1, "Added new barangay: $name"]);
                
                $message = "Barangay registered successfully!";
                $messageType = "success";
                
                // Set the newly created barangay as selected
                $_SESSION['selected_barangay_id'] = $barangayId;
                $selected_barangay_id = $barangayId;
            }
            
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

// Handle barangay deletion
if (isset($_GET['delete_barangay']) && isset($_GET['id'])) {
    $barangay_id = intval($_GET['id']);
    
    try {
        $db = new Database();
        
        // Get barangay name for activity log
        $nameQuery = "SELECT name FROM barangays WHERE barangay_id = ?";
        $result = $db->fetchOne($nameQuery, [$barangay_id]);
        $barangay_name = $result ? $result['name'] : 'Unknown';
        
        // Check if there are beneficiaries or users in this barangay
        $checkBeneficiariesQuery = "SELECT COUNT(*) as count FROM beneficiaries WHERE barangay_id = ?";
        $resultBeneficiaries = $db->fetchOne($checkBeneficiariesQuery, [$barangay_id]);
        
        $checkUsersQuery = "SELECT COUNT(*) as count FROM users WHERE barangay = ?";
        $resultUsers = $db->fetchOne($checkUsersQuery, [$barangay_id]);
        
        $totalAssociatedRecords = ($resultBeneficiaries ? $resultBeneficiaries['count'] : 0) + 
                                  ($resultUsers ? $resultUsers['count'] : 0);
        
        if ($totalAssociatedRecords > 0) {
            $message = "Cannot delete barangay because it has " . $totalAssociatedRecords . " associated records (beneficiaries or users). Please reassign or delete them first.";
            $messageType = "danger";
        } else {
            // Delete barangay
            $deleteQuery = "DELETE FROM barangays WHERE barangay_id = ?";
            $db->execute($deleteQuery, [$barangay_id]);
            
            // Log activity
            $activityQuery = "INSERT INTO activity_logs (user_id, activity_type, description, created_at) 
                              VALUES (?, 'barangay_deleted', ?, NOW())";
            $db->execute($activityQuery, [$_SESSION['user_id'] ?? 1, "Deleted barangay: $barangay_name"]);
            
            $message = "Barangay deleted successfully!";
            $messageType = "success";
            
            // If the deleted barangay was selected, clear the selection
            if ($_SESSION['selected_barangay_id'] == $barangay_id) {
                unset($_SESSION['selected_barangay_id']);
                $selected_barangay_id = null;
            }
        }
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "danger";
    }
}

// Update Barangay
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_barangay'])) {
    $barangay_id = intval($_POST['barangay_id']);
    $name = trim($_POST['barangay_name']);
    $captain_name = trim($_POST['captain_name']);
    
    // Check if name is provided
    if (empty($name)) {
        $message = "Barangay name is required!";
        $messageType = "danger";
    } else {
        try {
            $db = new Database();
            
            // Update image if provided
            $updateImageQuery = "";
            $params = [$name, $captain_name, $barangay_id];
            
            if (isset($_FILES['barangay_image']) && $_FILES['barangay_image']['error'] == 0) {
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                $filename = $_FILES['barangay_image']['name'];
                $ext = pathinfo($filename, PATHINFO_EXTENSION);
                
                if (in_array(strtolower($ext), $allowed)) {
                    $upload_dir = 'assets/images/barangays/';
                    
                    // Create directory if it doesn't exist
                    if (!file_exists($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    $new_filename = 'barangay_' . time() . '.' . $ext;
                    $destination = $upload_dir . $new_filename;
                    
                    if (move_uploaded_file($_FILES['barangay_image']['tmp_name'], $destination)) {
                        $updateImageQuery = ", image_path = ?";
                        $params = [$name, $captain_name, $destination, $barangay_id];
                    }
                }
            }
            
            // Update barangay
            $updateQuery = "UPDATE barangays SET name = ?, captain_name = ?" . $updateImageQuery . " WHERE barangay_id = ?";
            $db->execute($updateQuery, $params);
            
            // Log activity
            $activityQuery = "INSERT INTO activity_logs (user_id, activity_type, description, created_at) 
                              VALUES (?, 'barangay_updated', ?, NOW())";
            $db->execute($activityQuery, [$_SESSION['user_id'] ?? 1, "Updated barangay: $name"]);
            
            $message = "Barangay updated successfully!";
            $messageType = "success";
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "danger";
        }
    }
}

try {
    $db = new Database();
    
    // Fetch all barangays information
    $barangaysQuery = "SELECT b.barangay_id, b.name, 
                    COALESCE(b.captain_name, 'No Captain Assigned') as captain_name, 
                    (SELECT COUNT(*) FROM beneficiaries WHERE barangay_id = b.barangay_id) as total_beneficiaries,
                    (SELECT COUNT(*) FROM users WHERE barangay = b.barangay_id AND role = 'resident' AND account_status = 'active') as registered_users,
                    b.image_path
                    FROM barangays b 
                    GROUP BY b.barangay_id
                    ORDER BY b.name ASC";
    $barangays = $db->fetchAll($barangaysQuery);
    
    // Fetch current selected barangay details
    $current_barangay = null;
    if ($selected_barangay_id) {
        $barangayDetailsQuery = "SELECT * FROM barangays WHERE barangay_id = ?";
        $current_barangay = $db->fetchOne($barangayDetailsQuery, [$selected_barangay_id]);
    }
    
    // Count total barangays
    $barangay_count = count($barangays);
    
    // Fetch system statistics
    if ($barangay_count > 0) {
        // Base statistics queries
        $stats_params = [];
        $where_clause = "";
        
        // If a barangay is selected, filter statistics by that barangay
        if ($selected_barangay_id) {
            $where_clause = "WHERE barangay_id = ?";
            $stats_params[] = $selected_barangay_id;
            
            $users_where_clause = "WHERE barangay = ?";
            $users_stats_params = [$selected_barangay_id];
        } else {
            $where_clause = "";
            $users_where_clause = "";
            $users_stats_params = [];
        }
        
        // Total beneficiaries (filtered by barangay if selected)
        $totalBeneficiariesQuery = "SELECT COUNT(*) as total FROM beneficiaries " . $where_clause;
        $result = $db->fetchOne($totalBeneficiariesQuery, $stats_params);
        $total_beneficiaries = $result ? $result['total'] : 0;
        
        // Pending Verifications (filtered by barangay if selected)
        $pendingVerificationsQuery = "SELECT COUNT(*) as pending FROM users 
                                     WHERE account_status = 'pending' AND role = 'resident'";
        if ($selected_barangay_id) {
            $pendingVerificationsQuery .= " AND barangay = ?";
        }
        $result = $db->fetchOne($pendingVerificationsQuery, $users_stats_params);
        $pending_verifications = $result ? $result['pending'] : 0;
        
        // Upcoming Events (could be filtered by barangay if you have a barangay_id column in events table)
        $upcomingEventsQuery = "SELECT COUNT(*) as upcoming FROM events WHERE event_date >= CURDATE()";
        $result = $db->fetchOne($upcomingEventsQuery);
        $upcoming_events = $result ? $result['upcoming'] : 0;
        
        // Unread Messages
        $unreadMessagesQuery = "SELECT COUNT(*) as unread FROM messages WHERE status = 'unread'";
        $result = $db->fetchOne($unreadMessagesQuery);
        $unread_messages = $result ? $result['unread'] : 0;
    } else {
        // Set default values if no barangays exist
        $total_beneficiaries = 0;
        $pending_verifications = 0;
        $upcoming_events = 0;
        $unread_messages = 0;
    }
    
    // Close database connection
    $db->closeConnection();
    
} catch (Exception $e) {
    // Handle database errors
    error_log("Admin Dashboard Error: " . $e->getMessage());
    
    // Set default values in case of errors
    $barangays = [];
    $barangay_count = 0;
    $total_beneficiaries = 0;
    $pending_verifications = 0;
    $upcoming_events = 0;
    $unread_messages = 0;
    $current_barangay = null;
    
    $message = "Database connection error: " . $e->getMessage();
    $messageType = "danger";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #0000cc;
            --secondary-color: #f8f9fa;
            --accent-color: #dc3545;
            --text-color: #333;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f0f2f5;
            color: var(--text-color);
            margin: 0;
            padding: 0;
        }
        
        /* Header Styles */
        .header {
            background-color: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 10px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center; 
            text-align: center;
        }

        .logo-container img {
            height: 60px;
            margin-right: 15px;
        }

        .system-title {
            color: #0000cc;
            font-size: 2rem;
            font-weight: bold;
            margin: 0;
        }
        
        .system-title {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: bold;
            margin: 0;
        }
        
        /* Navigation */
        .top-nav {
            background-color: var(--primary-color);
            padding: 10px 0;
        }
        
        .top-nav .nav-link {
            color: white;
            font-weight: 500;
            padding: 8px 15px;
            transition: all 0.3s;
        }
        
        .top-nav .nav-link:hover {
            background-color: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }
        
        .top-nav .nav-link.active {
            background-color: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }
        
        .badge-nav {
            position: relative;
            top: -8px;
            right: -3px;
            font-size: 0.65rem;
        }
        
        /* Admin Title Section */
        .admin-title {
            background-color: var(--secondary-color);
            padding: 30px 0;
            position: relative;
        }
        
        .admin-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(to right, var(--primary-color), var(--accent-color));
        }
        
        .admin-title h1 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            text-align: center;
            margin: 0;
        }
        
        /* Barangay Card Styles */
        .barangay-container {
            margin: 30px 0;
            padding: 20px;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .barangay-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .barangay-title h2 {
            color: var(--primary-color);
            font-weight: 600;
            margin: 0;
        }
        
        .barangay-card {
            border: 1px solid #ddd;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s, box-shadow 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        
        .barangay-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
        }
        
        .barangay-image {
            height: 200px;
            overflow: hidden;
        }
        
        .barangay-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .barangay-card:hover .barangay-image img {
            transform: scale(1.05);
        }
        
        .barangay-details {
            padding: 15px;
            background-color: white;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .barangay-name {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        
        .captain-name, .beneficiary-count {
            color: #666;
            margin-bottom: 5px;
        }
        
        .barangay-actions {
            margin-top: auto;
            display: flex;
            gap: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background-color: white;
            border-radius: 10px;
            margin: 30px 0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--primary-color);
            margin-bottom: 15px;
        }
        
        /* Modal Styles */
        .modal-header.bg-primary {
            background-color: var(--primary-color) !important;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 200px;
            object-fit: cover;
            border-radius: 5px;
            margin-top: 10px;
        }
        
        /* Summary Card Styles */
        .summary-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .summary-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .summary-icon i {
            font-size: 1.8rem;
            color: white;
        }
        
        .summary-details {
            flex-grow: 1;
        }
        
        .summary-count {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
            line-height: 1.2;
        }
        
        .summary-label {
            color: #666;
            margin: 0;
        }
        
        .bg-primary-custom {
            background-color: var(--primary-color);
        }
        
        .bg-info-custom {
            background-color: #17a2b8;
        }
        
        .bg-warning-custom {
            background-color: #ffc107;
        }
        
        .bg-danger-custom {
            background-color: var(--accent-color);
        }
        
        .border-top-custom {
            border-top: 4px solid var(--primary-color);
        }
        
        /* Responsive Adjustments */
        @media (max-width: 768px) {
            .system-title {
                font-size: 1.5rem;
            }
            
            .admin-title h1 {
                font-size: 2rem;
            }
            
            .barangay-title {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .barangay-title button {
                align-self: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <div class="logo-container">
                <img src="User/assets/pngwing.com (7).png" alt="DSWD Logo">
                <h1 class="system-title">4P's Profiling System</h1>
            </div>
        </div>
    </header>
    
    
    
    <!-- Admin Title Section -->
    <section class="admin-title">
        <div class="container">
            <h1>ADMIN</h1>
        </div>
    </section>
    
    <!-- Main Content -->
    <div class="container mt-4">
        <?php if(!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <!-- System Summary -->
        <div class="row mb-4">
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="summary-card">
                    <div class="summary-icon bg-primary-custom">
                        <i class="bi bi-building"></i>
                    </div>
                    <div class="summary-details">
                        <h2 class="summary-count"><?php echo number_format($barangay_count); ?></h2>
                        <p class="summary-label">Registered Barangays</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="summary-card">
                    <div class="summary-icon bg-info-custom">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="summary-details">
                        <h2 class="summary-count"><?php echo number_format($total_beneficiaries); ?></h2>
                        <p class="summary-label">Total Beneficiaries</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="summary-card">
                    <div class="summary-icon bg-warning-custom">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <div class="summary-details">
                        <h2 class="summary-count"><?php echo number_format($pending_verifications); ?></h2>
                        <p class="summary-label">Pending Verifications</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-sm-6 mb-3">
                <div class="summary-card">
                    <div class="summary-icon bg-danger-custom">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="summary-details">
                        <h2 class="summary-count"><?php echo number_format($upcoming_events); ?></h2>
                        <p class="summary-label">Upcoming Events</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Barangay Management Section -->
        <div class="barangay-container">
            <div class="barangay-title">
                <h2><i class="bi bi-building me-2"></i>Registered Barangays</h2>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#registerBarangayModal">
                    <i class="bi bi-plus-circle me-2"></i>Register Barangay
                </button>
            </div>
            
            <?php if(empty($barangays)): ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i class="bi bi-building"></i>
                <h3>No Barangays Registered Yet</h3>
                <p>Start by registering barangays in your municipality. This is required before you can add beneficiaries.</p>
                <!--<button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#registerBarangayModal">
                    <i class="bi bi-plus-circle me-2"></i>Register Your First Barangay
                </button> -->
            </div>
            <?php else: ?>
            <!-- Barangay Cards -->
            <div class="row">
                <?php foreach($barangays as $barangay): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="barangay-card">
                        <div class="barangay-image">
                            <img src="<?php echo !empty($barangay['image_path']) ? $barangay['image_path'] : 'assets/images/barangay-default.jpg'; ?>" 
                                 alt="<?php echo htmlspecialchars($barangay['name']); ?>">
                        </div>
                        <div class="barangay-details">
                            <div class="barangay-name"><?php echo htmlspecialchars($barangay['name']); ?></div>
                            <div class="captain-name">Barangay Captain: <?php echo htmlspecialchars($barangay['captain_name']); ?></div>
                            <div class="beneficiary-count">Registered 4P's: <?php echo number_format($barangay['registered_users']); ?></div>
                            <div class="resident-count">Parent Leaders: <?php echo number_format($barangay['registered_users']); ?></div>
                            
                            <div class="barangay-actions mt-3">
                                <a href="Admin/admin_dashboard.php?barangay_id=<?php echo $barangay['barangay_id']; ?>" class="btn btn-primary flex-grow-1">
                                    <i class="bi bi-speedometer2"></i> View Barangay Dashboard
                                </a>
                                <div class="dropdown">
                                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="bi bi-three-dots-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <button class="dropdown-item" type="button" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#editBarangayModal" 
                                                    data-id="<?php echo $barangay['barangay_id']; ?>"
                                                    data-name="<?php echo htmlspecialchars($barangay['name']); ?>"
                                                    data-captain="<?php echo htmlspecialchars($barangay['captain_name']); ?>"
                                                    data-image="<?php echo !empty($barangay['image_path']) ? $barangay['image_path'] : ''; ?>">
                                                <i class="bi bi-pencil me-2"></i> Edit
                                            </button>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="barangay_reports.php?id=<?php echo $barangay['barangay_id']; ?>">
                                                <i class="bi bi-file-earmark-text me-2"></i> View Reports
                                            </a>
                                        </li>
                                        <?php if($barangay['total_beneficiaries'] == 0): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <button class="dropdown-item text-danger" onclick="confirmDelete(<?php echo $barangay['barangay_id']; ?>, '<?php echo htmlspecialchars($barangay['name']); ?>')">
                                                <i class="bi bi-trash me-2"></i> Delete
                                            </button>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Register Barangay Modal -->
    <div class="modal fade" id="registerBarangayModal" tabindex="-1" aria-labelledby="registerBarangayModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="registerBarangayModalLabel">
                        <i class="bi bi-building me-2"></i>Register New Barangay
                        </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="barangay_name" class="form-label">Barangay Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="barangay_name" name="barangay_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="captain_name" class="form-label">Barangay Captain Name</label>
                            <input type="text" class="form-control" id="captain_name" name="captain_name">
                        </div>
                        <div class="mb-3">
                            <label for="barangay_image" class="form-label">Barangay Image</label>
                            <input type="file" class="form-control" id="barangay_image" name="barangay_image" accept="image/*" onchange="previewImage(this, 'imagePreview')">
                            <div id="imagePreview" class="mt-2" style="display: none;">
                                <img src="#" alt="Preview" class="preview-image">
                            </div>
                            <small class="text-muted">Upload an image of the barangay hall (optional). Recommended size: 800x400 pixels.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="register_barangay" class="btn btn-primary">Register Barangay</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Barangay Modal -->
    <div class="modal fade" id="editBarangayModal" tabindex="-1" aria-labelledby="editBarangayModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="editBarangayModalLabel">
                        <i class="bi bi-pencil me-2"></i>Edit Barangay
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="barangay_id" id="edit_barangay_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_barangay_name" class="form-label">Barangay Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_barangay_name" name="barangay_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_captain_name" class="form-label">Barangay Captain Name</label>
                            <input type="text" class="form-control" id="edit_captain_name" name="captain_name">
                        </div>
                        <div class="mb-3">
                            <label for="current_image" class="form-label">Current Image</label>
                            <div id="current_image_container" class="mb-2">
                                <img id="current_image_preview" src="" alt="Current Barangay Image" class="preview-image">
                                <p id="no_image_message" class="text-muted">No image uploaded</p>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="edit_barangay_image" class="form-label">Change Image</label>
                            <input type="file" class="form-control" id="edit_barangay_image" name="barangay_image" accept="image/*" onchange="previewImage(this, 'editImagePreview')">
                            <div id="editImagePreview" class="mt-2" style="display: none;">
                                <img src="#" alt="Preview" class="preview-image">
                            </div>
                            <small class="text-muted">Upload a new image of the barangay hall (optional). Recommended size: 800x400 pixels.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_barangay" class="btn btn-primary">Update Barangay</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="mt-5 py-3 bg-light">
        <div class="container text-center">
            <p class="mb-0">&copy; <?php echo date("Y"); ?> Department of Social Welfare and Development - 4P's Profiling System</p>
        </div>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Image preview functions
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const previewImg = preview.querySelector('img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                }
                
                reader.readAsDataURL(input.files[0]);
            } else {
                preview.style.display = 'none';
            }
        }
        
        // Edit Barangay Modal
        document.addEventListener('DOMContentLoaded', function() {
            const editBarangayModal = document.getElementById('editBarangayModal');
            if (editBarangayModal) {
                editBarangayModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const id = button.getAttribute('data-id');
                    const name = button.getAttribute('data-name');
                    const captain = button.getAttribute('data-captain');
                    const image = button.getAttribute('data-image');
                    
                    document.getElementById('edit_barangay_id').value = id;
                    document.getElementById('edit_barangay_name').value = name;
                    document.getElementById('edit_captain_name').value = captain;
                    
                    // Update current image preview
                    const noImageMsg = document.getElementById('no_image_message');
                    const imagePreview = document.getElementById('current_image_preview');
                    
                    if (image && image.trim() !== '') {
                        imagePreview.src = image;
                        imagePreview.style.display = 'block';
                        noImageMsg.style.display = 'none';
                    } else {
                        imagePreview.style.display = 'none';
                        noImageMsg.style.display = 'block';
                    }
                    
                    // Reset the new image preview
                    document.getElementById('editImagePreview').style.display = 'none';
                });
            }
        });
        
        // Confirm delete function
        function confirmDelete(id, name) {
            if (confirm('Are you sure you want to delete Barangay ' + name + '? This action cannot be undone.')) {
                window.location.href = 'admin.php?delete_barangay=1&id=' + id;
            }
        }
    </script>
</body>
</html>