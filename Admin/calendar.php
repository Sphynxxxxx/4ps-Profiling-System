<?php
session_start();
require_once "../backend/connections/config.php";
require_once "../backend/connections/database.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Set the current page for navigation highlighting
$current_page = 'calendar';

// Get the selected barangay ID
$selected_barangay_id = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : null;

if ($selected_barangay_id) {
    $_SESSION['default_barangay_id'] = $selected_barangay_id;
} elseif (isset($_SESSION['default_barangay_id'])) {
    $selected_barangay_id = $_SESSION['default_barangay_id'];
}

// Get current month and year, or use the provided ones from URL
$month = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$year = isset($_GET['year']) ? intval($_GET['year']) : intval(date('Y'));

// Ensure valid month and year
if ($month < 1 || $month > 12) {
    $month = intval(date('m'));
}
if ($year < 2000 || $year > 2100) {
    $year = intval(date('Y'));
}

// Get first day of the month
$first_day = mktime(0, 0, 0, $month, 1, $year);
$first_day_of_week = date('w', $first_day); 
$days_in_month = date('t', $first_day);
$month_name = date('F', $first_day);

// Calculate previous and next month/year
$prev_month = $month - 1;
$prev_year = $year;
if ($prev_month < 1) {
    $prev_month = 12;
    $prev_year--;
}

$next_month = $month + 1;
$next_year = $year;
if ($next_month > 12) {
    $next_month = 1;
    $next_year++;
}

// Initialize variables
$error_message = '';
$success_message = '';

// Fetch available barangays for the dropdown
$barangays = [];
try {
    $db = new Database();
    $query = "SELECT barangay_id, name FROM barangays ORDER BY name";
    $barangays = $db->fetchAll($query);
    $db->closeConnection();
} catch (Exception $e) {
    $error_message = "Error fetching barangays: " . $e->getMessage();
}

// Fetch activities for this month
$activities = [];
try {
    $db = new Database();
    
    // Base query
    $activities_query = "SELECT a.*, b.name as barangay_name 
                        FROM activities a
                        LEFT JOIN barangays b ON a.barangay_id = b.barangay_id
                        WHERE ((YEAR(a.start_date) = ? AND MONTH(a.start_date) = ?) 
                           OR (YEAR(a.end_date) = ? AND MONTH(a.end_date) = ?)
                           OR (a.start_date <= LAST_DAY(?) AND a.end_date >= ?))";
    
    $params = [$year, $month, $year, $month, "$year-$month-01", "$year-$month-01"];
    
    // Add barangay filter if selected
    if ($selected_barangay_id) {
        $activities_query .= " AND a.barangay_id = ?";
        $params[] = $selected_barangay_id;
    }
    
    $activities_query .= " ORDER BY a.start_date, a.title";
    
    $activities_data = $db->fetchAll($activities_query, $params);
    
    // Organize activities by date
    foreach ($activities_data as $activity) {
        $start_date = new DateTime($activity['start_date']);
        $end_date = new DateTime($activity['end_date']);
        
        // For multi-day activities, add to all days within the range
        $current_date = clone $start_date;
        while ($current_date <= $end_date) {
            $day = $current_date->format('j');
            $activity_month = $current_date->format('n');
            $activity_year = $current_date->format('Y');
            
            // Only add to the current month's calendar
            if ($activity_month == $month && $activity_year == $year) {
                if (!isset($activities[$day])) {
                    $activities[$day] = [];
                }
                $activities[$day][] = $activity;
            }
            
            $current_date->modify('+1 day');
        }
    }
    
    $db->closeConnection();
} catch (Exception $e) {
    $error_message = "Error fetching activities: " . $e->getMessage();
}

// Activity type mapping for colors
$activity_type_colors = [
    'health_check' => 'success',
    'education' => 'info',
    'family_development_session' => 'warning',
    'community_meeting' => 'primary',
    'event' => 'danger',
    'other' => 'secondary'
];

// Activity type labels
$activity_type_labels = [
    'health_check' => 'Health Check',
    'education' => 'Education',
    'family_development_session' => 'Family Development Session',
    'community_meeting' => 'Community Meeting',
    'event' => 'Event',
    'other' => 'Other'
];

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

$events = [];
try {
    $db = new Database();
    
    // Base query
    $events_query = "SELECT e.*, b.name as barangay_name, CONCAT(u.firstname, ' ', u.lastname) as creator_name
                    FROM events e
                    LEFT JOIN barangays b ON e.barangay_id = b.barangay_id
                    LEFT JOIN users u ON e.created_by = u.user_id
                    WHERE YEAR(e.event_date) = ? AND MONTH(e.event_date) = ?";
    
    $params = [$year, $month];
    
    // Add barangay filter if selected
    if ($selected_barangay_id) {
        $events_query .= " AND e.barangay_id = ?";
        $params[] = $selected_barangay_id;
    }
    
    $events_query .= " ORDER BY e.event_date, e.event_time";
    
    $events_data = $db->fetchAll($events_query, $params);
    
    // Organize events by date
    foreach ($events_data as $event) {
        $event_date = new DateTime($event['event_date']);
        $day = $event_date->format('j');
        
        if (!isset($events[$day])) {
            $events[$day] = [];
        }
        $events[$day][] = $event;
    }
    
    $db->closeConnection();
} catch (Exception $e) {
    $error_message .= " Error fetching events: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Calendar - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        .calendar-header {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        
        .calendar {
            width: 100%;
            border-collapse: collapse;
        }
        
        .calendar th {
            background-color: #f5f5f5;
            color: #333;
            text-align: center;
            padding: 10px;
            border: 1px solid #ddd;
        }
        
        .calendar td {
            height: 120px;
            vertical-align: top;
            padding: 5px;
            border: 1px solid #ddd;
        }
        
        .calendar .day-number {
            font-weight: bold;
            font-size: 1.2rem;
            margin-bottom: 5px;
            color: #555;
        }
        
        .calendar .today {
            background-color: #f0f8ff;
        }
        
        .calendar .other-month {
            background-color: #f9f9f9;
            color: #aaa;
        }
        
        .activity-dot {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 5px;
        }
        
        .activity-item {
            font-size: 0.85rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
            padding: 2px 5px;
            border-radius: 3px;
        }
        
        .view-more {
            font-size: 0.8rem;
            color: #007bff;
            cursor: pointer;
        }
        
        .tooltip-inner {
            max-width: 300px;
            text-align: left;
        }
        
        @media (max-width: 768px) {
            .calendar td {
                height: 80px;
                padding: 3px;
            }
            
            .calendar .day-number {
                font-size: 1rem;
            }
            
            .activity-item {
                font-size: 0.7rem;
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
            <img src="../User/assets/pngwing.com (7).png" alt="DSWD Logo">
            <h1>4P's Profiling System - Activity Calendar</h1>
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
                <a class="nav-link active <?php echo $current_page == 'calendar' ? 'active' : ''; ?>" href="calendar.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
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

            <!-- Calendar Header -->
            <div class="calendar-header d-flex justify-content-between align-items-center">
                <div>
                    <h3 class="mb-0"><?php echo $month_name . ' ' . $year; ?></h3>
                    <?php if ($selected_barangay_id): ?>
                        <?php 
                        $selected_barangay_name = "All Barangays";
                        foreach ($barangays as $barangay) {
                            if ($barangay['barangay_id'] == $selected_barangay_id) {
                                $selected_barangay_name = $barangay['name'];
                                break;
                            }
                        }
                        ?>
                        <p class="text-muted mb-0">Activities for: <?php echo htmlspecialchars($selected_barangay_name); ?></p>
                    <?php else: ?>
                        <p class="text-muted mb-0">Activities for all barangays</p>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex">
                    <div class="btn-group me-3">
                        <a href="calendar.php?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-outline-primary">
                            <i class="bi bi-chevron-left"></i> Previous
                        </a>
                        <a href="calendar.php?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-outline-secondary">
                            Today
                        </a>
                        <a href="calendar.php?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-outline-primary">
                            Next <i class="bi bi-chevron-right"></i>
                        </a>
                    </div>
                    
                    <div class="d-flex">
                        <a href="add_activities.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-primary me-2">
                            <i class="bi bi-plus-circle me-1"></i> Add Activity
                        </a>
                        <a href="tabs/add_event.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i> Add Event
                        </a>
                    </div>

                </div>
            </div>
            
            <!-- Barangay Filter -->
            <div class="mb-4">
                <form id="barangayForm" action="calendar.php" method="GET" class="row g-2">
                    <input type="hidden" name="month" value="<?php echo $month; ?>">
                    <input type="hidden" name="year" value="<?php echo $year; ?>">
                    
                    <div class="col-md-4">
                        <select class="form-select" id="barangay_filter" name="barangay_id" onchange="this.form.submit()">
                            <option value="">All Barangays</option>
                            <?php foreach ($barangays as $barangay): ?>
                                <option value="<?php echo $barangay['barangay_id']; ?>" <?php echo ($selected_barangay_id == $barangay['barangay_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($barangay['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Activity Type Legend -->
            <div class="mb-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Activity Types</h5>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($activity_type_colors as $type => $color): ?>
                                <div class="d-flex align-items-center">
                                    <div class="activity-dot bg-<?php echo $color; ?>"></div>
                                    <span><?php echo $activity_type_labels[$type]; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Calendar -->
            <div class="table-responsive">
                <table class="calendar">
                    <thead>
                        <tr>
                            <th>Sunday</th>
                            <th>Monday</th>
                            <th>Tuesday</th>
                            <th>Wednesday</th>
                            <th>Thursday</th>
                            <th>Friday</th>
                            <th>Saturday</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $day_counter = 1;
                        $day_of_week = $first_day_of_week;
                        
                        // Get current date for highlighting today
                        $current_date = date('Y-m-d');
                        $current_day = date('j');
                        $current_month = date('n');
                        $current_year = date('Y');
                        
                        // Create calendar rows
                        for ($row = 0; $row < 6; $row++) {
                            echo "<tr>";
                            
                            for ($col = 0; $col < 7; $col++) {
                                // Empty cells before the first day of month
                                if ($row == 0 && $col < $first_day_of_week) {
                                    echo "<td class='other-month'></td>";
                                }
                                // Empty cells after the last day of month
                                elseif ($day_counter > $days_in_month) {
                                    echo "<td class='other-month'></td>";
                                }
                                // Days of the month
                                else {
                                    // Check if this is today
                                    $is_today = ($day_counter == $current_day && $month == $current_month && $year == $current_year);
                                    $today_class = $is_today ? ' today' : '';
                                    
                                    echo "<td class='$today_class'>";
                                    echo "<div class='day-number'>" . $day_counter . "</div>";
                                    
                                    // Display activities for this day
                                    if (isset($activities[$day_counter]) && !empty($activities[$day_counter])) {
                                        $day_activities = $activities[$day_counter];
                                        $max_display = 3; // Maximum activities to display before showing "more" link
                                        
                                        // Sort activities by start time (if available)
                                        usort($day_activities, function($a, $b) {
                                            return strcmp($a['start_date'], $b['start_date']);
                                        });
                                        
                                        $activity_count = count($day_activities);
                                        $displayed_count = min($activity_count, $max_display);
                                        
                                        for ($i = 0; $i < $displayed_count; $i++) {
                                            $activity = $day_activities[$i];
                                            $type_color = $activity_type_colors[$activity['activity_type']] ?? 'secondary';
                                            
                                            echo "<div class='activity-item bg-" . $type_color . " bg-opacity-10 text-" . $type_color . "'>";
                                            echo "<a href='control/view_activity.php?id=" . $activity['activity_id'] . "' class='text-decoration-none text-" . $type_color . "'>";
                                            echo htmlspecialchars($activity['title']);
                                            echo "</a></div>";
                                        }
                                        
                                        // Show "more" link if there are more activities
                                        if ($activity_count > $max_display) {
                                            $more_count = $activity_count - $max_display;
                                            echo "<div class='view-more' data-bs-toggle='modal' data-bs-target='#dayActivitiesModal' ";
                                            echo "data-date='" . $year . "-" . sprintf('%02d', $month) . "-" . sprintf('%02d', $day_counter) . "' ";
                                            echo "data-day='" . $day_counter . "' ";
                                            echo "data-month='" . $month_name . "' ";
                                            echo "data-year='" . $year . "'>";
                                            echo "+ " . $more_count . " more";
                                            echo "</div>";
                                        }
                                    }

                                    if (isset($events[$day_counter]) && !empty($events[$day_counter])) {
                                        $day_events = $events[$day_counter];
                                        
                                        echo "<div class='mt-2 border-top pt-1'>";
                                        echo "<small class='text-muted'>Events:</small>";
                                        
                                        foreach ($day_events as $event) {
                                            $event_time = !empty($event['event_time']) ? date('g:i A', strtotime($event['event_time'])) : '';
                                            
                                            echo "<div class='activity-item bg-danger bg-opacity-10 text-danger'>";
                                            echo "<a href='tabs/view_event.php?id=" . $event['event_id'] . "' class='text-decoration-none text-danger'>";
                                            echo "<i class='bi bi-calendar-event me-1'></i>";
                                            if (!empty($event_time)) {
                                                echo "<small>" . $event_time . "</small> ";
                                            }
                                            echo htmlspecialchars($event['title']);
                                            echo "</a></div>";
                                        }
                                        
                                        echo "</div>";
                                    }
                                    
                                    
                                    echo "</td>";
                                    $day_counter++;
                                }
                            }
                            
                            echo "</tr>";
                            
                            // Stop creating rows if we've displayed all days
                            if ($day_counter > $days_in_month) {
                                break;
                            }
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Day Activities Modal -->
    <div class="modal fade" id="dayActivitiesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="dayActivitiesTitle">Activities for <span id="modalDate"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="modalActivitiesList">
                        <!-- Activities will be loaded dynamically here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
            
            // Handle "View More" modal
            const dayActivitiesModal = document.getElementById('dayActivitiesModal');
            if (dayActivitiesModal) {
                dayActivitiesModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const date = button.getAttribute('data-date');
                    const day = button.getAttribute('data-day');
                    const month = button.getAttribute('data-month');
                    const year = button.getAttribute('data-year');
                    
                    document.getElementById('modalDate').textContent = `${month} ${day}, ${year}`;
                    
                    // Find all activities for this day
                    const activities = <?php echo json_encode($activities); ?>;
                    const activityTypeColors = <?php echo json_encode($activity_type_colors); ?>;
                    const activityTypeLabels = <?php echo json_encode($activity_type_labels); ?>;
                    
                    let activitiesList = '';
                    
                    if (activities[day] && activities[day].length > 0) {
                        activities[day].forEach(function(activity) {
                            const typeColor = activityTypeColors[activity.activity_type] || 'secondary';
                            const typeLabel = activityTypeLabels[activity.activity_type] || 'Other';
                            
                            activitiesList += `
                                <div class="card mb-2">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-1">
                                            <a href="control/view_activity.php?id=${activity.activity_id}" class="text-decoration-none">
                                                ${activity.title}
                                            </a>
                                        </h6>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="badge bg-${typeColor} bg-opacity-25 text-${typeColor}">${typeLabel}</span>
                                            <small class="text-muted">${activity.barangay_name || 'N/A'}</small>
                                        </div>
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        activitiesList = '<p class="text-muted">No activities found for this day.</p>';
                    }
                    
                    document.getElementById('modalActivitiesList').innerHTML = activitiesList;
                });
            }
            
            // Enable Bootstrap tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    html: true,
                    container: 'body'
                });
            });
            
            // Add current date to header
            const currentDateHeader = document.getElementById('currentDate');
            if (currentDateHeader) {
                const today = new Date();
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                currentDateHeader.textContent = today.toLocaleDateString('en-US', options);
            }
        });
    </script>
</body>
</html>