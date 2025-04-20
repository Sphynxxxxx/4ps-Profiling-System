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
    
    // Handling message sending
    if (isset($_POST['send_message'])) {
        $subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
        $message_content = isset($_POST['message_content']) ? trim($_POST['message_content']) : '';
        
        if (!empty($subject) && !empty($message_content)) {
            try {
                // Insert new message
                $insertQuery = "INSERT INTO messages (sender_id, sender_role, sender_barangay_id, receiver_id, receiver_role, receiver_barangay_id, subject, content, status, created_at) 
                               VALUES (?, 'resident', ?, NULL, 'admin', NULL, ?, ?, 'unread', NOW())";
                $insertParams = [$userId, $userBarangayId, $subject, $message_content];
                $db->execute($insertQuery, $insertParams);
                
                // Log activity
                $activity_description = "sent a message to admin";
                $logQuery = "INSERT INTO activity_logs (user_id, activity_type, description, created_at) 
                            VALUES (?, 'message', ?, NOW())";
                $db->execute($logQuery, [$userId, $activity_description]);
                
                $success_message = "Message sent successfully!";
            } catch (Exception $e) {
                $error_message = "Error sending message: " . $e->getMessage();
            }
        } else {
            $error_message = "Please fill all required fields.";
        }
    }
    
    // Mark a message as read if 'read_id' is specified
    if (isset($_GET['read_id'])) {
        $read_id = intval($_GET['read_id']);
        try {
            $updateQuery = "UPDATE messages SET status = 'read' WHERE message_id = ? AND receiver_id = ?";
            $db->execute($updateQuery, [$read_id, $userId]);
        } catch (Exception $e) {
            $error_message = "Error marking message as read: " . $e->getMessage();
        }
    }
    
    // Get received messages (from admin to user)
    try {
        $receivedMessagesQuery = "SELECT m.message_id, m.subject, m.content, m.status, m.created_at, 
                                 'Administrator' as sender_name
                                 FROM messages m
                                 WHERE m.receiver_id = ? AND m.receiver_role = 'resident'
                                 ORDER BY m.created_at DESC";
        $received_messages = $db->fetchAll($receivedMessagesQuery, [$userId]);
    } catch (Exception $e) {
        $received_messages = [];
        $error_message = "Error fetching received messages: " . $e->getMessage();
    }
    
    // Get sent messages (from user to admin)
    try {
        $sentMessagesQuery = "SELECT m.message_id, m.subject, m.content, m.status, m.created_at,
                             'Administrator' as receiver_name
                             FROM messages m
                             WHERE m.sender_id = ? AND m.sender_role = 'resident'
                             ORDER BY m.created_at DESC";
        $sent_messages = $db->fetchAll($sentMessagesQuery, [$userId]);
    } catch (Exception $e) {
        $sent_messages = [];
        $error_message = "Error fetching sent messages: " . $e->getMessage();
    }
    
    // Count unread messages
    $unread_count = 0;
    foreach ($received_messages as $message) {
        if ($message['status'] == 'unread') {
            $unread_count++;
        }
    }
    
} catch (Exception $e) {
    // Log error
    error_log("Messages Page Error: " . $e->getMessage());
    
    $user = [
        'firstname' => $_SESSION['firstname'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'resident',
        'profile_image' => 'assets/images/profile-placeholder.png'
    ];
    
    $userBarangayId = null;
    $received_messages = [];
    $sent_messages = [];
    $unread_count = 0;
    $error_message = "An error occurred while loading the messages page.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | DSWD 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/dashboard.css">
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
        .nav-tabs .nav-link {
            display: flex;
            align-items: center;
        }
        .badge-counter {
            margin-left: 8px;
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
        .message-content {
            white-space: pre-wrap;
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
                    <a href="messages.php" class="text-white">
                        <i class="bi bi-envelope-fill fs-5"></i>
                        <?php if ($unread_count > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                            <?php echo $unread_count; ?>
                        </span>
                        <?php endif; ?>
                    </a>
                </div>
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
                        <a class="nav-link" href="members.php">
                            <i class="bi bi-people"></i> Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="messages.php">
                            <i class="bi bi-chat-left-text"></i> Messages
                            <?php if ($unread_count > 0): ?>
                            <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_count; ?></span>
                            <?php endif; ?>
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
                    <!-- Page Title -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="welcome-card p-4 shadow-sm">
                                <div class="welcome-pattern"></div>
                                <div class="row align-items-center">
                                    <div class="col-md-7">
                                        <h1 class="h3 mb-2">Message Center</h1>
                                        <p class="mb-0">Communicate with DSWD Administrators</p>
                                    </div>
                                    <div class="col-md-5 d-none d-md-block text-end">
                                        <h4>DSWD 4P's Profiling System</h4>
                                        <p class="mb-0">Pantawid Pamilyang Pilipino Program</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

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
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Tabs for Message Center -->
                            <ul class="nav nav-tabs mb-4" id="messagesTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'inbox') ? 'active' : ''; ?>" 
                                        id="inbox-tab" data-bs-toggle="tab" data-bs-target="#inbox" 
                                        type="button" role="tab" aria-controls="inbox" aria-selected="true">
                                        <i class="bi bi-inbox me-2"></i> Inbox
                                        <?php if($unread_count > 0): ?>
                                        <span class="badge bg-danger rounded-pill badge-counter">
                                            <?php echo $unread_count; ?>
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
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'compose') ? 'active' : ''; ?>" 
                                        id="compose-tab" data-bs-toggle="tab" data-bs-target="#compose" 
                                        type="button" role="tab" aria-controls="compose" aria-selected="false">
                                        <i class="bi bi-pencil-square me-2"></i> Compose Message
                                    </button>
                                </li>
                            </ul>
                            
                            <!-- Tab Content -->
                            <div class="tab-content" id="messagesTabsContent">
                                <!-- Inbox Tab -->
                                <div class="tab-pane fade <?php echo (!isset($_GET['tab']) || $_GET['tab'] == 'inbox') ? 'show active' : ''; ?>" 
                                    id="inbox" role="tabpanel" aria-labelledby="inbox-tab">
                                    
                                    <?php if(empty($received_messages)): ?>
                                        <div class="text-center p-5">
                                            <i class="bi bi-inbox fs-1 text-muted"></i>
                                            <h5 class="mt-3 text-muted">Your Inbox is Empty</h5>
                                            <p class="text-muted">No messages from administrators yet.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach($received_messages as $message): ?>
                                                <div class="list-group-item list-group-item-action message-card <?php echo ($message['status'] == 'unread') ? 'message-unread' : ''; ?>" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewMessageModal" 
                                                    data-id="<?php echo $message['message_id']; ?>"
                                                    data-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                                                    data-sender="<?php echo htmlspecialchars($message['sender_name']); ?>"
                                                    data-content="<?php echo htmlspecialchars($message['content']); ?>"
                                                    data-date="<?php echo date('M d, Y g:i A', strtotime($message['created_at'])); ?>"
                                                    data-status="<?php echo $message['status']; ?>">
                                                    
                                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                                        <h5 class="mb-1">
                                                            <?php if($message['status'] == 'unread'): ?>
                                                                <i class="bi bi-envelope-fill text-primary me-2"></i>
                                                            <?php else: ?>
                                                                <i class="bi bi-envelope-open text-muted me-2"></i>
                                                            <?php endif; ?>
                                                            <?php echo htmlspecialchars($message['subject']); ?>
                                                        </h5>
                                                        <small class="message-time"><?php echo date('M d, Y', strtotime($message['created_at'])); ?></small>
                                                    </div>
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <p class="mb-1 text-truncate" style="max-width: 80%;">
                                                            <?php echo htmlspecialchars(substr($message['content'], 0, 100)) . (strlen($message['content']) > 100 ? '...' : ''); ?>
                                                        </p>
                                                        <?php if($message['status'] == 'unread'): ?>
                                                            <span class="badge bg-danger">New</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted">From: <?php echo htmlspecialchars($message['sender_name']); ?></small>
                                                </div>
                                            <?php endforeach; ?>
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
                                            <p class="text-muted">You haven't sent any messages to administrators yet.</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="list-group">
                                            <?php foreach($sent_messages as $message): ?>
                                                <div class="list-group-item list-group-item-action message-card" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewSentMessageModal" 
                                                    data-id="<?php echo $message['message_id']; ?>"
                                                    data-subject="<?php echo htmlspecialchars($message['subject']); ?>"
                                                    data-receiver="<?php echo htmlspecialchars($message['receiver_name']); ?>"
                                                    data-content="<?php echo htmlspecialchars($message['content']); ?>"
                                                    data-date="<?php echo date('M d, Y g:i A', strtotime($message['created_at'])); ?>"
                                                    data-status="<?php echo $message['status']; ?>">
                                                    
                                                    <div class="d-flex w-100 justify-content-between align-items-center">
                                                        <h5 class="mb-1">
                                                            <i class="bi bi-send text-muted me-2"></i>
                                                            <?php echo htmlspecialchars($message['subject']); ?>
                                                        </h5>
                                                        <small class="message-time"><?php echo date('M d, Y', strtotime($message['created_at'])); ?></small>
                                                    </div>
                                                    <div class="d-flex w-100 justify-content-between">
                                                        <p class="mb-1 text-truncate" style="max-width: 80%;">
                                                            <?php echo htmlspecialchars(substr($message['content'], 0, 100)) . (strlen($message['content']) > 100 ? '...' : ''); ?>
                                                        </p>
                                                        <?php if($message['status'] == 'read'): ?>
                                                            <span class="badge bg-success">Read</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning text-dark">Unread</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <small class="text-muted">To: <?php echo htmlspecialchars($message['receiver_name']); ?></small>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Compose Message Tab -->
                                <div class="tab-pane fade <?php echo (isset($_GET['tab']) && $_GET['tab'] == 'compose') ? 'show active' : ''; ?>" 
                                    id="compose" role="tabpanel" aria-labelledby="compose-tab">
                                    
                                    <div class="card">
                                        <div class="card-header bg-light">
                                            <h5 class="mb-0"><i class="bi bi-envelope me-2"></i>New Message to Administrator</h5>
                                        </div>
                                        <div class="card-body">
                                            <form id="messageForm" method="POST" action="messages.php<?php echo isset($_GET['tab']) ? '?tab=compose' : ''; ?>">
                                                <div class="mb-3">
                                                    <label for="recipient" class="form-label">To:</label>
                                                    <input type="text" class="form-control" id="recipient" value="Administrator" readonly>
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
                                                    <button type="submit" name="send_message" class="btn btn-primary">
                                                        <i class="bi bi-send me-2"></i>Send Message
                                                    </button>
                                                </div>
                                            </form>
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
                        </div>
                        <div>
                            <strong>Date:</strong> <span id="modalMessageDate"></span>
                        </div>
                    </div>
                    <hr>
                    <div class="message-content p-3 bg-light rounded">
                        <p id="modalMessageContent"></p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" id="replyBtn" class="btn btn-primary"><i class="bi bi-reply me-2"></i>Reply</button>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Mobile sidebar toggle
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
            
            // View Message Modal
            const viewMessageButtons = document.querySelectorAll('.list-group-item[data-bs-target="#viewMessageModal"]');
            
            viewMessageButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const messageId = this.getAttribute('data-id');
                    const subject = this.getAttribute('data-subject');
                    const sender = this.getAttribute('data-sender');
                    const content = this.getAttribute('data-content');
                    const date = this.getAttribute('data-date');
                    const status = this.getAttribute('data-status');
                    
                    document.getElementById('modalMessageSubject').textContent = subject;
                    document.getElementById('modalMessageSender').textContent = sender;
                    document.getElementById('modalMessageContent').textContent = content;
                    document.getElementById('modalMessageDate').textContent = date;
                    
                    // Mark as read when opened if it was unread
                    if (status === 'unread') {
                        // Update URL to mark as read
                        const currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('read_id', messageId);
                        
                        // Preserve the tab parameter
                        if (currentUrl.searchParams.has('tab')) {
                            currentUrl.searchParams.set('tab', 'inbox');
                        } else {
                            currentUrl.searchParams.append('tab', 'inbox');
                        }
                        
                        // Redirect to mark as read
                        window.location.href = currentUrl.toString();
                    }
                });
            });
            
            // View Sent Message Modal
            const viewSentMessageButtons = document.querySelectorAll('.list-group-item[data-bs-target="#viewSentMessageModal"]');
            
            viewSentMessageButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const subject = this.getAttribute('data-subject');
                    const receiver = this.getAttribute('data-receiver');
                    const content = this.getAttribute('data-content');
                    const date = this.getAttribute('data-date');
                    const status = this.getAttribute('data-status');
                    
                    document.getElementById('modalSentMessageSubject').textContent = subject;
                    document.getElementById('modalSentMessageReceiver').textContent = receiver;
                    document.getElementById('modalSentMessageContent').textContent = content;
                    document.getElementById('modalSentMessageDate').textContent = date;
                    
                    const statusSpan = document.getElementById('modalSentMessageStatus');
                    if (status === 'unread') {
                        statusSpan.innerHTML = '<span class="badge bg-warning text-dark">Unread</span>';
                    } else {
                        statusSpan.innerHTML = '<span class="badge bg-success">Read</span>';
                    }
                });
            });
            
            // Reply button functionality
            const replyBtn = document.getElementById('replyBtn');
            
            replyBtn.addEventListener('click', function() {
                // Get data from modal
                const subject = document.getElementById('modalMessageSubject').textContent;
                
                // Switch to compose tab
                const composeTab = document.getElementById('compose-tab');
                bootstrap.Tab.getOrCreateInstance(composeTab).show();
                
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
                
                // Close the modal
                const viewMessageModal = document.getElementById('viewMessageModal');
                bootstrap.Modal.getInstance(viewMessageModal).hide();
                
                // Update URL to show compose tab
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('tab', 'compose');
                history.pushState({}, '', currentUrl.toString());
            });
        });
    </script>
</body>
</html>