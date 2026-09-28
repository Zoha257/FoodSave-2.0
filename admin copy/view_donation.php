<?php
require_once(__DIR__ . '/../includes/config.php');

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirectTo('login.php');
}

$donation_id = $_GET['id'] ?? null;
if (!$donation_id || !is_numeric($donation_id)) {
    redirectTo('manage_donations.php');
}

try {
    $pdo = getDBConnection();
    
    // Get donation details with donor information
    $stmt = $pdo->prepare("
        SELECT fd.*, u.full_name as donor_name, u.phone as donor_phone, 
               u.email as donor_email, u.address, u.city, u.state
        FROM food_donations fd 
        JOIN users u ON fd.donor_id = u.id 
        WHERE fd.id = ?
    ");
    $stmt->execute([$donation_id]);
    $donation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donation) {
        redirectTo('manage_donations.php');
    }
    
    // Get pickup requests for this donation
    $stmt = $pdo->prepare("
        SELECT pr.*, u.full_name as ngo_name, u.phone as ngo_phone, 
               u.email as ngo_email, u.address as ngo_address, 
               u.city as ngo_city, u.state as ngo_state
        FROM pickup_requests pr 
        JOIN users u ON pr.ngo_id = u.id 
        WHERE pr.donation_id = ? 
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$donation_id]);
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
    <title>View Donation - FoodSave Admin</title>
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
                        <h5 class="text-white">FoodSave Admin</h5>
                        <small class="text-muted">Welcome, <?php echo $_SESSION['full_name']; ?></small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="manage_users.php">
                                <i class="fas fa-users"></i> Manage Users
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="manage_donations.php">
                                <i class="fas fa-utensils"></i> Manage Donations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="guidelines.php">
                                <i class="fas fa-clipboard-check"></i> Food Safety Guidelines
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="reports.php">
                                <i class="fas fa-chart-bar"></i> Reports
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
                    <h1 class="h2">Donation Details</h1>
                    <a href="manage_donations.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Donations
                    </a>
                </div>

                <div class="row">
                    <!-- Donation Information -->
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-utensils"></i> Donation Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Title:</strong></div>
                                    <div class="col-sm-9"><?php echo htmlspecialchars($donation['title']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Description:</strong></div>
                                    <div class="col-sm-9"><?php echo htmlspecialchars($donation['description'] ?: 'No description provided'); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Category:</strong></div>
                                    <div class="col-sm-9">
                                        <span class="badge bg-secondary">
                                            <?php echo ucfirst(str_replace('_', ' ', $donation['category'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Quantity:</strong></div>
                                    <div class="col-sm-9"><?php echo htmlspecialchars($donation['quantity']); ?></div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Expiration Date:</strong></div>
                                    <div class="col-sm-9">
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
                                            <?php echo date('F j, Y', $exp_date); ?>
                                            <?php if ($exp_date <= $today): ?>
                                                <small>(Expired)</small>
                                            <?php elseif ($exp_date <= strtotime('+2 days')): ?>
                                                <small>(Expires Soon)</small>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Status:</strong></div>
                                    <div class="col-sm-9">
                                        <span class="badge status-<?php echo $donation['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $donation['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Created:</strong></div>
                                    <div class="col-sm-9"><?php echo date('F j, Y g:i A', strtotime($donation['created_at'])); ?></div>
                                </div>
                                <?php if ($donation['updated_at'] && $donation['updated_at'] !== $donation['created_at']): ?>
                                <div class="row mb-3">
                                    <div class="col-sm-3"><strong>Last Updated:</strong></div>
                                    <div class="col-sm-9"><?php echo date('F j, Y g:i A', strtotime($donation['updated_at'])); ?></div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Donor Information -->
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-user"></i> Donor Information
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Name:</strong><br>
                                    <?php echo htmlspecialchars($donation['donor_name']); ?>
                                </div>
                                <div class="mb-3">
                                    <strong>Email:</strong><br>
                                    <a href="mailto:<?php echo htmlspecialchars($donation['donor_email']); ?>">
                                        <?php echo htmlspecialchars($donation['donor_email']); ?>
                                    </a>
                                </div>
                                <?php if ($donation['donor_phone']): ?>
                                <div class="mb-3">
                                    <strong>Phone:</strong><br>
                                    <a href="tel:<?php echo htmlspecialchars($donation['donor_phone']); ?>">
                                        <?php echo htmlspecialchars($donation['donor_phone']); ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                                <?php if ($donation['address']): ?>
                                <div class="mb-3">
                                    <strong>Address:</strong><br>
                                    <?php echo htmlspecialchars($donation['address']); ?><br>
                                    <?php echo htmlspecialchars($donation['city']); ?>, <?php echo htmlspecialchars($donation['state']); ?>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-grid">
                                    <a href="view_user.php?id=<?php echo $donation['donor_id']; ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-eye"></i> View Donor Profile
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Location Map -->
                        <?php if ($donation['address'] && $donation['city'] && $donation['state']): ?>
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-map-marker-alt"></i> Location
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div id="map" style="height: 200px;"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Pickup Requests -->
                <?php if (!empty($requests)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-truck"></i> Pickup Requests (<?php echo count($requests); ?>)
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>NGO</th>
                                        <th>Contact</th>
                                        <th>Status</th>
                                        <th>Pickup Date</th>
                                        <th>Message</th>
                                        <th>Requested</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($request['ngo_name']); ?></strong>
                                            <?php if ($request['ngo_address']): ?>
                                                <br><small class="text-muted">
                                                    <?php echo htmlspecialchars($request['ngo_city'] . ', ' . $request['ngo_state']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small>
                                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($request['ngo_email']); ?><br>
                                                <?php if ($request['ngo_phone']): ?>
                                                    <i class="fas fa-phone"></i> <?php echo htmlspecialchars($request['ngo_phone']); ?>
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge status-<?php echo $request['status']; ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($request['pickup_date']): ?>
                                                <?php echo date('M j, Y', strtotime($request['pickup_date'])); ?>
                                                <?php if ($request['pickup_time']): ?>
                                                    <br><small><?php echo date('g:i A', strtotime($request['pickup_time'])); ?></small>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not specified</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($request['request_message']): ?>
                                                <small><?php echo htmlspecialchars(substr($request['request_message'], 0, 50)) . (strlen($request['request_message']) > 50 ? '...' : ''); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">No message</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($request['created_at'])); ?></td>
                                        <td>
                                            <a href="view_user.php?id=<?php echo $request['ngo_id']; ?>" 
                                               class="btn btn-outline-info btn-sm" title="View NGO Profile">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info mt-4">
                    <i class="fas fa-info-circle"></i> No pickup requests have been made for this donation yet.
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
    
    <?php if ($donation['address'] && $donation['city'] && $donation['state']): ?>
    <script>
        function initMap() {
            const address = "<?php echo htmlspecialchars($donation['address'] . ', ' . $donation['city'] . ', ' . $donation['state']); ?>";
            const geocoder = new google.maps.Geocoder();
            
            geocoder.geocode({ address: address }, function(results, status) {
                if (status === 'OK') {
                    const map = new google.maps.Map(document.getElementById('map'), {
                        zoom: 15,
                        center: results[0].geometry.location
                    });
                    
                    new google.maps.Marker({
                        position: results[0].geometry.location,
                        map: map,
                        title: "<?php echo htmlspecialchars($donation['title']); ?>"
                    });
                }
            });
        }
    </script>
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo getGoogleMapsApiKey(); ?>&callback=initMap"></script>
    <?php endif; ?>
</body>
</html>

