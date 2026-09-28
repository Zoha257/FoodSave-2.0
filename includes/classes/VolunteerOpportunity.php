<?php
class VolunteerOpportunity {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Create a new volunteer opportunity
     */
    public function create($ngoId, $data) {
        $sql = "INSERT INTO volunteer_opportunities (
                    ngo_id, title, description, type, required_volunteers,
                    location_address, location_city, location_state, location_zip,
                    start_date, end_date, status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
                
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $ngoId,
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
            return $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            error_log("Error creating volunteer opportunity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * List all volunteer opportunities with optional filters
     */
    public function listOpportunities($filters = []) {
        $sql = "SELECT 
                    vo.*,
                    n.organization_name as ngo_name,
                    n.phone as ngo_phone,
                    n.email as ngo_email,
                    COUNT(va.id) as current_volunteers
                FROM volunteer_opportunities vo
                JOIN users n ON vo.ngo_id = n.id
                LEFT JOIN volunteer_assignments va ON vo.id = va.opportunity_id
                    AND va.status IN ('pending', 'approved', 'completed')
                WHERE vo.status = 'active'";
        
        $params = [];
        
        if (!empty($filters['type'])) {
            $sql .= " AND vo.type = ?";
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['city'])) {
            $sql .= " AND vo.location_city LIKE ?";
            $params[] = "%{$filters['city']}%";
        }
        
        if (!empty($filters['ngo_id'])) {
            $sql .= " AND vo.ngo_id = ?";
            $params[] = $filters['ngo_id'];
        }
        
        $sql .= " GROUP BY vo.id ORDER BY vo.created_at DESC";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error listing volunteer opportunities: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get details of a specific opportunity
     */
    public function get($id) {
        $sql = "SELECT 
                    vo.*,
                    n.organization_name as ngo_name,
                    n.phone as ngo_phone,
                    n.email as ngo_email,
                    COUNT(va.id) as current_volunteers
                FROM volunteer_opportunities vo
                JOIN users n ON vo.ngo_id = n.id
                LEFT JOIN volunteer_assignments va ON vo.id = va.opportunity_id
                    AND va.status IN ('pending', 'approved', 'completed')
                WHERE vo.id = ?
                GROUP BY vo.id";
                
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting volunteer opportunity: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Apply for a volunteer opportunity
     */
    public function apply($opportunityId, $volunteerId) {
        try {
            $this->pdo->beginTransaction();
            
            // Check if already applied
            $sql = "SELECT id FROM volunteer_assignments WHERE opportunity_id = ? AND volunteer_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$opportunityId, $volunteerId]);
            
            if ($stmt->fetch()) {
                $this->pdo->rollBack();
                throw new Exception('Already applied for this opportunity');
            }
            
            // Check if opportunity is still active and has space
            $sql = "SELECT 
                        required_volunteers,
                        (
                            SELECT COUNT(*) 
                            FROM volunteer_assignments 
                            WHERE opportunity_id = ? 
                              AND status IN ('pending', 'approved', 'completed')
                        ) as current_volunteers
                    FROM volunteer_opportunities 
                    WHERE id = ? AND status = 'active'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$opportunityId, $opportunityId]);
            $opportunity = $stmt->fetch();
            
            if (!$opportunity) {
                $this->pdo->rollBack();
                throw new Exception('Opportunity not found or not active');
            }
            
            if ($opportunity['current_volunteers'] >= $opportunity['required_volunteers']) {
                $this->pdo->rollBack();
                throw new Exception('This opportunity is fully staffed');
            }
            
            // Create assignment
            $sql = "INSERT INTO volunteer_assignments (opportunity_id, volunteer_id, status) VALUES (?, ?, 'pending')";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$opportunityId, $volunteerId]);
            
            $assignmentId = $this->pdo->lastInsertId();
            $this->pdo->commit();
            
            return $assignmentId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Error applying for volunteer opportunity: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update application status
     */
    public function updateApplicationStatus($assignmentId, $status) {
        $sql = "UPDATE volunteer_assignments SET status = ? WHERE id = ?";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$status, $assignmentId]);
        } catch (PDOException $e) {
            error_log("Error updating application status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get volunteer's assignments
     */
    public function getVolunteerAssignments($volunteerId, $status = null) {
        $sql = "SELECT 
                    va.*,
                    vo.title,
                    vo.description,
                    vo.type,
                    vo.location_address,
                    vo.location_city,
                    vo.location_state,
                    vo.location_zip,
                    vo.start_date,
                    vo.end_date,
                    n.organization_name as ngo_name
                FROM volunteer_assignments va
                JOIN volunteer_opportunities vo ON va.opportunity_id = vo.id
                JOIN users n ON vo.ngo_id = n.id
                WHERE va.volunteer_id = ?";
        
        $params = [$volunteerId];
        
        if ($status) {
            $sql .= " AND va.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY va.created_at DESC";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting volunteer assignments: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get NGO's applications
     */
    public function getNGOApplications($ngoId, $status = null) {
        $sql = "SELECT 
                    va.*,
                    vo.title as opportunity_title,
                    u.full_name as volunteer_name,
                    u.email as volunteer_email,
                    u.phone as volunteer_phone
                FROM volunteer_assignments va
                JOIN volunteer_opportunities vo ON va.opportunity_id = vo.id
                JOIN users u ON va.volunteer_id = u.id
                WHERE vo.ngo_id = ?";
        
        $params = [$ngoId];
        
        if ($status) {
            $sql .= " AND va.status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY va.created_at DESC";
        
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting NGO applications: " . $e->getMessage());
            return [];
        }
    }
}