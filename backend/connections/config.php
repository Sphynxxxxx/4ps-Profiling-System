<?php
define('DB_HOST', 'localhost');
define('DB_NAME', '4ps_profiling_system');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');

$conn = new mysqli(DB_HOST, DB_USERNAME, DB_PASSWORD, DB_NAME);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$conn->close();
?>