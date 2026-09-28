<?php
// Load local environment variables before reading configuration.
require_once __DIR__ . '/env.php';

// Database configuration.
// Environment variables are used when provided; these defaults keep XAMPP local setup simple.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : '');
define('DB_NAME', getenv('DB_NAME') ?: 'foodsave_db');

// Google Maps API key MUST come from the environment.
// Never hardcode a real API key in this repository.
define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: '');

// JWT secret. A real deployment should always provide JWT_SECRET.
define('JWT_SECRET', getenv('JWT_SECRET') ?: '');

// Create database connection
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch(PDOException $e) {
        // Do not expose database credentials/details to users.
        error_log("FoodSave database connection failed: " . $e->getMessage());
        die("Database connection failed. Please check the server configuration.");
    }
}

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function rememberUser(PDO $pdo, array $user, bool $remember) {
    if (!$remember) {
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        INDEX idx_remember_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");

    $token = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare('INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$user['id'], hash('sha256', $token), date('Y-m-d H:i:s', strtotime('+30 days'))]);

    setcookie('foodsave_remember', $user['id'] . ':' . $token, [
        'expires' => time() + (30 * 24 * 60 * 60),
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

function restoreRememberedUser(PDO $pdo, ?string $userType = null) {
    if (isLoggedIn() || empty($_COOKIE['foodsave_remember'])) {
        return;
    }

    [$userId, $token] = array_pad(explode(':', $_COOKIE['foodsave_remember'], 2), 2, '');
    if (!ctype_digit($userId) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        setcookie('foodsave_remember', '', time() - 3600, '/');
        return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS remember_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        expires_at DATETIME NOT NULL,
        INDEX idx_remember_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    $typeCondition = $userType === null ? '' : ' AND u.user_type = ?';
    $params = [(int) $userId];
    if ($userType !== null) {
        $params[] = $userType;
    }
    $params[] = hash('sha256', $token);
    $stmt = $pdo->prepare("SELECT u.* FROM users u INNER JOIN remember_tokens r ON r.user_id = u.id
        WHERE u.id = ?{$typeCondition} AND u.status = 'active'
        AND r.token_hash = ? AND r.expires_at > NOW()");
    $stmt->execute($params);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        setcookie('foodsave_remember', '', time() - 3600, '/');
        return;
    }

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['full_name'] = $user['full_name'];
}

function redirectTo($url) {
    header("Location: $url");
    exit();
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Email notification function (placeholder)
function sendNotification($to, $subject, $message) {
    // Configure a real SMTP provider for production.
    return true;
}

// Get Google Maps API key
function getGoogleMapsApiKey() {
    return GOOGLE_MAPS_API_KEY;
}
?>
