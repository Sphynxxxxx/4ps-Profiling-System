<?php
session_start();
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Set current page for navigation highlighting
$current_page = 'calendar';

// Get selected barangay ID
$selected_barangay_id = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : null;

if ($selected_barangay_id) {
    $_SESSION['default_barangay_id'] = $selected_barangay_id;
} elseif (isset($_SESSION['default_barangay_id'])) {
    $selected_barangay_id = $_SESSION['default_barangay_id'];
}

// Initialize variables
$error_message = '';
$success_message = '';

// Function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input));
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Start database connection
        $db = new Database();
        
        // Get the barangay_id from POST data
        $post_barangay_id = isset($_POST['barangay_id']) ? intval($_POST['barangay_id']) : $selected_barangay_id;
        
        // Validate that we have a barangay_id
        if (empty($post_barangay_id)) {
            throw new Exception("No barangay selected. Please select a barangay.");
        }
        
        // Sanitize and validate inputs
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $event_date = $_POST['event_date'] ? date('Y-m-d', strtotime($_POST['event_date'])) : null;
        $event_time = $_POST['event_time'] ? date('H:i:s', strtotime($_POST['event_time'])) : null;
        $location = sanitizeInput($_POST['location']);
        
        // Validate required fields
        if (empty($title) || empty($event_date)) {
            throw new Exception("Title and Event Date are required.");
        }
        
        // Insert event into database
        $insertQuery = "INSERT INTO events (
            title,
            description,
            event_date,
            event_time,
            location,
            barangay_id,
            created_by,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $params = [
            $title,
            $description,
            $event_date,
            $event_time,
            $location,
            $post_barangay_id,
            $_SESSION['user_id']
        ];
        
        $event_id = $db->insertAndGetId($insertQuery, $params);
        
        // Log activity
        logActivity(
            $_SESSION['user_id'],
            'CREATE_EVENT',
            "Created new event: $title for barangay ID: $post_barangay_id"
        );
        
        // Close database connection
        $db->closeConnection();
        
        // Set success message
        $success_message = "Event created successfully!";
        
        // Optional: Redirect to calendar
        // header("Location: calendar.php?month=" . date('n', strtotime($event_date)) . "&year=" . date('Y', strtotime($event_date)) . ($selected_barangay_id ? "&barangay_id=$selected_barangay_id" : ""));
        // exit();
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

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

// Get upcoming events count for notification badge
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Event - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../css/admin.css" rel="stylesheet">
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
            <h1>4P's Profiling System - Add New Event</h1>
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

            <!-- Event Creation Form -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="bi bi-calendar-plus me-2"></i>Create New Event</h3>
                </div>
                <div class="card-body">
                    <form action="" method="POST" id="eventForm">
                        <!-- Barangay Selection (Hidden if already selected) -->
                        <div class="mb-3">
                            <label class="form-label">Barangay <span class="text-danger">*</span></label>
                            <input type="hidden" id="barangay_id" name="barangay_id" value="<?php echo $selected_barangay_id; ?>">
                            
                            <?php
                            // Find the barangay name for display
                            $barangay_name = "Not Selected";
                            foreach ($barangays as $barangay) {
                                if ($barangay['barangay_id'] == $selected_barangay_id) {
                                    $barangay_name = htmlspecialchars($barangay['name']);
                                    break;
                                }
                            }
                            ?>
                            
                            <div class="form-control bg-light"><?php echo $barangay_name; ?></div>
                            <div class="form-text">Events will be associated with this barangay.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <label for="title" class="form-label">Event Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                    placeholder="Enter event title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Event Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3" 
                                placeholder="Enter event description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="event_date" class="form-label">Event Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="event_date" name="event_date" required
                                    value="<?php echo isset($_POST['event_date']) ? htmlspecialchars($_POST['event_date']) : date('Y-m-d'); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="event_time" class="form-label">Event Time</label>
                                <input type="time" class="form-control" id="event_time" name="event_time"
                                    value="<?php echo isset($_POST['event_time']) ? htmlspecialchars($_POST['event_time']) : '09:00'; ?>">
                                <div class="form-text">Optional. Leave blank if the event doesn't have a specific time.</div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="location" class="form-label">Event Location</label>
                            <input type="text" class="form-control" id="location" name="location" 
                                placeholder="Enter event location" value="<?php echo isset($_POST['location']) ? htmlspecialchars($_POST['location']) : ''; ?>">
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="../calendar.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" 
                               class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Calendar
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-calendar-plus me-2"></i>Create Event
                            </button>
                        </div>
                    </form>
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
            
            // Form validation
            const form = document.getElementById('eventForm');
            form.addEventListener('submit', function(event) {
                const title = document.getElementById('title');
                const eventDate = document.getElementById('event_date');
                
                // Reset previous error styles
                title.classList.remove('is-invalid');
                eventDate.classList.remove('is-invalid');
                
                let isValid = true;
                
                // Validate title
                if (title.value.trim() === '') {
                    title.classList.add('is-invalid');
                    isValid = false;
                }
                
                // Validate event date
                if (eventDate.value === '') {
                    eventDate.classList.add('is-invalid');
                    isValid = false;
                }
                
                // Prevent form submission if validation fails
                if (!isValid) {
                    event.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html>