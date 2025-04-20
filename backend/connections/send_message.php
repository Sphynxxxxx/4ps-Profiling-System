<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Ensure config and database files exist and can be included
if (!file_exists("config.php") || !file_exists("database.php")) {
    error_log("Configuration or database file missing");
    echo json_encode([
        'success' => false, 
        'error' => 'System configuration error'
    ]);
    exit();
}

require_once "config.php";
require_once "database.php";

header('Content-Type: application/json');

// Debug: Log all POST data
error_log("Received POST data: " . print_r($_POST, true));

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    echo json_encode([
        'success' => false, 
        'error' => 'Not logged in'
    ]);
    exit();
}

// Validate input
if (!isset($_POST['message_text']) || empty(trim($_POST['message_text']))) {
    error_log("Empty message");
    echo json_encode([
        'success' => false, 
        'error' => 'Message cannot be empty'
    ]);
    exit();
}

// Validate barangay_id
if (!isset($_POST['barangay_id']) || !is_numeric($_POST['barangay_id'])) {
    error_log("Invalid barangay ID");
    echo json_encode([
        'success' => false, 
        'error' => 'Invalid barangay'
    ]);
    exit();
}

try {
    $db = new Database();
    
    // Fetch sender details
    $userQuery = "SELECT user_id, firstname, lastname, barangay 
                  FROM users 
                  WHERE user_id = ? AND barangay = ?";
    $sender = $db->fetchOne($userQuery, [$_SESSION['user_id'], $_POST['barangay_id']]);
    
    if (!$sender) {
        error_log("Unauthorized access");
        echo json_encode([
            'success' => false, 
            'error' => 'Unauthorized access'
        ]);
        exit();
    }
    
    // Sanitize message
    $message_text = trim($_POST['message_text']);
    
    // Insert message
    $insertQuery = "INSERT INTO messages (
        sender_id, 
        barangay_id, 
        message_text, 
        status, 
        created_at
    ) VALUES (?, ?, ?, 'unread', NOW())";
    
    $message_id = $db->insertAndGetId($insertQuery, [
        $sender['user_id'], 
        $_POST['barangay_id'], 
        $message_text
    ]);
    
    // Log activity
    $activityQuery = "INSERT INTO activity_logs (
        user_id, 
        activity_type, 
        description, 
        created_at
    ) VALUES (?, 'message', ?, NOW())";
    $db->insert($activityQuery, [
        $sender['user_id'], 
        "Sent a message in Barangay group chat"
    ]);
    
    // Prepare response
    echo json_encode([
        'success' => true,
        'message' => $message_text,
        'sender_name' => $sender['firstname'] . ' ' . $sender['lastname'],
        'timestamp' => date('h:i A')
    ]);
} catch (Exception $e) {
    // Log the full error details
    error_log("Message send error: " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    
    echo json_encode([
        'success' => false, 
        'error' => 'An error occurred while sending the message: ' . $e->getMessage()
    ]);
}
exit();
?>