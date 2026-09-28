<?php
class Volunteer {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    public function getVolunteerByUserId($userId) {
        $sql = "SELECT v.*, u.full_name, u.email, u.phone
                FROM volunteers v
                JOIN users u ON v.user_id = u.id
                WHERE v.user_id = ?";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching volunteer by user id: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Register a new volunteer
     */
    public function register($userId, $ngoId, $vehicleType, $vehicleNumber, $licenseNumber) {
        $checkSql = "SELECT id FROM volunteers WHERE user_id = ? AND ngo_id = ? LIMIT 1";
        $sql = "INSERT INTO volunteers (user_id, ngo_id, vehicle_type, vehicle_number, license_number) 
                VALUES (?, ?, ?, ?, ?)";
        
        try {
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute([$userId, $ngoId]);
            if ($checkStmt->fetch(PDO::FETCH_ASSOC)) {
                return false;
            }

            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$userId, $ngoId, $vehicleType, $vehicleNumber, $licenseNumber]);
        } catch (PDOException $e) {
            error_log("Error registering volunteer: " . $e->getMessage());
            return false;
        }
    }

    public function getProfile($userId) {
        $sql = "SELECT user_id, vehicle_type, vehicle_number, license_number, availability_days, availability_hours, emergency_contact_name, emergency_contact_phone
                FROM volunteer_profiles
                WHERE user_id = ?";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($profile) {
                $profile['availability_days'] = $profile['availability_days'] ? explode(',', $profile['availability_days']) : [];
                return $profile;
            }

            return [
                'user_id' => $userId,
                'vehicle_type' => '',
                'vehicle_number' => '',
                'license_number' => '',
                'availability_days' => [],
                'availability_hours' => '',
                'emergency_contact_name' => '',
                'emergency_contact_phone' => ''
            ];
        } catch (PDOException $e) {
            error_log('Error fetching volunteer profile: ' . $e->getMessage());
            return [
                'user_id' => $userId,
                'vehicle_type' => '',
                'vehicle_number' => '',
                'license_number' => '',
                'availability_days' => [],
                'availability_hours' => '',
                'emergency_contact_name' => '',
                'emergency_contact_phone' => ''
            ];
        }
    }

    public function saveProfile($userId, array $data) {
        $sql = "INSERT INTO volunteer_profiles (user_id, vehicle_type, vehicle_number, license_number, availability_days, availability_hours, emergency_contact_name, emergency_contact_phone)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    vehicle_type = VALUES(vehicle_type),
                    vehicle_number = VALUES(vehicle_number),
                    license_number = VALUES(license_number),
                    availability_days = VALUES(availability_days),
                    availability_hours = VALUES(availability_hours),
                    emergency_contact_name = VALUES(emergency_contact_name),
                    emergency_contact_phone = VALUES(emergency_contact_phone),
                    updated_at = NOW()";

        $availabilityDays = isset($data['availability_days']) ? implode(',', array_filter($data['availability_days'])) : null;
        if ($availabilityDays === '') {
            $availabilityDays = null;
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                $userId,
                $data['vehicle_type'] ?? null,
                $data['vehicle_number'] ?? null,
                $data['license_number'] ?? null,
                $availabilityDays,
                $data['availability_hours'] ?? null,
                $data['emergency_contact_name'] ?? null,
                $data['emergency_contact_phone'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log('Error saving volunteer profile: ' . $e->getMessage());
            return false;
        }
    }

    public function getAvailableVolunteerUsers($ngoId) {
        $sql = "SELECT u.id, u.full_name, u.email, u.phone, vp.vehicle_type, vp.vehicle_number, vp.license_number, vp.availability_hours
                FROM users u
                LEFT JOIN volunteer_profiles vp ON vp.user_id = u.id
                WHERE u.user_type = 'volunteer'
                  AND u.status = 'active'
                  AND NOT EXISTS (
                      SELECT 1 FROM volunteers v
                      WHERE v.user_id = u.id AND v.ngo_id = ?
                  )
                ORDER BY u.full_name";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ngoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching available volunteer users: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get available volunteers for an NGO
     */
    public function getAvailableVolunteers($ngoId) {
        $sql = "SELECT v.*, u.full_name, u.phone 
                FROM volunteers v 
                JOIN users u ON v.user_id = u.id 
                WHERE v.ngo_id = ? 
                AND v.availability_status = 'available'";
                
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ngoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting volunteers: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Assign a delivery to a volunteer
     */
    public function assignDelivery($volunteerId, $pickupRequestId, $pickupTime) {
        try {
            $this->pdo->beginTransaction();

            $sql = "SELECT id, volunteer_id FROM delivery_assignments WHERE pickup_request_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$pickupRequestId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing) {
                if ($existing['volunteer_id'] && (int)$existing['volunteer_id'] !== (int)$volunteerId) {
                    $sql = "UPDATE volunteers SET availability_status = 'available' WHERE id = ?";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$existing['volunteer_id']]);
                }

                $sql = "UPDATE delivery_assignments 
                        SET volunteer_id = ?, pickup_time = ?, status = 'assigned', updated_at = NOW() 
                        WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$volunteerId, $pickupTime, $existing['id']]);
            } else {
                $sql = "INSERT INTO delivery_assignments (pickup_request_id, volunteer_id, pickup_time, status) 
                        VALUES (?, ?, ?, 'assigned')";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$pickupRequestId, $volunteerId, $pickupTime]);
            }

            $sql = "UPDATE volunteers SET availability_status = 'busy', updated_at = NOW() WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$volunteerId]);

            $sql = "UPDATE pickup_requests SET status = 'approved', updated_at = NOW() WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$pickupRequestId]);

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error assigning delivery: " . $e->getMessage());
            return false;
        }
    }

    public function unassignDelivery($pickupRequestId) {
        try {
            $this->pdo->beginTransaction();

            $sql = "SELECT id, volunteer_id FROM delivery_assignments WHERE pickup_request_id = ? FOR UPDATE";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$pickupRequestId]);
            $assignment = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($assignment) {
                if (!empty($assignment['volunteer_id'])) {
                    $sql = "UPDATE volunteers SET availability_status = 'available', updated_at = NOW() WHERE id = ?";
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$assignment['volunteer_id']]);
                }

                $sql = "UPDATE delivery_assignments SET status = 'cancelled', updated_at = NOW() WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$assignment['id']]);
            }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error unassigning delivery: " . $e->getMessage());
            return false;
        }
    }

    public function getAssignmentForRequest($pickupRequestId) {
        $sql = "SELECT da.*, u.full_name, u.phone, v.vehicle_type, v.vehicle_number, v.user_id 
            FROM delivery_assignments da 
            JOIN volunteers v ON da.volunteer_id = v.id 
            JOIN users u ON v.user_id = u.id 
            WHERE da.pickup_request_id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$pickupRequestId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching delivery assignment: " . $e->getMessage());
            return false;
        }
    }

    public function getAssignmentsMap(array $pickupRequestIds) {
        if (empty($pickupRequestIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($pickupRequestIds), '?'));
        $sql = "SELECT da.*, u.full_name, u.phone, v.vehicle_type, v.vehicle_number, v.user_id 
                FROM delivery_assignments da 
                JOIN volunteers v ON da.volunteer_id = v.id 
                JOIN users u ON v.user_id = u.id 
                WHERE da.pickup_request_id IN ($placeholders)";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($pickupRequestIds);
            $results = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $results[$row['pickup_request_id']] = $row;
            }
            return $results;
        } catch (PDOException $e) {
            error_log("Error fetching delivery assignments: " . $e->getMessage());
            return [];
        }
    }

    public function getActiveDeliveries($ngoId) {
        $sql = "SELECT da.id,
                           da.status,
                           da.pickup_time,
                           da.pickup_request_id,
                           fd.pickup_address,
                           fd.pickup_city,
                           fd.pickup_state,
                           fd.pickup_zip,
                           pr.delivery_address,
                           pr.delivery_city,
                           pr.delivery_state,
                           pr.delivery_zip,
                           u.full_name AS volunteer_name
                FROM delivery_assignments da
                JOIN pickup_requests pr ON da.pickup_request_id = pr.id
                JOIN food_donations fd ON pr.donation_id = fd.id
                JOIN volunteers v ON da.volunteer_id = v.id
                JOIN users u ON v.user_id = u.id
                WHERE pr.ngo_id = ?
                  AND da.status IN ('assigned', 'picked_up', 'in_transit')
                ORDER BY da.updated_at DESC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ngoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching active deliveries: " . $e->getMessage());
            return [];
        }
    }

    public function getAssignmentsForVolunteer($volunteerUserId) {
        $sql = "SELECT da.id,
                       da.status,
                       da.pickup_time,
                       da.delivery_time,
                       da.pickup_request_id,
                       fd.title,
                       fd.category,
                       fd.pickup_address,
                       fd.pickup_city,
                       fd.pickup_state,
                       fd.pickup_zip,
                       pr.delivery_address,
                       pr.delivery_city,
                       pr.delivery_state,
                       pr.delivery_zip,
                       pr.status AS request_status,
                       donors.full_name AS donor_name,
                       donors.phone AS donor_phone,
                       donors.email AS donor_email
                FROM delivery_assignments da
                JOIN volunteers v ON da.volunteer_id = v.id
                JOIN pickup_requests pr ON da.pickup_request_id = pr.id
                JOIN food_donations fd ON pr.donation_id = fd.id
                JOIN users donors ON fd.donor_id = donors.id
                WHERE v.user_id = ?
                  AND da.status IN ('assigned', 'picked_up', 'in_transit', 'at_pickup', 'at_delivery')
                ORDER BY da.updated_at DESC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$volunteerUserId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching volunteer assignments: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update delivery status
     */
    public function updateDeliveryStatus($assignmentId, $status, $latitude = null, $longitude = null, $notes = null) {
        try {
            $this->pdo->beginTransaction();
            
            // Update delivery assignment status
                $sql = "UPDATE delivery_assignments SET status = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$status, $assignmentId]);

                $sql = "INSERT INTO delivery_tracking (delivery_assignment_id, status_update, latitude, longitude, notes) 
                    VALUES (?, ?, ?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$assignmentId, $status, $latitude, $longitude, $notes]);

                if ($status === 'picked_up') {
                $sql = "UPDATE pickup_requests pr
                    JOIN delivery_assignments da ON pr.id = da.pickup_request_id
                    SET pr.status = 'approved', pr.updated_at = NOW()
                    WHERE da.id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$assignmentId]);

                $sql = "UPDATE food_donations fd
                    JOIN pickup_requests pr ON fd.id = pr.donation_id
                    JOIN delivery_assignments da ON pr.id = da.pickup_request_id
                    SET fd.status = 'picked_up', fd.updated_at = NOW()
                    WHERE da.id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$assignmentId]);
                }

                if ($status === 'delivered') {
                $sql = "UPDATE delivery_assignments SET delivery_time = NOW(), updated_at = NOW() WHERE id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$assignmentId]);

                $sql = "UPDATE volunteers v 
                    JOIN delivery_assignments da ON v.id = da.volunteer_id 
                    SET v.availability_status = 'available', v.updated_at = NOW()
                    WHERE da.id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$assignmentId]);

                $sql = "UPDATE pickup_requests pr
                    JOIN delivery_assignments da ON pr.id = da.pickup_request_id
                    SET pr.status = 'completed', pr.updated_at = NOW()
                    WHERE da.id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$assignmentId]);

                $sql = "UPDATE food_donations fd
                    JOIN pickup_requests pr ON fd.id = pr.donation_id
                    JOIN delivery_assignments da ON pr.id = da.pickup_request_id
                    SET fd.status = 'completed', fd.updated_at = NOW()
                    WHERE da.id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$assignmentId]);
                }

            $this->pdo->commit();
            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            error_log("Error updating delivery status: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get delivery tracking information
     */
    public function getDeliveryTracking($assignmentId) {
        $sql = "SELECT * FROM delivery_tracking 
                WHERE delivery_assignment_id = ? 
                ORDER BY created_at DESC";
                
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$assignmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting delivery tracking: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get active delivery locations
     */
    public function getActiveDeliveryLocations($ngoId) {
                $sql = "SELECT da.id,
                               da.status,
                               da.pickup_time,
                               v.id AS volunteer_id,
                               u.full_name AS volunteer_name,
                               fd.pickup_address,
                               fd.pickup_city,
                               fd.pickup_state,
                               fd.pickup_zip,
                               pr.delivery_address,
                               pr.delivery_city,
                               pr.delivery_state,
                               pr.delivery_zip,
                               pr.ngo_id,
                               tracker.latitude AS current_lat,
                               tracker.longitude AS current_lng,
                               tracker.created_at AS last_update
                        FROM delivery_assignments da
                        JOIN volunteers v ON da.volunteer_id = v.id
                        JOIN users u ON v.user_id = u.id
                        JOIN pickup_requests pr ON da.pickup_request_id = pr.id
                        JOIN food_donations fd ON pr.donation_id = fd.id
                        LEFT JOIN (
                            SELECT dt1.delivery_assignment_id,
                                   dt1.latitude,
                                   dt1.longitude,
                                   dt1.created_at
                            FROM delivery_tracking dt1
                            JOIN (
                                SELECT delivery_assignment_id, MAX(created_at) AS latest
                                FROM delivery_tracking
                                GROUP BY delivery_assignment_id
                            ) latest ON latest.delivery_assignment_id = dt1.delivery_assignment_id
                                     AND latest.latest = dt1.created_at
                        ) tracker ON tracker.delivery_assignment_id = da.id
                        WHERE pr.ngo_id = ?
                          AND da.status IN ('assigned', 'picked_up', 'in_transit')
                        ORDER BY da.updated_at DESC";
                
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ngoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting active delivery locations: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get all volunteers for an NGO
     */
    public function getVolunteers($ngoId) {
        $sql = "SELECT v.*, u.full_name, u.phone 
                FROM volunteers v 
                JOIN users u ON v.user_id = u.id 
                WHERE v.ngo_id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$ngoId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching volunteers: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Update the status of a volunteer
     */
    public function updateStatus($volunteerId, $status) {
        $sql = "UPDATE volunteers SET availability_status = ? WHERE id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$status, $volunteerId]);
        } catch (PDOException $e) {
            error_log("Error updating volunteer status: " . $e->getMessage());
            return false;
        }
    }
}