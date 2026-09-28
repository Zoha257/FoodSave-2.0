<?php
require_once(__DIR__ . '/../includes/config.php');

if (!isLoggedIn() || getUserType() !== 'ngo') {
    redirectTo('login.php');
}

$error = '';
$success = '';
$ngoProfile = null;

// Get donation ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    redirectTo('browse_donations.php');
}

$donation_id = $_GET['id'];

try {
    $pdo = getDBConnection();
    
    // Get donation details
    $stmt = $pdo->prepare("
        SELECT fd.*, u.full_name as donor_name, u.phone as donor_phone, u.email as donor_email
        FROM food_donations fd 
        JOIN users u ON fd.donor_id = u.id 
        WHERE fd.id = ? AND fd.status = 'available'
    ");
    $stmt->execute([$donation_id]);
    $donation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donation) {
        redirectTo('browse_donations.php');
    }

    // Prefill NGO delivery information
    $stmt = $pdo->prepare("SELECT full_name, organization_name, phone, address, city, state, zip_code FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $ngoProfile = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if already requested
    $stmt = $pdo->prepare("SELECT id FROM pickup_requests WHERE donation_id = ? AND ngo_id = ?");
    $stmt->execute([$donation_id, $_SESSION['user_id']]);
    if ($stmt->fetch()) {
        $error = 'You have already requested this donation.';
    }
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($error)) {
    $request_message = sanitizeInput($_POST['request_message']);
    $pickup_date = sanitizeInput($_POST['pickup_date']);
    $pickup_time = sanitizeInput($_POST['pickup_time']);
    $delivery_address = sanitizeInput($_POST['delivery_address']);
    $delivery_city = sanitizeInput($_POST['delivery_city']);
    $delivery_state = sanitizeInput($_POST['delivery_state']);
    $delivery_zip = sanitizeInput($_POST['delivery_zip']);
    
    // Validation
    if (empty($pickup_date)) {
        $error = 'Please select a pickup date.';
    } elseif (strtotime($pickup_date) < strtotime('today')) {
        $error = 'Pickup date cannot be in the past.';
    } elseif (strtotime($pickup_date) > strtotime($donation['expiration_date'])) {
        $error = 'Pickup date cannot be after the expiration date.';
    } elseif (empty($delivery_address) || empty($delivery_city) || empty($delivery_state) || empty($delivery_zip)) {
        $error = 'Please provide the delivery location where the food will be taken.';
    } else {
        try {
            $preferredDateTime = null;
            if (!empty($pickup_time)) {
                $preferredDateTime = date('Y-m-d H:i:s', strtotime($pickup_date . ' ' . $pickup_time));
            }

            $stmt = $pdo->prepare("
                INSERT INTO pickup_requests (
                    donation_id, ngo_id, request_message, pickup_date, pickup_time,
                    preferred_pickup_time, delivery_address, delivery_city,
                    delivery_state, delivery_zip
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $donation_id,
                $_SESSION['user_id'],
                $request_message,
                $pickup_date,
                $pickup_time ?: null,
                $preferredDateTime,
                $delivery_address,
                $delivery_city,
                $delivery_state,
                $delivery_zip
            ]);

            // Flag donation as pending so other NGOs see updated state
            $updateDonation = $pdo->prepare("UPDATE food_donations SET status = 'requested' WHERE id = ?");
            $updateDonation->execute([$donation_id]);
            
            $success = 'Pickup request sent successfully! The donor will be notified.';
        } catch (PDOException $e) {
            $error = 'Error sending request. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Pickup - FoodSave</title>
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
                        <h5 class="text-white">FoodSave NGO</h5>
                        <small class="text-muted">Welcome, <?php echo $_SESSION['full_name']; ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="browse_donations.php">
                                <i class="fas fa-search"></i> Browse Donations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="my_requests.php">
                                <i class="fas fa-truck"></i> My Requests
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
                    <h1 class="h2">Request Pickup</h1>
                    <a href="browse_donations.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Browse
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo $success; ?>
                        <div class="mt-2">
                            <a href="browse_donations.php" class="btn btn-primary">Browse More Donations</a>
                            <a href="my_requests.php" class="btn btn-outline-primary">View My Requests</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Donation Details -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-utensils"></i> Donation Details</h5>
                            </div>
                            <div class="card-body">
                                <h6><?php echo htmlspecialchars($donation['title']); ?></h6>
                                
                                <?php if ($donation['description']): ?>
                                    <p class="text-muted"><?php echo htmlspecialchars($donation['description']); ?></p>
                                <?php endif; ?>
                                
                                <div class="row mb-2">
                                    <div class="col-sm-4"><strong>Category:</strong></div>
                                    <div class="col-sm-8">
                                        <span class="badge bg-primary"><?php echo ucfirst(str_replace('_', ' ', $donation['category'])); ?></span>
                                    </div>
                                </div>
                                
                                <div class="row mb-2">
                                    <div class="col-sm-4"><strong>Quantity:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($donation['quantity']); ?></div>
                                </div>
                                
                                <div class="row mb-2">
                                    <div class="col-sm-4"><strong>Expires:</strong></div>
                                    <div class="col-sm-8">
                                        <?php 
                                        $exp_date = strtotime($donation['expiration_date']);
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
                                </div>
                                
                                <div class="row mb-2">
                                    <div class="col-sm-4"><strong>Posted:</strong></div>
                                    <div class="col-sm-8"><?php echo date('M j, Y g:i A', strtotime($donation['created_at'])); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Donor Information -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-user"></i> Donor Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-2">
                                    <div class="col-sm-4"><strong>Name:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($donation['donor_name']); ?></div>
                                </div>
                                
                                <div class="row mb-2">
                                    <div class="col-sm-4"><strong>Email:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($donation['donor_email']); ?></div>
                                </div>
                                
                                <?php if ($donation['donor_phone']): ?>
                                <div class="row mb-2">
                                    <div class="col-sm-4"><strong>Phone:</strong></div>
                                    <div class="col-sm-8"><?php echo htmlspecialchars($donation['donor_phone']); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Pickup Location -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-map-marker-alt"></i> Pickup Location</h5>
                            </div>
                            <div class="card-body">
                                <address>
                                    <?php echo htmlspecialchars($donation['pickup_address']); ?><br>
                                    <?php echo htmlspecialchars($donation['pickup_city']); ?><?php echo $donation['pickup_city'] && $donation['pickup_state'] ? ', ' : ''; ?><?php echo htmlspecialchars($donation['pickup_state']); ?> <?php echo htmlspecialchars($donation['pickup_zip']); ?>
                                </address>
                            </div>
                        </div>
                    </div>

                    <!-- Request Form -->
                    <div class="col-md-6">
                        <?php if (!$success): ?>
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-paper-plane"></i> Send Pickup Request</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="pickup_date" class="form-label">Preferred Pickup Date *</label>
                                        <input type="date" class="form-control" id="pickup_date" name="pickup_date" 
                                               min="<?php echo date('Y-m-d'); ?>" 
                                               max="<?php echo $donation['expiration_date']; ?>" required>
                                        <div class="form-text">Must be before expiration date (<?php echo date('M j, Y', strtotime($donation['expiration_date'])); ?>)</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="pickup_time" class="form-label">Preferred Pickup Time</label>
                                        <input type="time" class="form-control" id="pickup_time" name="pickup_time">
                                        <div class="form-text">Optional - specify if you have a preferred time</div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Delivery Location *</label>
                                        <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" required><?php echo htmlspecialchars($ngoProfile['address'] ?? ''); ?></textarea>
                                        <div class="form-text">Where the donated food will be delivered after pickup.</div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-4 mb-3">
                                            <label for="delivery_city" class="form-label">City *</label>
                                            <input type="text" class="form-control" id="delivery_city" name="delivery_city" value="<?php echo htmlspecialchars($ngoProfile['city'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="delivery_state" class="form-label">State *</label>
                                            <input type="text" class="form-control" id="delivery_state" name="delivery_state" value="<?php echo htmlspecialchars($ngoProfile['state'] ?? ''); ?>" required>
                                        </div>
                                        <div class="col-md-4 mb-3">
                                            <label for="delivery_zip" class="form-label">ZIP Code *</label>
                                            <input type="text" class="form-control" id="delivery_zip" name="delivery_zip" value="<?php echo htmlspecialchars($ngoProfile['zip_code'] ?? ''); ?>" required>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="request_message" class="form-label">Message to Donor</label>
                                        <textarea class="form-control" id="request_message" name="request_message" rows="4" 
                                                  placeholder="Introduce your organization and explain how this donation will help..."></textarea>
                                        <div class="form-text">Optional - but recommended to increase approval chances</div>
                                    </div>
                                    
                                    <div class="alert alert-info">
                                        <small>
                                            <i class="fas fa-info-circle"></i> 
                                            Your request will be sent to the donor for approval. You will be notified of their decision.
                                        </small>
                                    </div>
                                    
                                    <div class="d-grid gap-2">
                                        <button type="submit" class="btn btn-success">
                                            <i class="fas fa-paper-plane"></i> Send Request
                                        </button>
                                        <a href="browse_donations.php" class="btn btn-outline-secondary">Cancel</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
    <script>
        // Set default pickup date to tomorrow
        document.addEventListener('DOMContentLoaded', function() {
            const pickupDate = document.getElementById('pickup_date');
            const deliveryAddress = document.getElementById('delivery_address');

            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            pickupDate.value = tomorrow.toISOString().split('T')[0];

            if (!deliveryAddress.value.trim() && <?php echo json_encode(!empty($ngoProfile['full_name'])); ?>) {
                deliveryAddress.value = <?php echo json_encode(($ngoProfile['address'] ?? '')); ?>;
            }
        });
    </script>
</body>
</html>

