<?php
require_once 'APIController.php';

class AuthController extends APIController {
    /**
     * Login endpoint
     */
    public function login() {
        $this->validateMethod('POST');
        $data = $this->getRequestBody();
        
        if (empty($data['email']) || empty($data['password'])) {
            $this->sendError('Email and password are required', 422);
        }
        
        // Check rate limit
        $identifier = $_SERVER['REMOTE_ADDR'] . '_' . strtolower($data['email']);
        if (!$this->security->checkRateLimit('api_login', $identifier, 5, 900)) {
            $this->sendError('Too many login attempts', 429);
        }
        
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([strtolower($data['email'])]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($data['password'], $user['password'])) {
                if ($user['status'] !== 'active') {
                    $this->sendError('Account is not active');
                }
                
                $token = $this->jwt->generateToken($user['id'], $user['user_type']);
                
                $this->sendResponse([
                    'token' => $token,
                    'user' => [
                        'id' => (int) $user['id'],
                        'full_name' => $user['full_name'],
                        'email' => $user['email'],
                        'user_type' => $user['user_type'],
                        'organization_name' => $user['organization_name'] ?? null
                    ]
                ]);
            }
            
            $this->sendError('Invalid credentials', 401);
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
    
    /**
     * Register endpoint
     */
    public function register() {
        $this->validateMethod('POST');
        $data = $this->getRequestBody();
        
        // Validate required fields
        $required = ['email', 'password', 'full_name', 'user_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendError("$field is required");
            }
        }
        
        $email = strtolower(trim($data['email']));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->sendError('Invalid email address');
        }
        
        $userType = strtolower($data['user_type']);
        $allowedTypes = ['donor', 'ngo'];
        if (!in_array($userType, $allowedTypes, true)) {
            $this->sendError('Invalid user type');
        }
        
        if ($userType === 'ngo' && trim($data['organization_name'] ?? '') === '') {
            $this->sendError('organization_name is required for NGOs');
        }
        
        // Validate password strength
        $passwordCheck = $this->security->enhancePassword($data['password']);
        if ($passwordCheck !== true) {
            $this->sendError($passwordCheck[0]);
        }
        
        $fullName = trim($data['full_name']);
        $organizationName = isset($data['organization_name']) ? trim($data['organization_name']) : null;
        $phone = isset($data['phone']) ? trim($data['phone']) : null;
        $address = isset($data['address']) ? trim($data['address']) : null;
        $city = isset($data['city']) ? trim($data['city']) : null;
        $state = isset($data['state']) ? trim($data['state']) : null;
        $zipCode = isset($data['zip_code']) ? trim($data['zip_code']) : null;

        try {
            // Check if username exists
            if (!empty($data['username'])) {
                $usernameInput = strtolower(trim($data['username']));
                $usernameInput = preg_replace('/[^a-z0-9._-]/', '', $usernameInput);
                if ($usernameInput === '') {
                    $this->sendError('Invalid username');
                }

                $stmt = $this->pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$usernameInput]);
                if ($stmt->fetch()) {
                    $this->sendError('Username already exists');
                }
                $username = $usernameInput;
            } else {
                $username = $this->generateUniqueUsername($email);
            }
            
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $this->sendError('Email already exists');
            }
            
            // Create user
            $stmt = $this->pdo->prepare("
                INSERT INTO users (username, email, password, full_name, user_type, phone, address, city, state, zip_code, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $status = $userType === 'ngo' ? 'pending' : 'active';
            $stmt->execute([
                $username,
                $email,
                password_hash($data['password'], PASSWORD_DEFAULT),
                $userType === 'ngo' ? ($organizationName ?: $fullName) : $fullName,
                $userType,
                $phone !== '' ? $phone : null,
                $address !== '' ? $address : null,
                $city !== '' ? $city : null,
                $state !== '' ? $state : null,
                $zipCode !== '' ? $zipCode : null,
                $status
            ]);
            
            $userId = $this->pdo->lastInsertId();
            
            if ($status === 'active') {
                $token = $this->jwt->generateToken($userId, $userType);
                $this->sendResponse([
                    'message' => 'Registration successful',
                    'user_id' => (int) $userId,
                    'token' => $token
                ]);
            } else {
                $this->sendResponse([
                    'message' => 'Registration successful. Please wait for admin approval.',
                    'user_id' => (int) $userId
                ]);
            }
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
    
    /**
     * Refresh token endpoint
     */
    public function refreshToken() {
        $this->authenticate();
        
        $token = $this->jwt->generateToken($this->user['id'], $this->user['user_type']);
        $this->sendResponse(['token' => $token]);
    }

    private function generateUniqueUsername($email)
    {
        $base = preg_replace('/[^a-z0-9]+/i', '', strstr($email, '@', true) ?: 'user');
        $candidate = strtolower(substr($base, 0, 30));
        if ($candidate === '') {
            $candidate = 'user';
        }

        $suffix = 0;
        while (true) {
            $username = $suffix === 0 ? $candidate : $candidate . $suffix;
            $stmt = $this->pdo->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if (!$stmt->fetch()) {
                return $username;
            }
            $suffix++;
        }
    }
}