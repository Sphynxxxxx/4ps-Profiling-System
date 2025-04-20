<?php
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";
require_once "../../backend/connections/helpers.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if event ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid event ID.";
    header("Location: calendar.php");
    exit();
}

$event_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];

// Initialize variables
$event = null;
$error_message = '';
$success_message = '';

try {
    $db = new Database();
    
    // Get user information
    $userSql = "SELECT * FROM users WHERE user_id = ?";
    $user = $db->fetchOne($userSql, [$user_id]);
    
    $userRole = $user['role'] ?? 'resident';
    
    // Get current user's barangay
    $userBarangayId = null;
    if (isset($user['barangay'])) {
        $userBarangayId = intval($user['barangay']);
    } else {
        try {
            $beneficiaryQuery = "SELECT barangay_id FROM beneficiaries WHERE user_id = ?";
            $beneficiaryResult = $db->fetchOne($beneficiaryQuery, [$user_id]);
            if ($beneficiaryResult) {
                $userBarangayId = $beneficiaryResult['barangay_id'];
            }
        } catch (Exception $e) {
            error_log("Error fetching user's barangay: " . $e->getMessage());
        }
    }
    
    // Fetch event details
    $eventQuery = "SELECT e.*, b.name as barangay_name, CONCAT(u.firstname, ' ', u.lastname) as creator_name
                  FROM events e
                  LEFT JOIN barangays b ON e.barangay_id = b.barangay_id
                  LEFT JOIN users u ON e.created_by = u.user_id
                  WHERE e.event_id = ?";
    
    $event = $db->fetchOne($eventQuery, [$event_id]);
    
    if (!$event) {
        $_SESSION['error'] = "Event not found.";
        header("Location: calendar.php");
        exit();
    }
    
    // Check if the user has access to view this event
    // Admin and staff can view all events, regular users can only view events in their barangay
    if ($userRole != 'admin' && $userRole != 'staff' && $userBarangayId != $event['barangay_id']) {
        $_SESSION['error'] = "You don't have permission to view this event.";
        header("Location: calendar.php");
        exit();
    }
    
    $db->closeConnection();
} catch (Exception $e) {
    // Log error
    error_log("View Event Error: " . $e->getMessage());
    
    $_SESSION['error'] = "An error occurred while retrieving the event details.";
    header("Location: calendar.php");
    exit();
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
    <title>View Event | DSWD 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/dashboard.css">
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
    <!-- Top Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <button class="btn btn-primary sidebar-toggle d-lg-none" type="button">
                <i class="bi bi-list"></i>
            </button>
            
            <a class="navbar-brand d-flex align-items-center" href="#">
                <img src="../assets/pngwing.com (7).png" alt="DSWD Logo">
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
                        <a class="nav-link" href="../dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../profile.php">
                            <i class="bi bi-person"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../activities.php">
                            <i class="bi bi-calendar2-check"></i> Activities
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../members.php">
                            <i class="bi bi-people"></i> Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../messages.php">
                            <i class="bi bi-chat-left-text"></i> Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="../calendar.php">
                            <i class="bi bi-calendar3"></i> Calendar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../settings.php">
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
                                    <li class="breadcrumb-item"><a href="calendar.php">Calendar</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">View Event</li>
                                </ol>
                            </nav>
                            <h1 class="h3 mb-0 text-gray-800">
                                Event Details
                            </h1>
                        </div>
                        <div class="mt-2 mt-md-0">
                            <a href="../calendar.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back to Calendar
                            </a>
                        </div>
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
                            
                            <?php if (!empty($event['event_time'])): ?>
                            <div class="meta-item">
                                <i class="bi bi-clock"></i>
                                <span><?php echo $event_time; ?></span>
                            </div>
                            <?php endif; ?>
                            
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
                    
                    <!-- Other Upcoming Events Card -->
                    <?php
                    // Fetch other upcoming events from the same barangay
                    try {
                        $db = new Database();
                        $otherEventsQuery = "SELECT e.event_id, e.title, e.event_date, e.event_time 
                                           FROM events e 
                                           WHERE e.barangay_id = ? 
                                             AND e.event_id != ? 
                                             AND e.event_date >= CURDATE() 
                                           ORDER BY e.event_date ASC 
                                           LIMIT 5";
                        $otherEvents = $db->fetchAll($otherEventsQuery, [$event['barangay_id'], $event_id]);
                        $db->closeConnection();
                        
                        if (!empty($otherEvents)):
                    ?>
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0">
                                <i class="bi bi-calendar-event me-2 text-primary"></i>
                                Other Upcoming Events in <?php echo htmlspecialchars($event['barangay_name']); ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <?php foreach ($otherEvents as $otherEvent): ?>
                                <a href="view_event.php?id=<?php echo $otherEvent['event_id']; ?>" class="list-group-item list-group-item-action">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?php echo htmlspecialchars($otherEvent['title']); ?></h6>
                                            <small class="text-muted">
                                                <?php echo date('F d, Y', strtotime($otherEvent['event_date'])); ?>
                                                <?php if (!empty($otherEvent['event_time'])): ?>
                                                    at <?php echo date('g:i A', strtotime($otherEvent['event_time'])); ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-primary rounded-pill">
                                            <i class="bi bi-calendar"></i>
                                        </span>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php 
                        endif;
                    } catch (Exception $e) {
                        error_log("Error fetching other events: " . $e->getMessage());
                    }
                    ?>
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