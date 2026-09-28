<?php
require_once 'APIController.php';

class VolunteerController extends APIController {
    public function __construct() {
        parent::__construct();
        $this->authenticate();
    }
    
    /**
     * List available volunteer opportunities
     */
    public function listOpportunities() {
        $this->validateMethod('GET');
        
        try {
            $query = "
                SELECT 
                    vo.*,
                    n.organization_name as ngo_name,
                    n.phone as ngo_phone,
                    n.email as ngo_email,
                    COUNT(va.id) as current_volunteers
                FROM volunteer_opportunities vo
                JOIN users n ON vo.ngo_id = n.id
                LEFT JOIN volunteer_assignments va ON vo.id = va.opportunity_id
                WHERE vo.status = 'active'
                GROUP BY vo.id
                ORDER BY vo.created_at DESC
            ";
            
            // Apply filters
            $params = [];
            $where = [];
            
            if (isset($_GET['type'])) {
                $where[] = "vo.type = ?";
                $params[] = $_GET['type'];
            }
            
            if (isset($_GET['city'])) {
                $where[] = "vo.location_city LIKE ?";
                $params[] = "%{$_GET['city']}%";
            }
            
            if (!empty($where)) {
                $query = str_replace('WHERE vo.status', 'WHERE vo.status = \'active\' AND ' . implode(' AND ', $where), $query);
            }
            
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $opportunities = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $this->sendResponse(['opportunities' => $opportunities]);
            
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
    
    /**
     * Create a new volunteer opportunity (NGO only)
     */
    public function createOpportunity() {
        $this->validateMethod('POST');
        
        if ($this->user['user_type'] !== 'ngo') {
            $this->sendError('Unauthorized', 403);
        }
        
        $data = $this->getRequestBody();
        
        $required = ['title', 'description', 'type', 'required_volunteers', 
                    'location_address', 'location_city', 'location_state', 'location_zip',
                    'start_date', 'end_date'];
        
        foreach ($required as $field) {
            if (empty($data[$field])) {
                $this->sendError("$field is required");
            }
        }
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO volunteer_opportunities (
                    ngo_id, title, description, type, required_volunteers,
                    location_address, location_city, location_state, location_zip,
                    start_date, end_date, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
            ");
            
            $stmt->execute([
                $this->user['id'],
                $data['title'],
                $data['description'],
                $data['type'],
                $data['required_volunteers'],
                $data['location_address'],
                $data['location_city'],
                $data['location_state'],
                $data['location_zip'],
                $data['start_date'],
                $data['end_date']
            ]);
            
            $opportunityId = $this->pdo->lastInsertId();
            
            $this->sendResponse([
                'message' => 'Volunteer opportunity created successfully',
                'opportunity_id' => $opportunityId
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
    
    /**
     * Apply for a volunteer opportunity
     */
    public function applyForOpportunity() {
        $this->validateMethod('POST');
        
        if ($this->user['user_type'] !== 'volunteer') {
            $this->sendError('Unauthorized', 403);
        }
        
        $data = $this->getRequestBody();
        
        if (empty($data['opportunity_id'])) {
            $this->sendError('Opportunity ID is required');
        }
        
        try {
            // Check if already applied
            $stmt = $this->pdo->prepare("
                SELECT id FROM volunteer_assignments 
                WHERE opportunity_id = ? AND volunteer_id = ?
            ");
            $stmt->execute([$data['opportunity_id'], $this->user['id']]);
            
            if ($stmt->fetch()) {
                $this->sendError('Already applied for this opportunity');
            }
            
            // Check if opportunity is still active
            $stmt = $this->pdo->prepare("
                SELECT required_volunteers, 
                       (SELECT COUNT(*) FROM volunteer_assignments 
                        WHERE opportunity_id = ?) as current_volunteers
                FROM volunteer_opportunities 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$data['opportunity_id'], $data['opportunity_id']]);
            $opportunity = $stmt->fetch();
            
            if (!$opportunity) {
                $this->sendError('Opportunity not found or not active');
            }
            
            if ($opportunity['current_volunteers'] >= $opportunity['required_volunteers']) {
                $this->sendError('This opportunity is fully staffed');
            }
            
            // Create assignment
            $stmt = $this->pdo->prepare("
                INSERT INTO volunteer_assignments (
                    opportunity_id, volunteer_id, status
                ) VALUES (?, ?, 'pending')
            ");
            
            $stmt->execute([$data['opportunity_id'], $this->user['id']]);
            
            $this->sendResponse([
                'message' => 'Application submitted successfully',
                'assignment_id' => $this->pdo->lastInsertId()
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
    
    /**
     * Update volunteer application status
     */
    public function updateApplicationStatus($id) {
        $this->validateMethod('PUT');
        
        $data = $this->getRequestBody();
        if (empty($data['status'])) {
            $this->sendError('Status is required');
        }
        
        try {
            // Get assignment details
            $stmt = $this->pdo->prepare("
                SELECT va.*, vo.ngo_id
                FROM volunteer_assignments va
                JOIN volunteer_opportunities vo ON va.opportunity_id = vo.id
                WHERE va.id = ?
            ");
            $stmt->execute([$id]);
            $assignment = $stmt->fetch();
            
            if (!$assignment) {
                $this->sendError('Assignment not found', 404);
            }
            
            // Verify authorization
            if ($this->user['user_type'] === 'ngo' && $assignment['ngo_id'] !== $this->user['id']) {
                $this->sendError('Unauthorized', 403);
            } elseif ($this->user['user_type'] === 'volunteer' && $assignment['volunteer_id'] !== $this->user['id']) {
                $this->sendError('Unauthorized', 403);
            }
            
            $stmt = $this->pdo->prepare("
                UPDATE volunteer_assignments 
                SET status = ? 
                WHERE id = ?
            ");
            
            $stmt->execute([$data['status'], $id]);
            
            $this->sendResponse([
                'message' => 'Application status updated successfully'
            ]);
            
        } catch (Exception $e) {
            $this->sendError('Server error', 500);
        }
    }
}