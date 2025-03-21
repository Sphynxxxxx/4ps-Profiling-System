<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
   
    // $userId = $_SESSION['user_id'];
    // $db = new mysqli('localhost', 'username', 'password', 'dswd_4ps');
    // $stmt = $db->prepare("UPDATE user_activity_log SET logout_time = NOW() WHERE user_id = ? AND logout_time IS NULL");
    // $stmt->bind_param('i', $userId);
    // $stmt->execute();
    // $stmt->close();
    // $db->close();
}

$_SESSION = array();

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

$logoutMessage = "You have been successfully logged out.";

header("Location: ../login.php?message=" . urlencode($logoutMessage) . "&type=success");
exit();
?>