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

        // Insert activity into database
        $insertQuery = "INSERT INTO activities (
            title, 
            description, 
            activity_type, 
            start_date, 
            end_date, 
            attachments, 
            barangay_id, 
            created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        $params = [
            $title, 
            $description, 
            $activity_type, 
            $start_date, 
            $end_date, 
            $attachments_json, 
            $post_barangay_id, // Use the validated barangay_id 
            $_SESSION['user_id']
        ];

        $activity_id = $db->insertAndGetId($insertQuery, $params);

        // Log activity
        logActivity(
            $_SESSION['user_id'], 
            'CREATE_ACTIVITY', 
            "Created new activity: $title for barangay ID: $post_barangay_id"
        );

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

try {
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
                <a class="nav-link active <?php echo $current_page == 'activities' ? 'active' : ''; ?>" href="add_activities.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
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
                <a class="nav-link <?php echo $current_page == 'messages' ? 'active' : ''; ?>" href="messages.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-chat-left-text"></i> Messages
                    <?php if($unread_messages > 0): ?>
                    <span class="badge bg-danger rounded-pill ms-auto"><?php echo $unread_messages; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'reports' ? 'active' : ''; ?>" href="reports.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
                    <i class="bi bi-file-earmark-text"></i> Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page == 'settings' ? 'active' : ''; ?>" href="settings.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>">
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

            <!-- Activity Creation Form -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="bi bi-plus-circle me-2"></i>Create New Activity</h3>
                </div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data" id="activityForm">
                        <!-- Barangay Selection Dropdown -->
                        <div class="mb-3">
                            <label for="barangay_id" class="form-label">Select Barangay <span class="text-danger">*</span></label>
                            <select class="form-select" id="barangay_id" name="barangay_id" required>
                                <option value="">-- Select Barangay --</option>
                                <?php foreach ($barangays as $barangay): ?>
                                    <option value="<?php echo $barangay['barangay_id']; ?>" <?php echo ($selected_barangay_id == $barangay['barangay_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($barangay['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Activities will be associated with the selected barangay only.</div>
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
            // Image preview functionality
            const imageInput = document.getElementById('images');
            const imagePreview = document.getElementById('imagePreview');

            imageInput.addEventListener('change', function(event) {
                // Clear previous previews
                imagePreview.innerHTML = '';

                // Create previews for selected images
                for (let file of event.target.files) {
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
                        closeBtn.addEventListener('click', function() {
                            previewItem.remove();
                            // Update file input to remove the specific file
                            const dataTransfer = new DataTransfer();
                            Array.from(imageInput.files)
                                .filter(f => f.name !== file.name)
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

            // Form validation
            const form = document.getElementById('activityForm');
            form.addEventListener('submit', function(event) {
                const title = document.getElementById('title');
                const activityType = document.getElementById('activity_type');
                const startDate = document.getElementById('start_date');
                const endDate = document.getElementById('end_date');
                const barangayId = document.getElementById('barangay_id');

                // Reset previous error styles
                title.classList.remove('is-invalid');
                activityType.classList.remove('is-invalid');
                barangayId.classList.remove('is-invalid');

                let isValid = true;

                // Validate barangay selection
                if (barangayId.value === '') {
                    barangayId.classList.add('is-invalid');
                    isValid = false;
                }

                // Validate title
                if (title.value.trim() === '') {
                    title.classList.add('is-invalid');
                    isValid = false;
                }

                // Validate activity type
                if (activityType.value === '') {
                    activityType.classList.add('is-invalid');
                    isValid = false;
                }

                // Optional: Validate date range
                if (startDate.value && endDate.value) {
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
        });

        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            const barangayId = <?php echo $selected_barangay_id ? $selected_barangay_id : 'null'; ?>;
            
            if (barangayId) {
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