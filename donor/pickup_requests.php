<?php
require_once '../includes/config.php';

if (!isLoggedIn() || getUserType() !== 'donor') {
    redirectTo('login.php');
}

$message = '';

// Handle request approval/rejection
if (isset($_GET['action']) && isset($_GET['id']) && is_numeric($_GET['id'])) {
    $action = $_GET['action'];
    $request_id = $_GET['id'];
    
    if ($action === 'approve' || $action === 'reject') {
        try {
            $pdo = getDBConnection();
            
            // Verify this request belongs to a donation by this donor
            $stmt = $pdo->prepare("
                SELECT pr.*, fd.donor_id 
                FROM pickup_requests pr 
                JOIN food_donations fd ON pr.donation_id = fd.id 
                WHERE pr.id = ? AND fd.donor_id = ?
            ");
            $stmt->execute([$request_id, $_SESSION['user_id']]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($request) {
                $new_status = ($action === 'approve') ? 'approved' : 'rejected';
                
                // Update request status
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("UPDATE pickup_requests SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$new_status, $request_id]);
                
                // If approved, update donation status
                if ($action === 'approve') {
                    $stmt = $pdo->prepare("UPDATE food_donations SET status = 'requested', updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$request['donation_id']]);
                    $stmt = $pdo->prepare("INSERT INTO delivery_tasks (pickup_request_id, status, pickup_time) SELECT ?, 'pending', preferred_pickup_time FROM pickup_requests WHERE id = ? AND NOT EXISTS (SELECT 1 FROM delivery_tasks WHERE pickup_request_id = ?)");
                    $stmt->execute([(int)$request_id, (int)$request_id, (int)$request_id]);
                }
                $pdo->commit();
                $message = 'Request ' . $new_status . ' successfully.';
            } else {
                $message = 'Invalid request.';
            }
        } catch (PDOException $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $message = 'Error processing request.';
        }
    }
}

try {
    $pdo = getDBConnection();
    $donor_id = $_SESSION['user_id'];
    
    // Get all pickup requests for this donor's donations
    $stmt = $pdo->prepare("
        SELECT pr.*, fd.title, fd.category, fd.quantity, fd.expiration_date,
               u.full_name as ngo_name, u.phone as ngo_phone, u.email as ngo_email
        FROM pickup_requests pr 
        JOIN food_donations fd ON pr.donation_id = fd.id 
        JOIN users u ON pr.ngo_id = u.id 
        WHERE fd.donor_id = ? 
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$donor_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickup Requests - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h5 class="text-white">FoodSave Donor</h5>
                        <small class="text-muted">Welcome, <?php echo $_SESSION['full_name']; ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="add_donation.php">
                                <i class="fas fa-plus"></i> Add Donation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_donations.php">
                                <i class="fas fa-utensils"></i> My Donations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="pickup_requests.php">
                                <i class="fas fa-truck"></i> Pickup Requests
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="food_safety.php">
                                <i class="fas fa-clipboard-check"></i> Food Safety Guidelines
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">
                                <i class="fas fa-user"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Pickup Requests</h1>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Filter Tabs -->
                <ul class="nav nav-tabs mb-3" id="requestTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                            All Requests
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab">
                            Pending
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="approved-tab" data-bs-toggle="tab" data-bs-target="#approved" type="button" role="tab">
                            Approved
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab">
                            Completed
                        </button>
                    </li>
                </ul>

                <div class="tab-content" id="requestTabsContent">
                    <div class="tab-pane fade show active" id="all" role="tabpanel">
                        <?php if (empty($requests)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> No pickup requests found. 
                                <a href="add_donation.php">Add a donation</a> to start receiving requests.
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($requests as $request): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($request['title']); ?></h6>
                                            <span class="badge status-<?php echo $request['status']; ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <p class="card-text">
                                                <strong>NGO:</strong> <?php echo htmlspecialchars($request['ngo_name']); ?><br>
                                                <strong>Category:</strong> <?php echo ucfirst(str_replace('_', ' ', $request['category'])); ?><br>
                                                <strong>Quantity:</strong> <?php echo htmlspecialchars($request['quantity']); ?><br>
                                                <strong>Expires:</strong> <?php echo date('M j, Y', strtotime($request['expiration_date'])); ?>
                                            </p>
                                            
                                            <?php if ($request['request_message']): ?>
                                                <div class="alert alert-light">
                                                    <small><strong>Message:</strong> <?php echo htmlspecialchars($request['request_message']); ?></small>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($request['pickup_date'] || $request['preferred_pickup_time']): ?>
                                                <p class="small">
                                                    <strong>Requested Pickup:</strong>
                                                    <?php
                                                        if (!empty($request['preferred_pickup_time'])) {
                                                            echo date('M j, Y g:i A', strtotime($request['preferred_pickup_time']));
                                                        } else {
                                                            echo date('M j, Y', strtotime($request['pickup_date']));
                                                            if (!empty($request['pickup_time'])) {
                                                                echo ' at ' . date('g:i A', strtotime($request['pickup_time']));
                                                            }
                                                        }
                                                    ?>
                                                </p>
                                            <?php endif; ?>

                                            <?php if ($request['delivery_address']): ?>
                                                <p class="small mb-2">
                                                    <strong>Delivery Location:</strong><br>
                                                    <?php echo htmlspecialchars($request['delivery_address']); ?><br>
                                                    <?php echo htmlspecialchars($request['delivery_city']); ?><?php echo $request['delivery_city'] && $request['delivery_state'] ? ', ' : ''; ?><?php echo htmlspecialchars($request['delivery_state']); ?> <?php echo htmlspecialchars($request['delivery_zip']); ?>
                                                </p>
                                            <?php endif; ?>
                                            
                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($request['ngo_phone'] ?: 'No phone'); ?><br>
                                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($request['ngo_email']); ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <?php if ($request['status'] === 'pending'): ?>
                                        <div class="card-footer">
                                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                                <a href="?action=reject&id=<?php echo $request['id']; ?>" 
                                                   class="btn btn-outline-danger btn-sm"
                                                   onclick="return confirm('Are you sure you want to reject this request?')">
                                                    <i class="fas fa-times"></i> Reject
                                                </a>
                                                <a href="?action=approve&id=<?php echo $request['id']; ?>" 
                                                   class="btn btn-success btn-sm"
                                                   onclick="return confirm('Are you sure you want to approve this request?')">
                                                    <i class="fas fa-check"></i> Approve
                                                </a>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <div class="card-footer">
                                            <small class="text-muted">
                                                Requested on <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                                            </small>
                                            <?php if ($request['status'] === 'completed'): ?>
                                                <div class="mt-2">
                                                    <a class="btn btn-outline-success btn-sm" href="../ngo/pickup_receipt.php?id=<?php echo $request['id']; ?>" target="_blank">
                                                        <i class="fas fa-file-invoice"></i> View Receipt
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-info"><?php echo count($requests); ?></h5>
                                <p class="card-text">Total Requests</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-warning">
                                    <?php echo count(array_filter($requests, function($r) { return $r['status'] === 'pending'; })); ?>
                                </h5>
                                <p class="card-text">Pending</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-success">
                                    <?php echo count(array_filter($requests, function($r) { return $r['status'] === 'approved'; })); ?>
                                </h5>
                                <p class="card-text">Approved</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card text-center">
                            <div class="card-body">
                                <h5 class="card-title text-primary">
                                    <?php echo count(array_filter($requests, function($r) { return $r['status'] === 'completed'; })); ?>
                                </h5>
                                <p class="card-text">Completed</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
    <script>
        // Filter requests by status
        document.addEventListener('DOMContentLoaded', function() {
            const tabs = document.querySelectorAll('#requestTabs button[data-bs-toggle="tab"]');
            const cards = document.querySelectorAll('.card');
            
            tabs.forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    const target = e.target.getAttribute('data-bs-target').replace('#', '');
                    
                    cards.forEach(card => {
                        const status = card.querySelector('.badge').textContent.toLowerCase();
                        const cardContainer = card.closest('.col-md-6');
                        
                        if (target === 'all' || status === target) {
                            cardContainer.style.display = 'block';
                        } else {
                            cardContainer.style.display = 'none';
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>

