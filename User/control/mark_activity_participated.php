<?php
require_once "../../backend/connections/config.php";
require_once "../../backend/connections/database.php";

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

try {
    $data = json_decode(file_get_contents('php://input'), true);
    $activityId = isset($data['activity_id']) ? intval($data['activity_id']) : 0;
    $userId = $_SESSION['user_id'];
    
    if (!$activityId) {
        echo json_encode(['success' => false, 'message' => 'Invalid activity ID']);
        exit();
    }
    
    $db = new Database();
    
    // Check if the user has already participated
    $checkSql = "SELECT * FROM activity_participants WHERE activity_id = ? AND user_id = ?";
    $existing = $db->fetchOne($checkSql, [$activityId, $userId]);
    
    if ($existing) {
        // Update existing record
        $updateSql = "UPDATE activity_participants SET attended = 1, attendance_date = NOW() WHERE activity_id = ? AND user_id = ?";
        $result = $db->execute($updateSql, [$activityId, $userId]);
    } else {
        // Insert new record
        $insertSql = "INSERT INTO activity_participants (activity_id, user_id, status, attended, attendance_date, created_at) 
                     VALUES (?, ?, 'yes', 1, NOW(), NOW())";
       $result = $db->execute($insertSql, [$activityId, $userId]);
   }
   
   if ($result) {
       echo json_encode(['success' => true, 'message' => 'Activity marked as completed']);
   } else {
       echo json_encode(['success' => false, 'message' => 'Failed to mark activity as completed']);
   }
   
   $db->closeConnection();
   
} catch (Exception $e) {
   error_log("Mark Activity Error: " . $e->getMessage());
   echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request']);
}
?>