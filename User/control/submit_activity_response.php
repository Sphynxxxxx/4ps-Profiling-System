<?php
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to submit a response.";
    header("Location: ../../login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = "Invalid request method.";
    header("Location: ../activities.php");
    exit();
}

// Validate required fields
if (!isset($_POST['activity_id']) || !is_numeric($_POST['activity_id'])) {
    $_SESSION['error'] = "Invalid activity ID.";
    header("Location: ../activities.php");
    exit();
}

$activityId = intval($_POST['activity_id']);
$userId = $_SESSION['user_id'];
$comments = isset($_POST['comments']) ? trim($_POST['comments']) : '';
$responseType = isset($_POST['response_type']) ? $_POST['response_type'] : 'participation';
$attendanceStatus = isset($_POST['attendance_status']) ? $_POST['attendance_status'] : 'yes';

// Default values if not provided
if (empty($responseType)) {
    $responseType = 'participation';
}

if (empty($attendanceStatus)) {
    $attendanceStatus = 'yes';
}

// Initialize file path variable
$filePath = null;
$fileName = null;

try {
    $db = new Database();
    
    // Check if the activity exists
    $activitySql = "SELECT * FROM activities WHERE activity_id = ?";
    $activity = $db->fetchOne($activitySql, [$activityId]);
    
    if (!$activity) {
        $_SESSION['error'] = "Activity not found.";
        header("Location: ../activities.php");
        exit();
    }
    
    // Check if user has already submitted a response to this activity
    $checkSql = "SELECT * FROM activity_submissions WHERE activity_id = ? AND user_id = ?";
    $existingSubmission = $db->fetchOne($checkSql, [$activityId, $userId]);
    
    if ($existingSubmission) {
        $_SESSION['error'] = "You have already submitted a response to this activity. You can edit your existing response instead.";
        header("Location: view_activity.php?id=" . $activityId);
        exit();
    }
    
    // Handle file upload if provided
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == 0) {
        $uploadDir = "../uploads/submissions/";
        
        // Create uploads/submissions directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                error_log("Failed to create directory: " . $uploadDir);
                $_SESSION['error'] = "Failed to create upload directory. Please contact the administrator.";
                header("Location: view_activity.php?id=" . $activityId);
                exit();
            }
        }
        
        // Get file information
        $fileInfo = pathinfo($_FILES['submission_file']['name']);
        $fileName = $fileInfo['basename'];
        $fileExt = strtolower($fileInfo['extension']);
        
        // Allow certain file formats
        $allowedExt = array('pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt');
        
        if (!in_array($fileExt, $allowedExt)) {
            $_SESSION['error'] = "Invalid file format. Allowed formats: PDF, DOC, DOCX, JPG, JPEG, PNG, TXT.";
            header("Location: view_activity.php?id=" . $activityId);
            exit();
        }
        
        // Check file size (5MB max)
        if ($_FILES['submission_file']['size'] > 5000000) {
            $_SESSION['error'] = "File size too large. Maximum size: 5MB.";
            header("Location: view_activity.php?id=" . $activityId);
            exit();
        }
        
        // Generate unique filename to prevent overwriting
        $newFileName = uniqid('sub_') . '_' . $_FILES['submission_file']['name'];
        $filePath = $uploadDir . $newFileName;
        
        // Move uploaded file to destination directory
        if (!move_uploaded_file($_FILES['submission_file']['tmp_name'], $filePath)) {
            $_SESSION['error'] = "Failed to upload file. Please try again.";
            header("Location: view_activity.php?id=" . $activityId);
            exit();
        }
    }
    
    // Insert the submission into the database
    $insertSql = "INSERT INTO activity_submissions (activity_id, user_id, response_type, comments, 
                attendance_status, file_path, file_name, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $params = [
        $activityId,
        $userId,
        $responseType,
        $comments,
        $attendanceStatus,
        $filePath,
        $fileName
    ];
    
    $result = $db->execute($insertSql, $params);
    
    if ($result) {
        // Record the participation in activity_participants table
        if ($attendanceStatus != 'no') {
            try {
                $participantSql = "INSERT INTO activity_participants (activity_id, user_id, status, created_at) 
                                VALUES (?, ?, ?, NOW())";
                $db->execute($participantSql, [$activityId, $userId, $attendanceStatus]);
            } catch (Exception $e) {
                // If table doesn't exist or other error, log it but continue
                error_log("Error recording participation: " . $e->getMessage());
            }
        }
        
        $_SESSION['success'] = "Your response has been submitted successfully.";
    } else {
        $_SESSION['error'] = "Failed to submit response. Please try again.";
        
        // If file was uploaded but database insert failed, remove the file
        if ($filePath && file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    // Close database connection
    $db->closeConnection();
    
} catch (Exception $e) {
    // Log error
    error_log("Activity Submission Error: " . $e->getMessage());
    
    $_SESSION['error'] = "An error occurred while submitting your response. Please try again.";
    
    // If file was uploaded but an exception occurred, remove the file
    if ($filePath && file_exists($filePath)) {
        unlink($filePath);
    }
}

// Redirect back to the activity page
header("Location: view_activity.php?id=" . $activityId);
exit();
?>