<?php
session_start();
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";

if (!isset($_SESSION['user_id'])) {
    $_SESSION['error'] = "You must be logged in to delete an activity.";
    header("Location: ../../login.php");
    exit();
}

// Check if form was submitted and activity ID was provided
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['activity_id']) || !is_numeric($_POST['activity_id'])) {
    $_SESSION['error'] = "Invalid request. Activity ID is required.";
    header("Location: ../add_activities.php");
    exit();
}

$activity_id = intval($_POST['activity_id']);
$user_id = $_SESSION['user_id'];
$redirect_url = "../add_activities.php";

// Check if barangay ID was provided for redirect
if (isset($_POST['barangay_id']) && is_numeric($_POST['barangay_id'])) {
    $redirect_url .= "?barangay_id=" . intval($_POST['barangay_id']);
}

try {
    $db = new Database();
    
    // First, get the activity details to check permissions and for logging
    $activityQuery = "SELECT title, barangay_id, created_by FROM activities WHERE activity_id = ?";
    $activity = $db->fetchOne($activityQuery, [$activity_id]);
    
    if (!$activity) {
        $_SESSION['error'] = "Activity not found.";
        header("Location: " . $redirect_url);
        exit();
    }
    
    // Admins can delete any activity, others can only delete their own
    $is_admin = isset($_SESSION['role']) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'staff');
    
    if (!$is_admin && $activity['created_by'] != $user_id) {
        $_SESSION['error'] = "You don't have permission to delete this activity.";
        header("Location: " . $redirect_url);
        exit();
    }
    
    // Begin transaction
    $db->beginTransaction();
    
    // Delete any activity submissions
    $deleteSubmissionsQuery = "DELETE FROM activity_submissions WHERE activity_id = ?";
    $db->execute($deleteSubmissionsQuery, [$activity_id]);
    
    // Delete any activity participants
    $deleteParticipantsQuery = "DELETE FROM activity_participants WHERE activity_id = ?";
    $db->execute($deleteParticipantsQuery, [$activity_id]);
    
    // Delete the activity
    $deleteActivityQuery = "DELETE FROM activities WHERE activity_id = ?";
    $db->execute($deleteActivityQuery, [$activity_id]);
    
    $db->commit();
    
    logActivity(
        $user_id,
        'DELETE_ACTIVITY',
        "Deleted activity ID: $activity_id, Title: {$activity['title']}"
    );
    
    // Clean up any files associated with the activity
    $attachments_dir = "../../uploads/activities/";
    
 
    
    $_SESSION['success'] = "Activity has been successfully deleted.";
    
} catch (Exception $e) {
    // Rollback transaction in case of error
    if (isset($db) && $db->inTransaction()) {
        $db->rollback();
    }
    
    error_log("Error deleting activity: " . $e->getMessage());
    $_SESSION['error'] = "An error occurred while deleting the activity: " . $e->getMessage();
} finally {
    // Close database connection
    if (isset($db)) {
        $db->closeConnection();
    }
}

header("Location: " . $redirect_url);
exit();
?>