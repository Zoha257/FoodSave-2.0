<?php
require_once 'APIController.php';
require_once __DIR__ . '/../includes/ai_prediction.php';

class DonationController extends APIController {
    public function __construct() {
        parent::__construct();
        $this->authenticate();
    }
    
    /**
     * List available donations
     */
    public function listDonations() {
        $this->validateMethod('GET');
        
        try {
            $query = "
                SELECT 
                    fd.*,
                    u.full_name as donor_name,
                    u.phone as donor_phone
                FROM food_donations fd
                JOIN users u ON fd.donor_id = u.id
                WHERE fd.status = ?
            ";
            
            // Apply filters if provided
            $params = ['available'];
            $where = [];
            
            if (isset($_GET['category'])) {
                $where[] = "fd.category = ?";
                $params[] = $_GET['category'];
            }
            
            if (isset($_GET['city'])) {
                $where[] = "fd.pickup_city LIKE ?";
                $params[] = "%{$_GET['city']}%";
            }
            
            if (!empty($where)) {
                $query .= ' AND ' . implode(' AND ', $where);
            }

            $query .= ' ORDER BY fd.created_at DESC';
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendResponse(['donations' => $donations]);
            
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
    
    /**
     * Get donation details
     */
    public function getDonation($id) {
        $this->validateMethod('GET');
        
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    fd.*,
                    u.full_name as donor_name,
                    u.phone as donor_phone,
                    u.email as donor_email
                FROM food_donations fd
                JOIN users u ON fd.donor_id = u.id
                WHERE fd.id = ?
            ");
            $stmt->execute([$id]);
            $donation = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$donation) {
                $this->sendError('Donation not found', 404);
            }
            
            $this->sendResponse(['donation' => $donation]);
            
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
    
    /**
     * Create new donation
     */
    public function createDonation() {
        $this->validateMethod('POST');
        
        if ($this->user['user_type'] !== 'donor') {
            $this->sendError('Unauthorized', 403);
        }
        
        $data = $this->getRequestBody();
        
        // Validate required fields
        $required = ['title', 'description', 'category', 'quantity', 'expiration_date', 
                    'pickup_address', 'pickup_city', 'pickup_state', 'pickup_zip'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendError("$field is required");
            }
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO food_donations (
                    donor_id, title, description, category, quantity,
                    expiration_date, pickup_address, pickup_city,
                    pickup_state, pickup_zip, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'available')
            ");
            
            $stmt->execute([
                $this->user['id'],
                $data['title'],
                $data['description'],
                $data['category'],
                $data['quantity'],
                $data['expiration_date'],
                $data['pickup_address'],
                $data['pickup_city'],
                $data['pickup_state'],
                $data['pickup_zip']
            ]);
            
            $donationId = $this->pdo->lastInsertId();

            $createdAt = time();
            $quantityValue = (float) preg_replace('/[^0-9.]/', '', (string) $data['quantity']);
            $prediction = generateDonationExpiryPrediction($this->pdo, $donationId, [
                'category' => $data['category'],
                'quantity_value' => $quantityValue > 0 ? $quantityValue : 1,
                'days_until_expiration' => max(0, (strtotime($data['expiration_date']) - $createdAt) / 86400),
                'creation_hour' => (int) date('G', $createdAt),
                'creation_day_of_week' => (int) date('w', $createdAt),
                'pickup_requested' => 0,
                'volunteer_assigned' => 0
            ]);
            
            $this->sendResponse([
                'message' => 'Donation created successfully',
                'donation_id' => $donationId,
                'ai_prediction_generated' => $prediction !== false
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
    
    /**
     * Update donation status
     */
    public function updateStatus($id) {
        $this->validateMethod('PUT');
        
        if ($this->user['user_type'] !== 'donor' && $this->user['user_type'] !== 'admin') {
            $this->sendError('Unauthorized', 403);
        }
        
        $data = $this->getRequestBody();
        if (empty($data['status'])) {
            $this->sendError('Status is required');
        }
        
        try {
            // Verify donation ownership if donor
            if ($this->user['user_type'] === 'donor') {
                $stmt = $this->pdo->prepare("SELECT donor_id FROM food_donations WHERE id = ?");
                $stmt->execute([$id]);
                $donation = $stmt->fetch();
                
                if (!$donation || $donation['donor_id'] !== $this->user['id']) {
                    $this->sendError('Unauthorized', 403);
                }
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE food_donations 
                SET status = ? 
                WHERE id = ?
            ");
            
            $stmt->execute([$data['status'], $id]);
            
            $this->sendResponse([
                'message' => 'Donation status updated successfully'
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
}