<?php
class Notification {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new notification
     */
    public function create($userId, $title, $message, $type, $relatedTo, $relatedId = null) {
        $sql = "INSERT INTO notifications (user_id, title, message, type, related_to, related_id) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$userId, $title, $message, $type, $relatedTo, $relatedId]);
        } catch (PDOException $e) {
            error_log("Error creating notification: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get unread notifications for a user
     */
    public function getUnreadNotifications($userId) {
        $sql = "SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting notifications: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Mark a notification as read
     */
    public function markAsRead($notificationId, $userId) {
        $sql = "UPDATE notifications SET is_read = 1 
                WHERE id = ? AND user_id = ?";
                
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$notificationId, $userId]);
        } catch (PDOException $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create expiration notifications for donations
     */
    public function checkAndCreateExpirationAlerts() {
        // Get donations expiring in 24 hours
        $sql = "SELECT fd.*, u.id as donor_id, u.full_name as donor_name 
                FROM food_donations fd 
                JOIN users u ON fd.donor_id = u.id 
                WHERE fd.status = 'available' 
                AND fd.expiration_date BETWEEN NOW() 
                AND DATE_ADD(NOW(), INTERVAL 24 HOUR)";
                
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
            $expiringDonations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($expiringDonations as $donation) {
                // Notify donor
                $this->create(
                    $donation['donor_id'],
                    'Donation Expiring Soon',
                    "Your donation '{$donation['title']}' will expire in 24 hours.",
                    'warning',
                    'expiration',
                    $donation['id']
                );
                
                // Notify nearby NGOs
                $this->notifyNearbyNGOs($donation);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Error checking expiration: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Notify nearby NGOs about expiring donations
     */
    private function notifyNearbyNGOs($donation) {
        // Get NGOs in the same city
        $sql = "SELECT id FROM users 
                WHERE user_type = 'ngo' 
                AND status = 'active' 
                AND city = ?";
                
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$donation['pickup_city']]);
            $ngos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($ngos as $ngo) {
                $this->create(
                    $ngo['id'],
                    'Urgent Donation Available',
                    "A donation of {$donation['title']} is expiring soon in your area.",
                    'alert',
                    'donation',
                    $donation['id']
                );
            }
        } catch (PDOException $e) {
            error_log("Error notifying NGOs: " . $e->getMessage());
        }
    }
}