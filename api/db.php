<?php
// backend/db.php
// Secure Database Connection Configuration using PDO

ob_start();
date_default_timezone_set('GMT');

// Include CORS middleware EARLY before any processing
$config = require '.env.php';
require_once 'cors_middleware.php';

$host = $config['DB_HOST'];
$user = $config['DB_USER'];
$pass = $config['DB_PASS'];
$db   = $config['DB_NAME'];
$charset = 'utf8mb4';

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Global Security Middleware
    if (file_exists('security.php')) {
        require_once 'security.php';

        // --- NEW: Debug Mode Logic ---
        if (isDebugEnabled()) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            error_reporting(E_ALL);
        }
        // -----------------------------

        // checkRateLimit($pdo);
        checkMaintenanceMode($pdo);

        // Include traffic monitor now that $pdo is ready
        if (file_exists('traffic_monitor.php')) {
            require_once 'traffic_monitor.php';
        }
    }
} catch (\Throwable $e) {
    // SECURITY: Don't expose database credentials/paths in production
    // UNLESS Debug Mode is explicitly enabled
    $message = 'Internal Server Error: Service Unavailable.';

    // Check debug status if security.php was loaded, otherwise check file directly
    $debug = false;
    if (function_exists('isDebugEnabled')) {
        $debug = isDebugEnabled();
    } else {
        $sf = __DIR__ . '/data/super_settings.json';
        if (file_exists($sf)) {
            $s = json_decode(file_get_contents($sf), true);
            $debug = isset($s['debugMode']) && $s['debugMode'] === true;
        }
    }

    if ($debug) {
        $message = "DATABASE CONNECTION ERROR: " . $e->getMessage();
    } else {
        error_log("Database connection failed: " . $e->getMessage());
    }

    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'debug_info' => $debug ? [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => explode("\n", $e->getTraceAsString())
        ] : null
    ]);
    exit;
}

/**
 * Helper function to handle JSON responses consistently
 */
if (!function_exists('sendResponse')) {
    function sendResponse($success, $message, $data = null, $code = 200)
    {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'success' => $success,
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

/**
 * Custom logging to app.log
 */
if (!function_exists('logApp')) {
    function logApp($level, $source, $message) {
        $file = __DIR__ . '/logs/app.log';
        if (!is_dir(__DIR__ . '/logs')) mkdir(__DIR__ . '/logs', 0755, true);
        $ts = date('Y-m-d H:i:s');
        $line = "$ts [" . strtoupper($level) . "] [" . strtoupper($source) . "] $message\n";
        file_put_contents($file, $line, FILE_APPEND);
    }
}

