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

// Set current page for navigation highlighting
$current_page = 'messages';

try {
    $db = new Database();
    
    // Fetch current barangay details if a specific barangay is selected
    $barangayQuery = "SELECT name, captain_name FROM barangays WHERE barangay_id = ?";
    $current_barangay = $db->fetchOne($barangayQuery, [$selected_barangay_id]);
    
    // Get total parent leaders count
    $totalParentLeadersQuery = $selected_barangay_id 
    ? "SELECT COUNT(*) as total FROM users WHERE role = 'resident' AND account_status = 'active' AND barangay = ?" 
    : "SELECT COUNT(*) as total FROM users WHERE role = 'resident' AND account_status = 'active'";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $result = $params ? $db->fetchOne($totalParentLeadersQuery, $params) : $db->fetchOne($totalParentLeadersQuery);
    $total_parent_leaders = $result ? $result['total'] : 0;
    
    // Pending Verifications
    $pendingVerificationsQuery = $selected_barangay_id
        ? "SELECT COUNT(*) as pending FROM users 
           WHERE account_status = 'pending' AND role = 'resident' AND barangay = ?"
        : "SELECT COUNT(*) as pending FROM users 
           WHERE account_status = 'pending' AND role = 'resident'";
    $params = $selected_barangay_id ? [$selected_barangay_id] : [];
    $result = $params ? $db->fetchOne($pendingVerificationsQuery, $params) : $db->fetchOne($pendingVerificationsQuery);
    $pending_verifications = $result ? $result['pending'] : 0;
    
    // Upcoming Events
    try {
        $upcomingEventsQuery = $selected_barangay_id
            ? "SELECT COUNT(*) as upcoming FROM events 
               WHERE event_date >= CURDATE() AND barangay_id = ?"
            : "SELECT COUNT(*) as upcoming FROM events WHERE event_date >= CURDATE()";
        $params = $selected_barangay_id ? [$selected_barangay_id] : [];
        $result = $params ? $db->fetchOne($upcomingEventsQuery, $params) : $db->fetchOne($upcomingEventsQuery);
        $upcoming_events = $result ? $result['upcoming'] : 0;
    } catch (Exception $e) {
        $upcoming_events = 0;
    }
    
    // Unread Messages
    try {
        $unreadMessagesQuery = $selected_barangay_id
            ? "SELECT COUNT(*) as unread FROM messages 
               WHERE status = 'unread' AND (sender_barangay_id = ? OR receiver_barangay_id = ?)"
            : "SELECT COUNT(*) as unread FROM messages WHERE status = 'unread'";
        $params = $selected_barangay_id ? [$selected_barangay_id, $selected_barangay_id] : [];
        $result = $params ? $db->fetchOne($unreadMessagesQuery, $params) : $db->fetchOne($unreadMessagesQuery);
        $unread_messages = $result ? $result['unread'] : 0;
    } catch (Exception $e) {
        $unread_messages = 0;
    }
    
    // Barangay Information
    try {
        $barangaysQuery = "SELECT b.barangay_id, b.name, 
                        COALESCE(b.captain_name, 'No Captain Assigned') as captain_name, 
                        COUNT(ben.beneficiary_id) as total_beneficiaries, 
                        b.image_path,
                        CASE WHEN b.barangay_id = ? THEN 1 ELSE 0 END as is_selected
                        FROM barangays b 
                        LEFT JOIN beneficiaries ben ON b.barangay_id = ben.barangay_id 
                        GROUP BY b.barangay_id
                        ORDER BY is_selected DESC, total_beneficiaries DESC";
        $params = [$selected_barangay_id ?? 0];
        $barangays = $db->fetchAll($barangaysQuery, $params);
    } catch (Exception $e) {
        $barangays = [];
    }
    
    // Handling message sending
    if (isset($_POST['send_message'])) {
        $receiver_id = isset($_POST['receiver_id']) ? intval($_POST['receiver_id']) : 0;
        $receiver_barangay = isset($_POST['receiver_barangay']) ? intval($_POST['receiver_barangay']) : 0;
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $message_content = isset($_POST['message_content']) ? trim($_POST['message_content']) : '';
        $admin_id = isset($_SESSION['admin_id']) ? $_SESSION['admin_id'] : 1; // Fallback to 1 if not set
        
        if ($receiver_id && !empty($subject) && !empty($message_content)) {
            try {
                // Insert new message
                $insertQuery = "INSERT INTO messages (sender_id, sender_role, sender_barangay_id, receiver_id, receiver_role, receiver_barangay_id, subject, content, status, created_at) 
                               VALUES (?, 'admin', NULL, ?, 'resident', ?, ?, ?, 'unread', NOW())";
                $insertParams = [$admin_id, $receiver_id, $receiver_barangay, $subject, $message_content];
                $db->execute($insertQuery, $insertParams);
                
                // Log activity
                $recipient_name = "User #" . $receiver_id;
                try {
                    $userQuery = "SELECT CONCAT(firstname, ' ', lastname) as name FROM users WHERE user_id = ?";
                    $user = $db->fetchOne($userQuery, [$receiver_id]);
                    if ($user) {
                        $recipient_name = $user['name'];
                    }
                } catch (Exception $e) {
                    // Keep default recipient name
                }
                
                $activity_description = "sent a message to " . $recipient_name;
                $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, created_at) 
                            VALUES (?, 'message', ?, NOW())";
                $db->execute($logQuery, [$admin_id, $activity_description]);
                
                $success_message = "Message sent successfully!";
            } catch (Exception $e) {
                $error_message = "Error sending message: " . $e->getMessage();
            }
        } else {
            $error_message = "Please fill all required fields.";
        }
    }
    
    // Get all users for messaging
    try {
        $usersQuery = $selected_barangay_id
            ? "SELECT u.user_id, u.firstname, u.lastname, u.email, u.barangay, u.role, u.account_status, 
                COALESCE(b.name, 'Unknown') as barangay_name
               FROM users u
               LEFT JOIN barangays b ON u.barangay = b.barangay_id
               WHERE u.role = 'resident' AND u.account_status = 'active' AND u.barangay = ?
               ORDER BY u.lastname, u.firstname"
            : "SELECT u.user_id, u.firstname, u.lastname, u.email, u.barangay, u.role, u.account_status,
                COALESCE(b.name, 'Unknown') as barangay_name
               FROM users u
               LEFT JOIN barangays b ON u.barangay = b.barangay_id
               WHERE u.role = 'resident' AND u.account_status = 'active'
               ORDER BY b.name, u.lastname, u.firstname";
        $params = $selected_barangay_id ? [$selected_barangay_id] : [];
        $users = $params ? $db->fetchAll($usersQuery, $params) : $db->fetchAll($usersQuery);
    } catch (Exception $e) {
        $users = [];
        $error_message = "Error fetching users: " . $e->getMessage();
    }
    
    // Get sent messages
    try {
        $sentMessagesQuery = $selected_barangay_id
            ? "SELECT m.message_id, m.subject, m.content, m.status, m.created_at,
                CONCAT(u.firstname, ' ', u.lastname) as receiver_name,
                b.name as barangay_name
               FROM messages m
               JOIN users u ON m.receiver_id = u.user_id
               LEFT JOIN barangays b ON u.barangay = b.barangay_id
               WHERE m.sender_role = 'admin' AND u.barangay = ?
               ORDER BY m.created_at DESC"
            : "SELECT m.message_id, m.subject, m.content, m.status, m.created_at,
                CONCAT(u.firstname, ' ', u.lastname) as receiver_name,
                b.name as barangay_name
               FROM messages m
               JOIN users u ON m.receiver_id = u.user_id
               LEFT JOIN barangays b ON u.barangay = b.barangay_id
               WHERE m.sender_role = 'admin'
               ORDER BY m.created_at DESC";
        $params = $selected_barangay_id ? [$selected_barangay_id] : [];
        $sent_messages = $params ? $db->fetchAll($sentMessagesQuery, $params) : $db->fetchAll($sentMessagesQuery);
    } catch (Exception $e) {
        $sent_messages = [];
        $error_message = "Error fetching sent messages: " . $e->getMessage();
    }
    
    // Get received messages
    try {
        $receivedMessagesQuery = $selected_barangay_id
            ? "SELECT m.message_id, m.subject, m.content, m.status, m.created_at,
                CONCAT(u.firstname, ' ', u.lastname) as sender_name,
                b.name as barangay_name
               FROM messages m
               JOIN users u ON m.sender_id = u.user_id
               LEFT JOIN barangays b ON u.barangay = b.barangay_id
               WHERE m.receiver_role = 'admin' AND u.barangay = ?
               ORDER BY m.created_at DESC"
            : "SELECT m.message_id, m.subject, m.content, m.status, m.created_at,
                CONCAT(u.firstname, ' ', u.lastname) as sender_name,
                b.name as barangay_name
               FROM messages m
               JOIN users u ON m.sender_id = u.user_id
               LEFT JOIN barangays b ON u.barangay = b.barangay_id
               WHERE m.receiver_role = 'admin'
               ORDER BY m.created_at DESC";
        $params = $selected_barangay_id ? [$selected_barangay_id] : [];
        $received_messages = $params ? $db->fetchAll($receivedMessagesQuery, $params) : $db->fetchAll($receivedMessagesQuery);
    } catch (Exception $e) {
        $received_messages = [];
        $error_message = "Error fetching received messages: " . $e->getMessage();
    }
    
    // Mark a message as read if 'read_id' is specified
    if (isset($_GET['read_id'])) {
        $read_id = intval($_GET['read_id']);
        try {
            $updateQuery = "UPDATE messages SET status = 'read' WHERE message_id = ? AND receiver_role = 'admin'";
            $db->execute($updateQuery, [$read_id]);
        } catch (Exception $e) {
            $error_message = "Error marking message as read: " . $e->getMessage();
        }
    }
    
} catch (Exception $e) {
    // Handle database errors
    error_log("Messages Page Error: " . $e->getMessage());
    $error_message = "An error occurred while loading the messages page.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $selected_barangay_id && $current_barangay ? 'Barangay ' . htmlspecialchars($current_barangay['name']) : 'All Barangays'; ?> - Messages</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        .message-card {
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .message-card:hover {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        .message-unread {
            background-color: #f0f7ff;
            border-left: 4px solid #0d6efd;
            font-weight: 500;
        }
        .message-time {
            font-size: 0.8rem;
            color: #6c757d;
        }
        .recipient-row {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .recipient-row:hover {
            background-color: #f8f9fa;
        }
        .nav-tabs .nav-link {
            display: flex;
            align-items: center;
        }
        .badge-counter {
            margin-left: 8px;
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
            <h1>4P's Profiling System ADMIN DASHBOARD</h1>
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
                <a class="nav-link active <?php echo $current_page == 'messages' ? 'active' : ''; ?>" href="messages.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
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
        
        <!-- Barangay Selector -->
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
                    <?php if (isset($_GET['tab'])): ?>
                    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($_GET['tab']); ?>">
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
                    <h4 class="alert-heading">Barangay <?php echo htmlspecialchars($current_barangay['name']); ?> Messages</h4>
                    <p class="mb-0">Barangay Captain: <?php echo htmlspecialchars($current_barangay['captain_name'] ?: 'Not Assigned'); ?></p>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="alert alert-primary mb-4" role="alert">
            <div class="d-flex align-items-center">
                <i class="bi bi-chat-text fs-1 me-3"></i>
                <div>
                    <h4 class="alert-heading">All Barangays Message Center</h4>
                    <p class="mb-0">Manage communications with parent leaders across all barangays</p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Success/Error Messages -->
        <?php if(isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i> <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Message Center Container -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-chat-dots me-2"></i>Message Center
                    <?php if($selected_barangay_id && $current_barangay): ?>
                    - Barangay <?php echo htmlspecialchars($current_barangay['name']); ?>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="card-body">
                <!-- Tabs for Message Center -->
                <ul class="nav nav-tabs mb-4" id="messagesTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'compose') ? 'active' : ''; ?>" 
                            id="compose-tab" data-bs-toggle="tab" data-bs-target="#compose" 
                            type="button" role="tab" aria-controls="compose" aria-selected="true">
                            <i class="bi bi-pencil-square me-2"></i> Compose Message
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'inbox') ? 'active' : ''; ?>" 
                            id="inbox-tab" data-bs-toggle="tab" data-bs-target="#inbox" 
                            type="button" role="tab" aria-controls="inbox" aria-selected="false">
                            <i class="bi bi-inbox me-2"></i> Inbox
                            <?php if(count(array_filter($received_messages, function($m) { return $m['status'] == 'unread'; })) > 0): ?>
                            <span class="badge bg-danger rounded-pill badge-counter">
                                <?php echo count(array_filter($received_messages, function($m) { return $m['status'] == 'unread'; })); ?>
                            </span>
                            <?php endif; ?>
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'sent') ? 'active' : ''; ?>" 
                            id="sent-tab" data-bs-toggle="tab" data-bs-target="#sent" 
                            type="button" role="tab" aria-controls="sent" aria-selected="false">
                            <i class="bi bi-send me-2"></i> Sent Messages
                        </button>
                    </li>
                </ul>
                
                <!-- Tab Content -->
                <div class="tab-content" id="messagesTabsContent">
                    <!-- Compose Message Tab -->
                    <div class="tab-pane fade <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'compose') ? 'show active' : ''; ?>" 
                        id="compose" role="tabpanel" aria-labelledby="compose-tab">
                        
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="bi bi-people me-2"></i>Select Recipient</h5>
                                    </div>
                                    <div class="card-body p-0">
                                        <?php if(empty($users)): ?>
                                            <div class="text-center p-4">
                                                <i class="bi bi-exclamation-circle fs-1 text-muted"></i>
                                                <p class="mt-2 text-muted">No active parent leaders found.</p>
                                            </div>
                                        <?php else: ?>
                                            <div class="list-group list-group-flush" id="recipientsList">
                                                <?php foreach($users as $user): ?>
                                                    <div class="list-group-item list-group-item-action recipient-row" 
                                                        data-id="<?php echo $user['user_id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>"
                                                        data-barangay="<?php echo $user['barangay']; ?>">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <div>
                                                                <h6 class="mb-0"><?php echo htmlspecialchars(ucwords($user['firstname'] . ' ' . $user['lastname'])); ?></h6>
                                                                <small class="text-muted">
                                                                    <?php echo htmlspecialchars($user['email']); ?>
                                                                </small>
                                                            </div>
                                                            <span class="badge bg-primary rounded-pill">
                                                                <?php echo htmlspecialchars($user['barangay_name']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-8">
                                <div class="card">
                                    <div class="card-header bg-light">
                                        <h5 class="mb-0"><i class="bi bi-envelope me-2"></i>Compose Message</h5>
                                    </div>
                                    <div class="card-body">
                                        <form id="messageForm" method="POST" action="messages.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                                            <div class="mb-3">
                                                <label for="recipient" class="form-label">To:</label>
                                                <input type="text" class="form-control" id="recipient" readonly>
                                                <input type="hidden" name="receiver_id" id="receiver_id" required>
                                                <input type="hidden" name="receiver_barangay" id="receiver_barangay" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="subject" class="form-label">Subject:</label>
                                                <input type="text" class="form-control" id="subject" name="subject" required>
                                            </div>
                                            <div class="mb-3">
                                                <label for="message_content" class="form-label">Message:</label>
                                                <textarea class="form-control" id="message_content" name="message_content" rows="6" required></textarea>
                                            </div>
                                            <div class="text-end">
                                                <button type="submit" name="send_message" class="btn btn-primary" id="sendBtn" disabled>
                                                    <i class="bi bi-send me-2"></i>Send Message
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Inbox Tab -->
                    <div class="tab-pane fade <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'inbox') ? 'show active' : ''; ?>" 
                        id="inbox" role="tabpanel" aria-labelledby="inbox-tab">
                        
                        <?php if(empty($received_messages)): ?>
                            <div class="text-center p-5">
                                <i class="bi bi-inbox fs-1 text-muted"></i>
                                <h5 class="mt-3 text-muted">Your Inbox is Empty</h5>
                                <p class="text-muted">No messages have been received yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>From</th>
                                            <th>Subject</th>
                                            <th>Barangay</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($received_messages as $message): ?>
                                            <tr class="<?php echo ($message['status'] == 'unread') ? 'message-unread' : ''; ?>">
                                                <td><?php echo htmlspecialchars($message['sender_name']); ?></td>
                                                <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                                <td><?php echo htmlspecialchars($message['barangay_name']); ?></td>
                                                <td><?php echo date('M d, Y g:i A', strtotime($message['created_at'])); ?></td>
                                                <td>
                                                    <?php if($message['status'] == 'unread'): ?>
                                                        <span class="badge bg-danger">Unread</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Read</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary view-message" 
                                                        data-bs-toggle="modal" data-bs-target="#viewMessageModal"
                                                        data-id="<?php echo $message['message_id']; ?>"
                                                        data-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                                                        data-sender="<?php echo htmlspecialchars($message['sender_name']); ?>"
                                                        data-content="<?php echo htmlspecialchars($message['content']); ?>"
                                                        data-date="<?php echo date('M d, Y g:i A', strtotime($message['created_at'])); ?>"
                                                        data-status="<?php echo $message['status']; ?>"
                                                        data-barangay="<?php echo htmlspecialchars($message['barangay_name']); ?>">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Sent Messages Tab -->
                    <div class="tab-pane fade <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'sent') ? 'show active' : ''; ?>" 
                        id="sent" role="tabpanel" aria-labelledby="sent-tab">
                        
                        <?php if(empty($sent_messages)): ?>
                            <div class="text-center p-5">
                                <i class="bi bi-send fs-1 text-muted"></i>
                                <h5 class="mt-3 text-muted">No Sent Messages</h5>
                                <p class="text-muted">You haven't sent any messages yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>To</th>
                                            <th>Subject</th>
                                            <th>Barangay</th>
                                            <th>Date</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($sent_messages as $message): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($message['receiver_name']); ?></td>
                                                <td><?php echo htmlspecialchars($message['subject']); ?></td>
                                                <td><?php echo htmlspecialchars($message['barangay_name']); ?></td>
                                                <td><?php echo date('M d, Y g:i A', strtotime($message['created_at'])); ?></td>
                                                <td>
                                                    <?php if($message['status'] == 'unread'): ?>
                                                        <span class="badge bg-warning">Unread</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">Read</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary view-sent-message" 
                                                        data-bs-toggle="modal" data-bs-target="#viewSentMessageModal"
                                                        data-id="<?php echo $message['message_id']; ?>"
                                                        data-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                                                        data-receiver="<?php echo htmlspecialchars($message['receiver_name']); ?>"
                                                        data-content="<?php echo htmlspecialchars($message['content']); ?>"
                                                        data-date="<?php echo date('M d, Y g:i A', strtotime($message['created_at'])); ?>"
                                                        data-status="<?php echo $message['status']; ?>"
                                                        data-barangay="<?php echo htmlspecialchars($message['barangay_name']); ?>">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- View Received Message Modal -->
        <div class="modal fade" id="viewMessageModal" tabindex="-1" aria-labelledby="viewMessageModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="viewMessageModalLabel">
                            <i class="bi bi-envelope-open me-2"></i><span id="modalMessageSubject"></span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <strong>From:</strong> <span id="modalMessageSender"></span>
                                <br>
                                <strong>Barangay:</strong> <span id="modalMessageBarangay"></span>
                            </div>
                            <div>
                                <strong>Date:</strong> <span id="modalMessageDate"></span>
                                <br>
                                <strong>Status:</strong> <span id="modalMessageStatus"></span>
                            </div>
                        </div>
                        <hr>
                        <div class="message-content p-3 bg-light rounded">
                            <p id="modalMessageContent"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <a href="#" id="replyBtn" class="btn btn-primary"><i class="bi bi-reply me-2"></i>Reply</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- View Sent Message Modal -->
        <div class="modal fade" id="viewSentMessageModal" tabindex="-1" aria-labelledby="viewSentMessageModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="viewSentMessageModalLabel">
                            <i class="bi bi-envelope-paper me-2"></i><span id="modalSentMessageSubject"></span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex justify-content-between mb-3">
                            <div>
                                <strong>To:</strong> <span id="modalSentMessageReceiver"></span>
                                <br>
                                <strong>Barangay:</strong> <span id="modalSentMessageBarangay"></span>
                            </div>
                            <div>
                                <strong>Date:</strong> <span id="modalSentMessageDate"></span>
                                <br>
                                <strong>Status:</strong> <span id="modalSentMessageStatus"></span>
                            </div>
                        </div>
                        <hr>
                        <div class="message-content p-3 bg-light rounded">
                            <p id="modalSentMessageContent"></p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
            
            // Recipient selection
            const recipientRows = document.querySelectorAll('.recipient-row');
            const recipientInput = document.getElementById('recipient');
            const receiverIdInput = document.getElementById('receiver_id');
            const receiverBarangayInput = document.getElementById('receiver_barangay');
            const sendBtn = document.getElementById('sendBtn');
            
            recipientRows.forEach(row => {
                row.addEventListener('click', function() {
                    // Clear any previous selections
                    recipientRows.forEach(r => r.classList.remove('active', 'bg-light'));
                    
                    // Set this row as selected
                    this.classList.add('active', 'bg-light');
                    
                    // Set form values
                    recipientInput.value = this.getAttribute('data-name');
                    receiverIdInput.value = this.getAttribute('data-id');
                    receiverBarangayInput.value = this.getAttribute('data-barangay');
                    
                    // Enable send button
                    sendBtn.disabled = false;
                });
            });
            
            // View Message Modal
            const viewMessageButtons = document.querySelectorAll('.view-message');
            
            viewMessageButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const messageId = this.getAttribute('data-id');
                    const subject = this.getAttribute('data-subject');
                    const sender = this.getAttribute('data-sender');
                    const content = this.getAttribute('data-content');
                    const date = this.getAttribute('data-date');
                    const status = this.getAttribute('data-status');
                    const barangay = this.getAttribute('data-barangay');
                    
                    document.getElementById('modalMessageSubject').textContent = subject;
                    document.getElementById('modalMessageSender').textContent = sender;
                    document.getElementById('modalMessageContent').textContent = content;
                    document.getElementById('modalMessageDate').textContent = date;
                    document.getElementById('modalMessageBarangay').textContent = barangay;
                    
                    const statusSpan = document.getElementById('modalMessageStatus');
                    if (status === 'unread') {
                        statusSpan.innerHTML = '<span class="badge bg-danger">Unread</span>';
                        
                        // Mark as read when opened
                        const currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('read_id', messageId);
                        
                        // Preserve the tab parameter
                        if (currentUrl.searchParams.has('tab')) {
                            currentUrl.searchParams.set('tab', 'inbox');
                        } else {
                            currentUrl.searchParams.append('tab', 'inbox');
                        }
                        
                        // Add or preserve the barangay_id parameter
                        <?php if($selected_barangay_id): ?>
                        if (!currentUrl.searchParams.has('barangay_id')) {
                            currentUrl.searchParams.append('barangay_id', '<?php echo $selected_barangay_id; ?>');
                        }
                        <?php endif; ?>
                        
                        // Redirect to mark as read
                        window.location.href = currentUrl.toString();
                    } else {
                        statusSpan.innerHTML = '<span class="badge bg-success">Read</span>';
                    }
                });
            });
            
            // View Sent Message Modal
            const viewSentMessageButtons = document.querySelectorAll('.view-sent-message');
            
            viewSentMessageButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const subject = this.getAttribute('data-subject');
                    const receiver = this.getAttribute('data-receiver');
                    const content = this.getAttribute('data-content');
                    const date = this.getAttribute('data-date');
                    const status = this.getAttribute('data-status');
                    const barangay = this.getAttribute('data-barangay');
                    
                    document.getElementById('modalSentMessageSubject').textContent = subject;
                    document.getElementById('modalSentMessageReceiver').textContent = receiver;
                    document.getElementById('modalSentMessageContent').textContent = content;
                    document.getElementById('modalSentMessageDate').textContent = date;
                    document.getElementById('modalSentMessageBarangay').textContent = barangay;
                    
                    const statusSpan = document.getElementById('modalSentMessageStatus');
                    if (status === 'unread') {
                        statusSpan.innerHTML = '<span class="badge bg-warning">Unread</span>';
                    } else {
                        statusSpan.innerHTML = '<span class="badge bg-success">Read</span>';
                    }
                });
            });
            
            // Reply button functionality
            const replyBtn = document.getElementById('replyBtn');
            
            replyBtn.addEventListener('click', function() {
                // Get data from modal
                const sender = document.getElementById('modalMessageSender').textContent;
                const subject = document.getElementById('modalMessageSubject').textContent;
                
                // Switch to compose tab
                const composeTab = document.getElementById('compose-tab');
                bootstrap.Tab.getOrCreateInstance(composeTab).show();
                
                // Find the matching recipient
                recipientRows.forEach(row => {
                    if (row.getAttribute('data-name') === sender) {
                        // Simulate click on the recipient
                        row.click();
                        
                        // Set subject with "Re: " prefix if not already there
                        const subjectInput = document.getElementById('subject');
                        if (!subject.startsWith('Re: ')) {
                            subjectInput.value = 'Re: ' + subject;
                        } else {
                            subjectInput.value = subject;
                        }
                        
                        // Focus the message textarea
                        setTimeout(() => {
                            document.getElementById('message_content').focus();
                        }, 500);
                    }
                });
                
                // Close the modal
                const viewMessageModal = document.getElementById('viewMessageModal');
                bootstrap.Modal.getInstance(viewMessageModal).hide();
            });
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
        });
    </script>
</body>
</html>