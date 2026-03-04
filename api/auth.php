
<?php
// backend/products.php
require 'cors_middleware.php';
require 'db.php';
require 'security.php';

header('Content-Type: application/json');

// --- Self-healing Schema ---
if ($config['DB_AUTO_REPAIR'] ?? false) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            phone VARCHAR(20),
            address TEXT,
            role ENUM('customer', 'admin', 'branch_admin', 'marketing', 'super') DEFAULT 'customer',
            level INT DEFAULT 1,
            level_name VARCHAR(50) DEFAULT 'Starter',
            avatar_text VARCHAR(10) DEFAULT 'U',
            profile_image LONGTEXT,
            id_number VARCHAR(50) DEFAULT NULL,
            id_photo LONGTEXT DEFAULT NULL,
            id_verified TINYINT(1) DEFAULT 0,
            id_verified_at DATETIME DEFAULT NULL,
            wallet_balance DECIMAL(10, 2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");

        $cols = $pdo->query("DESCRIBE users")->fetchAll(PDO::FETCH_COLUMN);
        if (!in_array('role', $cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN role ENUM('customer', 'admin', 'branch_admin', 'marketing', 'super') DEFAULT 'customer' AFTER address");
        }
        if (!in_array('wallet_balance', $cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN wallet_balance DECIMAL(10, 2) DEFAULT 0.00 AFTER id_verified_at");
        }
        if (!in_array('profile_image', $cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN profile_image LONGTEXT AFTER avatar_text");
        }
        if (!in_array('id_verified', $cols)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN id_verified TINYINT(1) DEFAULT 0 AFTER id_photo");
        }
    } catch (Exception $e) {
        error_log("Users schema self-healing failed: " . $e->getMessage());
    }
}

$method = $_SERVER['REQUEST_METHOD'];
$contentType = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : '';

if ($method === 'GET' && isset($_GET['action']) && $_GET['action'] === 'check_session') {
    // Only if using PHP sessions
    session_start();
    if (isset($_SESSION['user_id'])) {
        echo json_encode(['status' => 'authenticated', 'user' => $_SESSION['user']]);
    } else {
        echo json_encode(['status' => 'guest']);
    }
    exit;
}

if ($method === 'POST') {
    // Determine if content is JSON
    if (strcasecmp($contentType, 'application/json') != 0) {
        // Fallback for form data if needed, but we stick to JSON
    }

    $content = trim(file_get_contents("php://input"));
    $decoded = json_decode($content, true);

    if (!is_array($decoded)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON payload']);
        exit;
    }

    $action = $decoded['action'] ?? '';

    if ($action === 'register') {
        $name = $decoded['name'] ?? '';
        $email = $decoded['email'] ?? '';
        $password = $decoded['password'] ?? '';

        if (!$name || !$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing required fields']);
            exit;
        }

        // Check if email exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Email already registered']);
            exit;
        }

        // Create new user
        $hash = hashPassword($password);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password_hash) VALUES (?, ?, ?)");

        try {
            $stmt->execute([$name, $email, $hash]);
            $userId = $pdo->lastInsertId();

            $user = ['id' => $userId, 'name' => $name, 'email' => $email, 'role' => 'customer'];

            // Start session (optional)
            // session_start();
            // $_SESSION['user_id'] = $userId;
            // $_SESSION['user'] = $user;

            echo json_encode(['success' => true, 'user' => $user]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
        }
    } elseif ($action === 'login') {
        $email = $decoded['email'] ?? '';
        $password = $decoded['password'] ?? '';

        if (!$email || !$password) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing email or password']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && verifyPassword($password, $user['password_hash'])) {
            // Remove password hash from response
            unset($user['password_hash']);

            // session_start();
            // $_SESSION['user_id'] = $user['id'];
            // $_SESSION['user'] = $user;

            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid credentials']);
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
    }
}
