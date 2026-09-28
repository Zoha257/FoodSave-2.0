<?php
class Security {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Generate CSRF token
     */
    public function generateCSRFToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
    
    /**
     * Verify CSRF token
     */
    public function verifyCSRFToken($token) {
        if (empty($_SESSION['csrf_token']) || empty($token)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * Check rate limit
     */
    public function checkRateLimit($action, $identifier, $limit, $timeWindow) {
        try {
            // Clean up old entries
            $stmt = $this->pdo->prepare("
                DELETE FROM rate_limits 
                WHERE action = ? 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$action, $timeWindow]);
            
            // Count attempts
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM rate_limits 
                WHERE action = ? 
                AND identifier = ? 
                AND created_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
            ");
            $stmt->execute([$action, $identifier, $timeWindow]);
            $attempts = $stmt->fetchColumn();
            
            if ($attempts >= $limit) {
                return false;
            }
            
            // Log attempt
            $stmt = $this->pdo->prepare("
                INSERT INTO rate_limits (action, identifier) 
                VALUES (?, ?)
            ");
            $stmt->execute([$action, $identifier]);
            
            return true;
        } catch (Exception $e) {
            error_log("Rate limit error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Enhance password security
     */
    public function enhancePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "Password must be at least 8 characters long";
        }
        if (!preg_match("/[A-Z]/", $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        if (!preg_match("/[a-z]/", $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        if (!preg_match("/[0-9]/", $password)) {
            $errors[] = "Password must contain at least one number";
        }
        if (!preg_match("/[^A-Za-z0-9]/", $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return empty($errors) ? true : $errors;
    }
    
    /**
     * Sanitize input
     */
    public function sanitize($input) {
        if (is_array($input)) {
            return array_map([$this, 'sanitize'], $input);
        }
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent($event, $userId = null, $details = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO security_logs (event, user_id, details, ip_address) 
                VALUES (?, ?, ?, ?)
            ");
            return $stmt->execute([
                $event,
                $userId,
                $details,
                $_SERVER['REMOTE_ADDR']
            ]);
        } catch (Exception $e) {
            error_log("Security log error: " . $e->getMessage());
            return false;
        }
    }
}