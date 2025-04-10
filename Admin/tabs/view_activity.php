<?php
session_start();
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";

// Get activity ID from URL
$activity_id = isset($_GET['id']) ? intval($_GET['id']) : null;
$selected_barangay_id = isset($_GET['barangay_id']) ? intval($_GET['barangay_id']) : null;

// Initialize variables
$activity = null;
$error_message = '';
$success_message = '';

// Fetch activity details
try {
    $db = new Database();
    
    // Base query
    $query = "SELECT a.*, 
                     b.name as barangay_name,
                     CONCAT(u.firstname, ' ', u.lastname) as creator_name
              FROM activities a
              LEFT JOIN barangays b ON a.barangay_id = b.barangay_id
              LEFT JOIN users u ON a.created_by = u.user_id
              WHERE a.activity_id = ?";
    
    $params = [$activity_id];
    $activity = $db->fetchOne($query, $params);
    
    if (!$activity) {
        $error_message = "Activity not found.";
    }
    
} catch (Exception $e) {
    $error_message = "Error loading activity: " . $e->getMessage();
}

// Handle activity deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_activity'])) {
    try {
        $db = new Database();
        
        // Delete activity
        $deleteQuery = "DELETE FROM activities WHERE activity_id = ?";
        $db->execute($deleteQuery, [$activity_id]);
        
        // Log activity
        logActivity(
            $_SESSION['user_id'],
            'DELETE_ACTIVITY',
            "Deleted activity ID: $activity_id"
        );
        
        // Redirect to activities page
        $redirect_url = "view_activity.php";
        if ($selected_barangay_id) {
            $redirect_url .= "?barangay_id=$selected_barangay_id";
        }
        header("Location: $redirect_url");
        exit();
        
    } catch (Exception $e) {
        $error_message = "Error deleting activity: " . $e->getMessage();
    }
}

// Close database connection
if (isset($db)) {
    $db->closeConnection();
}

// Activity type mapping
$activity_types = [
    'health_check' => 'Health Check',
    'education' => 'Education',
    'family_development_session' => 'Family Development Session',
    'community_meeting' => 'Community Meeting',
    'other' => 'Other Activity'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($activity['title']) ? htmlspecialchars($activity['title']) : 'Activity Details'; ?> - 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../css/admin.css" rel="stylesheet">
    <style>
        .attachment-thumbnail {
            width: 100px;
            height: 100px;
            object-fit: cover;
            margin: 5px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        .document-icon {
            font-size: 3rem;
            color: #6c757d;
        }
        .gallery-modal img {
            max-height: 80vh;
            width: auto;
            max-width: 100%;
        }
        /* Adjusted main content padding since sidebar is removed */
        .main-content {
            padding: 20px;
            margin-left: 0;
        }
        /* Improved header styling */
        .page-header {
            padding: 15px 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        /* Better card styling */
        .activity-card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .activity-card .card-header {
            border-radius: 10px 10px 0 0 !important;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="page-header bg-primary text-white">
        <div class="container-fluid d-flex justify-content-between align-items-center">
            <div class="logo-container d-flex align-items-center">
                <img src="../../User/assets/pngwing.com (7).png" alt="DSWD Logo" style="height: 50px;">
                <h1 class="h4 mb-0 ms-3">4P's Profiling System - Activity Details</h1>
            </div>
            <div>
                <a href="../add_activities.php<?php echo $selected_barangay_id ? '?barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-light">
                    <i class="bi bi-arrow-left me-2"></i>Back to Activities
                </a>
            </div>
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

            <!-- Activity Details Card -->
            <?php if ($activity): ?>
            <div class="card mb-4 activity-card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3 class="mb-0"><?php echo htmlspecialchars($activity['title']); ?></h3>
                    <div>
                        <a href="edit_activity.php?id=<?php echo $activity_id; ?><?php echo $selected_barangay_id ? '&barangay_id='.$selected_barangay_id : ''; ?>" class="btn btn-sm btn-light me-2">
                            <i class="bi bi-pencil-square me-1"></i> Edit
                        </a>
                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="bi bi-trash me-1"></i> Delete
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h5 class="text-muted">Activity Type</h5>
                                <p class="fs-5"><?php echo $activity_types[$activity['activity_type']] ?? $activity['activity_type']; ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <h5 class="text-muted">Barangay</h5>
                                <p class="fs-5"><?php echo $activity['barangay_name'] ? htmlspecialchars($activity['barangay_name']) : 'All Barangays'; ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <h5 class="text-muted">Created By</h5>
                                <p class="fs-5"><?php echo $activity['creator_name'] ? htmlspecialchars($activity['creator_name']) : 'System'; ?></p>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <h5 class="text-muted">Start Date</h5>
                                <p class="fs-5">
                                    <?php 
                                    if ($activity['start_date'] && $activity['end_date'] && $activity['start_date'] != $activity['end_date']) {
                                        echo date('F j, Y', strtotime($activity['start_date']));
                                    } elseif ($activity['start_date']) {
                                        echo date('F j, Y', strtotime($activity['start_date']));
                                    } else {
                                        echo 'No date specified';
                                    }
                                    ?>
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <h5 class="text-muted">Deadline</h5>
                                <p class="fs-5">
                                    <?php 
                                    if ($activity['end_date']) {
                                        echo date('F j, Y', strtotime($activity['end_date']));
                                        
                                        // Add visual indicator if deadline is today or passed
                                        $today = new DateTime();
                                        $deadline = new DateTime($activity['end_date']);
                                        
                                        if ($deadline < $today) {
                                            echo ' <span class="badge bg-danger">Past Deadline</span>';
                                        } elseif ($deadline->format('Y-m-d') == $today->format('Y-m-d')) {
                                            echo ' <span class="badge bg-warning text-dark">Due Today</span>';
                                        }
                                    } else {
                                        echo 'No deadline specified';
                                    }
                                    ?>
                                </p>
                            </div>
                            
                            <div class="mb-3">
                                <h5 class="text-muted">Created On</h5>
                                <p class="fs-5"><?php echo date('F j, Y \a\t g:i a', strtotime($activity['created_at'])); ?></p>
                            </div>
                            
                            <div class="mb-3">
                                <h5 class="text-muted">Last Updated</h5>
                                <p class="fs-5"><?php echo date('F j, Y \a\t g:i a', strtotime($activity['updated_at'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h5 class="text-muted">Description</h5>
                        <div class="border p-3 rounded bg-light">
                            <?php echo $activity['description'] ? nl2br(htmlspecialchars($activity['description'])) : 'No description provided'; ?>
                        </div>
                    </div>
                    
                    <!-- Attachments Section -->
                    <?php 
                    $attachments = json_decode($activity['attachments'], true);
                    if ($attachments && (count($attachments['images'] ?? []) > 0 || count($attachments['documents'] ?? []) > 0)): 
                    ?>
                    <div class="mb-3">
                        <h5 class="text-muted">Attachments</h5>
                        
                        <!-- Images -->
                        <?php if (!empty($attachments['images'])): ?>
                        <div class="mb-4">
                            <h6>Images</h6>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($attachments['images'] as $image): ?>
                                <div class="me-3 mb-3">
                                    <img src="../../uploads/activities/images/<?php echo htmlspecialchars($image); ?>" 
                                         class="attachment-thumbnail" 
                                         data-bs-toggle="modal" 
                                         data-bs-target="#imageModal"
                                         data-image-src="../../uploads/activities/images/<?php echo htmlspecialchars($image); ?>">
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Documents -->
                        <?php if (!empty($attachments['documents'])): ?>
                        <div>
                            <h6>Documents</h6>
                            <div class="d-flex flex-wrap">
                                <?php foreach ($attachments['documents'] as $document): 
                                    $file_ext = pathinfo($document, PATHINFO_EXTENSION);
                                ?>
                                <div class="me-4 mb-3 text-center">
                                    <div class="document-icon">
                                        <?php if ($file_ext == 'pdf'): ?>
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        <?php elseif (in_array($file_ext, ['doc', 'docx'])): ?>
                                            <i class="bi bi-file-earmark-word"></i>
                                        <?php else: ?>
                                            <i class="bi bi-file-earmark"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="small text-truncate" style="max-width: 100px;"><?php echo htmlspecialchars($document); ?></div>
                                    <a href="../../uploads/activities/documents/<?php echo htmlspecialchars($document); ?>" 
                                       class="btn btn-sm btn-outline-primary mt-1" download>
                                        <i class="bi bi-download me-1"></i> Download
                                    </a>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <p>Are you sure you want to delete this activity? This action cannot be undone.</p>
                        <p class="fw-bold"><?php echo htmlspecialchars($activity['title'] ?? ''); ?></p>
                        <input type="hidden" name="activity_id" value="<?php echo $activity_id; ?>">
                        <?php if ($selected_barangay_id): ?>
                            <input type="hidden" name="barangay_id" value="<?php echo $selected_barangay_id; ?>">
                        <?php endif; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_activity" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i> Delete Activity
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Image Preview Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" id="modalImage" class="img-fluid">
                </div>
                <div class="modal-footer justify-content-center">
                    <a href="#" id="downloadImage" class="btn btn-primary">
                        <i class="bi bi-download me-1"></i> Download
                    </a>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer bg-light py-3">
        <div class="container-fluid">
            <p class="text-center mb-0">&copy; <?php echo date("Y"); ?> Department of Social Welfare and Development - 4P's Profiling System</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Image modal functionality
            const imageModal = document.getElementById('imageModal');
            if (imageModal) {
                imageModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const imageSrc = button.getAttribute('data-image-src');
                    const modalImage = document.getElementById('modalImage');
                    const downloadLink = document.getElementById('downloadImage');
                    
                    modalImage.src = imageSrc;
                    downloadLink.href = imageSrc;
                    downloadLink.download = imageSrc.split('/').pop();
                });
            }
        });
    </script>
</body>
</html>