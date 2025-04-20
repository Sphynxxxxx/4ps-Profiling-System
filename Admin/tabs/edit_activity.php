<?php
session_start();
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to edit an activity.";
    header("Location: ../../login.php");
    exit();
}

// Check if activity ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid activity ID.";
    header("Location: ../add_activities.php");
    exit();
}

$activity_id = intval($_GET['id']);
$user_id = $_SESSION['user_id'];
$selected_barangay_id = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : null;

// Initialize variables
$activity = null;
$error_message = '';
$success_message = '';

// Get barangay list for the form
$barangays = [];
try {
    $db = new Database();
    $query = "SELECT barangay_id, name FROM barangays ORDER BY name";
    $barangays = $db->fetchAll($query);
} catch (Exception $e) {
    $error_message = "Error fetching barangays: " . $e->getMessage();
}

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

// Fetch activity details
try {
    $db = new Database();
    
    $activityQuery = "SELECT a.*, CONCAT(u.firstname, ' ', u.lastname) as creator_name
                     FROM activities a
                     LEFT JOIN users u ON a.created_by = u.user_id
                     WHERE a.activity_id = ?";
    
    $activity = $db->fetchOne($activityQuery, [$activity_id]);
    
    if (!$activity) {
        $_SESSION['error'] = "Activity not found.";
        header("Location: ../add_activities.php" . ($selected_barangay_id ? "?barangay_id=$selected_barangay_id" : ""));
        exit();
    }
    
    // Check if user has permission to edit this activity
    $is_admin = isset($_SESSION['role']) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff');
    
    if (!$is_admin && $activity['created_by'] != $user_id) {
        $_SESSION['error'] = "You don't have permission to edit this activity.";
        header("Location: ../add_activities.php" . ($selected_barangay_id ? "?barangay_id=$selected_barangay_id" : ""));
        exit();
    }
    
    // Parse attachments
    $activity['attachments_array'] = json_decode($activity['attachments'], true) ?: [];
    
} catch (Exception $e) {
    $error_message = "Error fetching activity details: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db = new Database();
        
        // Sanitize and validate inputs
        $title = sanitizeInput($_POST['title']);
        $description = sanitizeInput($_POST['description']);
        $activity_type = sanitizeInput($_POST['activity_type']);
        $start_date = $_POST['start_date'] ? date('Y-m-d', strtotime($_POST['start_date'])) : null;
        $end_date = $_POST['end_date'] ? date('Y-m-d', strtotime($_POST['end_date'])) : null;
        $barangay_id = intval($_POST['barangay_id']);
        
        // Validate required fields
        if (empty($title) || empty($activity_type) || empty($barangay_id)) {
            throw new Exception("Title, Activity Type, and Barangay are required.");
        }
        
        // Get current attachments
        $current_attachments = json_decode($activity['attachments'], true) ?: [];
        
        // Handle document removals
        if (isset($_POST['remove_documents']) && is_array($_POST['remove_documents'])) {
            foreach ($_POST['remove_documents'] as $doc_index) {
                if (isset($current_attachments['documents'][$doc_index])) {
                    $doc_to_remove = $current_attachments['documents'][$doc_index];
                    $doc_path = "../../uploads/activities/documents/" . $doc_to_remove;
                    
                    // Remove the file if it exists
                    if (file_exists($doc_path)) {
                        unlink($doc_path);
                    }
                    
                    // Remove from the array
                    unset($current_attachments['documents'][$doc_index]);
                }
            }
            
            // Re-index the array
            if (isset($current_attachments['documents'])) {
                $current_attachments['documents'] = array_values($current_attachments['documents']);
            }
        }
        
        // Handle image removals
        if (isset($_POST['remove_images']) && is_array($_POST['remove_images'])) {
            foreach ($_POST['remove_images'] as $img_index) {
                if (isset($current_attachments['images'][$img_index])) {
                    $img_to_remove = $current_attachments['images'][$img_index];
                    $img_path = "../../uploads/activities/images/" . $img_to_remove;
                    
                    // Remove the file if it exists
                    if (file_exists($img_path)) {
                        unlink($img_path);
                    }
                    
                    // Remove from the array
                    unset($current_attachments['images'][$img_index]);
                }
            }
            
            // Re-index the array
            if (isset($current_attachments['images'])) {
                $current_attachments['images'] = array_values($current_attachments['images']);
            }
        }
        
        // Handle new document uploads
        $upload_dir = "../../uploads/activities/";
        
        if (!empty($_FILES['documents']['name'][0])) {
            $doc_types = ['pdf', 'doc', 'docx'];
            
            if (!isset($current_attachments['documents'])) {
                $current_attachments['documents'] = [];
            }
            
            foreach ($_FILES['documents']['name'] as $key => $name) {
                if ($_FILES['documents']['error'][$key] == UPLOAD_ERR_OK) {
                    $doc_file = [
                        'name' => $name,
                        'type' => $_FILES['documents']['type'][$key],
                        'tmp_name' => $_FILES['documents']['tmp_name'][$key],
                        'error' => $_FILES['documents']['error'][$key],
                        'size' => $_FILES['documents']['size'][$key]
                    ];
                    $current_attachments['documents'][] = uploadFile($doc_file, $doc_types, $upload_dir . 'documents/');
                }
            }
        }
        
        // Handle new image uploads
        if (!empty($_FILES['images']['name'][0])) {
            $image_types = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!isset($current_attachments['images'])) {
                $current_attachments['images'] = [];
            }
            
            foreach ($_FILES['images']['name'] as $key => $name) {
                if ($_FILES['images']['error'][$key] == UPLOAD_ERR_OK) {
                    $image_file = [
                        'name' => $name,
                        'type' => $_FILES['images']['type'][$key],
                        'tmp_name' => $_FILES['images']['tmp_name'][$key],
                        'error' => $_FILES['images']['error'][$key],
                        'size' => $_FILES['images']['size'][$key]
                    ];
                    $current_attachments['images'][] = uploadFile($image_file, $image_types, $upload_dir . 'images/');
                }
            }
        }
        
        // Convert attachments back to JSON
        $attachments_json = json_encode($current_attachments);
        
        // Update the activity
        $updateQuery = "UPDATE activities SET 
                        title = ?,
                        description = ?,
                        activity_type = ?,
                        start_date = ?,
                        end_date = ?,
                        attachments = ?,
                        barangay_id = ?,
                        updated_at = NOW()
                        WHERE activity_id = ?";
        
        $params = [
            $title,
            $description,
            $activity_type,
            $start_date,
            $end_date,
            $attachments_json,
            $barangay_id,
            $activity_id
        ];
        
        $db->execute($updateQuery, $params);
        
        // Log the activity update
        logActivity(
            $user_id,
            'UPDATE_ACTIVITY',
            "Updated activity ID: $activity_id, Title: $title"
        );
        
        // Set success message
        $success_message = "Activity updated successfully!";
        
        // Refresh the activity data
        $activity = $db->fetchOne($activityQuery, [$activity_id]);
        $activity['attachments_array'] = json_decode($activity['attachments'], true) ?: [];
        
    } catch (Exception $e) {
        $error_message = "Error updating activity: " . $e->getMessage();
    } finally {
        // Close database connection
        if (isset($db)) {
            $db->closeConnection();
        }
    }
}

// Activity type mapping
$activity_types = [
    'health_check' => 'Health Check',
    'education' => 'Education',
    'family_development_session' => 'Family Development Session',
    'community_meeting' => 'Community Meeting',
    'other' => 'Other'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Activity - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../css/admin.css" rel="stylesheet">
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
        .existing-attachment {
            display: flex;
            align-items: center;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            margin-bottom: 10px;
        }
        .attachment-preview {
            width: 60px;
            height: 60px;
            object-fit: cover;
            margin-right: 10px;
        }
        .document-icon {
            font-size: 30px;
            margin-right: 10px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="page-header">
        <div class="logo-container">
            <img src="../../User/assets/pngwing.com (7).png" alt="DSWD Logo">
            <h1>4P's Profiling System - Edit Activity</h1>
        </div>
    </header>
    
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

            <!-- Activity Edit Form -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0"><i class="bi bi-pencil-square me-2"></i>Edit Activity</h3>
                </div>
                <div class="card-body">
                    <form action="" method="POST" enctype="multipart/form-data" id="activityForm">
                        <div class="mb-3">
                            <label class="form-label">Barangay <span class="text-danger">*</span></label>
                            <input type="hidden" id="barangay_id" name="barangay_id" value="<?php echo $activity['barangay_id']; ?>">
                            
                            <?php
                            // Find the barangay name for display
                            $barangay_name = "Unknown";
                            foreach ($barangays as $barangay) {
                                if ($barangay['barangay_id'] == $activity['barangay_id']) {
                                    $barangay_name = htmlspecialchars($barangay['name']);
                                    break;
                                }
                            }
                            ?>
                            
                            <div class="form-control bg-light" readonly><?php echo $barangay_name; ?></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Activity Title <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                    value="<?php echo htmlspecialchars($activity['title']); ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="activity_type" class="form-label">Activity Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="activity_type" name="activity_type" required>
                                    <option value="">Select Activity Type</option>
                                    <?php foreach ($activity_types as $value => $label): ?>
                                        <option value="<?php echo $value; ?>" <?php echo ($activity['activity_type'] == $value) ? 'selected' : ''; ?>>
                                            <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Activity Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"><?php echo htmlspecialchars($activity['description']); ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date" class="form-label">Start Date</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" 
                                    value="<?php echo $activity['start_date']; ?>">
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="end_date" class="form-label">End Date</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" 
                                    value="<?php echo $activity['end_date']; ?>">
                            </div>
                        </div>

                        <!-- Existing Attachments -->
                        <?php if (!empty($activity['attachments_array'])): ?>
                            <div class="mb-4">
                                <h5>Current Attachments</h5>
                                
                                <!-- Documents -->
                                <?php if (!empty($activity['attachments_array']['documents'])): ?>
                                    <div class="mb-3">
                                        <h6>Documents</h6>
                                        <?php foreach ($activity['attachments_array']['documents'] as $index => $document): ?>
                                            <div class="existing-attachment">
                                                <div class="document-icon">
                                                    <?php 
                                                    $file_ext = pathinfo($document, PATHINFO_EXTENSION);
                                                    if ($file_ext == 'pdf'): 
                                                    ?>
                                                        <i class="bi bi-file-earmark-pdf"></i>
                                                    <?php elseif (in_array($file_ext, ['doc', 'docx'])): ?>
                                                        <i class="bi bi-file-earmark-word"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-file-earmark"></i>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <p class="mb-0"><?php echo htmlspecialchars($document); ?></p>
                                                    <div>
                                                        <a href="../../uploads/activities/documents/<?php echo $document; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                            <i class="bi bi-eye me-1"></i> View
                                                        </a>
                                                        <a href="../../uploads/activities/documents/<?php echo $document; ?>" class="btn btn-sm btn-outline-success" download>
                                                            <i class="bi bi-download me-1"></i> Download
                                                        </a>
                                                    </div>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="remove_documents[]" value="<?php echo $index; ?>" id="remove_doc_<?php echo $index; ?>">
                                                    <label class="form-check-label" for="remove_doc_<?php echo $index; ?>">
                                                        Remove
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <!-- Images -->
                                <?php if (!empty($activity['attachments_array']['images'])): ?>
                                    <div>
                                        <h6>Images</h6>
                                        <?php foreach ($activity['attachments_array']['images'] as $index => $image): ?>
                                            <div class="existing-attachment">
                                                <img src="../../uploads/activities/images/<?php echo $image; ?>" alt="Activity Image" class="attachment-preview">
                                                <div class="flex-grow-1">
                                                    <p class="mb-0"><?php echo htmlspecialchars($image); ?></p>
                                                    <div>
                                                        <a href="../../uploads/activities/images/<?php echo $image; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                            <i class="bi bi-eye me-1"></i> View Full Size
                                                        </a>
                                                    </div>
                                                </div>
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="remove_images[]" value="<?php echo $index; ?>" id="remove_img_<?php echo $index; ?>">
                                                    <label class="form-check-label" for="remove_img_<?php echo $index; ?>">
                                                        Remove
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- New File Uploads -->
                        <div class="mb-3">
                            <label for="documents" class="form-label">Upload New Documents (PDF, DOC)</label>
                            <input type="file" class="form-control" id="documents" name="documents[]" multiple 
                                accept=".pdf,.doc,.docx">
                        </div>

                        <div class="mb-3">
                            <label for="images" class="form-label">Upload New Images</label>
                            <input type="file" class="form-control" id="images" name="images[]" multiple 
                                accept="image/jpeg,image/png,image/gif">
                            <div id="imagePreview" class="preview-container"></div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <a href="../add_activities.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" 
                               class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-2"></i>Back to Activities
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save Changes
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
            // Image preview functionality for new uploads
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
                
                // Validate barangay
                if (barangayId.value === '') {
                    barangayId.classList.add('is-invalid');
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
                    alert('Please fill in all required fields.');
                }
            });
        });
    </script>
</body>
</html>