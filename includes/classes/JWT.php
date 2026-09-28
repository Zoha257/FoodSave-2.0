<?php
class JWT {
    private $secret;
    
    public function __construct() {
        $this->secret = defined('JWT_SECRET') ? JWT_SECRET : '';
    }
    
    /**
     * Generate JWT token
     */
    public function generateToken($userId, $userType) {
        if ($this->secret === '') {
            throw new Exception('JWT_SECRET is not configured.');
        }
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]);
        
        $payload = json_encode([
            'user_id' => $userId,
            'user_type' => $userType,
            'iat' => time(),
            'exp' => time() + (60 * 60 * 24) // 24 hours
        ]);
        
        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payload);
        
        $signature = hash_hmac('sha256', 
            $base64Header . "." . $base64Payload, 
            $this->secret, 
            true
        );
        
        $base64Signature = $this->base64UrlEncode($signature);
        
        return $base64Header . "." . $base64Payload . "." . $base64Signature;
    }
    
    /**
     * Verify JWT token
     */
    public function verifyToken($token) {
        if ($this->secret === '') {
            return false;
        }
        try {
            $tokenParts = explode('.', $token);
            if (count($tokenParts) !== 3) {
                return false;
            }
            
            list($base64Header, $base64Payload, $base64Signature) = $tokenParts;
            
            $signature = $this->base64UrlDecode($base64Signature);
            
            $validSignature = hash_hmac('sha256', 
                $base64Header . "." . $base64Payload, 
                $this->secret, 
                true
            );
            
            if (!hash_equals($signature, $validSignature)) {
                return false;
            }
            
            $payload = json_decode($this->base64UrlDecode($base64Payload), true);
            
            if ($payload['exp'] < time()) {
                return false;
            }
            
            return $payload;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    private function base64UrlDecode($data) {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}