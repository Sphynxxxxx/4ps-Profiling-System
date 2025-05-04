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
    
    // Check if already joined
    $checkSql = "SELECT * FROM activity_participants WHERE activity_id = ? AND user_id = ?";
    $existing = $db->fetchOne($checkSql, [$activityId, $userId]);
    
    if (!$existing) {
        // Insert new participant
        $insertSql = "INSERT INTO activity_participants (activity_id, user_id, status, attended, created_at) 
                      VALUES (?, ?, 'yes', 0, NOW())";
        $result = $db->execute($insertSql, [$activityId, $userId]);
        
        if ($result) {
            echo json_encode(['success' => true, 'message' => 'Successfully joined activity']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to join activity']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Already joined this activity']);
    }
    
    $db->closeConnection();
    
} catch (Exception $e) {
    error_log("Join Activity Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>