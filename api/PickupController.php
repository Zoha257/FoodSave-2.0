<?php
require_once 'APIController.php';

class PickupController extends APIController {
    public function __construct() {
        parent::__construct();
        $this->authenticate();
    }
    
    /**
     * Request pickup for a donation
     */
    public function requestPickup() {
        $this->validateMethod('POST');
        
        if ($this->user['user_type'] !== 'ngo') {
            $this->sendError('Unauthorized', 403);
        }
        
        $data = $this->getRequestBody();
        
        if (empty($data['donation_id'])) {
            $this->sendError('Donation ID is required');
        }
        
        try {
            // Verify donation is available
            $stmt = $this->pdo->prepare("
                SELECT status 
                FROM food_donations 
                WHERE id = ?
            ");
            $stmt->execute([$data['donation_id']]);
            $donation = $stmt->fetch();
            
            if (!$donation || $donation['status'] !== 'available') {
                $this->sendError('Donation is not available');
            }
            
            // Create pickup request
            $stmt = $this->pdo->prepare("
                INSERT INTO pickup_requests (
                    donation_id, ngo_id, status, requested_at
                ) VALUES (?, ?, 'pending', NOW())
            ");
            
            $stmt->execute([
                $data['donation_id'],
                $this->user['id']
            ]);
            
            // Update donation status so donors see the request
            $stmt = $this->pdo->prepare("
                UPDATE food_donations 
                SET status = 'requested' 
                WHERE id = ?
            ");
            $stmt->execute([$data['donation_id']]);
            
            $this->sendResponse([
                'message' => 'Pickup request created successfully',
                'request_id' => $this->pdo->lastInsertId()
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
    
    /**
     * List pickup requests
     */
    public function listRequests() {
        $this->validateMethod('GET');
        
        try {
            $query = "
                SELECT 
                    pr.*,
                    fd.title as donation_title,
                    fd.description as donation_description,
                    fd.category,
                    fd.quantity,
                    fd.pickup_address,
                    fd.pickup_city,
                    fd.pickup_state,
                    fd.pickup_zip,
                    u.full_name as donor_name,
                    u.phone as donor_phone,
                    n.organization_name as ngo_name,
                    n.phone as ngo_phone
                FROM pickup_requests pr
                JOIN food_donations fd ON pr.donation_id = fd.id
                JOIN users u ON fd.donor_id = u.id
                JOIN users n ON pr.ngo_id = n.id
            ";
            
            $params = [];
            $where = [];
            
            // Filter based on user type
            if ($this->user['user_type'] === 'donor') {
                $where[] = "fd.donor_id = ?";
                $params[] = $this->user['id'];
            } elseif ($this->user['user_type'] === 'ngo') {
                $where[] = "pr.ngo_id = ?";
                $params[] = $this->user['id'];
            }
            
            if (isset($_GET['status'])) {
                $where[] = "pr.status = ?";
                $params[] = $_GET['status'];
            }
            
            if (!empty($where)) {
                $query .= " WHERE " . implode(' AND ', $where);
            }
            
            $query .= " ORDER BY pr.requested_at DESC";
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendResponse(['requests' => $requests]);
            
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
    
    /**
     * Update pickup request status
     */
    public function updateRequestStatus($id) {
        $this->validateMethod('PUT');
        
        $data = $this->getRequestBody();
        if (empty($data['status'])) {
            $this->sendError('Status is required');
        }
        
        try {
            // Get request details
            $stmt = $this->pdo->prepare("
                SELECT pr.*, fd.donor_id, fd.id as donation_id
                FROM pickup_requests pr
                JOIN food_donations fd ON pr.donation_id = fd.id
                WHERE pr.id = ?
            ");
            $stmt->execute([$id]);
            $request = $stmt->fetch();
            
            if (!$request) {
                $this->sendError('Request not found', 404);
            }
            
            // Verify authorization
            if ($this->user['user_type'] === 'donor' && $request['donor_id'] !== $this->user['id']) {
                $this->sendError('Unauthorized', 403);
            } elseif ($this->user['user_type'] === 'ngo' && $request['ngo_id'] !== $this->user['id']) {
                $this->sendError('Unauthorized', 403);
            }
            
            // Update request status
            $stmt = $this->pdo->prepare("
                UPDATE pickup_requests 
                SET status = ? 
                WHERE id = ?
            ");
            $stmt->execute([$data['status'], $id]);
            
            // Update donation status accordingly
            $donationStatus = $data['status'] === 'completed' ? 'completed' : 
                            ($data['status'] === 'cancelled' ? 'available' : 'pending');
            
            $stmt = $this->pdo->prepare("
                UPDATE food_donations 
                SET status = ? 
                WHERE id = ?
            ");
            $stmt->execute([$donationStatus, $request['donation_id']]);
            
            $this->sendResponse([
                'message' => 'Request status updated successfully'
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
}