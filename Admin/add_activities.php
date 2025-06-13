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

// Get selected parent leader ID from URL parameter
$selected_parent_leader_id = isset($_GET['parent_leader_id']) ? intval($_GET['parent_leader_id']) : null;

// Initialize variables
$error_message = '';
$success_message = '';

// Function to sanitize input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input));
}

// Function to handle file upload
function uploadFile($file, $allowed_types, $upload_dir) {
    // Ensure upload directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Generate a unique filename
    $file_name = uniqid() . '_' . basename($file['name']);
    $target_path = $upload_dir . $file_name;

    // Check file type
    $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_type, $allowed_types)) {
        throw new Exception("Invalid file type. Allowed types: " . implode(', ', $allowed_types));
    }

    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new Exception("File is too large. Maximum size is 10MB.");
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return $file_name;
    } else {
        throw new Exception("File upload failed.");
    }
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
        $activity_type = sanitizeInput($_POST['activity_type']);
        $start_date = $_POST['start_date'] ? date('Y-m-d', strtotime($_POST['start_date'])) : null;
        $end_date = $_POST['end_date'] ? date('Y-m-d', strtotime($_POST['end_date'])) : null;

        // Validate required fields
        if (empty($title) || empty($activity_type)) {
            throw new Exception("Title and Activity Type are required.");
        }

        // Prepare file uploads
        $attachments = [];
        $upload_dir = "../uploads/activities/";

        // Handle document uploads (PDF, DOC)
        if (!empty($_FILES['documents']['name'][0])) {
            $doc_types = ['pdf', 'doc', 'docx'];
            foreach ($_FILES['documents']['name'] as $key => $name) {
                if ($_FILES['documents']['error'][$key] == UPLOAD_ERR_OK) {
                    $doc_file = [
                        'name' => $name,
                        'type' => $_FILES['documents']['type'][$key],
                        'tmp_name' => $_FILES['documents']['tmp_name'][$key],
                        'error' => $_FILES['documents']['error'][$key],
                        'size' => $_FILES['documents']['size'][$key]
                    ];
                    $attachments['documents'][] = uploadFile($doc_file, $doc_types, $upload_dir . 'documents/');
                }
            }
        }

        // Handle image uploads
        if (!empty($_FILES['images']['name'][0])) {
            $image_types = ['jpg', 'jpeg', 'png', 'gif'];
            foreach ($_FILES['images']['name'] as $key => $name) {
                if ($_FILES['images']['error'][$key] == UPLOAD_ERR_OK) {
                    $image_file = [
                        'name' => $name,
                        'type' => $_FILES['images']['type'][$key],
                        'tmp_name' => $_FILES['images']['tmp_name'][$key],
                        'error' => $_FILES['images']['error'][$key],
                        'size' => $_FILES['images']['size'][$key]
                    ];
                    $attachments['images'][] = uploadFile($image_file, $image_types, $upload_dir . 'images/');
                }
            }
        }

        // Prepare attachment JSON
        $attachments_json = json_encode($attachments);

        // Insert activity into database (removed created_by field)
        $insertQuery = "INSERT INTO activities (
            title, 
            description, 
            activity_type, 
            start_date, 
            end_date, 
            attachments, 
            barangay_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $title, 
            $description, 
            $activity_type, 
            $start_date, 
            $end_date, 
            $attachments_json, 
            $post_barangay_id
        ];

        $activity_id = $db->insertAndGetId($insertQuery, $params);

        // Log activity (made user_id optional)
        if (isset($_SESSION['user_id']) && function_exists('logActivity')) {
            logActivity(
                $_SESSION['user_id'], 
                'CREATE_ACTIVITY', 
                "Created new activity: $title for barangay ID: $post_barangay_id"
            );
        }

        // Close database connection
        $db->closeConnection();

        // Set success message
        $success_message = "Activity created successfully for the selected barangay!";

        // Redirect to view activity or stay on the page with success message
        // header("Location: view_activity.php?id=" . $activity_id);
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
    // Use the fetchAll method that's actually defined in your Database class
    $barangays = $db->fetchAll($query);
    $db->closeConnection();
} catch (Exception $e) {
    $error_message = "Error fetching barangays: " . $e->getMessage();
}

// Fetch activities for the selected barangay (removed creator join and field)
$activities = [];
$activities_query_params = [];
$activities_query = "SELECT a.*, b.name as barangay_name 
                    FROM activities a
                    LEFT JOIN barangays b ON a.barangay_id = b.barangay_id
                    WHERE 1=1 ";

if ($selected_barangay_id) {
    $activities_query .= "AND a.barangay_id = ? ";
    $activities_query_params[] = $selected_barangay_id;
}

$activities_query .= "ORDER BY a.created_at DESC";

try {
    $db = new Database();
    if (!empty($activities_query_params)) {
        $activities = $db->fetchAll($activities_query, $activities_query_params);
    } else {
        $activities = $db->fetchAll($activities_query);
    }
    $db->closeConnection();
} catch (Exception $e) {
    $error_message = "Error fetching activities: " . $e->getMessage();
}

// Group activities by type for summary
$activity_type_counts = [
    'health_check' => 0,
    'education' => 0,
    'family_development_session' => 0,
    'community_meeting' => 0,
    'other' => 0
];

$upcoming_activities = 0;
$active_activities = 0;
$completed_activities = 0;

$today = date('Y-m-d');

foreach ($activities as $activity) {
    // Count by type
    if (isset($activity_type_counts[$activity['activity_type']])) {
        $activity_type_counts[$activity['activity_type']]++;
    } else {
        $activity_type_counts['other']++;
    }
    
    // Count by status - handle null dates
    $start_date = $activity['start_date'];
    $end_date = $activity['end_date'];
    
    if ($start_date && $today < $start_date) {
        $upcoming_activities++;
    } elseif ($end_date && $today > $end_date) {
        $completed_activities++;
    } else {
        $active_activities++;
    }
}

// Activity type mapping
$activity_type_labels = [
    'health_check' => 'Health Check',
    'education' => 'Education',
    'family_development_session' => 'Family Development Session',
    'community_meeting' => 'Community Meeting',
    'other' => 'Other'
];

// Activity type styles
$activity_type_styles = [
    'health_check' => 'success',
    'education' => 'info',
    'family_development_session' => 'warning',
    'community_meeting' => 'primary',
    'other' => 'secondary'
];

// Initialize statistics variables
$pending_verifications = 0;
$upcoming_events = 0;
$unread_messages = 0;

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
    <title>Add New Activity - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="css/admin.css" rel="stylesheet">
    <style>
        .preview-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        .preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            object-fit: cover;
        }
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .preview-close {
            position: absolute;
            top: 0;
            right: 0;
            background: rgba(0,0,0,0.5);
            color: white;
            border: none;
            padding: 2px 5px;
            cursor: pointer;
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
            <h1>4P's Profiling System - Add New Activity</h1>
        </div>
    </header>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'dashboard') ? 'active' : ''; ?>" href="admin_dashboard.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'verification') ? 'active' : ''; ?>" href="participant_verification.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-person-check"></i> Parent Leader Verification
                    <?php if($pending_verifications > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $pending_verifications; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'parent_leaders') ? 'active' : ''; ?>" href="parent_leaders.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-people"></i> List of Parent Leaders
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'beneficiaries') ? 'active' : ''; ?>" href="beneficiaries.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-people"></i> Beneficiaries
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="add_activities.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-arrow-repeat"></i> Activities
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'calendar') ? 'active' : ''; ?>" href="calendar.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-calendar3"></i> Calendar
                    <?php if($upcoming_events > 0): ?>
                    <span class="badge bg-primary rounded-pill ms-auto"><?php echo $upcoming_events; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo (isset($current_page) && $current_page == 'messages') ? 'active' : ''; ?>" href="messages.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-chat-left-text"></i> Messages
                    <?php if($unread_messages > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
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

            <!-- Activity Creation Form -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create New Activity</h3>
                </div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data" id="activityForm">
                        <!-- Replace the barangay dropdown with a hidden input and display -->
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
                            <div class="form-text">Activities will be associated with this barangay.</div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Activity Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                    placeholder="Enter activity title" value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="activity_type" class="form-label">Activity Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="activity_type" name="activity_type" required>
                                    <option value="">Select Activity Type</option>
                                    <option value="health_check" <?php echo (isset($_POST['activity_type']) && $_POST['activity_type'] == 'health_check') ? 'selected' : ''; ?>>Health Check</option>
                                    <option value="education" <?php echo (isset($_POST['activity_type']) && $_POST['activity_type'] == 'education') ? 'selected' : ''; ?>>Education</option>
                                    <option value="family_development_session" <?php echo (isset($_POST['activity_type']) && $_POST['activity_type'] == 'family_development_session') ? 'selected' : ''; ?>>Family Development Session</option>
                                    <option value="community_meeting" <?php echo (isset($_POST['activity_type']) && $_POST['activity_type'] == 'community_meeting') ? 'selected' : ''; ?>>Community Meeting</option>
                                    <option value="other" <?php echo (isset($_POST['activity_type']) && $_POST['activity_type'] == 'other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Activity Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4" 
                                placeholder="Enter detailed description of the activity"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                    value="<?php echo isset($_POST['start_date']) ? htmlspecialchars($_POST['start_date']) : ''; ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                    value="<?php echo isset($_POST['end_date']) ? htmlspecialchars($_POST['end_date']) : ''; ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="documents" class="form-label">Upload Documents (PDF, DOC)</label>
                            <input type="file" class="form-control" id="documents" name="documents[]" multiple 
                                accept=".pdf,.doc,.docx">
                        </div>

                        <div class="mb-3">
                            <label for="images" class="form-label">Upload Images</label>
                            <input type="file" class="form-control" id="images" name="images[]" multiple 
                                accept="image/jpeg,image/png,image/gif">
                            <div id="imagePreview" class="preview-container"></div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="admin_dashboard.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" 
                               class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-2"></i>Create Activity
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3 class="mb-0"><i class="bi bi-list-check me-2"></i>Activity List</h3>
                    <button class="btn btn-sm btn-light" type="button" data-bs-toggle="collapse" data-bs-target="#activitiesCollapse" aria-expanded="true" aria-controls="activitiesCollapse">
                        <i class="bi bi-chevron-down"></i>
                    </button>
                </div>
                
                <div class="collapse show" id="activitiesCollapse">
                    <div class="card-body">
                        <!-- Activity Summary Cards -->
                        <div class="row mb-4">
                            <div class="col-md-8">
                                <div class="card border-0 bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title">Activities by Type</h5>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <?php foreach ($activity_type_counts as $type => $count): ?>
                                                <?php if ($count > 0): ?>
                                                <div class="badge bg-<?php echo $activity_type_styles[$type]; ?> bg-opacity-25 text-<?php echo $activity_type_styles[$type]; ?> p-2">
                                                    <span class="fw-bold fs-6"><?php echo $count; ?></span>
                                                    <span class="ms-1"><?php echo $activity_type_labels[$type]; ?></span>
                                                </div>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-0 bg-light">
                                    <div class="card-body">
                                        <h5 class="card-title">Activity Status</h5>
                                        <div class="d-flex flex-wrap gap-2 mt-2">
                                            <div class="badge bg-info bg-opacity-25 text-info p-2">
                                                <span class="fw-bold fs-6"><?php echo $upcoming_activities; ?></span>
                                                <span class="ms-1">Upcoming</span>
                                            </div>
                                            <div class="badge bg-success bg-opacity-25 text-success p-2">
                                                <span class="fw-bold fs-6"><?php echo $active_activities; ?></span>
                                                <span class="ms-1">Active</span>
                                            </div>
                                            <div class="badge bg-secondary bg-opacity-25 text-secondary p-2">
                                                <span class="fw-bold fs-6"><?php echo $completed_activities; ?></span>
                                                <span class="ms-1">Completed</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (empty($activities)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> No activities found for the selected barangay. Create a new activity using the form above.
                            </div>
                        <?php else: ?>
                            <!-- Activities Table -->
                            <div class="table-responsive">
                                <table class="table table-striped table-hover border">
                                    <thead class="table-primary">
                                        <tr>
                                            <th>Title</th>
                                            <th>Type</th>
                                            <th>Date Range</th>
                                            <th>Status</th>
                                            <th>Barangay</th>
                                            <th>Created On</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($activities as $activity): ?>
                                            <?php
                                            // Determine activity status
                                            $start_date = $activity['start_date'];
                                            $end_date = $activity['end_date'];
                                            
                                            if ($start_date && $today < $start_date) {
                                                $status = ['label' => 'Upcoming', 'class' => 'info'];
                                            } elseif ($end_date && $today > $end_date) {
                                                $status = ['label' => 'Completed', 'class' => 'secondary'];
                                            } else {
                                                $status = ['label' => 'Active', 'class' => 'success'];
                                            }
                                            
                                            $type_class = $activity_type_styles[$activity['activity_type']] ?? 'secondary';
                                            $type_label = $activity_type_labels[$activity['activity_type']] ?? ucfirst(str_replace('_', ' ', $activity['activity_type']));
                                            ?>
                                            <tr>
                                                <td>
                                                    <a href="tabs/view_activity.php?id=<?php echo $activity['activity_id']; ?>" class="fw-bold text-decoration-none">
                                                        <?php echo htmlspecialchars($activity['title']); ?>
                                                    </a>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $type_class; ?>">
                                                        <?php echo $type_label; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($activity['start_date'] && $activity['end_date']): ?>
                                                        <?php echo date('M d, Y', strtotime($activity['start_date'])); ?> - 
                                                        <?php echo date('M d, Y', strtotime($activity['end_date'])); ?>
                                                    <?php elseif ($activity['start_date']): ?>
                                                        <?php echo date('M d, Y', strtotime($activity['start_date'])); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No date specified</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?php echo $status['class']; ?>">
                                                        <?php echo $status['label']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo htmlspecialchars($activity['barangay_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($activity['created_at'])); ?></td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="tabs/view_activity.php?id=<?php echo $activity['activity_id']; ?>" class="btn btn-sm btn-primary">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <a href="tabs/edit_activity.php?id=<?php echo $activity['activity_id']; ?>" class="btn btn-sm btn-warning">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal" 
                                                                data-activity-id="<?php echo $activity['activity_id']; ?>" 
                                                                data-activity-title="<?php echo htmlspecialchars($activity['title']); ?>">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
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

            <!-- Delete Modal -->
            <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form action="tabs/delete_activity.php" method="POST">
                            <div class="modal-body">
                                <p>Are you sure you want to delete this activity? This action cannot be undone.</p>
                                <p class="fw-bold" id="activityTitleToDelete"></p>
                                <input type="hidden" name="activity_id" id="activityIdToDelete">
                                <?php if ($selected_barangay_id): ?>
                                    <input type="hidden" name="barangay_id" value="<?php echo $selected_barangay_id; ?>">
                                <?php endif; ?>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">
                                    <i class="bi bi-trash me-1"></i> Delete Activity
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
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
            
            if (menuToggle && sidebar) {
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
            }

            // Image preview functionality
            const imageInput = document.getElementById('images');
            const imagePreview = document.getElementById('imagePreview');

            if (imageInput && imagePreview) {
                imageInput.addEventListener('change', function(event) {
                    // Clear previous previews
                    imagePreview.innerHTML = '';

                    // Create previews for selected images
                    Array.from(event.target.files).forEach((file, index) => {
                        if (file.type.startsWith('image/')) {
                            const reader = new FileReader();
                            
                            reader.onload = function(e) {
                                const previewItem = document.createElement('div');
                                previewItem.classList.add('preview-item');
                                
                                const img = document.createElement('img');
                                img.src = e.target.result;
                                img.alt = 'Image Preview';
                                
                                const closeBtn = document.createElement('button');
                                closeBtn.innerHTML = '&times;';
                                closeBtn.classList.add('preview-close');
                                closeBtn.type = 'button';
                                closeBtn.addEventListener('click', function() {
                                    previewItem.remove();
                                    // Remove file from input
                                    const dataTransfer = new DataTransfer();
                                    Array.from(imageInput.files)
                                        .filter((f, i) => i !== index)
                                        .forEach(f => dataTransfer.items.add(f));
                                    imageInput.files = dataTransfer.files;
                                });
                                
                                previewItem.appendChild(img);
                                previewItem.appendChild(closeBtn);
                                imagePreview.appendChild(previewItem);
                            };
                            
                            reader.readAsDataURL(file);
                        }
                    });
                });
            }

            // Form validation
            const form = document.getElementById('activityForm');
            if (form) {
                form.addEventListener('submit', function(event) {
                    const title = document.getElementById('title');
                    const activityType = document.getElementById('activity_type');
                    const startDate = document.getElementById('start_date');
                    const endDate = document.getElementById('end_date');
                    const barangayId = document.getElementById('barangay_id');

                    // Reset previous error styles
                    [title, activityType, barangayId, startDate, endDate].forEach(field => {
                        if (field) field.classList.remove('is-invalid');
                    });

                    let isValid = true;

                    // Validate barangay selection
                    if (!barangayId || barangayId.value === '') {
                        if (barangayId) barangayId.classList.add('is-invalid');
                        isValid = false;
                    }

                    // Validate title
                    if (!title || title.value.trim() === '') {
                        if (title) title.classList.add('is-invalid');
                        isValid = false;
                    }

                    // Validate activity type
                    if (!activityType || activityType.value === '') {
                        if (activityType) activityType.classList.add('is-invalid');
                        isValid = false;
                    }

                    // Validate date range
                    if (startDate && endDate && startDate.value && endDate.value) {
                        const start = new Date(startDate.value);
                        const end = new Date(endDate.value);
                        
                        if (start > end) {
                            startDate.classList.add('is-invalid');
                            endDate.classList.add('is-invalid');
                            alert('Start date must be before or equal to end date.');
                            event.preventDefault();
                            return;
                        }
                    }

                    // Prevent form submission if validation fails
                    if (!isValid) {
                        event.preventDefault();
                        alert('Please fill in all required fields including selecting a barangay.');
                    }
                });
            }

            // Delete modal functionality
            const deleteModal = document.getElementById('deleteModal');
            if (deleteModal) {
                deleteModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const activityId = button.getAttribute('data-activity-id');
                    const activityTitle = button.getAttribute('data-activity-title');
                    
                    const modalTitle = deleteModal.querySelector('#activityTitleToDelete');
                    const modalInput = deleteModal.querySelector('#activityIdToDelete');
                    
                    if (modalTitle) modalTitle.textContent = activityTitle;
                    if (modalInput) modalInput.value = activityId;
                });
            }

            // Auto-add barangay_id to forms
            const barangayId = <?php echo $selected_barangay_id ? $selected_barangay_id : 'null'; ?>;
            
            if (barangayId) {
                const forms = document.querySelectorAll('form');
                forms.forEach(form => {
                    // Skip forms that already handle barangay_id
                    if (form.id === 'barangayForm' || form.querySelector('[name="barangay_id"]')) {
                        return;
                    }
                    
                    // Only add to forms that submit to the same site (not external)
                    if (!form.action || form.action.includes(window.location.hostname)) {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'barangay_id';
                        input.value = barangayId;
                        form.appendChild(input);
                    }
                });
            }
        });
    </script>
</body>
</html>