<?php
require_once(__DIR__ . '/../includes/config.php');
require_once(__DIR__ . '/../includes/classes/Volunteer.php');
require_once(__DIR__ . '/../includes/classes/Driver.php');

if (!isLoggedIn() || getUserType() !== 'ngo') {
    redirectTo('login.php');
}

$activePage = 'requests';

$requests = [];
$volunteers = [];
$assignments = [];
$driverAssignments = [];
$drivers = [];
$successMessage = $_SESSION['success_message'] ?? '';
$errorMessage = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

try {
    $pdo = getDBConnection();
    $ngo_id = $_SESSION['user_id'];
    
    // Get all requests by this NGO
    $stmt = $pdo->prepare("
        SELECT pr.*, fd.title, fd.category, fd.quantity, fd.expiration_date,
               u.full_name as donor_name, u.phone as donor_phone, u.email as donor_email
        FROM pickup_requests pr 
        JOIN food_donations fd ON pr.donation_id = fd.id 
        JOIN users u ON fd.donor_id = u.id 
        WHERE pr.ngo_id = ? 
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$ngo_id]);
    $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $volunteerManager = new Volunteer($pdo);

    $volunteers = $volunteerManager->getVolunteers($ngo_id);
    $driverManager = new Driver($pdo);
    $drivers = $driverManager->getAvailableDrivers();
    if (!empty($requests)) {
        $requestIds = array_column($requests, 'id');
        $assignments = $volunteerManager->getAssignmentsMap($requestIds);
        $driverAssignments = $driverManager->getAssignmentsMap($requestIds);
    }
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/partials/sidebar.php'; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Pickup Requests</h1>
                    <a href="browse_donations.php" class="btn btn-success">
                        <i class="fas fa-plus"></i> Make New Request
                    </a>
                </div>

                <?php if (!empty($successMessage)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($successMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errorMessage)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($errorMessage); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
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
                                <i class="fas fa-info-circle"></i> You haven't made any pickup requests yet. 
                                <a href="browse_donations.php">Browse available donations</a> to get started.
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
                                            <?php 
                                                $assignment = $assignments[$request['id']] ?? null; 
                                                if ($assignment && $assignment['status'] === 'cancelled') {
                                                    $assignment = null;
                                                }
                                            ?>
                                            <div class="mb-2">
                                                <strong>Donor:</strong> <?php echo htmlspecialchars($request['donor_name']); ?>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <strong>Category:</strong> 
                                                <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $request['category'])); ?></span>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <strong>Quantity:</strong> <?php echo htmlspecialchars($request['quantity']); ?>
                                            </div>
                                            
                                            <div class="mb-2">
                                                <strong>Expires:</strong> 
                                                <?php 
                                                $exp_date = strtotime($request['expiration_date']);
                                                $today = strtotime('today');
                                                $class = '';
                                                if ($exp_date <= $today) {
                                                    $class = 'text-danger';
                                                } elseif ($exp_date <= strtotime('+2 days')) {
                                                    $class = 'text-warning';
                                                }
                                                ?>
                                                <span class="<?php echo $class; ?>">
                                                    <?php echo date('M j, Y', $exp_date); ?>
                                                </span>
                                            </div>
                                            
                                            <?php if ($request['pickup_date'] || $request['preferred_pickup_time']): ?>
                                                <div class="mb-2">
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
                                                </div>
                                            <?php endif; ?>

                                            <?php if ($request['delivery_address']): ?>
                                                <div class="mb-2">
                                                    <strong>Delivery Location:</strong><br>
                                                    <?php echo htmlspecialchars($request['delivery_address']); ?><br>
                                                    <?php echo htmlspecialchars($request['delivery_city']); ?><?php echo $request['delivery_city'] && $request['delivery_state'] ? ', ' : ''; ?><?php echo htmlspecialchars($request['delivery_state']); ?> <?php echo htmlspecialchars($request['delivery_zip']); ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($request['request_message']): ?>
                                                <div class="alert alert-light">
                                                    <small><strong>Your Message:</strong> <?php echo htmlspecialchars($request['request_message']); ?></small>
                                                </div>
                                            <?php endif; ?>

                                            <hr>
                                            <div class="mb-2">
                                                <strong>Delivery Assignment:</strong><br>
                                                <?php if ($assignment): ?>
                                                    <span class="badge bg-success mb-2">Volunteer Assigned</span><br>
                                                    <strong>Volunteer:</strong> <?php echo htmlspecialchars($assignment['full_name']); ?><br>
                                                    <strong>Contact:</strong> <?php echo htmlspecialchars($assignment['phone'] ?: 'N/A'); ?><br>
                                                    <?php if (!empty($assignment['vehicle_type'])): ?>
                                                        <strong>Vehicle:</strong> <?php echo htmlspecialchars(ucfirst($assignment['vehicle_type'])); ?> <?php echo htmlspecialchars($assignment['vehicle_number'] ?: ''); ?><br>
                                                    <?php endif; ?>
                                                    <?php if (!empty($assignment['pickup_time'])): ?>
                                                        <strong>Pickup Time:</strong> <?php echo date('M j, Y g:i A', strtotime($assignment['pickup_time'])); ?><br>
                                                    <?php endif; ?>
                                                    <strong>Status:</strong> <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $assignment['status']))); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">No volunteer assigned yet.</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <?php $driverAssignment = $driverAssignments[$request['id']] ?? null; ?>
                                            <div class="mt-3 p-2 border rounded">
                                                <strong>Driver:</strong>
                                                <?php if ($driverAssignment && !empty($driverAssignment['full_name'])): ?>
                                                    <span class="badge bg-primary">Assigned</span><br>
                                                    <?php echo htmlspecialchars($driverAssignment['full_name']); ?>
                                                    <?php if (!empty($driverAssignment['vehicle_type'])): ?>
                                                        — <?php echo htmlspecialchars(ucfirst($driverAssignment['vehicle_type'])); ?> <?php echo htmlspecialchars($driverAssignment['vehicle_number'] ?? ''); ?>
                                                    <?php endif; ?>
                                                    <br><small><?php echo htmlspecialchars($driverAssignment['phone'] ?? ''); ?> · <?php echo htmlspecialchars(ucwords(str_replace('_',' ',$driverAssignment['status']))); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">No driver assigned.</span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="mt-3">
                                                <small class="text-muted">
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($request['donor_phone'] ?: 'No phone'); ?><br>
                                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($request['donor_email']); ?>
                                                </small>
                                            </div>
                                        </div>
                                        
                                        <div class="card-footer">
                                            <small class="text-muted">
                                                Requested on <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                                            </small>

                                            <?php if ($request['status'] === 'completed'): ?>
                                                <div class="mt-2">
                                                    <a href="pickup_receipt.php?id=<?php echo $request['id']; ?>" class="btn btn-sm btn-outline-success" target="_blank">
                                                        <i class="fas fa-file-invoice"></i> View Receipt
                                                    </a>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (in_array($request['status'], ['pending', 'approved']) && !empty($volunteers)): ?>
                                                <button type="button"
                                                        class="btn btn-sm btn-primary mt-2 assign-delivery-btn"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#assignDeliveryModal"
                                                        data-request-id="<?php echo $request['id']; ?>"
                                                        data-donation-title="<?php echo htmlspecialchars($request['title']); ?>"
                                                        data-volunteer-id="<?php echo $assignment['volunteer_id'] ?? ''; ?>"
                                                        data-pickup-time="<?php echo !empty($assignment['pickup_time']) ? date('Y-m-d\TH:i', strtotime($assignment['pickup_time'])) : ''; ?>">
                                                    <i class="fas fa-user-plus me-1"></i>Assign / Update Delivery
                                                </button>
                                            <?php elseif (in_array($request['status'], ['pending', 'approved'])): ?>
                                                <div class="alert alert-warning mt-2 mb-0 p-2">
                                                    <small>Add volunteers before assigning deliveries.</small>
                                                </div>
                                            <?php endif; ?>

                                            <?php if (in_array($request['status'], ['approved']) && !empty($drivers)): ?>
                                                <button type="button" class="btn btn-sm btn-outline-primary mt-2 assign-driver-btn" data-bs-toggle="modal" data-bs-target="#assignDriverModal" data-request-id="<?php echo $request['id']; ?>" data-donation-title="<?php echo htmlspecialchars($request['title']); ?>" data-pickup-time="<?php echo !empty($driverAssignment['pickup_time']) ? date('Y-m-d\TH:i', strtotime($driverAssignment['pickup_time'])) : ''; ?>">
                                                    <i class="fas fa-truck me-1"></i>Assign / Update Driver
                                                </button>
                                            <?php elseif ($request['status'] === 'approved'): ?>
                                                <div class="small text-muted mt-2">No available driver accounts. Admin can create/activate drivers.</div>
                                            <?php endif; ?>

                                            <?php if ($request['status'] === 'approved'): ?>
                                                <div class="mt-2">
                                                    <a href="mark_completed.php?id=<?php echo $request['id']; ?>" 
                                                       class="btn btn-sm btn-success"
                                                       onclick="return confirm('Mark this pickup as completed?')">
                                                        <i class="fas fa-check"></i> Mark Completed
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

    <div class="modal fade" id="assignDeliveryModal" tabindex="-1" aria-labelledby="assignDeliveryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="assign_delivery.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="assignDeliveryModalLabel">Assign Volunteer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="pickup_request_id" id="assignRequestId">
                        <div class="mb-3">
                            <label class="form-label">Donation</label>
                            <input type="text" class="form-control" id="assignDonationTitle" readonly>
                        </div>
                        <div class="mb-3">
                            <label for="volunteerSelect" class="form-label">Select Volunteer</label>
                            <select class="form-select" id="volunteerSelect" name="volunteer_id" required>
                                <option value="">Choose volunteer</option>
                                <?php foreach ($volunteers as $volunteer): ?>
                                    <option value="<?php echo $volunteer['id']; ?>">
                                        <?php echo htmlspecialchars($volunteer['full_name']); ?> (<?php echo htmlspecialchars($volunteer['vehicle_type']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="pickupTime" class="form-label">Pickup Time</label>
                            <input type="datetime-local" class="form-control" id="pickupTime" name="pickup_time">
                            <div class="form-text">Optional but helps volunteers plan their route.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


    <div class="modal fade" id="assignDriverModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="assign_driver.php">
                    <div class="modal-header"><h5 class="modal-title">Assign Driver</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                    <div class="modal-body">
                        <input type="hidden" name="pickup_request_id" id="driverRequestId">
                        <div class="mb-3"><label class="form-label">Donation</label><input class="form-control" id="driverDonationTitle" readonly></div>
                        <div class="mb-3"><label class="form-label">Driver</label><select class="form-select" name="driver_user_id" required><option value="">Choose driver</option>
                            <?php foreach ($drivers as $d): ?><option value="<?php echo (int)$d['user_id']; ?>"><?php echo htmlspecialchars($d['full_name']); ?> — <?php echo htmlspecialchars(ucfirst($d['vehicle_type'])); ?> <?php echo htmlspecialchars($d['vehicle_number']); ?></option><?php endforeach; ?>
                        </select></div>
                        <div><label class="form-label">Pickup Time (optional)</label><input type="datetime-local" class="form-control" name="pickup_time" id="driverPickupTime"></div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit">Save Driver Assignment</button></div>
                </form>
            </div>
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
                        const statusBadge = card.querySelector('.badge');
                        if (statusBadge) {
                            const status = statusBadge.textContent.toLowerCase();
                            const cardContainer = card.closest('.col-md-6');
                            
                            if (target === 'all' || status === target) {
                                cardContainer.style.display = 'block';
                            } else {
                                cardContainer.style.display = 'none';
                            }
                        }
                    });
                });
            });
        });

        const volunteerSelect = document.getElementById('volunteerSelect');
        const modalLabel = document.getElementById('assignDeliveryModalLabel');

        document.querySelectorAll('.assign-delivery-btn').forEach(button => {
            button.addEventListener('click', () => {
                document.getElementById('assignRequestId').value = button.dataset.requestId;
                document.getElementById('assignDonationTitle').value = button.dataset.donationTitle;

                const volunteerId = button.dataset.volunteerId || '';
                document.getElementById('pickupTime').value = button.dataset.pickupTime || '';

                Array.from(volunteerSelect.options).forEach(option => {
                    option.selected = option.value === volunteerId;
                });
                modalLabel.textContent = 'Assign Volunteer';
            });
        });
    </script>
</body>
</html>


<script>
document.querySelectorAll('.assign-driver-btn').forEach(function(btn){btn.addEventListener('click',function(){document.getElementById('driverRequestId').value=btn.dataset.requestId;document.getElementById('driverDonationTitle').value=btn.dataset.donationTitle;document.getElementById('driverPickupTime').value=btn.dataset.pickupTime||'';});});
</script>
