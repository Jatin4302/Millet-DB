<?php
// Lightweight deployment check for monitoring and troubleshooting.
$fail_hard = false;
require "db_connect.php";

header('Content-Type: application/json');

$database_ok = $conn->ping();
$status = $database_ok ? 'healthy' : 'unhealthy';
http_response_code($database_ok ? 200 : 503);

echo json_encode([
    'status' => $status,
    'php' => 'ok',
    'php_version' => PHP_VERSION,
    'database' => $database_ok ? 'ok' : 'unavailable'
]);

$conn->close();
?>