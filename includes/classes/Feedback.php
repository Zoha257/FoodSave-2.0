<?php
class Feedback {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create new feedback
     */
    public function create($pickupRequestId, $rating, $comments, $feedbackType) {
        $sql = "INSERT INTO feedback (pickup_request_id, rating, comments, feedback_type) 
                VALUES (?, ?, ?, ?)";
                
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$pickupRequestId, $rating, $comments, $feedbackType]);
        } catch (PDOException $e) {
            error_log("Error creating feedback: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get feedback for a pickup request
     */
    public function getFeedback($pickupRequestId) {
        $sql = "SELECT f.*, u.full_name 
                FROM feedback f 
                JOIN pickup_requests pr ON f.pickup_request_id = pr.id 
                JOIN users u ON pr.ngo_id = u.id 
                WHERE f.pickup_request_id = ?";
                
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$pickupRequestId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting feedback: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get average rating for a volunteer
     */
    public function getVolunteerRating($volunteerId) {
        $sql = "SELECT AVG(f.rating) as avg_rating, COUNT(f.id) as total_ratings 
                FROM feedback f 
                JOIN pickup_requests pr ON f.pickup_request_id = pr.id 
                JOIN delivery_assignments da ON pr.id = da.pickup_request_id 
                WHERE da.volunteer_id = ?";
                
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$volunteerId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting volunteer rating: " . $e->getMessage());
            return ['avg_rating' => 0, 'total_ratings' => 0];
        }
    }
    
    /**
     * Get feedback statistics for NGO
     */
    public function getNGOStats($ngoId) {
        $sql = "SELECT 
                    COUNT(f.id) as total_feedback,
                    AVG(f.rating) as avg_rating,
                    COUNT(CASE WHEN f.rating >= 4 THEN 1 END) as positive_feedback,
                    COUNT(CASE WHEN f.rating <= 2 THEN 1 END) as negative_feedback
                FROM feedback f 
                JOIN pickup_requests pr ON f.pickup_request_id = pr.id 
                WHERE pr.ngo_id = ?";
                
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ngoId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting NGO stats: " . $e->getMessage());
            return [
                'total_feedback' => 0,
                'avg_rating' => 0,
                'positive_feedback' => 0,
                'negative_feedback' => 0
            ];
        }
    }
}