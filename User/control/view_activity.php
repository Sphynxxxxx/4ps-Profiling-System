<?php
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if activity ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: activities.php");
    exit();
}

$activityId = intval($_GET['id']);

try {
    $db = new Database();
    
    // Get user information
    $userId = $_SESSION['user_id'];
    $userSql = "SELECT * FROM users WHERE user_id = ?";
    $user = $db->fetchOne($userSql, [$userId]);
    
    // Get user's role
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
    
    // Get activity details with JOIN to get barangay and creator info
    $activitySql = "SELECT a.*, b.name as barangay_name, u.firstname, u.lastname, u.email, u.phone_number
                    FROM activities a 
                    LEFT JOIN barangays b ON a.barangay_id = b.barangay_id
                    LEFT JOIN users u ON a.created_by = u.user_id
                    WHERE a.activity_id = ?";
    $activity = $db->fetchOne($activitySql, [$activityId]);
    
    // Check if activity exists
    if (!$activity) {
        $_SESSION['error'] = "Activity not found.";
        header("Location: activities.php");
        exit();
    }
    
    $hasPermission = ($userRole == 'admin' || $userRole == 'staff' || 
                     $activity['barangay_id'] == $userBarangayId || 
                     $activity['created_by'] == $userId);
    
    if (!$hasPermission) {
        $_SESSION['error'] = "You don't have permission to view this activity.";
        header("Location: activities.php");
        exit();
    }
    
    // Count participants (if you have a participants table)
    $participantsCount = 0;
    try {
        // This is just a placeholder - adjust based on your actual database structure
        $participantsSql = "SELECT COUNT(*) as count FROM activity_participants WHERE activity_id = ?";
        $participantsResult = $db->fetchOne($participantsSql, [$activityId]);
        $participantsCount = $participantsResult ? $participantsResult['count'] : 0;
    } catch (Exception $e) {
        // Silently handle this error, as the participants table might not exist
        error_log("Error fetching participants: " . $e->getMessage());
    }
    
    // Get other activities from the same barangay
    $relatedActivitiesSql = "SELECT activity_id, title, activity_type, start_date 
                            FROM activities 
                            WHERE barangay_id = ? 
                            AND activity_id != ? 
                            ORDER BY created_at DESC LIMIT 5";
    $relatedActivities = $db->fetchAll($relatedActivitiesSql, [$activity['barangay_id'], $activityId]);
    
    // Close database connection
    $db->closeConnection();
    
} catch (Exception $e) {
    // Log error
    error_log("View Activity Error: " . $e->getMessage());
    
    $_SESSION['error'] = "An error occurred while retrieving the activity details.";
    header("Location: activities.php");
    exit();
}

// Helper function to format activity type for display
function formatActivityType($type) {
    switch ($type) {
        case 'health_check':
            return ['name' => 'Health Check', 'class' => 'success', 'icon' => 'heart-pulse'];
        case 'education':
            return ['name' => 'Education', 'class' => 'info', 'icon' => 'book'];
        case 'family_development_session':
            return ['name' => 'Family Development Session', 'class' => 'warning', 'icon' => 'people'];
        case 'community_meeting':
            return ['name' => 'Community Meeting', 'class' => 'primary', 'icon' => 'chat-square-text'];
        case 'other':
            return ['name' => 'Other', 'class' => 'secondary', 'icon' => 'grid'];
        default:
            return ['name' => ucfirst(str_replace('_', ' ', $type)), 'class' => 'secondary', 'icon' => 'grid'];
    }
}

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

// Get activity type and status
$activityType = formatActivityType($activity['activity_type']);
$activityStatus = getActivityStatus($activity['start_date'], $activity['end_date']);

// Parse attachments from JSON
$attachments = json_decode($activity['attachments'], true) ?? [];

// Format dates for display
$startDate = date('F d, Y', strtotime($activity['start_date']));
$endDate = date('F d, Y', strtotime($activity['end_date']));
$createdDate = date('F d, Y g:i A', strtotime($activity['created_at']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Activity | DSWD 4P's Profiling System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .activity-header {
            background-color: #f8f9fa;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border-left: 5px solid var(--bs-primary);
        }
        
        .activity-info {
            padding: 1.5rem;
            border-radius: 10px;
            background-color: #fff;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .activity-meta {
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
        
        .activity-icon {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            margin-right: 1rem;
        }
        
        .attachments-section {
            margin-top: 1.5rem;
        }
        
        .attachment-card {
            display: flex;
            align-items: center;
            padding: 0.75rem;
            background-color: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: all 0.2s ease;
        }
        
        .attachment-card:hover {
            background-color: #e9ecef;
        }
        
        .attachment-icon {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #dee2e6;
            border-radius: 8px;
            margin-right: 0.75rem;
        }
        
        .attachment-details {
            flex-grow: 1;
        }
        
        .attachment-preview {
            max-width: 100%;
            max-height: 200px;
            object-fit: contain;
            margin-top: 1rem;
            border-radius: 4px;
        }
        
        .sidebar-widget {
            background-color: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            margin-bottom: 1.5rem;
        }
        
        .sidebar-widget-title {
            background-color: #f8f9fa;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
        }
        
        .sidebar-widget-body {
            padding: 1rem;
        }
        
        .related-activity {
            display: flex;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .related-activity:last-child {
            border-bottom: none;
        }
        
        .related-activity-icon {
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            margin-right: 0.75rem;
            font-size: 0.8rem;
        }
        
        .related-activity-details {
            flex-grow: 1;
        }
        
        .related-activity-title {
            font-weight: 500;
            margin-bottom: 0.25rem;
            display: block;
        }
        
        .btn-action {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
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
                        <a class="nav-link active" href="../activities.php">
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
                        <a class="nav-link" href="../calendar.php">
                            <i class="bi bi-calendar3"></i> Calendar
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
                                    <li class="breadcrumb-item"><a href="activities.php">Activities</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">View Activity</li>
                                </ol>
                            </nav>
                            <h1 class="h3 mb-0 text-gray-800">
                                Activity Details
                            </h1>
                        </div>
                        <div class="mt-2 mt-md-0">
                            <a href="../activities.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back to Activities
                            </a>
                            
                            <?php if ($userRole == 'admin' || $userRole == 'staff' || $activity['created_by'] == $userId): ?>

                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Main Content Row -->
                    <div class="row">
                        <!-- Activity Details Column -->
                        <div class="col-lg-8">
                            <!-- Activity Header -->
                            <div class="activity-header">
                                <div class="d-flex">
                                    <div class="activity-icon bg-<?php echo $activityType['class']; ?> bg-opacity-10">
                                        <i class="bi bi-<?php echo $activityType['icon']; ?> text-<?php echo $activityType['class']; ?> fs-2"></i>
                                    </div>
                                    <div>
                                        <h3 class="mb-1"><?php echo htmlspecialchars($activity['title']); ?></h3>
                                        <div class="d-flex flex-wrap gap-2">
                                            <span class="badge bg-<?php echo $activityType['class']; ?> bg-opacity-10 text-<?php echo $activityType['class']; ?> px-3 py-2">
                                                <?php echo $activityType['name']; ?>
                                            </span>
                                            <span class="badge bg-<?php echo $activityStatus['class']; ?> bg-opacity-10 text-<?php echo $activityStatus['class']; ?> px-3 py-2">
                                                <?php echo $activityStatus['label']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="activity-meta mt-3">
                                    <div class="meta-item">
                                        <i class="bi bi-calendar-event"></i> 
                                        <span>
                                            <?php echo $startDate; ?> 
                                            <?php if ($startDate != $endDate): ?>
                                            - <?php echo $endDate; ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    
                                    <?php if (!empty($activity['barangay_name'])): ?>
                                    <div class="meta-item">
                                        <i class="bi bi-geo-alt"></i> 
                                        <span><?php echo htmlspecialchars($activity['barangay_name']); ?></span>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="meta-item">
                                        <i class="bi bi-person"></i>
                                        <span>Created by: Administrator</span>
                                    </div>
                                    
                                    <div class="meta-item">
                                        <i class="bi bi-clock"></i>
                                        <span>Posted: <?php echo $createdDate; ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Activity Description -->
                            <div class="activity-info">
                                <h5 class="fw-bold">Description</h5>
                                <div class="mt-3">
                                    <?php if (!empty($activity['description'])): ?>
                                        <p><?php echo nl2br(htmlspecialchars($activity['description'])); ?></p>
                                    <?php else: ?>
                                        <p class="text-muted">No description available.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                            

                            <!-- Attachments Section -->
                            <?php if (!empty($attachments)): ?>
                            <div class="activity-info">
                                <h5 class="fw-bold">Attachments</h5>
                                
                                <div class="attachments-section">
                                    <!-- Document Attachments -->
                                    <?php if (isset($attachments['documents']) && !empty($attachments['documents'])): ?>
                                        <h6 class="text-muted mb-3">Documents</h6>
                                        <?php foreach ($attachments['documents'] as $index => $document): ?>
                                            <div class="attachment-card">
                                                <div class="attachment-icon">
                                                    <i class="bi bi-file-earmark-text text-primary"></i>
                                                </div>
                                                <div class="attachment-details">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-0">Document <?php echo $index + 1; ?></h6>
                                                        <a href="../../uploads/activities/documents/<?php echo $document; ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                            <i class="bi bi-download me-1"></i> Download
                                                        </a>
                                                    </div>
                                                    <small class="text-muted"><?php echo $document; ?></small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    
                                    <!-- Image Attachments -->
                                    <?php if (isset($attachments['images']) && !empty($attachments['images'])): ?>
                                        <h6 class="text-muted mb-3 mt-4">Images</h6>
                                        <?php foreach ($attachments['images'] as $index => $image): ?>
                                            <div class="attachment-card">
                                                <div class="attachment-icon">
                                                    <i class="bi bi-image text-success"></i>
                                                </div>
                                                <div class="attachment-details">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <h6 class="mb-0">Image <?php echo $index + 1; ?></h6>
                                                        <a href="../../uploads/activities/images/<?php echo $image; ?>" class="btn btn-sm btn-outline-success" target="_blank">
                                                            <i class="bi bi-eye me-1"></i> View
                                                        </a>
                                                    </div>
                                                    <small class="text-muted"><?php echo $image; ?></small>
                                                    <div>
                                                        <img src="../../uploads/activities/images/<?php echo $image; ?>" alt="Image Preview" class="attachment-preview mt-2">
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endif; ?>


                            <div class="activity-info">
                                <h5 class="fw-bold">Activity Response</h5>
                                
                                <?php
                                // Check if user already submitted a response
                                $hasSubmitted = false;
                                try {
                                    $checkSubmissionSql = "SELECT * FROM activity_submissions WHERE activity_id = ? AND user_id = ?";
                                    $existingSubmission = $db->fetchOne($checkSubmissionSql, [$activityId, $userId]);
                                    $hasSubmitted = !empty($existingSubmission);
                                } catch (Exception $e) {
                                    // Silently handle this error, as the table might not exist yet
                                    error_log("Error checking submissions: " . $e->getMessage());
                                }
                                
                                // Display submission form only if activity is active or upcoming
                                if ($activityStatus['status'] != 'completed'):
                                    if (!$hasSubmitted):
                                ?>
                                <div class="mt-3">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Please complete this form to provide feedback or confirm your participation in this activity.
                                    </div>
                                    
                                    <form action="submit_activity_response.php" method="POST" enctype="multipart/form-data" class="mt-4">
                                        <input type="hidden" name="activity_id" value="<?php echo $activityId; ?>">
                                        
                                        <div class="mb-3">
                                            <label for="response_type" class="form-label">Response Type <span class="text-danger">*</span></label>
                                            <select class="form-select" id="response_type" name="response_type" required>
                                                <option value="">-- Select Response Type --</option>
                                                <option value="participation">I will participate</option>
                                                <option value="feedback">Provide feedback only</option>
                                                <option value="question">I have questions</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="comments" class="form-label">Comments or Questions</label>
                                            <textarea class="form-control" id="comments" name="comments" rows="4" placeholder="Enter your comments, questions, or feedback about this activity"></textarea>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="attendance_status" class="form-label">Attendance Status <span class="text-danger">*</span></label>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="attendance_status" id="attendance_yes" value="yes" required>
                                                <label class="form-check-label" for="attendance_yes">I will attend this activity</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="radio" name="attendance_status" id="attendance_no" value="no">
                                                <label class="form-check-label" for="attendance_no">I cannot attend this activity</label>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label for="submission_file" class="form-label">Upload Document (Optional)</label>
                                            <input class="form-control" type="file" id="submission_file" name="submission_file">
                                            <div class="form-text text-muted">Upload any supporting documents (PDF, Word, or image files, max 5MB)</div>
                                        </div>
                                        
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-send me-2"></i> Submit Response
                                            </button>
                                        </div>
                                    </form>
                                </div>
                                <?php else: ?>
                                <div class="alert alert-success mt-3">
                                    <i class="bi bi-check-circle-fill me-2"></i>
                                    <strong>Thank you!</strong> You have already submitted your response to this activity.
                                    
                                    <?php if (!empty($existingSubmission)): ?>
                                    <div class="mt-2">
                                        <strong>Your submission details:</strong>
                                        <ul class="mb-0 mt-1">
                                            <li>Response Type: <?php echo ucfirst($existingSubmission['response_type'] ?? 'N/A'); ?></li>
                                            <li>Attendance: <?php echo ucfirst($existingSubmission['attendance_status'] ?? 'N/A'); ?></li>
                                            <li>Submitted on: <?php echo date('F d, Y g:i A', strtotime($existingSubmission['created_at'])); ?></li>
                                        </ul>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#editSubmissionModal">
                                            <i class="bi bi-pencil me-1"></i> Edit My Response
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <div class="alert alert-secondary mt-3">
                                    <i class="bi bi-calendar-check me-2"></i>
                                    This activity has been completed and is no longer accepting submissions.
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Edit Submission Modal -->
                            <?php if ($hasSubmitted): ?>
                            <div class="modal fade" id="editSubmissionModal" tabindex="-1" aria-labelledby="editSubmissionModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editSubmissionModalLabel">Edit Your Submission</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="update_activity_response.php" method="POST" enctype="multipart/form-data">
                                            <div class="modal-body">
                                                <input type="hidden" name="activity_id" value="<?php echo $activityId; ?>">
                                                <input type="hidden" name="submission_id" value="<?php echo $existingSubmission['submission_id'] ?? ''; ?>">
                                                
                                                <div class="mb-3">
                                                    <label for="edit_response_type" class="form-label">Response Type <span class="text-danger">*</span></label>
                                                    <select class="form-select" id="edit_response_type" name="response_type" required>
                                                        <option value="">-- Select Response Type --</option>
                                                        <option value="participation" <?php echo ($existingSubmission['response_type'] ?? '') == 'participation' ? 'selected' : ''; ?>>I will participate</option>
                                                        <option value="feedback" <?php echo ($existingSubmission['response_type'] ?? '') == 'feedback' ? 'selected' : ''; ?>>Provide feedback only</option>
                                                        <option value="question" <?php echo ($existingSubmission['response_type'] ?? '') == 'question' ? 'selected' : ''; ?>>I have questions</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="edit_comments" class="form-label">Comments or Questions</label>
                                                    <textarea class="form-control" id="edit_comments" name="comments" rows="4" placeholder="Enter your comments, questions, or feedback about this activity"><?php echo htmlspecialchars($existingSubmission['comments'] ?? ''); ?></textarea>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="edit_attendance_status" class="form-label">Attendance Status <span class="text-danger">*</span></label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="attendance_status" id="edit_attendance_yes" value="yes" <?php echo ($existingSubmission['attendance_status'] ?? '') == 'yes' ? 'checked' : ''; ?> required>
                                                        <label class="form-check-label" for="edit_attendance_yes">I will attend this activity</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="attendance_status" id="edit_attendance_no" value="no" <?php echo ($existingSubmission['attendance_status'] ?? '') == 'no' ? 'checked' : ''; ?>>
                                                        <label class="form-check-label" for="edit_attendance_no">I cannot attend this activity</label>
                                                    </div>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="edit_submission_file" class="form-label">Upload New Document (Optional)</label>
                                                    <input class="form-control" type="file" id="edit_submission_file" name="submission_file">
                                                    <div class="form-text text-muted">Upload any supporting documents (PDF, Word, or image files, max 5MB)</div>
                                                    
                                                    <?php if (!empty($existingSubmission['file_path'])): ?>
                                                    <div class="form-check mt-2">
                                                        <input class="form-check-input" type="checkbox" id="keep_existing_file" name="keep_existing_file" value="1" checked>
                                                        <label class="form-check-label" for="keep_existing_file">
                                                            Keep existing file if no new file is uploaded
                                                        </label>
                                                    </div>
                                                    <div class="mt-2">
                                                        <small class="text-muted">Current file: <?php echo basename($existingSubmission['file_path']); ?></small>
                                                    </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-save me-1"></i> Update Response
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>

                        <!-- Sidebar Column -->
                        <div class="col-lg-4">
                            
                            <!-- Activity Participation -->
                            <div class="sidebar-widget">
                                <div class="sidebar-widget-title">
                                    <i class="bi bi-people me-2"></i> Participation
                                </div>
                                <div class="sidebar-widget-body">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <span>Participants</span>
                                        <span class="badge bg-secondary"><?php echo $participantsCount; ?></span>
                                    </div>
                                    
                                    <?php
                                    // Fetch participant names
                                    try {
                                        $participantsSql = "SELECT u.firstname, u.lastname, ap.attended, ap.attendance_date 
                                                        FROM activity_participants ap 
                                                        JOIN users u ON ap.user_id = u.user_id 
                                                        WHERE ap.activity_id = ? 
                                                        ORDER BY ap.created_at DESC";
                                        $participants = $db->fetchAll($participantsSql, [$activityId]);
                                        
                                        if (!empty($participants)): ?>
                                            <div class="participants-list mb-3" style="max-height: 200px; overflow-y: auto;">
                                                <?php foreach ($participants as $participant): ?>
                                                    <div class="participant-item d-flex align-items-center mb-2">
                                                        <i class="bi bi-person-circle me-2 text-muted"></i>
                                                        <div class="flex-grow-1">
                                                            <div class="participant-name">
                                                                <?php echo htmlspecialchars($participant['firstname'] . ' ' . $participant['lastname']); ?>
                                                                <?php if ($participant['attended'] == 1): ?>
                                                                    <span class="badge bg-success ms-1" title="Completed on <?php echo date('M d, Y', strtotime($participant['attendance_date'])); ?>">
                                                                        <i class="bi bi-check-circle-fill"></i>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <p class="text-muted small mb-3">No participants yet.</p>
                                        <?php endif;
                                    } catch (Exception $e) {
                                        error_log("Error fetching participant names: " . $e->getMessage());
                                    }
                                    ?>
                                    
                                    <?php if ($activityStatus['status'] != 'completed'): ?>
                                        <?php if ($activityStatus['status'] == 'upcoming'): ?>
                                            <div class="alert alert-info mb-3" role="alert">
                                                <i class="bi bi-info-circle me-2"></i>
                                                This activity is upcoming. You can join now to reserve your spot.
                                            </div>
                                        <?php elseif ($activityStatus['status'] == 'active'): ?>
                                            <div class="alert alert-success mb-3" role="alert">
                                                <i class="bi bi-check-circle me-2"></i>
                                                This activity is currently active. You can still join if slots are available.
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php
                                        // Check if current user has already joined
                                        $hasJoined = false;
                                        try {
                                            $checkUserJoined = "SELECT * FROM activity_participants WHERE activity_id = ? AND user_id = ?";
                                            $userParticipation = $db->fetchOne($checkUserJoined, [$activityId, $userId]);
                                            $hasJoined = !empty($userParticipation);
                                        } catch (Exception $e) {
                                            error_log("Error checking user participation: " . $e->getMessage());
                                        }
                                        
                                        if ($hasJoined): ?>
                                            <button class="btn btn-secondary w-100" disabled>
                                                <i class="bi bi-check-circle me-2"></i> Already Joined
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-primary w-100" id="joinActivityBtn" onclick="joinActivity(<?php echo $activityId; ?>)">
                                                <i class="bi bi-person-plus me-2"></i> Join Activity
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="alert alert-secondary mb-0" role="alert">
                                            <i class="bi bi-calendar-check me-2"></i>
                                            This activity has been completed.
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            
                            <!-- Related Activities -->
                            <?php if (!empty($relatedActivities)): ?>
                            <div class="sidebar-widget">
                                <div class="sidebar-widget-title">
                                    <i class="bi bi-calendar2-week me-2"></i> Other Activities in This Barangay
                                </div>
                                <div class="sidebar-widget-body p-0">
                                    <?php foreach ($relatedActivities as $relatedActivity): ?>
                                        <?php $relatedType = formatActivityType($relatedActivity['activity_type']); ?>
                                        <div class="related-activity p-3">
                                            <div class="related-activity-icon bg-<?php echo $relatedType['class']; ?> bg-opacity-10 text-<?php echo $relatedType['class']; ?>">
                                                <i class="bi bi-<?php echo $relatedType['icon']; ?>"></i>
                                            </div>
                                            <div class="related-activity-details">
                                                <a href="view_activity.php?id=<?php echo $relatedActivity['activity_id']; ?>" class="related-activity-title text-decoration-none">
                                                    <?php echo htmlspecialchars($relatedActivity['title']); ?>
                                                </a>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="bi bi-calendar3 me-1"></i> 
                                                        <?php echo date('M d, Y', strtotime($relatedActivity['start_date'])); ?>
                                                    </small>
                                                    <span class="badge bg-<?php echo $relatedType['class']; ?> bg-opacity-10 text-<?php echo $relatedType['class']; ?> small">
                                                        <?php echo $relatedType['name']; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Activity Modal -->
    <?php if ($userRole == 'admin' || $userRole == 'staff' || $activity['created_by'] == $userId): ?>
    <div class="modal fade" id="deleteActivityModal" tabindex="-1" aria-labelledby="deleteActivityModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteActivityModalLabel">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this activity? This action cannot be undone.</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Deleting this activity will remove all associated data, including attachments and participant information.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form action="control/delete_activity.php" method="POST">
                        <input type="hidden" name="activity_id" value="<?php echo $activityId; ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i> Delete Activity
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile sidebar toggle
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

        document.querySelector('.btn-primary.w-100')?.addEventListener('click', function() {
            if (confirm('Are you sure you want to join this activity?')) {
                fetch('join_activity.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        activity_id: <?php echo $activityId; ?>
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Successfully joined the activity!');
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to join activity.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while joining the activity.');
                });
            }
        });
    </script>
</body>
</html>