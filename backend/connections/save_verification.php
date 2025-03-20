<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Set headers for JSON response
header('Content-Type: application/json');

// Get JSON data from request
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);

// Initialize response array
$response = ['success' => false];

// Validate input data
if (empty($data['email']) || empty($data['code'])) {
    $response['error'] = 'Email and verification code are required';
    echo json_encode($response);
    exit;
}

// Sanitize input
$email = filter_var($data['email'], FILTER_SANITIZE_EMAIL);
$code = preg_replace('/[^0-9]/', '', $data['code']);

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['error'] = 'Invalid email format';
    echo json_encode($response);
    exit;
}

// Validate code format (6-digit number)
if (!preg_match('/^[0-9]{6}$/', $code)) {
    $response['error'] = 'Invalid verification code format';
    echo json_encode($response);
    exit;
}

try {
    // Save verification code in session
    $_SESSION['verification_code'] = $code;
    $_SESSION['verification_email'] = $email;
    $_SESSION['verification_expires'] = time() + 1800; // 30 minutes

    
    $response['success'] = true;
    
} catch (Exception $e) {
    $response['error'] = 'Failed to save verification code: ' . $e->getMessage();
    error_log('Verification code error: ' . $e->getMessage());
}

// Send JSON response
echo json_encode($response);