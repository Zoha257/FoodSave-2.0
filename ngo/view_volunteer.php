<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/classes/Volunteer.php';

if (!isLoggedIn() || getUserType() !== 'ngo') {
    redirectTo('login.php');
}

$volunteerId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($volunteerId <= 0) {
    http_response_code(400);
    echo 'Invalid volunteer reference.';
    exit;
}

$volunteer = null;
$profile = null;
$error = '';

try {
    $pdo = getDBConnection();
    $volunteerManager = new Volunteer($pdo);

    $stmt = $pdo->prepare('SELECT v.*, u.full_name, u.email, u.phone FROM volunteers v JOIN users u ON v.user_id = u.id WHERE v.id = ? AND v.ngo_id = ?');
    $stmt->execute([$volunteerId, $_SESSION['user_id']]);
    $volunteer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$volunteer) {
        http_response_code(404);
        echo 'Volunteer not found.';
        exit;
    }

    $profile = $volunteerManager->getProfile($volunteer['user_id']);
} catch (Exception $e) {
    error_log('View volunteer error: ' . $e->getMessage());
    $error = 'Unable to load volunteer details right now.';
}

function formatAvailabilityDays(array $days): string
{
    if (empty($days)) {
        return 'Not specified';
    }
    $labels = array_map(static function ($day) {
        return ucfirst($day);
    }, $days);
    return implode(', ', $labels);
}

function formatValue(?string $value, string $fallback = 'Not specified'): string
{
    return $value !== null && $value !== '' ? htmlspecialchars($value) : $fallback;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Details - FoodSave NGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php $activePage = 'volunteers'; include __DIR__ . '/partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">Volunteer Profile</h1>
                        <p class="text-muted mb-0">Review the volunteer's contact, vehicle, and availability details.</p>
                    </div>
                    <a href="manage_volunteers.php" class="btn btn-outline-light">
                        <i class="fas fa-arrow-left me-2"></i>Back to Volunteers
                    </a>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-triangle-exclamation me-2"></i><?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-success text-white">
                                <i class="fas fa-user me-2"></i>Contact Information
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-5">Name</dt>
                                    <dd class="col-sm-7"><?php echo htmlspecialchars($volunteer['full_name']); ?></dd>

                                    <dt class="col-sm-5">Email</dt>
                                    <dd class="col-sm-7"><?php echo htmlspecialchars($volunteer['email']); ?></dd>

                                    <dt class="col-sm-5">Phone</dt>
                                    <dd class="col-sm-7"><?php echo formatValue($volunteer['phone']); ?></dd>

                                    <dt class="col-sm-5">Availability Status</dt>
                                    <dd class="col-sm-7"><span class="badge bg-<?php echo $volunteer['availability_status'] === 'available' ? 'success' : ($volunteer['availability_status'] === 'busy' ? 'warning text-dark' : 'secondary'); ?>"><?php echo ucfirst($volunteer['availability_status']); ?></span></dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-primary text-white">
                                <i class="fas fa-car me-2"></i>Vehicle Details
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-5">Vehicle Type</dt>
                                    <dd class="col-sm-7"><?php echo formatValue($volunteer['vehicle_type']); ?></dd>

                                    <dt class="col-sm-5">Vehicle Number</dt>
                                    <dd class="col-sm-7"><?php echo formatValue($volunteer['vehicle_number']); ?></dd>

                                    <dt class="col-sm-5">License Number</dt>
                                    <dd class="col-sm-7"><?php echo formatValue($volunteer['license_number']); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-warning text-dark">
                                <i class="fas fa-clock me-2"></i>Availability
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-5">Days Available</dt>
                                    <dd class="col-sm-7"><?php echo htmlspecialchars(formatAvailabilityDays($profile['availability_days'] ?? [])); ?></dd>

                                    <dt class="col-sm-5">Preferred Time</dt>
                                    <dd class="col-sm-7"><?php echo formatValue(isset($profile['availability_hours']) && $profile['availability_hours'] !== null ? ucfirst($profile['availability_hours']) : null); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-danger text-white">
                                <i class="fas fa-phone-volume me-2"></i>Emergency Contact
                            </div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-5">Contact Name</dt>
                                    <dd class="col-sm-7"><?php echo formatValue($profile['emergency_contact_name'] ?? null); ?></dd>

                                    <dt class="col-sm-5">Contact Phone</dt>
                                    <dd class="col-sm-7"><?php echo formatValue($profile['emergency_contact_phone'] ?? null); ?></dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
</body>
</html>
