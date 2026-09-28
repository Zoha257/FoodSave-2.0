<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn()) {
    redirectTo('login.php');
}

$userId = $_SESSION['user_id'];
$userType = getUserType();
$requestId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($requestId <= 0) {
    http_response_code(400);
    echo 'Invalid receipt request.';
    exit;
}

try {
    $pdo = getDBConnection();
    $sql = <<<SQL
SELECT pr.*,
    fd.title AS donation_title,
       fd.category,
       fd.quantity,
       fd.expiration_date,
       fd.pickup_address,
       fd.pickup_city,
       fd.pickup_state,
       fd.pickup_zip,
       fd.created_at AS donation_created_at,
    fd.donor_id AS donor_id_ref,
       donor.full_name AS donor_name,
       donor.email AS donor_email,
       donor.phone AS donor_phone,
       ngo.full_name AS ngo_name,
       ngo.email AS ngo_email,
       ngo.phone AS ngo_phone,
       da.id AS volunteer_assignment_id,
       da.status AS volunteer_status,
       da.pickup_time AS volunteer_pickup_time,
       da.delivery_time AS volunteer_delivery_time,
       v.user_id AS volunteer_user_id,
       v.vehicle_type AS volunteer_vehicle_type,
       v.vehicle_number AS volunteer_vehicle_number,
       volunteer_user.full_name AS volunteer_name,
       volunteer_user.email AS volunteer_email,
    volunteer_user.phone AS volunteer_phone
FROM pickup_requests pr
JOIN food_donations fd ON pr.donation_id = fd.id
JOIN users donor ON fd.donor_id = donor.id
JOIN users ngo ON pr.ngo_id = ngo.id
LEFT JOIN delivery_assignments da ON pr.id = da.pickup_request_id
LEFT JOIN volunteers v ON da.volunteer_id = v.id
LEFT JOIN users volunteer_user ON v.user_id = volunteer_user.id
WHERE pr.id = ?
SQL;
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($requestId));
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        http_response_code(404);
        echo 'Pickup record not found.';
        exit;
    }

    $authorized = false;
    if ($userType === 'ngo' && (int) $record['ngo_id'] === (int) $userId) {
        $authorized = true;
    } elseif ($userType === 'donor' && (int) $record['donor_id_ref'] === (int) $userId) {
        $authorized = true;
    } elseif ($userType === 'admin') {
        $authorized = true;
    }

    if (!$authorized) {
        http_response_code(403);
        echo 'You are not authorized to view this receipt.';
        exit;
    }
} catch (Exception $e) {
    error_log('Receipt load error: ' . $e->getMessage());
    http_response_code(500);
    echo 'Unable to load pickup receipt right now.';
    exit;
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function formatDateTime($value, $fallback = 'N/A')
{
    if (empty($value)) {
        return $fallback;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $fallback;
    }
    return date('M j, Y g:i A', $timestamp);
}

function formatDateOnly($value, $fallback = 'N/A')
{
    if (empty($value)) {
        return $fallback;
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return $fallback;
    }
    return date('M j, Y', $timestamp);
}

$receiptNumber = 'FS-PR-' . str_pad((string) $record['id'], 6, '0', STR_PAD_LEFT);
$completedOn = formatDateTime($record['updated_at']);
$createdOn = formatDateTime($record['created_at']);
$donationCreatedOn = formatDateTime($record['donation_created_at']);
$categoryLabel = ucfirst(str_replace('_', ' ', (string) $record['category']));
$statusLabel = ucfirst(str_replace('_', ' ', (string) $record['status']));
$notes = $record['request_message'] ?: '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Pickup Receipt #<?php echo h($receiptNumber); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        :root {
            color-scheme: light;
            font-family: 'Segoe UI', Tahoma, sans-serif;
        }
        body {
            margin: 0;
            padding: 24px;
            background: #f3f4f6;
            color: #1f2933;
        }
        .receipt-wrapper {
            max-width: 900px;
            margin: 0 auto;
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.12);
            overflow: hidden;
        }
        header {
            background: linear-gradient(135deg, #1a5d1a, #1e4d92);
            color: #fff;
            padding: 32px 36px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
        }
        header .title {
            font-size: 28px;
            font-weight: 600;
            margin: 0;
        }
        header .meta {
            text-align: right;
            font-size: 14px;
            line-height: 1.6;
        }
        .section {
            padding: 28px 36px;
            border-bottom: 1px solid #e5e7eb;
        }
        .section h2 {
            margin: 0 0 16px;
            font-size: 20px;
            color: #1e4d92;
        }
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px 28px;
        }
        .details-grid .label {
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #6b7280;
            margin-bottom: 6px;
        }
        .details-grid .value {
            font-size: 16px;
            font-weight: 600;
        }
        .tag {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
            background: rgba(30, 77, 146, 0.1);
            color: #1e4d92;
        }
        .status-tag {
            background: rgba(26, 93, 26, 0.1);
            color: #1a5d1a;
        }
        .notes {
            background: #f9fafb;
            padding: 18px;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            font-size: 15px;
            line-height: 1.6;
        }
        .footer {
            padding: 24px 36px;
            font-size: 12px;
            color: #6b7280;
            background: #f9fafb;
        }
        .actions {
            margin: 18px 36px 0;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        .actions button,
        .actions a {
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            color: #fff;
            background: #1e4d92;
        }
        .actions button.secondary,
        .actions a.secondary {
            background: #6b7280;
        }
        @media print {
            body {
                background: #fff;
                padding: 0;
            }
            .actions { display: none; }
            .receipt-wrapper {
                box-shadow: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
<div class="receipt-wrapper">
    <header>
        <div>
            <p class="title">FoodSave Pickup Receipt</p>
            <div class="tag status-tag">Status: <?php echo h($statusLabel); ?></div>
        </div>
        <div class="meta">
            <div>Receipt: <strong><?php echo h($receiptNumber); ?></strong></div>
            <div>Created: <?php echo h($createdOn); ?></div>
            <div>Completed: <?php echo h($completedOn); ?></div>
        </div>
    </header>

    <section class="section">
        <h2>Donation Overview</h2>
        <div class="details-grid">
            <div>
                <div class="label">Donation Title</div>
                <div class="value"><?php echo h($record['donation_title']); ?></div>
            </div>
            <div>
                <div class="label">Category</div>
                <div class="value"><?php echo h($categoryLabel); ?></div>
            </div>
            <div>
                <div class="label">Quantity</div>
                <div class="value"><?php echo h($record['quantity']); ?></div>
            </div>
            <div>
                <div class="label">Expiration Date</div>
                <div class="value"><?php echo h(formatDateOnly($record['expiration_date'])); ?></div>
            </div>
            <div>
                <div class="label">Donation Created</div>
                <div class="value"><?php echo h($donationCreatedOn); ?></div>
            </div>
        </div>
    </section>

    <section class="section">
        <h2>Pickup & Delivery Details</h2>
        <div class="details-grid">
            <div>
                <div class="label">Pickup Address</div>
                <div class="value"><?php echo h($record['pickup_address']); ?><br><?php echo h($record['pickup_city']); ?>, <?php echo h($record['pickup_state']); ?> <?php echo h($record['pickup_zip']); ?></div>
            </div>
            <div>
                <div class="label">Delivery Address</div>
                <div class="value"><?php echo h($record['delivery_address']); ?><br><?php echo h($record['delivery_city']); ?>, <?php echo h($record['delivery_state']); ?> <?php echo h($record['delivery_zip']); ?></div>
            </div>
            <div>
                <div class="label">Preferred Pickup</div>
                <div class="value"><?php echo h(formatDateTime($record['preferred_pickup_time'], 'Flex Schedule')); ?></div>
            </div>
            <div>
                <div class="label">Confirmed Pickup</div>
                <div class="value"><?php echo h(formatDateTime($record['volunteer_pickup_time'])); ?></div>
            </div>
            <div>
                <div class="label">Delivery Confirmed</div>
                <div class="value"><?php echo h(formatDateTime($record['volunteer_delivery_time'], $completedOn)); ?></div>
            </div>
        </div>
    </section>

    <section class="section">
        <h2>Participants</h2>
        <div class="details-grid">
            <div>
                <div class="label">Donor</div>
                <div class="value"><?php echo h($record['donor_name']); ?></div>
                <div><?php echo h($record['donor_email']); ?><br><?php echo h($record['donor_phone'] ?: 'N/A'); ?></div>
            </div>
            <div>
                <div class="label">Receiving NGO</div>
                <div class="value"><?php echo h($record['ngo_name']); ?></div>
                <div><?php echo h($record['ngo_email']); ?><br><?php echo h($record['ngo_phone'] ?: 'N/A'); ?></div>
            </div>
            <?php if (!empty($record['volunteer_name'])): ?>
            <div>
                <div class="label">Assigned Volunteer</div>
                <div class="value"><?php echo h($record['volunteer_name']); ?></div>
                <div><?php echo h($record['volunteer_email']); ?><br><?php echo h($record['volunteer_phone'] ?: 'N/A'); ?></div>
                <?php if (!empty($record['volunteer_vehicle_type'])): ?>
                    <div class="tag">Vehicle: <?php echo h(ucfirst($record['volunteer_vehicle_type'])); ?> <?php echo h($record['volunteer_vehicle_number']); ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <?php if (!empty($notes)): ?>
    <section class="section">
        <h2>Pickup Notes</h2>
        <div class="notes"><?php echo nl2br(h($notes)); ?></div>
    </section>
    <?php endif; ?>

    <section class="section">
        <h2>Certification</h2>
        <p style="margin: 0; line-height: 1.8; font-size: 15px;">
            This receipt confirms that the donation listed above has been collected from the donor and safely delivered to the receiving organization.
            Both parties acknowledge that the food was handled in accordance with the FoodSave safety guidelines and local regulations.
        </p>
    </section>

    <div class="actions">
        <button type="button" onclick="window.print()">Print Receipt</button>
        <a class="secondary" href="javascript:window.close();">Close</a>
    </div>

    <div class="footer">
        <strong>FoodSave</strong> &bull; Community Food Redistribution Platform<br>
        Generated on <?php echo h(date('M j, Y g:i A')); ?>
    </div>
</div>
</body>
</html>
