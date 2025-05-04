<?php
session_start();
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../../login.php");
    exit();
}

// Check if event ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid event ID.";
    header("Location: ../calendar.php");
    exit();
}

$event_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$selected_barangay_id = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : null;

// Set current page for navigation highlighting
$current_page = 'calendar';

// Initialize variables
$event = null;
$error_message = '';
$success_message = '';

// Fetch event details
try {
    $db = new Database();
    
    $eventQuery = "SELECT e.*, b.name as barangay_name, CONCAT(u.firstname, ' ', u.lastname) as creator_name
                  FROM events e
                  LEFT JOIN barangays b ON e.barangay_id = b.barangay_id
                  LEFT JOIN users u ON e.created_by = u.user_id
                  WHERE e.event_id = ?";
    
    $event = $db->fetchOne($eventQuery, [$event_id]);
    
    if (!$event) {
        $_SESSION['error'] = "Event not found.";
        header("Location: ../calendar.php" . ($selected_barangay_id ? "?barangay_id=$selected_barangay_id" : ""));
        exit();
    }
    
    $db->closeConnection();
} catch (Exception $e) {
    $error_message = "Error fetching event details: " . $e->getMessage();
}

// Handle event deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_event'])) {
    try {
        $db = new Database();
        
        // Check if user has permission to delete this event
        $is_admin = isset($_SESSION['role']) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff');
        
        if (!$is_admin && $event['created_by'] != $user_id) {
            $_SESSION['error'] = "You don't have permission to delete this event.";
            header("Location: ../calendar.php" . ($selected_barangay_id ? "?barangay_id=$selected_barangay_id" : ""));
            exit();
        }
        
        // Delete the event
        $deleteEventQuery = "DELETE FROM events WHERE event_id = ?";
        $db->execute($deleteEventQuery, [$event_id]);
        
        // Log the activity
        logActivity(
            $user_id,
            'DELETE_EVENT',
            "Deleted event ID: $event_id, Title: {$event['title']}"
        );
        
        $_SESSION['success'] = "Event has been successfully deleted.";
        header("Location: ../calendar.php" . ($selected_barangay_id ? "?barangay_id=$selected_barangay_id" : ""));
        exit();
        
    } catch (Exception $e) {
        $error_message = "Error deleting event: " . $e->getMessage();
    } finally {
        if (isset($db)) {
            $db->closeConnection();
        }
    }
}

// Get statistics
try {
    $db = new Database();
    
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
    
    $db->closeConnection();
} catch (Exception $e) {
    // Set defaults if there's a database error
    $pending_verifications = 0;
    $upcoming_events = 0;
    $unread_messages = 0;
}

// Format dates for display
$event_date = date('F d, Y', strtotime($event['event_date']));
$event_time = !empty($event['event_time']) ? date('g:i A', strtotime($event['event_time'])) : 'No specific time';
$created_date = date('F d, Y g:i A', strtotime($event['created_at']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Event - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../css/admin.css" rel="stylesheet">
    <style>
        .event-header {
            padding: 1.5rem;
            background-color: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 1.5rem;
        }
        
        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            color: #6c757d;
            font-size: 0.9rem;
        }
        
        .meta-item i {
            margin-right: 0.5rem;
        }
        
        .event-description {
            background-color: #fff;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
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
            <h1>4P's Profiling System - Event Details</h1>
        </div>
    </header>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'dashboard' ? 'active' : ''; ?>" href="../admin_dashboard.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'verification' ? 'active' : ''; ?>" href="../participant_verification.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-person-check"></i> Parent Leader Verification
                    <?php if($pending_verifications > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $pending_verifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'parent_leaders' ? 'active' : ''; ?>" href="../parent_leaders.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-people"></i> List of Parent Leaders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'beneficiaries' ? 'active' : ''; ?>" href="../beneficiaries.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-people"></i> Beneficiaries
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'activities' ? 'active' : ''; ?>" href="../add_activities.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-arrow-repeat"></i> Activities
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active <?php echo $current_page == 'calendar' ? 'active' : ''; ?>" href="../calendar.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-calendar3"></i> Calendar
                    <?php if($upcoming_events > 0): ?>
                    <span class="badge bg-primary rounded-pill ms-auto"><?php echo $upcoming_events; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'messages' ? 'active' : ''; ?>" href="../messages.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-chat-left-text"></i> Messages
                    <?php if($unread_messages > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <!--<li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reports' ? 'active' : ''; ?>" href="../reports.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i> Reports
                </a>
            </li>-->
            <li class="nav-item">
                <a class="nav-link" href="../../admin.php">
                    <i class="bi bi-box-arrow-right"></i> Back
                </a>
            </li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="container-fluid">
            <!-- Error or Success Messages -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- Navigation buttons -->
            <div class="mb-3">
                <a href="../calendar.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back to Calendar
                </a>
                
                <?php 
                // Check if user has permission to edit/delete this event
                $is_admin = isset($_SESSION['role']) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff');
                $can_edit = $is_admin || $event['created_by'] == $user_id;
                
                if ($can_edit): 
                ?>
                <div class="float-end">
                    <a href="edit_event.php?id=<?php echo $event_id; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-primary me-2">
                        <i class="bi bi-pencil me-1"></i> Edit Event
                    </a>
                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#deleteEventModal">
                        <i class="bi bi-trash me-1"></i> Delete Event
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <!-- Event Header -->
            <div class="event-header">
                <h2 class="mb-2"><?php echo htmlspecialchars($event['title']); ?></h2>
                
                <span class="badge bg-info text-white">Event</span>
                <?php 
                $today = date('Y-m-d');
                if ($event['event_date'] < $today): ?>
                    <span class="badge bg-secondary">Past Event</span>
                <?php elseif ($event['event_date'] == $today): ?>
                    <span class="badge bg-success">Today</span>
                <?php else: ?>
                    <span class="badge bg-primary">Upcoming</span>
                <?php endif; ?>
                
                <div class="event-meta">
                    <div class="meta-item">
                        <i class="bi bi-calendar-date"></i>
                        <span><?php echo $event_date; ?></span>
                    </div>
                    
                    <div class="meta-item">
                        <i class="bi bi-clock"></i>
                        <span><?php echo $event_time; ?></span>
                    </div>
                    
                    <?php if (!empty($event['location'])): ?>
                    <div class="meta-item">
                        <i class="bi bi-geo-alt"></i>
                        <span><?php echo htmlspecialchars($event['location']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="meta-item">
                        <i class="bi bi-building"></i>
                        <span>Barangay: <?php echo htmlspecialchars($event['barangay_name'] ?? 'Not specified'); ?></span>
                    </div>
                    
                    <div class="meta-item">
                        <i class="bi bi-person"></i>
                        <span>Created by: Administrator</span>
                    </div>
                    
                    <div class="meta-item">
                        <i class="bi bi-calendar-plus"></i>
                        <span>Created on: <?php echo $created_date; ?></span>
                    </div>
                </div>
            </div>

            <!-- Event Description -->
            <?php if (!empty($event['description'])): ?>
            <div class="event-description">
                <h5 class="mb-3">Description</h5>
                <div>
                    <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Additional content can be added here -->
        </div>
    </div>

    <!-- Delete Event Modal -->
    <?php if ($can_edit): ?>
    <div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteEventModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <p>Are you sure you want to delete this event? This action cannot be undone.</p>
                        <p class="fw-bold"><?php echo htmlspecialchars($event['title']); ?></p>
                        <p><?php echo $event_date; ?> at <?php echo $event_time; ?></p>
                        <input type="hidden" name="delete_event" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i> Delete Event
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
        });
    </script>
</body>
</html>