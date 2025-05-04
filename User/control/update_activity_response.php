<?php
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: ../activities.php");
    exit();
}

// Get form data
$userId = $_SESSION['user_id'];
$activityId = isset($_POST['activity_id']) ? intval($_POST['activity_id']) : 0;
$submissionId = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
$responseType = isset($_POST['response_type']) ? $_POST['response_type'] : '';
$comments = isset($_POST['comments']) ? $_POST['comments'] : '';
$attendanceStatus = isset($_POST['attendance_status']) ? $_POST['attendance_status'] : '';
$keepExistingFile = isset($_POST['keep_existing_file']) ? true : false;

// Validate required fields
if (!$activityId || !$submissionId || !$responseType || !$attendanceStatus) {
    $_SESSION['error'] = "All required fields must be filled.";
    header("Location: ../view_activity.php?id=" . $activityId);
    exit();
}

// Validate response type and attendance status
$validResponseTypes = ['participation', 'feedback', 'question'];
$validAttendanceStatuses = ['yes', 'no', 'maybe'];

if (!in_array($responseType, $validResponseTypes) || !in_array($attendanceStatus, $validAttendanceStatuses)) {
    $_SESSION['error'] = "Invalid response type or attendance status.";
    header("Location: ../view_activity.php?id=" . $activityId);
    exit();
}

try {
    $db = new Database();
    
    // Verify the submission belongs to the current user
    $checkSql = "SELECT * FROM activity_submissions WHERE submission_id = ? AND user_id = ? AND activity_id = ?";
    $existingSubmission = $db->fetchOne($checkSql, [$submissionId, $userId, $activityId]);
    
    if (!$existingSubmission) {
        $_SESSION['error'] = "Submission not found or you don't have permission to edit it.";
        header("Location: ../view_activity.php?id=" . $activityId);
        exit();
    }
    
    // Handle file upload if a new file is provided
    $filePath = null;
    $fileName = null;
    
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == UPLOAD_ERR_OK) {
        // Validate file type and size
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $maxFileSize = 5 * 1024 * 1024; // 5MB
        
        $fileInfo = $_FILES['submission_file'];
        
        if (!in_array($fileInfo['type'], $allowedTypes)) {
            $_SESSION['error'] = "Invalid file type. Only images, PDF, and Word documents are allowed.";
            header("Location: ../view_activity.php?id=" . $activityId);
            exit();
        }
        
        if ($fileInfo['size'] > $maxFileSize) {
            $_SESSION['error'] = "File is too large. Maximum size is 5MB.";
            header("Location: ../view_activity.php?id=" . $activityId);
            exit();
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = '../../uploads/submissions/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($fileInfo['name'], PATHINFO_EXTENSION);
        $uniqueFileName = $userId . '_' . $activityId . '_' . time() . '.' . $fileExtension;
        $uploadPath = $uploadDir . $uniqueFileName;
        
        // Move uploaded file
        if (move_uploaded_file($fileInfo['tmp_name'], $uploadPath)) {
            $filePath = 'uploads/submissions/' . $uniqueFileName;
            $fileName = $fileInfo['name'];
            
            // Delete old file if it exists and user doesn't want to keep it
            if (!$keepExistingFile && !empty($existingSubmission['file_path'])) {
                $oldFilePath = '../../' . $existingSubmission['file_path'];
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }
        } else {
            $_SESSION['error'] = "Failed to upload file.";
            header("Location: ../view_activity.php?id=" . $activityId);
            exit();
        }
    } elseif ($keepExistingFile && !empty($existingSubmission['file_path'])) {
        // Keep existing file
        $filePath = $existingSubmission['file_path'];
        $fileName = $existingSubmission['file_name'];
    }
    
    // Update submission
    $updateSql = "UPDATE activity_submissions SET 
                  response_type = ?, 
                  comments = ?, 
                  attendance_status = ?, 
                  file_path = ?, 
                  file_name = ?, 
                  updated_at = NOW() 
                  WHERE submission_id = ?";
    
    $updateParams = [$responseType, $comments, $attendanceStatus, $filePath, $fileName, $submissionId];
    
    if ($db->execute($updateSql, $updateParams)) {
        $_SESSION['success'] = "Your submission has been updated successfully.";
        
        // Check if attendance status changed to 'yes' and update activity_participants if needed
        if ($attendanceStatus == 'yes' && $existingSubmission['attendance_status'] != 'yes') {
            // Check if already in participants table
            $checkParticipantSql = "SELECT * FROM activity_participants WHERE activity_id = ? AND user_id = ?";
            $participant = $db->fetchOne($checkParticipantSql, [$activityId, $userId]);
            
            if (!$participant) {
                // Add to participants
                $insertParticipantSql = "INSERT INTO activity_participants (activity_id, user_id, status, attended, created_at) 
                                       VALUES (?, ?, 'yes', 0, NOW())";
                $db->execute($insertParticipantSql, [$activityId, $userId]);
            } else {
                // Update status if changed
                $updateParticipantSql = "UPDATE activity_participants SET status = 'yes' WHERE activity_id = ? AND user_id = ?";
                $db->execute($updateParticipantSql, [$activityId, $userId]);
            }
        } elseif ($attendanceStatus == 'no' && $existingSubmission['attendance_status'] == 'yes') {
            // Update participant status to 'no' if they were previously saying 'yes'
            $updateParticipantSql = "UPDATE activity_participants SET status = 'no' WHERE activity_id = ? AND user_id = ?";
            $db->execute($updateParticipantSql, [$activityId, $userId]);
        }
    } else {
        $_SESSION['error'] = "Failed to update submission.";
    }
    
    $db->closeConnection();
    
} catch (Exception $e) {
    error_log("Error updating activity submission: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while updating your submission.";
}

// Redirect back to activity page
header("Location: view_activity.php?id=" . $activityId);
exit();
?>