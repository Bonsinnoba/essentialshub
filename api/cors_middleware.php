<?php
$config = require '.env.php';
$appEnv = $config['APP_ENV'] ?? 'production';
$allowedOrigins = $config['ALLOWED_ORIGINS'] ?? [];
$frontendUrl = $config['FRONTEND_URL'] ?? 'http://localhost:5173';

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';

if ($appEnv === 'development') {
    // In development, we are more flexible with localhost ports
    $isLocalhost = preg_match('/^http:\/\/localhost:\d+$/', $origin);
    if ($isLocalhost || in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header("Access-Control-Allow-Origin: $frontendUrl");
    }
} else {
    // In production, we are strict and only allow defined origins
    if (in_array($origin, $allowedOrigins)) {
        header("Access-Control-Allow-Origin: $origin");
    } else {
        header("Access-Control-Allow-Origin: $frontendUrl");
    }
}

header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    // Return early for preflight requests
    http_response_code(200);
    exit;
}

require_once 'traffic_monitor.php';
