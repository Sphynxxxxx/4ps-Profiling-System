<?php
// This file should be placed in ../backend/connections/send_admin_message.php

header('Content-Type: application/json');
require_once "config.php";
require_once "database.php";

// Set up response array
$response = [
    'success' => false,
    'message' => '',
    'timestamp' => '',
    'error' => ''
];

try {
    // Check if the request is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Validate required fields
    if (empty($_POST['message_text']) || empty($_POST['barangay_id'])) {
        throw new Exception('Missing required fields');
    }
    
    // First check if there's an admin user with role 'admin' in the users table
    $adminQuery = "SELECT user_id FROM users WHERE role = 'admin' LIMIT 1";
    $admin = $db->fetchOne($adminQuery);
    
    if ($admin) {
        // Use an existing admin user ID
        $admin_user_id = $admin['user_id'];
    } else {
        // Use the first user ID as a fallback
        $userQuery = "SELECT user_id FROM users LIMIT 1";
        $user = $db->fetchOne($userQuery);
        $admin_user_id = $user ? $user['user_id'] : 0; // Default to 0 if no users exist
    }
    
    // Clean and validate message text
    $message_text = trim($_POST['message_text']);
    $barangay_id = intval($_POST['barangay_id']);
    
    if (empty($message_text)) {
        throw new Exception('Message cannot be empty');
    }
    
    // Connect to database
    $db = new Database();
    
    // Insert the message
    $sql = "INSERT INTO messages (sender_id, barangay_id, message_text, created_at) VALUES (?, ?, ?, NOW())";
    $result = $db->execute($sql, [$admin_user_id, $barangay_id, $message_text]);
    
    if ($result) {
        $response['success'] = true;
        $response['message'] = $message_text;
        $response['timestamp'] = date('h:i A'); // Current time in 12-hour format
    } else {
        throw new Exception('Failed to save message');
    }
    
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
    error_log("Admin Message Error: " . $e->getMessage());
}

// Return JSON response
echo json_encode($response);
?>