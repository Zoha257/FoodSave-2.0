<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirectTo('login.php');
}

$type = isset($_GET['type']) ? $_GET['type'] : '';
$from = isset($_GET['from']) ? $_GET['from'] : '';
$to = isset($_GET['to']) ? $_GET['to'] : '';

$allowedTypes = array('donations', 'requests', 'users');
if (!in_array($type, $allowedTypes, true)) {
    http_response_code(400);
    echo 'Invalid export type.';
    exit;
}

$startDate = null;
$endDate = null;

if ($from !== '') {
    $start = DateTime::createFromFormat('Y-m-d', $from);
    if (!$start) {
        http_response_code(400);
        echo 'Invalid start date.';
        exit;
    }
    $start->setTime(0, 0, 0);
    $startDate = $start->format('Y-m-d H:i:s');
}

if ($to !== '') {
    $end = DateTime::createFromFormat('Y-m-d', $to);
    if (!$end) {
        http_response_code(400);
        echo 'Invalid end date.';
        exit;
    }
    $end->setTime(23, 59, 59);
    $endDate = $end->format('Y-m-d H:i:s');
}

try {
    $pdo = getDBConnection();
    $query = '';
    $params = array();
    $columns = array();
    $dateField = '';

    switch ($type) {
        case 'donations':
            $columns = array(
                'donation_title' => 'Donation Title',
                'category' => 'Category',
                'quantity' => 'Quantity',
                'expiration_date' => 'Expiration Date',
                'status' => 'Status',
                'pickup_city' => 'Pickup City',
                'pickup_state' => 'Pickup State',
                'donor_name' => 'Donor Name',
                'created_at' => 'Created At'
            );
            $query = "
                SELECT
                    fd.title AS donation_title,
                    fd.category,
                    fd.quantity,
                    fd.expiration_date,
                    fd.status,
                    fd.pickup_city,
                    fd.pickup_state,
                    u.full_name AS donor_name,
                    fd.created_at
                FROM food_donations fd
                JOIN users u ON fd.donor_id = u.id
                WHERE 1 = 1
            ";
            $dateField = 'fd.created_at';
            break;
        case 'requests':
            $columns = array(
                'request_id' => 'Request ID',
                'donation_title' => 'Donation Title',
                'ngo_name' => 'NGO Name',
                'status' => 'Status',
                'preferred_pickup_time' => 'Preferred Pickup',
                'delivery_city' => 'Delivery City',
                'delivery_state' => 'Delivery State',
                'created_at' => 'Requested At',
                'updated_at' => 'Updated At'
            );
            $query = "
                SELECT
                    pr.id AS request_id,
                    fd.title AS donation_title,
                    ngo.full_name AS ngo_name,
                    pr.status,
                    pr.preferred_pickup_time,
                    pr.delivery_city,
                    pr.delivery_state,
                    pr.created_at,
                    pr.updated_at
                FROM pickup_requests pr
                JOIN food_donations fd ON pr.donation_id = fd.id
                JOIN users ngo ON pr.ngo_id = ngo.id
                WHERE 1 = 1
            ";
            $dateField = 'pr.created_at';
            break;
        case 'users':
            $columns = array(
                'user_id' => 'User ID',
                'full_name' => 'Name',
                'user_type' => 'Type',
                'email' => 'Email',
                'phone' => 'Phone',
                'city' => 'City',
                'state' => 'State',
                'status' => 'Status',
                'created_at' => 'Joined At'
            );
            $query = "
                SELECT
                    u.id AS user_id,
                    u.full_name,
                    u.user_type,
                    u.email,
                    u.phone,
                    u.city,
                    u.state,
                    u.status,
                    u.created_at
                FROM users u
                WHERE u.user_type != 'admin'
            ";
            $dateField = 'u.created_at';
            break;
    }

    if ($startDate !== null) {
        $query .= " AND {$dateField} >= ?";
        $params[] = $startDate;
    }

    if ($endDate !== null) {
        $query .= " AND {$dateField} <= ?";
        $params[] = $endDate;
    }

    $query .= ' ORDER BY ' . $dateField . ' DESC';

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="foodsave_' . $type . '_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new RuntimeException('Unable to open output stream.');
    }

    fputcsv($output, array_values($columns));

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $line = array();
        foreach ($columns as $key => $label) {
            $value = isset($row[$key]) ? $row[$key] : '';
            if ($value instanceof DateTime) {
                $value = $value->format('Y-m-d H:i:s');
            }
            if (is_string($value)) {
                $line[] = $value;
            } elseif ($value === null) {
                $line[] = '';
            } else {
                $line[] = (string) $value;
            }
        }
        fputcsv($output, $line);
    }

    fclose($output);
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo 'Failed to export data.';
    exit;
}
