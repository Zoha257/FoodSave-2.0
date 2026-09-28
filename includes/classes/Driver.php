<?php
class Driver {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function register($userId, $vehicleType, $vehicleNumber, $licenseNumber) {
        $stmt = $this->pdo->prepare(
            "INSERT INTO drivers (user_id, vehicle_type, vehicle_number, license_number, status)
             VALUES (?, ?, ?, ?, 'available')"
        );
        return $stmt->execute([$userId, $vehicleType, $vehicleNumber, $licenseNumber]);
    }

    public function getAvailableDrivers() {
        $stmt = $this->pdo->query(
            "SELECT d.*, u.full_name, u.phone, u.email
             FROM drivers d
             JOIN users u ON d.user_id = u.id
             WHERE d.status = 'available' AND u.status = 'active'
             ORDER BY u.full_name"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listDeliveryTasks($status = 'pending', $driverUserId = null) {
        $allowed = ['pending','assigned','picked_up','in_transit','completed','cancelled'];
        if (!in_array($status, $allowed, true)) $status = 'pending';

        $sql = "SELECT dt.*, fd.title AS donation_title,
                       fd.pickup_address, fd.pickup_city, fd.pickup_state, fd.pickup_zip,
                       du.full_name AS donor_name, du.phone AS donor_phone,
                       nu.organization_name AS ngo_name, nu.full_name AS ngo_contact_name, nu.phone AS ngo_phone,
                       pr.delivery_address, pr.delivery_city, pr.delivery_state, pr.delivery_zip,
                       pr.preferred_pickup_time
                FROM delivery_tasks dt
                JOIN pickup_requests pr ON dt.pickup_request_id = pr.id
                JOIN food_donations fd ON pr.donation_id = fd.id
                JOIN users du ON fd.donor_id = du.id
                JOIN users nu ON pr.ngo_id = nu.id
                WHERE dt.status = ?";
        $params = [$status];

        if ($status !== 'pending' && $driverUserId !== null) {
            $sql .= " AND dt.driver_id = ?";
            $params[] = $driverUserId;
        }
        if ($status === 'pending') {
            $sql .= " AND dt.driver_id IS NULL";
        }
        $sql .= " ORDER BY COALESCE(dt.pickup_time, pr.preferred_pickup_time, dt.created_at) ASC, dt.created_at DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function acceptTask($taskId, $driverUserId) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT id, driver_id, status FROM delivery_tasks WHERE id = ? FOR UPDATE");
            $stmt->execute([(int)$taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$task || $task['status'] !== 'pending' || $task['driver_id'] !== null) {
                throw new Exception('Task is no longer available.');
            }

            $stmt = $this->pdo->prepare("SELECT user_id, status FROM drivers WHERE user_id = ? FOR UPDATE");
            $stmt->execute([(int)$driverUserId]);
            $driver = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$driver || $driver['status'] !== 'available') {
                throw new Exception('Driver is not available.');
            }

            $stmt = $this->pdo->prepare("UPDATE delivery_tasks SET driver_id = ?, status = 'assigned', updated_at = NOW() WHERE id = ? AND status = 'pending' AND driver_id IS NULL");
            $stmt->execute([(int)$driverUserId, (int)$taskId]);
            if ($stmt->rowCount() !== 1) throw new Exception('Task could not be accepted.');

            $stmt = $this->pdo->prepare("UPDATE drivers SET status = 'busy', updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([(int)$driverUserId]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('Driver acceptTask: '.$e->getMessage());
            throw $e;
        }
    }

    public function updateTaskStatus($taskId, $status, $driverUserId, $latitude = null, $longitude = null, $notes = null) {
        $allowed = ['picked_up','in_transit','completed','cancelled'];
        if (!in_array($status, $allowed, true)) throw new Exception('Invalid delivery status.');

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM delivery_tasks WHERE id = ? FOR UPDATE");
            $stmt->execute([(int)$taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$task || (int)$task['driver_id'] !== (int)$driverUserId) throw new Exception('Unauthorized');

            $current = $task['status'];
            $transitions = [
                'assigned' => ['picked_up','cancelled'],
                'picked_up' => ['in_transit','completed','cancelled'],
                'in_transit' => ['completed','cancelled'],
            ];
            if (!isset($transitions[$current]) || !in_array($status, $transitions[$current], true)) {
                throw new Exception('Invalid status transition.');
            }

            $stmt = $this->pdo->prepare("UPDATE delivery_tasks SET status = ?, updated_at = NOW(), pickup_time = CASE WHEN ? = 'picked_up' THEN COALESCE(pickup_time, NOW()) ELSE pickup_time END, delivery_time = CASE WHEN ? = 'completed' THEN NOW() ELSE delivery_time END, notes = COALESCE(?, notes) WHERE id = ?");
            $stmt->execute([$status, $status, $status, $notes, (int)$taskId]);

            $stmt = $this->pdo->prepare("INSERT INTO driver_tracking (delivery_task_id, status_update, latitude, longitude, notes) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([(int)$taskId, $status, $latitude, $longitude, $notes]);

            if ($status === 'picked_up') {
                $stmt = $this->pdo->prepare("UPDATE food_donations fd JOIN pickup_requests pr ON fd.id = pr.donation_id SET fd.status = 'picked_up', fd.updated_at = NOW() WHERE pr.id = ?");
                $stmt->execute([(int)$task['pickup_request_id']]);
            }
            if ($status === 'completed') {
                $stmt = $this->pdo->prepare("UPDATE pickup_requests SET status = 'completed', updated_at = NOW() WHERE id = ?");
                $stmt->execute([(int)$task['pickup_request_id']]);
                $stmt = $this->pdo->prepare("UPDATE food_donations fd JOIN pickup_requests pr ON fd.id = pr.donation_id SET fd.status = 'completed', fd.updated_at = NOW() WHERE pr.id = ?");
                $stmt->execute([(int)$task['pickup_request_id']]);
                $stmt = $this->pdo->prepare("UPDATE drivers SET status = 'available', updated_at = NOW() WHERE user_id = ?");
                $stmt->execute([(int)$driverUserId]);
            }
            if ($status === 'cancelled') {
                $stmt = $this->pdo->prepare("UPDATE drivers SET status = 'available', updated_at = NOW() WHERE user_id = ?");
                $stmt->execute([(int)$driverUserId]);
                $stmt = $this->pdo->prepare("UPDATE delivery_tasks SET driver_id = NULL, status = 'pending', updated_at = NOW() WHERE id = ?");
                $stmt->execute([(int)$taskId]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            error_log('Driver updateTaskStatus: '.$e->getMessage());
            throw $e;
        }
    }

    public function assignPickup($pickupRequestId, $driverUserId, $pickupTime = null) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT id, status FROM drivers WHERE user_id = ? FOR UPDATE");
            $stmt->execute([(int)$driverUserId]);
            $driver = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$driver || $driver['status'] !== 'available') throw new Exception('Driver is currently unavailable.');

            $stmt = $this->pdo->prepare("SELECT id, status FROM pickup_requests WHERE id = ? FOR UPDATE");
            $stmt->execute([(int)$pickupRequestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$request || !in_array($request['status'], ['approved','pending'], true)) throw new Exception('Pickup request is not eligible for driver assignment.');

            $stmt = $this->pdo->prepare("SELECT id, driver_id, status FROM delivery_tasks WHERE pickup_request_id = ? FOR UPDATE");
            $stmt->execute([(int)$pickupRequestId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($task) {
                if (!empty($task['driver_id']) && (int)$task['driver_id'] !== (int)$driverUserId) throw new Exception('A different driver is already assigned.');
                $stmt = $this->pdo->prepare("UPDATE delivery_tasks SET driver_id = ?, status = 'assigned', pickup_time = COALESCE(?, pickup_time), updated_at = NOW() WHERE id = ?");
                $stmt->execute([(int)$driverUserId, $pickupTime, (int)$task['id']]);
            } else {
                $stmt = $this->pdo->prepare("INSERT INTO delivery_tasks (pickup_request_id, driver_id, status, pickup_time) VALUES (?, ?, 'assigned', ?)");
                $stmt->execute([(int)$pickupRequestId, (int)$driverUserId, $pickupTime]);
            }

            $stmt = $this->pdo->prepare("UPDATE drivers SET status = 'busy', updated_at = NOW() WHERE user_id = ?");
            $stmt->execute([(int)$driverUserId]);
            $stmt = $this->pdo->prepare("UPDATE pickup_requests SET status = 'approved', updated_at = NOW() WHERE id = ?");
            $stmt->execute([(int)$pickupRequestId]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function getAssignmentsMap(array $pickupRequestIds) {
        if (!$pickupRequestIds) return [];
        $ph = implode(',', array_fill(0, count($pickupRequestIds), '?'));
        $stmt = $this->pdo->prepare("SELECT dt.*, u.full_name, u.phone, d.vehicle_type, d.vehicle_number FROM delivery_tasks dt LEFT JOIN drivers d ON dt.driver_id = d.user_id LEFT JOIN users u ON dt.driver_id = u.id WHERE dt.pickup_request_id IN ($ph)");
        $stmt->execute(array_map('intval', $pickupRequestIds));
        $out=[];
        while ($row=$stmt->fetch(PDO::FETCH_ASSOC)) $out[$row['pickup_request_id']]=$row;
        return $out;
    }

    public function getActiveDeliveriesByNGO($ngoId) {
        $stmt=$this->pdo->prepare("SELECT dt.id, dt.status, dt.pickup_time, dt.pickup_request_id, dt.driver_id, fd.pickup_address, fd.pickup_city, fd.pickup_state, fd.pickup_zip, pr.delivery_address, pr.delivery_city, pr.delivery_state, pr.delivery_zip, u.full_name AS driver_name, tracker.latitude AS current_lat, tracker.longitude AS current_lng, tracker.created_at AS last_update FROM delivery_tasks dt JOIN pickup_requests pr ON dt.pickup_request_id=pr.id JOIN food_donations fd ON pr.donation_id=fd.id LEFT JOIN users u ON dt.driver_id=u.id LEFT JOIN (SELECT lt.delivery_task_id,lt.latitude,lt.longitude,lt.created_at FROM driver_tracking lt JOIN (SELECT delivery_task_id,MAX(created_at) latest FROM driver_tracking GROUP BY delivery_task_id) x ON x.delivery_task_id=lt.delivery_task_id AND x.latest=lt.created_at) tracker ON tracker.delivery_task_id=dt.id WHERE pr.ngo_id=? AND dt.status IN ('assigned','picked_up','in_transit') ORDER BY dt.updated_at DESC");
        $stmt->execute([(int)$ngoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTrackingForTask($taskId) {
        $stmt=$this->pdo->prepare("SELECT * FROM driver_tracking WHERE delivery_task_id=? ORDER BY created_at DESC");
        $stmt->execute([(int)$taskId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStatistics($driverId) {
        $stmt=$this->pdo->prepare("SELECT COUNT(*) total_deliveries, SUM(status='completed') completed_deliveries, SUM(status IN ('assigned','picked_up','in_transit')) active_deliveries FROM delivery_tasks WHERE driver_id=?");
        $stmt->execute([(int)$driverId]);
        $stats=$stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $stats['total_deliveries']=(int)($stats['total_deliveries']??0);
        $stats['completed_deliveries']=(int)($stats['completed_deliveries']??0);
        $stats['active_deliveries']=(int)($stats['active_deliveries']??0);
        $stmt=$this->pdo->prepare("SELECT dt.*,fd.title donation_title,nu.organization_name ngo_name FROM delivery_tasks dt JOIN pickup_requests pr ON dt.pickup_request_id=pr.id JOIN food_donations fd ON pr.donation_id=fd.id JOIN users nu ON pr.ngo_id=nu.id WHERE dt.driver_id=? ORDER BY dt.updated_at DESC LIMIT 5");
        $stmt->execute([(int)$driverId]);
        $stats['recent_deliveries']=$stmt->fetchAll(PDO::FETCH_ASSOC);
        return $stats;
    }
}
?>
