<?php
require_once '../includes/config.php';
require_once '../includes/classes/JWT.php';
require_once '../includes/classes/Security.php';

class APIController {
    protected $pdo;
    protected $jwt;
    protected $security;
    protected $user;
    
    public function __construct() {
        header('Content-Type: application/json');
        $this->pdo = getDBConnection();
        $this->jwt = new JWT();
        $this->security = new Security($this->pdo);
    }
    
    /**
     * Verify API authentication
     */
    protected function authenticate() {
        $headers = array_change_key_case(getallheaders(), CASE_LOWER);
        if (!isset($headers['authorization'])) {
            $this->sendError('Unauthorized', 401);
            exit;
        }
        
        $token = str_replace('Bearer ', '', $headers['authorization']);
        $payload = $this->jwt->verifyToken($token);
        
        if (!$payload) {
            $this->sendError('Invalid token', 401);
            exit;
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
            $stmt->execute([$payload['user_id']]);
            $this->user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$this->user) {
                $this->sendError('User not found', 401);
                exit;
            }
        } catch (Exception $e) {
            $this->sendError('Database error', 500);
            exit;
        }
    }
    
    /**
     * Send JSON response
     */
    protected function sendResponse($data, $code = 200) {
        http_response_code($code);

        if (!is_array($data)) {
            echo json_encode([
                'status' => 'success',
                'data' => $data
            ]);
            exit;
        }

        $response = array_merge(['status' => 'success'], $data);
        echo json_encode($response);
        exit;
    }
    
    /**
     * Send error response
     */
    protected function sendError($message, $code = 400) {
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message
        ]);
        exit;
    }
    
    /**
     * Validate request method
     */
    protected function validateMethod($method) {
        if ($_SERVER['REQUEST_METHOD'] !== $method) {
            $this->sendError('Method not allowed', 405);
            exit;
        }
    }
    
    /**
     * Get JSON request body
     */
    protected function getRequestBody() {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sendError('Invalid JSON');
            exit;
        }
        
        return $data;
    }
}