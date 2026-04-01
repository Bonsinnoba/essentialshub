<?php
// backend/security.php
// Security Utilities and Middleware

// Standardized RBAC Role Groups
if (!defined('RBAC_ADMIN_GROUP')) {
    define('RBAC_ADMIN_GROUP', ['admin', 'branch_admin', 'store_manager', 'marketing', 'accountant']);
}
if (!defined('RBAC_STAFF_GROUP')) {
    define('RBAC_STAFF_GROUP', ['pos_cashier', 'branch_admin', 'store_manager']);
}
if (!defined('RBAC_SUPER_GROUP')) {
    define('RBAC_SUPER_GROUP', ['super']);
}
if (!defined('RBAC_ALL_ADMINS')) {
    define('RBAC_ALL_ADMINS', array_merge(RBAC_ADMIN_GROUP, RBAC_SUPER_GROUP));
}

/**
 * Hash a password using Argon2id with a server-side pepper.
 */
if (!function_exists('hashPassword')) {
    function hashPassword($password)
    {
        $config = require '.env.php';
        $pepper = $config['PASSWORD_PEPPER'] ?? '';
        return password_hash($password . $pepper, PASSWORD_ARGON2ID);
    }
}

/**
 * Verify a password against a hash.
 */
if (!function_exists('verifyPassword')) {
    function verifyPassword($password, $hash, &$needsRehash = false)
    {
        $config = require '.env.php';
        $pepper = $config['PASSWORD_PEPPER'] ?? '';
        if (password_verify($password . $pepper, $hash)) {
            $needsRehash = false;
            return true;
        }
        if (password_verify($password, $hash)) {
            $needsRehash = true;
            return true;
        }
        return false;
    }
}

/**
 * Sanitize input to prevent XSS
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data)
    {
        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

/**
 * Validate Email Format
 */
if (!function_exists('isValidEmail')) {
    function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }
}

/**
 * AES-256-CBC Encryption
 */
if (!function_exists('encryptData')) {
    function encryptData($plaintext)
    {
        $config = require '.env.php';
        $key = $config['DATA_ENCRYPTION_KEY'] ?? '';
        if (!$key) return $plaintext;
        $iv = openssl_random_pseudo_bytes(16);
        $ciphertext = openssl_encrypt($plaintext, 'AES-256-CBC', substr(hash('sha256', $key, true), 0, 32), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $ciphertext);
    }
}

if (!function_exists('decryptData')) {
    function decryptData($ciphertext)
    {
        $config = require '.env.php';
        $key = $config['DATA_ENCRYPTION_KEY'] ?? '';
        if (!$key) return $ciphertext;
        $data = base64_decode($ciphertext);
        if ($data === false || strlen($data) < 16) return '';
        $iv = substr($data, 0, 16);
        $raw = substr($data, 16);
        $plaintext = openssl_decrypt($raw, 'AES-256-CBC', substr(hash('sha256', $key, true), 0, 32), OPENSSL_RAW_DATA, $iv);
        return $plaintext === false ? '' : $plaintext;
    }
}

/**
 * Generate JWT Token
 */
if (!function_exists('generateToken')) {
    function generateToken($userId)
    {
        $config = require '.env.php';
        $secret = $config['JWT_SECRET'];
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode(['user_id' => $userId, 'exp' => time() + (60 * 60 * 24), 'iat' => time()]);
        $b64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $b64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
        $sig = hash_hmac('sha256', "$b64Header.$b64Payload", $secret, true);
        $b64Sig = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($sig));
        return "$b64Header.$b64Payload.$b64Sig";
    }
}

/**
 * Polyfill for getallheaders() if missing (common in php -S or FastCGI)
 */
if (!function_exists('getallheaders')) {
    function getallheaders() {
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
}

/**
 * Authenticate Request
 */
if (!function_exists('authenticate')) {
    function authenticate($pdo = null, $dieOnError = true)
    {
        $token = null;
        $headers = getallheaders();

        // 1. Explicit Headers (Highest priority to prevent cross-app local HTTP cookie contamination)
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
        if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $token = $matches[1];
        }

        if (!$token) {
            $token = $headers['X-Session-Token'] ?? $headers['x-session-token'] ?? null;
        }

        // 2. Cookie Fallback
        if (!$token) {
            $token = $_COOKIE['ehub_session'] ?? null;
        }

        if (!$token) {
            if (function_exists('logApp')) logApp('error', 'AUTH', "AUTH FAIL: No token found. Headers: " . json_encode($headers));
            if ($dieOnError) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized: Missing or invalid token.']);
                exit;
            }
            return null;
        }

        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            if ($dieOnError) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid token format.']);
                exit;
            }
            return null;
        }

        // SECURITY FIX: Verify Signature
        $config = require '.env.php';
        $secret = $config['JWT_SECRET'];
        $headerAndPayload = $parts[0] . '.' . $parts[1];
        
        // Re-calculate signature
        $expectedSig = hash_hmac('sha256', $headerAndPayload, $secret, true);
        $encodedSig = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($expectedSig));

        if (!hash_equals($encodedSig, $parts[2])) {
            if (function_exists('logApp')) logApp('error', 'AUTH', "AUTH FAIL: Invalid token signature. Header+Payload: " . $headerAndPayload);
            if ($dieOnError) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized: Invalid token signature.']);
                exit;
            }
            return null;
        }

        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            if (function_exists('logApp')) logApp('error', 'AUTH', "AUTH FAIL: Token expired.");
            clearSession();
            if ($dieOnError) {
                header('Content-Type: application/json');
                http_response_code(401);
                echo json_encode(['success' => false, 'message' => 'Unauthorized: Token expired.']);
                exit;
            }
            return null;
        }

        $userId = $payload['user_id'] ?? null;

        // If PDO is available, verify the user actually exists in the database
        if ($userId && $pdo) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                clearSession();
                if ($dieOnError) {
                    header('Content-Type: application/json');
                    http_response_code(401);
                    echo json_encode(['success' => false, 'message' => 'Account no longer exists. Please log in again.']);
                    exit;
                }
                return null;
            }
        }

        return $userId;
    }
}

/**
 * Clear the session cookie
 */
if (!function_exists('clearSession')) {
    function clearSession() {
        $cookieParams = [
            'expires' => time() - 3600,
            'path' => '/',
            'domain' => '', // Set if using a specific domain
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Strict'
        ];
        setcookie('ehub_session', '', $cookieParams);
    }
}

/**
 * Get User Role
 */
if (!function_exists('getUserRole')) {
    function getUserRole($userId, $pdo)
    {
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? $row['role'] : null;
    }
}

/**
 * Get Manager Branch ID
 */
if (!function_exists('getManagerBranchId')) {
    function getManagerBranchId($userId, $pdo)
    {
        $stmt = $pdo->prepare("SELECT branch_id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? $row['branch_id'] : null;
    }
}

/**
 * Get User Name
 */
if (!function_exists('getUserName')) {
    function getUserName($userId, $pdo)
    {
        $stmt = $pdo->prepare("SELECT name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? $row['name'] : 'System';
    }
}


/**
 * Check if Super Admin (non-blocking)
 */
if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin($pdo)
    {
        $token = $_COOKIE['ehub_session'] ?? null;

        if (!$token) {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
            if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                $token = $matches[1];
            }
            // Also check X-Session-Token (matches cors_middleware.php)
            if (!$token) {
                $token = $headers['X-Session-Token'] ?? $headers['x-session-token'] ?? null;
            }
        }

        if (!$token) return false;

        $parts = explode('.', $token);
        if (count($parts) !== 3) return false;
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
        if (!$payload || !isset($payload['user_id'])) return false;
        if (isset($payload['exp']) && $payload['exp'] < time()) return false;
        return getUserRole($payload['user_id'], $pdo) === 'super';
    }
}

/**
 * Require Role
 */
if (!function_exists('requireRole')) {
    function requireRole($roles, $pdo)
    {
        $userId = authenticate();
        $role = getUserRole($userId, $pdo);
        if (!is_array($roles)) $roles = [$roles];
        if ($role === 'super' || in_array($role, $roles)) return $userId;
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Forbidden.']);
        exit;
    }
}

/**
 * Logger
 */
if (!function_exists('logger')) {
    function logger($level, $source, $message)
    {
        $logDir = __DIR__ . '/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0755, true);
        
        $userIdCtx = '';
        if (function_exists('authenticate')) {
            try {
                $uid = authenticate(null, false);
                if ($uid) {
                    $userIdCtx = " [UID:$uid]";
                }
            } catch (Exception $e) {}
        }

        $line = date('Y-m-d H:i:s') . " [" . strtoupper($level) . "] [" . strtoupper($source) . "]$userIdCtx $message" . PHP_EOL;
        file_put_contents($logDir . '/app.log', $line, FILE_APPEND);
    }
}

/**
 * Rate Limiter
 * $limit: request count per window
 * $window: time window in seconds (e.g., 60 for minute, 3600 for hour)
 */
if (!function_exists('checkRateLimit')) {
    function checkRateLimit($pdo, $limit = 300, $window = 60)
    {
        // Self-heal table if needed
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS api_rate_limits (
                ip_address VARCHAR(45) PRIMARY KEY,
                request_count INT DEFAULT 1,
                last_request TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
        } catch (Exception $e) {}

        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        try {
            $stmt = $pdo->prepare("SELECT request_count, last_request FROM api_rate_limits WHERE ip_address = ?");
            $stmt->execute([$ip]);
            $row = $stmt->fetch();
            
            if ($row) {
                $lastTime = strtotime($row['last_request']);
                // Check if we are still within the same window since the last request
                if (time() - $lastTime < $window) {
                    if ($row['request_count'] >= $limit) {
                        header('Content-Type: application/json');
                        http_response_code(429);
                        $timeRemaining = ceil(($window - (time() - $lastTime)) / 60);
                        echo json_encode([
                            'success' => false, 
                            'message' => "Too many attempts ($limit per hour). Please wait about $timeRemaining minutes."
                        ]);
                        exit;
                    }
                    $pdo->prepare("UPDATE api_rate_limits SET request_count = request_count + 1, last_request = CURRENT_TIMESTAMP WHERE ip_address = ?")->execute([$ip]);
                } else {
                    // Reset if the window has passed since the last attempt
                    $pdo->prepare("UPDATE api_rate_limits SET request_count = 1, last_request = CURRENT_TIMESTAMP WHERE ip_address = ?")->execute([$ip]);
                }
            } else {
                $pdo->prepare("INSERT INTO api_rate_limits (ip_address, request_count, last_request) VALUES (?, 1, CURRENT_TIMESTAMP)")->execute([$ip]);
            }
        } catch (Exception $e) {
            logger('error', 'SECURITY', "Rate limit error: " . $e->getMessage());
        }
    }
}

/**
 * Maintenance Mode Check
 */
if (!function_exists('checkMaintenanceMode')) {
    function checkMaintenanceMode($pdo)
    {
        $settingsFile = __DIR__ . '/data/super_settings.json';
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            if (isset($settings['maintenanceMode']) && $settings['maintenanceMode'] === true) {
                if (isSuperAdmin($pdo)) return;
                $script = basename($_SERVER['SCRIPT_NAME']);
                if (in_array($script, ['super_settings.php', 'login.php'])) return;
                header('Content-Type: application/json');
                http_response_code(503);
                echo json_encode(['success' => false, 'maintenance' => true, 'message' => 'Under maintenance.']);
                exit;
            }
        }
    }
}

/**
 * Debug Mode Status
 */
if (!function_exists('isDebugEnabled')) {
    function isDebugEnabled()
    {
        $settingsFile = __DIR__ . '/data/super_settings.json';
        if (file_exists($settingsFile)) {
            $settings = json_decode(file_get_contents($settingsFile), true);
            return isset($settings['debugMode']) && $settings['debugMode'] === true;
        }
        return false;
    }
}
