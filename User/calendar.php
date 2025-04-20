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
$first_day_of_week = date('w', $first_day); // 0 (Sunday) to 6 (Saturday)
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

    // Fetch activities for this month
    $activities = [];
    $activitiesQuery = "SELECT a.*, b.name as barangay_name 
                        FROM activities a
                        LEFT JOIN barangays b ON a.barangay_id = b.barangay_id
                        WHERE ((YEAR(a.start_date) = ? AND MONTH(a.start_date) = ?) 
                            OR (YEAR(a.end_date) = ? AND MONTH(a.end_date) = ?)
                            OR (a.start_date <= LAST_DAY(?) AND a.end_date >= ?))";
    
    $params = [$year, $month, $year, $month, "$year-$month-01", "$year-$month-01"];
    
    // For regular users, only show activities in their barangay
    if ($userRole != 'admin' && $userRole != 'staff' && $userBarangayId) {
        $activitiesQuery .= " AND a.barangay_id = ?";
        $params[] = $userBarangayId;
    }
    
    $activitiesQuery .= " ORDER BY a.start_date, a.title";
    
    $activitiesData = $db->fetchAll($activitiesQuery, $params);
    
    // Organize activities by date
    foreach ($activitiesData as $activity) {
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
    
    // Fetch events for this month
    $events = [];
    $eventsQuery = "SELECT e.*, b.name as barangay_name
                    FROM events e
                    LEFT JOIN barangays b ON e.barangay_id = b.barangay_id
                    WHERE YEAR(e.event_date) = ? AND MONTH(e.event_date) = ?";
    
    $eventsParams = [$year, $month];
    
    // For regular users, only show events in their barangay
    if ($userRole != 'admin' && $userRole != 'staff' && $userBarangayId) {
        $eventsQuery .= " AND e.barangay_id = ?";
        $eventsParams[] = $userBarangayId;
    }
    
    $eventsQuery .= " ORDER BY e.event_date, e.event_time";
    
    $eventsData = $db->fetchAll($eventsQuery, $eventsParams);
    
    // Organize events by date
    foreach ($eventsData as $event) {
        $event_date = new DateTime($event['event_date']);
        $day = $event_date->format('j');
        
        if (!isset($events[$day])) {
            $events[$day] = [];
        }
        $events[$day][] = $event;
    }
    
    $db->closeConnection();
    
} catch (Exception $e) {
    // Log error
    error_log("Calendar Error: " . $e->getMessage());
    
    $user = [
        'firstname' => $_SESSION['firstname'] ?? 'User',
        'role' => $_SESSION['role'] ?? 'resident',
        'profile_image' => 'assets/images/profile-placeholder.png'
    ];
    
    $activities = [];
    $events = [];
    $userBarangayId = null;
}

// Activity type styling
$activity_type_colors = [
    'health_check' => 'success',
    'education' => 'info',
    'family_development_session' => 'warning',
    'community_meeting' => 'primary',
    'other' => 'secondary',
    'event' => 'danger' 
];

// Activity type labels
$activity_type_labels = [
    'health_check' => 'Health Check',
    'education' => 'Education',
    'family_development_session' => 'Family Development Session',
    'community_meeting' => 'Community Meeting',
    'other' => 'Other',
    'event' => 'Events' 

];

// Helper function to determine activity status
function getActivityStatus($startDate, $endDate) {
    $today = date('Y-m-d');
    $startDate = date('Y-m-d', strtotime($startDate));
    $endDate = date('Y-m-d', strtotime($endDate));
    
    if ($today < $startDate) {
        return ['status' => 'upcoming', 'class' => 'info', 'label' => 'Upcoming'];
    } elseif ($today > $endDate) {
        return ['status' => 'completed', 'class' => 'secondary', 'label' => 'Completed'];
    } else {
        return ['status' => 'active', 'class' => 'success', 'label' => 'Active'];
    }
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar | DSWD 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/dashboard.css">
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
                        <a class="nav-link" href="members.php">
                            <i class="bi bi-people"></i> Members
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="messages.php">
                            <i class="bi bi-chat-left-text"></i> Messages
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="calendar.php">
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
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <nav aria-label="breadcrumb">
                                <ol class="breadcrumb mb-1">
                                    <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Calendar</li>
                                </ol>
                            </nav>
                            <h1 class="h3 mb-0 text-gray-800">
                                <i class="bi bi-calendar3 me-2"></i> Activity Calendar
                            </h1>
                        </div>
                    </div>
                    
                    <!-- Calendar Header -->
                    <div class="calendar-header d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="h4 mb-0"><?php echo $month_name . ' ' . $year; ?></h2>
                        </div>
                        
                        <div class="btn-group">
                            <a href="calendar.php?month=<?php echo $prev_month; ?>&year=<?php echo $prev_year; ?>" class="btn btn-outline-primary">
                                <i class="bi bi-chevron-left"></i> Previous
                            </a>
                            <a href="calendar.php?month=<?php echo date('n'); ?>&year=<?php echo date('Y'); ?>" class="btn btn-outline-secondary">
                                Today
                            </a>
                            <a href="calendar.php?month=<?php echo $next_month; ?>&year=<?php echo $next_year; ?>" class="btn btn-outline-primary">
                                Next <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Activity Type Legend -->
                    <div class="mb-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Legend</h5>
                                <div class="d-flex flex-wrap gap-3">
                                    <?php foreach ($activity_type_colors as $type => $color): ?>
                                        <div class="d-flex align-items-center me-3">
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
                                            
                                            // Display events for this day
                                            if (isset($events[$day_counter]) && !empty($events[$day_counter])) {
                                                $day_events = $events[$day_counter];
                                                
                                                echo "<div class='mt-2 border-top pt-1'>";
                                                echo "<small class='text-muted'>Events:</small>";
                                                
                                                foreach ($day_events as $event) {
                                                    $event_time = !empty($event['event_time']) ? date('g:i A', strtotime($event['event_time'])) : '';
                                                    
                                                    echo "<div class='activity-item bg-danger bg-opacity-10 text-danger'>";
                                                    echo "<a href='control/view_event.php?id=" . $event['event_id'] . "' class='text-decoration-none text-danger'>";
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
    
    <!-- Event Details Modal -->
    <div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="eventTitle"></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-calendar me-2 text-info"></i>
                            <span id="eventDate"></span>
                        </div>
                        
                        <div class="d-flex align-items-center mb-2" id="eventTimeContainer">
                            <i class="bi bi-clock me-2 text-info"></i>
                            <span id="eventTime"></span>
                        </div>
                        
                        <div class="d-flex align-items-center mb-2" id="eventLocationContainer">
                            <i class="bi bi-geo-alt me-2 text-info"></i>
                            <span id="eventLocation"></span>
                        </div>
                        
                        <div class="d-flex align-items-center mb-2">
                            <i class="bi bi-building me-2 text-info"></i>
                            <span id="eventBarangay"></span>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="eventDescriptionContainer">
                        <h6>Description</h6>
                        <p id="eventDescription" class="mb-0"></p>
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
            
            // Handle Event Modal
            const eventModal = document.getElementById('eventModal');
            if (eventModal) {
                eventModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    
                    // Get event details from data attributes
                    const title = button.getAttribute('data-event-title');
                    const description = button.getAttribute('data-event-description');
                    const date = button.getAttribute('data-event-date');
                    const time = button.getAttribute('data-event-time');
                    const location = button.getAttribute('data-event-location');
                    const barangay = button.getAttribute('data-event-barangay');
                    
                    // Update modal content
                    document.getElementById('eventTitle').textContent = title;
                    document.getElementById('eventDate').textContent = date;
                    
                    // Handle optional fields
                    const timeContainer = document.getElementById('eventTimeContainer');
                    if (time && time.trim() !== '') {
                        document.getElementById('eventTime').textContent = time;
                        timeContainer.style.display = 'flex';
                    } else {
                        timeContainer.style.display = 'none';
                    }
                    
                    const locationContainer = document.getElementById('eventLocationContainer');
                    if (location && location.trim() !== '') {
                        document.getElementById('eventLocation').textContent = location;
                        locationContainer.style.display = 'flex';
                    } else {
                        locationContainer.style.display = 'none';
                    }
                    
                    document.getElementById('eventBarangay').textContent = barangay || 'Not specified';
                    
                    const descriptionContainer = document.getElementById('eventDescriptionContainer');
                    if (description && description.trim() !== '') {
                        document.getElementById('eventDescription').textContent = description;
                        descriptionContainer.style.display = 'block';
                    } else {
                        descriptionContainer.style.display = 'none';
                    }
                });
            }
        });
    </script>
</body>
</html>