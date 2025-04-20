<?php
session_start();
require_once "config.php";
require_once "database.php";

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'error' => 'Not logged in'
    ]);
    exit();
}

// Validate barangay_id
if (!isset($_GET['barangay_id']) || !is_numeric($_GET['barangay_id'])) {
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid barangay'
    ]);
    exit();
}

try {
    $db = new Database();
    
    // Verify user belongs to this barangay
    $userQuery = "SELECT user_id FROM users 
                  WHERE user_id = ? AND barangay = ?";
    $user = $db->fetchOne($userQuery, [$_SESSION['user_id'], $_GET['barangay_id']]);
    
    if (!$user) {
        echo json_encode([
            'success' => false, 
            'error' => 'Unauthorized access'
        ]);
        exit();
    }
    
    // Prepare query to fetch new messages
    $query = "SELECT m.*, 
              CONCAT(u.firstname, ' ', u.lastname) as sender_name 
              FROM messages m
              JOIN users u ON m.sender_id = u.user_id
              WHERE m.barangay_id = ? 
              AND m.sender_id != ?";
    
    $params = [$_GET['barangay_id'], $_SESSION['user_id']];
    
    // If last message time is provided, fetch messages after that time
    if (isset($_GET['last_message_time']) && !empty($_GET['last_message_time'])) {
        $query .= " AND m.created_at > ?";
        $params[] = date('Y-m-d H:i:s', strtotime($_GET['last_message_time']));
    }
    
    $query .= " ORDER BY m.created_at ASC";
    
    // Fetch new messages
    $messages = $db->fetchAll($query, $params);
    
    // Transform messages for response
    $responseMessages = array_map(function($message) {
        return [
            'message' => $message['message_text'],
            'sender_name' => $message['sender_name'],
            'timestamp' => date('h:i A', strtotime($message['created_at']))
        ];
    }, $messages);
    
    // Mark these messages as read for this user
    if (!empty($messages)) {
        $updateQuery = "UPDATE messages 
                        SET status = 'read' 
                        WHERE barangay_id = ? 
                        AND sender_id != ?
                        AND status = 'unread'";
        $db->update($updateQuery, [$_GET['barangay_id'], $_SESSION['user_id']]);
    }
    
    echo json_encode([
        'success' => true,
        'messages' => $responseMessages
    ]);
} catch (Exception $e) {
    error_log("Fetch messages error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'An error occurred while fetching messages'
    ]);
}
exit();
?>