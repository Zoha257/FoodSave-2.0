<?php
require_once '../includes/config.php';
require_once '../includes/classes/Volunteer.php';

if (!isLoggedIn() || getUserType() !== 'volunteer') {
    redirectTo('login.php');
}

$success = '';
$error = '';
$availabilityOptions = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
$hoursOptions = ['morning','afternoon','evening','flexible'];

try {
    $pdo = getDBConnection();
    $volunteerManager = new Volunteer($pdo);

    $userStmt = $pdo->prepare('SELECT phone, address, city, state FROM users WHERE id = ?');
    $userStmt->execute([$_SESSION['user_id']]);
    $userDetails = $userStmt->fetch(PDO::FETCH_ASSOC) ?: ['phone' => '', 'address' => '', 'city' => '', 'state' => ''];

    $profile = $volunteerManager->getProfile($_SESSION['user_id']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $phone = sanitizeInput($_POST['phone'] ?? '');
        $address = sanitizeInput($_POST['address'] ?? '');
        $city = sanitizeInput($_POST['city'] ?? '');
        $state = sanitizeInput($_POST['state'] ?? '');
        $vehicleType = sanitizeInput($_POST['vehicle_type'] ?? '');
        $vehicleNumber = sanitizeInput($_POST['vehicle_number'] ?? '');
        $licenseNumber = sanitizeInput($_POST['license_number'] ?? '');
        $availabilityDays = isset($_POST['availability_days']) ? array_intersect($availabilityOptions, (array)$_POST['availability_days']) : [];
        $availabilityHours = sanitizeInput($_POST['availability_hours'] ?? '');
        $emergencyContactName = sanitizeInput($_POST['emergency_contact_name'] ?? '');
        $emergencyContactPhone = sanitizeInput($_POST['emergency_contact_phone'] ?? '');

        if ($availabilityHours !== '' && !in_array($availabilityHours, $hoursOptions, true)) {
            $error = 'Please select a valid availability slot.';
        } else {
            $vehicleType = $vehicleType !== '' ? $vehicleType : null;
            $vehicleNumber = $vehicleNumber !== '' ? $vehicleNumber : null;
            $licenseNumber = $licenseNumber !== '' ? $licenseNumber : null;
            $availabilityHours = $availabilityHours !== '' ? $availabilityHours : null;
            $emergencyContactName = $emergencyContactName !== '' ? $emergencyContactName : null;
            $emergencyContactPhone = $emergencyContactPhone !== '' ? $emergencyContactPhone : null;

            $pdo->beginTransaction();

            $updateStmt = $pdo->prepare('UPDATE users SET phone = ?, address = ?, city = ?, state = ? WHERE id = ?');
            $updateStmt->execute([$phone, $address, $city, $state, $_SESSION['user_id']]);

            $profileSaved = $volunteerManager->saveProfile($_SESSION['user_id'], [
                'vehicle_type' => $vehicleType,
                'vehicle_number' => $vehicleNumber,
                'license_number' => $licenseNumber,
                'availability_days' => $availabilityDays,
                'availability_hours' => $availabilityHours,
                'emergency_contact_name' => $emergencyContactName,
                'emergency_contact_phone' => $emergencyContactPhone
            ]);

            if ($profileSaved) {
                $pdo->commit();
                $success = 'Profile updated successfully.';
                $userDetails = ['phone' => $phone, 'address' => $address, 'city' => $city, 'state' => $state];
                $profile = $volunteerManager->getProfile($_SESSION['user_id']);
            } else {
                $pdo->rollBack();
                $error = 'Unable to save your volunteer details right now.';
            }
        }
    }
} catch (PDOException $e) {
    $error = 'Server error encountered. Please try again later.';
    error_log('Volunteer profile error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Profile - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php $activePage = 'profile'; include __DIR__ . '/partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">Volunteer Profile</h1>
                        <p class="text-muted mb-0">Keep your contact, vehicle, and availability information up to date.</p>
                    </div>
                </div>

                <?php if ($success): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-triangle-exclamation me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title mb-3 text-dar"><i class="fas fa-address-card me-2"></i>Contact Information</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Phone Number</label>
                                <input class="form-control" type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($userDetails['phone'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="address">Address</label>
                                <input class="form-control" type="text" id="address" name="address" value="<?php echo htmlspecialchars($userDetails['address'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="city">City</label>
                                <input class="form-control" type="text" id="city" name="city" value="<?php echo htmlspecialchars($userDetails['city'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="state">State</label>
                                <input class="form-control" type="text" id="state" name="state" value="<?php echo htmlspecialchars($userDetails['state'] ?? ''); ?>">
                            </div>
                        </div>

                        <h5 class="card-title mb-3"><i class="fas fa-id-card me-2"></i>Vehicle & License</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label class="form-label" for="vehicle_type">Vehicle Type</label>
                                <select class="form-select" id="vehicle_type" name="vehicle_type">
                                    <option value="">Select Vehicle</option>
                                    <?php foreach (['car','van','truck','bicycle','motorcycle'] as $vehicle): ?>
                                        <option value="<?php echo $vehicle; ?>" <?php echo ($profile['vehicle_type'] ?? '') === $vehicle ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($vehicle); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="vehicle_number">Vehicle Number</label>
                                <input class="form-control" type="text" id="vehicle_number" name="vehicle_number" value="<?php echo htmlspecialchars($profile['vehicle_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="license_number">License Number</label>
                                <input class="form-control" type="text" id="license_number" name="license_number" value="<?php echo htmlspecialchars($profile['license_number'] ?? ''); ?>">
                            </div>
                        </div>

                        <h5 class="card-title mb-3"><i class="fas fa-calendar-check me-2"></i>Availability</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label d-block">Days Available</label>
                                <div class="row">
                                    <?php foreach ($availabilityOptions as $day): ?>
                                        <?php $isChecked = in_array($day, $profile['availability_days'] ?? [], true); ?>
                                        <div class="col-sm-6 col-md-4 mb-2">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="day_<?php echo $day; ?>" name="availability_days[]" value="<?php echo $day; ?>" <?php echo $isChecked ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="day_<?php echo $day; ?>"><?php echo ucfirst($day); ?></label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="availability_hours">Preferred Time Slot</label>
                                <select class="form-select" id="availability_hours" name="availability_hours">
                                    <option value="">Select Time Slot</option>
                                    <?php foreach ($hoursOptions as $option): ?>
                                        <option value="<?php echo $option; ?>" <?php echo ($profile['availability_hours'] ?? '') === $option ? 'selected' : ''; ?>>
                                            <?php echo ucfirst($option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <h5 class="card-title mb-3"><i class="fas fa-phone-volume me-2"></i>Emergency Contact</h5>
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label" for="emergency_contact_name">Contact Name</label>
                                <input class="form-control" type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($profile['emergency_contact_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="emergency_contact_phone">Contact Phone</label>
                                <input class="form-control" type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($profile['emergency_contact_phone'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-success"><i class="fas fa-save me-2"></i>Save Profile</button>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
</body>
</html>
