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

// Handle marking participant as completed
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_completed'])) {
    try {
        $db = new Database();
        $user_id = intval($_POST['user_id']);
        $activity_id = intval($_POST['activity_id']);
        
        // Check if record exists in activity_participants
        $checkSql = "SELECT * FROM activity_participants WHERE activity_id = ? AND user_id = ?";
        $existing = $db->fetchOne($checkSql, [$activity_id, $user_id]);
        
        if ($existing) {
            // Update existing record
            $updateSql = "UPDATE activity_participants SET attended = 1, attendance_date = NOW() WHERE activity_id = ? AND user_id = ?";
            $db->execute($updateSql, [$activity_id, $user_id]);
        } else {
            // Insert new record
            $insertSql = "INSERT INTO activity_participants (activity_id, user_id, status, attended, attendance_date, created_at) 
                         VALUES (?, ?, 'yes', 1, NOW(), NOW())";
            $db->execute($insertSql, [$activity_id, $user_id]);
        }
        
        $success_message = "Participant marked as completed successfully.";
        
    } catch (Exception $e) {
        $error_message = "Error marking participant as completed: " . $e->getMessage();
    }
}

// Fetch activity details and submissions
try {
    $db = new Database();
    
    // Base query for activity details
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
    
    // Fetch submissions for this activity with attendance status
    $submissions = [];
    if ($activity_id) {
        $submissionsQuery = "SELECT s.*, 
                            CONCAT(u.firstname, ' ', u.lastname) as user_name,
                            u.email,
                            u.user_id,
                            (SELECT name FROM barangays WHERE barangay_id = 
                                (SELECT barangay_id FROM beneficiaries WHERE user_id = s.user_id LIMIT 1)
                            ) as barangay_name,
                            ap.attended,
                            ap.attendance_date
                        FROM activity_submissions s
                        JOIN users u ON s.user_id = u.user_id
                        LEFT JOIN activity_participants ap ON s.activity_id = ap.activity_id AND s.user_id = ap.user_id
                        WHERE s.activity_id = ?
                        ORDER BY s.created_at DESC";
        
        $submissions = $db->fetchAll($submissionsQuery, [$activity_id]);
    }
    
    // Get the total count of submissions
    $submissionCount = count($submissions);
    
    // Get the count by response type
    $responseTypeCounts = [
        'participation' => 0,
        'feedback' => 0,
        'question' => 0
    ];
    
    // Get the count by attendance status
    $attendanceStatusCounts = [
        'yes' => 0,
        'maybe' => 0,
        'no' => 0,
        'completed' => 0
    ];
    
    foreach ($submissions as $submission) {
        $responseTypeCounts[$submission['response_type']]++;
        if ($submission['attended'] == 1) {
            $attendanceStatusCounts['completed']++;
        } else {
            $attendanceStatusCounts[$submission['attendance_status']]++;
        }
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
        .avatar-circle {
            width: 40px;
            height: 40px;
            background-color: #6c757d;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .avatar-initial {
            font-size: 1.2rem;
        }

        .table th {
            font-weight: 600;
            color: #495057;
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
                    <!-- Activity details content -->
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
            
            <!-- Activity Submissions and Participants Row -->
            <div class="row">
                <!-- Activity Submissions Column -->
                <div class="col-lg-8">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0 text-primary">
                                <i class="bi bi-file-earmark-text-fill me-2"></i>Activity Submissions (<?php echo $submissionCount; ?>)
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if ($submissionCount > 0): ?>
                                <!-- Summary Statistics -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="card border-0 bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">Response Types</h6>
                                                <div class="d-flex">
                                                    <div class="me-3">
                                                        <span class="badge bg-success"><?php echo $responseTypeCounts['participation']; ?></span>
                                                        <span class="small ms-1">Participation</span>
                                                    </div>
                                                    <div class="me-3">
                                                        <span class="badge bg-info"><?php echo $responseTypeCounts['feedback']; ?></span>
                                                        <span class="small ms-1">Feedback</span>
                                                    </div>
                                                    <div>
                                                        <span class="badge bg-warning"><?php echo $responseTypeCounts['question']; ?></span>
                                                        <span class="small ms-1">Questions</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-6">
                                        <div class="card border-0 bg-light">
                                            <div class="card-body">
                                                <h6 class="card-title">Attendance Status</h6>
                                                <div class="d-flex">
                                                    <div class="me-3">
                                                        <span class="badge bg-success"><?php echo $attendanceStatusCounts['yes']; ?></span>
                                                        <span class="small ms-1">Will Attend</span>
                                                    </div>
                                                    <div class="me-3">
                                                        <span class="badge bg-danger"><?php echo $attendanceStatusCounts['no']; ?></span>
                                                        <span class="small ms-1">Cannot Attend</span>
                                                    </div>
                                                    <div>
                                                        <span class="badge bg-primary"><?php echo $attendanceStatusCounts['completed']; ?></span>
                                                        <span class="small ms-1">Completed</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Submissions Table -->
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover border">
                                        <thead class="table-primary">
                                            <tr>
                                                <th>User</th>
                                                <th>Response Type</th>
                                                <th>Attendance</th>
                                                <th>Date Submitted</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($submissions as $submission): ?>
                                            <tr>
                                                <td>
                                                    <div><?php echo htmlspecialchars($submission['user_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($submission['email']); ?></small>
                                                </td>
                                                <td>
                                                    <?php if ($submission['response_type'] == 'participation'): ?>
                                                        <span class="badge bg-success">Participation</span>
                                                    <?php elseif ($submission['response_type'] == 'feedback'): ?>
                                                        <span class="badge bg-info">Feedback</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">Question</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($submission['attended'] == 1): ?>
                                                        <span class="badge bg-primary">
                                                            <i class="bi bi-check-circle-fill me-1"></i>Completed
                                                        </span>
                                                        <div class="small text-muted">
                                                            <?php echo date('M d, Y', strtotime($submission['attendance_date'])); ?>
                                                        </div>
                                                    <?php elseif ($submission['attendance_status'] == 'yes'): ?>
                                                        <span class="badge bg-success">Will Attend</span>
                                                    <?php elseif ($submission['attendance_status'] == 'maybe'): ?>
                                                        <span class="badge bg-warning text-dark">Maybe</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger">Cannot Attend</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo date('M d, Y g:i A', strtotime($submission['created_at'])); ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-primary me-1" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#viewSubmissionModal"
                                                            data-submission-id="<?php echo $submission['submission_id']; ?>"
                                                            data-user-name="<?php echo htmlspecialchars($submission['user_name']); ?>"
                                                            data-comments="<?php echo htmlspecialchars($submission['comments']); ?>"
                                                            data-has-file="<?php echo !empty($submission['file_path']) ? 'true' : 'false'; ?>"
                                                            data-file-path="<?php echo htmlspecialchars($submission['file_path'] ?? ''); ?>"
                                                            data-file-name="<?php echo htmlspecialchars($submission['file_name'] ?? ''); ?>">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                    
                                                    <?php if ($submission['attended'] != 1 && $submission['attendance_status'] == 'yes'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="user_id" value="<?php echo $submission['user_id']; ?>">
                                                        <input type="hidden" name="activity_id" value="<?php echo $activity_id; ?>">
                                                        <button type="submit" name="mark_completed" class="btn btn-sm btn-success"
                                                                onclick="return confirm('Mark this participant as completed?');">
                                                            <i class="bi bi-check-lg"></i> Mark Completed
                                                        </button>
                                                    </form>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i> No submissions have been received for this activity yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Participants Column -->
                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h5 class="mb-0 text-primary">
                                <i class="bi bi-people-fill me-2"></i>Activity Participants
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            // Fetch all participants for this activity
                            $participantsSql = "SELECT u.firstname, u.lastname, u.email, ap.attended, ap.attendance_date, ap.created_at 
                                            FROM activity_participants ap 
                                            JOIN users u ON ap.user_id = u.user_id 
                                            WHERE ap.activity_id = ? 
                                            ORDER BY ap.created_at DESC";
                            $participants = $db->fetchAll($participantsSql, [$activity_id]);
                            
                            $totalParticipants = count($participants);
                            $completedParticipants = 0;
                            
                            foreach ($participants as $participant) {
                                if ($participant['attended'] == 1) {
                                    $completedParticipants++;
                                }
                            }
                            ?>
                            
                            <div class="card border-0 bg-light mb-3">
                                <div class="card-body">
                                    <h6 class="card-title">Participation Summary</h6>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <span class="fs-4 fw-bold text-primary"><?php echo $totalParticipants; ?></span>
                                            <span class="text-muted ms-2">Total</span>
                                        </div>
                                        <div>
                                            <span class="fs-4 fw-bold text-success"><?php echo $completedParticipants; ?></span>
                                            <span class="text-muted ms-2">Completed</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if ($totalParticipants > 0): ?>
                                <div class="participants-list" style="max-height: 500px; overflow-y: auto;">
                                    <?php foreach ($participants as $participant): ?>
                                    <div class="participant-item d-flex align-items-center p-2 border-bottom">
                                        <div class="avatar-circle me-3" style="width: 40px; height: 40px; background-color: #6c757d; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                                            <span class="avatar-initial"><?php echo strtoupper(substr($participant['firstname'], 0, 1)); ?></span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars($participant['firstname'] . ' ' . $participant['lastname']); ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo htmlspecialchars($participant['email']); ?>
                                            </small>
                                        </div>
                                        <div>
                                            <?php if ($participant['attended'] == 1): ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle-fill"></i>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-clock-fill"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    <i class="bi bi-info-circle me-2"></i> No participants have joined this activity yet.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
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

    <div class="modal fade" id="viewSubmissionModal" tabindex="-1" aria-labelledby="viewSubmissionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewSubmissionModalLabel">Submission Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6>Submitted by</h6>
                        <p id="submissionUserName" class="fs-5"></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Comments</h6>
                        <div id="submissionComments" class="border p-3 rounded bg-light"></div>
                    </div>
                    
                    <div id="submissionFileContainer" class="mb-3 d-none">
                        <h6>Attachment</h6>
                        <div id="submissionFilePreview" class="mb-3">
                            <!-- Image or file icon will be inserted here -->
                        </div>
                        <div class="d-flex align-items-center">
                            <span id="submissionFileName"></span>
                            <a id="submissionFileDownload" href="#" class="btn btn-sm btn-outline-primary ms-3" download>
                                <i class="bi bi-download me-1"></i> Download
                            </a>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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

            const submissionModal = document.getElementById('viewSubmissionModal');
            if (submissionModal) {
                submissionModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    
                    // Extract data from button attributes
                    const userName = button.getAttribute('data-user-name');
                    const comments = button.getAttribute('data-comments');
                    const hasFile = button.getAttribute('data-has-file') === 'true';
                    const filePath = button.getAttribute('data-file-path');
                    const fileName = button.getAttribute('data-file-name');
                    
                    // Update modal content
                    document.getElementById('submissionUserName').textContent = userName;
                    document.getElementById('submissionComments').textContent = comments || 'No comments provided.';
                    
                    // Handle file display
                    const fileContainer = document.getElementById('submissionFileContainer');
                    const filePreview = document.getElementById('submissionFilePreview');
                    
                    if (hasFile && filePath) {
                        fileContainer.classList.remove('d-none');
                        document.getElementById('submissionFileName').textContent = fileName || filePath.split('/').pop();
                        document.getElementById('submissionFileDownload').href = '../../' + filePath;
                        
                        // Check if file is an image
                        const fileExtension = filePath.split('.').pop().toLowerCase();
                        const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
                        
                        if (imageExtensions.includes(fileExtension)) {
                            // Display image preview
                            filePreview.innerHTML = `<img src="../../${filePath}" alt="Attachment" class="img-fluid" style="max-height: 400px; border-radius: 8px;">`;
                        } else {
                            // Display file icon for non-image files
                            let iconClass = 'bi-file-earmark';
                            let iconColor = 'text-secondary';
                            
                            if (fileExtension === 'pdf') {
                                iconClass = 'bi-file-earmark-pdf';
                                iconColor = 'text-danger';
                            } else if (['doc', 'docx'].includes(fileExtension)) {
                                iconClass = 'bi-file-earmark-word';
                                iconColor = 'text-primary';
                            } else if (['xls', 'xlsx'].includes(fileExtension)) {
                                iconClass = 'bi-file-earmark-excel';
                                iconColor = 'text-success';
                            }
                            
                            filePreview.innerHTML = `
                                <div class="text-center">
                                    <i class="bi ${iconClass} ${iconColor}" style="font-size: 5rem;"></i>
                                    <p class="mt-2 mb-0">${fileName || 'Document'}</p>
                                </div>
                            `;
                        }
                    } else {
                        fileContainer.classList.add('d-none');
                    }
                });
            }
        });
   </script>
</body>
</html>