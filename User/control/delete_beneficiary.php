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

// Check if an ID was provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Invalid beneficiary ID";
    header("Location: register_beneficiary.php");
    exit();
}

$beneficiaryId = intval($_GET['id']);
$userId = $_SESSION['user_id'];
$success = false;
$message = "";

try {
    $db = new Database();
    
    // First verify that this beneficiary belongs to the current user
    $checkQuery = "SELECT b.beneficiary_id, b.parent_leader_id 
                 FROM beneficiaries b
                 WHERE b.beneficiary_id = ? 
                 AND b.parent_leader_id = ?";
                 
    $beneficiary = $db->fetchOne($checkQuery, [$beneficiaryId, $userId]);
    
    if (!$beneficiary) {
        throw new Exception("You don't have permission to delete this beneficiary or the beneficiary doesn't exist");
    }
    
    // Perform the deletion
    $deleteQuery = "DELETE FROM beneficiaries WHERE beneficiary_id = ?";
    $result = $db->execute($deleteQuery, [$beneficiaryId]);
    
    if ($result) {
        $success = true;
        $_SESSION['success'] = "Beneficiary deleted successfully";
    } else {
        throw new Exception("Failed to delete the beneficiary");
    }
    
    $db->closeConnection();
    
} catch (Exception $e) {
    error_log("Error deleting beneficiary: " . $e->getMessage());
    $_SESSION['error'] = $e->getMessage();
}

// Redirect back to the beneficiary registration page
header("Location: register_beneficiary.php");
exit();
?>