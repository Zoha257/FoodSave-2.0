<?php
require_once '../includes/config.php';
require_once '../includes/classes/Volunteer.php';

if (!isLoggedIn() || getUserType() !== 'ngo') {
    redirectTo('login.php');
}

$activePage = 'volunteers';

$error = '';
$success = '';
$volunteers = [];
$available_users = [];

try {
    $pdo = getDBConnection();
    $volunteerManager = new Volunteer($pdo);
    
    // Handle form submission for new volunteer
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add_volunteer':
                    $userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
                    $vehicleType = trim($_POST['vehicle_type'] ?? '');
                    $vehicleNumber = trim($_POST['vehicle_number'] ?? '');
                    $licenseNumber = trim($_POST['license_number'] ?? '');

                    $allowedVehicleTypes = ['car', 'van', 'truck', 'bicycle', 'motorcycle'];

                    if (!$userId || $vehicleType === '' || $vehicleNumber === '' || $licenseNumber === '') {
                        $error = 'Please fill in all required fields.';
                        break;
                    }

                    if (!in_array($vehicleType, $allowedVehicleTypes, true)) {
                        $error = 'Invalid vehicle type selected.';
                        break;
                    }

                    if ($volunteerManager->register($userId, $_SESSION['user_id'], $vehicleType, $vehicleNumber, $licenseNumber)) {
                        $success = 'Volunteer added successfully!';
                    } else {
                        $error = 'Unable to add volunteer. They might already be linked to your NGO.';
                    }
                    break;
                    
                case 'update_status':
                    $volunteerId = isset($_POST['volunteer_id']) ? (int)$_POST['volunteer_id'] : 0;
                    $status = trim($_POST['status'] ?? '');
                    $allowedStatuses = ['available', 'busy', 'inactive'];
                    if (!$volunteerId || $status === '') {
                        $error = 'Please select a valid status.';
                        break;
                    }
                    if (!in_array($status, $allowedStatuses, true)) {
                        $error = 'Invalid status selected.';
                        break;
                    }
                    if ($volunteerManager->updateStatus($volunteerId, $status)) {
                        $success = 'Volunteer status updated successfully!';
                    } else {
                        $error = 'Error updating volunteer status.';
                    }
                    break;
            }
        }
    }
    
    // Get list of volunteers
    $volunteers = $volunteerManager->getVolunteers($_SESSION['user_id']);
    $available_users = $volunteerManager->getAvailableVolunteerUsers($_SESSION['user_id']);
    
} catch (Exception $e) {
    $error = 'Server error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Volunteers - FoodSave NGO</title>
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
                    <h1 class="h2">Manage Volunteers</h1>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addVolunteerModal">
                        <i class="fas fa-plus"></i> Add Volunteer
                    </button>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>

                <!-- Volunteers List -->
                <div class="table-overlay">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Vehicle Type</th>
                                <th>Vehicle Number</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($volunteers as $volunteer): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($volunteer['full_name']); ?></td>
                                    <td><?php echo ucfirst(htmlspecialchars($volunteer['vehicle_type'])); ?></td>
                                    <td><?php echo htmlspecialchars($volunteer['vehicle_number']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $volunteer['availability_status'] === 'available' ? 'success' : ($volunteer['availability_status'] === 'busy' ? 'warning' : 'secondary'); ?>">
                                            <?php echo ucfirst($volunteer['availability_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a class="btn btn-sm btn-info" href="view_volunteer.php?id=<?php echo $volunteer['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button class="btn btn-sm btn-warning" onclick="updateVolunteerStatus(<?php echo $volunteer['id']; ?>)">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Volunteer Modal -->
    <div class="modal fade" id="addVolunteerModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Volunteer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="addVolunteerForm">
                        <input type="hidden" name="action" value="add_volunteer">
                        
                        <div class="mb-3">
                            <label for="user_id" class="form-label">Volunteer</label>
                            <select class="form-select" name="user_id" id="availableVolunteerSelect" <?php echo empty($available_users) ? 'disabled' : 'required'; ?>>
                                <option value="">Select Volunteer</option>
                                <?php foreach ($available_users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"
                                        data-phone="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                        data-email="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                        data-vehicle="<?php echo htmlspecialchars($user['vehicle_type'] ?? ''); ?>"
                                        data-vehiclenumber="<?php echo htmlspecialchars($user['vehicle_number'] ?? ''); ?>"
                                        data-license="<?php echo htmlspecialchars($user['license_number'] ?? ''); ?>"
                                        data-availability="<?php echo htmlspecialchars($user['availability_hours'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($available_users)): ?>
                                <small class="text-muted">No eligible volunteer accounts available right now.</small>
                            <?php endif; ?>
                            <div class="mt-3" id="volunteerProfileHint" style="display:none;">
                                <div class="alert alert-info mb-0">
                                    <i class="fas fa-user me-2"></i><span id="volunteerProfileText"></span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="vehicle_type" class="form-label">Vehicle Type</label>
                            <select class="form-select" name="vehicle_type" id="modal_vehicle_type" required>
                                <option value="car">Car</option>
                                <option value="van">Van</option>
                                <option value="truck">Truck</option>
                                <option value="bicycle">Bicycle</option>
                                <option value="motorcycle">Motorcycle</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="vehicle_number" class="form-label">Vehicle Number</label>
                            <input type="text" class="form-control" name="vehicle_number" id="modal_vehicle_number" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="license_number" class="form-label">License Number</label>
                            <input type="text" class="form-control" name="license_number" id="modal_license_number" required>
                        </div>
                        
                        <button type="submit" class="btn btn-primary" <?php echo empty($available_users) ? 'disabled' : ''; ?>>Add Volunteer</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Volunteer Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" id="updateStatusForm">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="volunteer_id" id="update_volunteer_id">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" name="status" required>
                                <option value="available">Available</option>
                                <option value="busy">Busy</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
    <script>
    function updateVolunteerStatus(volunteerId) {
        document.getElementById('update_volunteer_id').value = volunteerId;
        new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
    }

    const volunteerSelect = document.getElementById('availableVolunteerSelect');
    if (volunteerSelect) {
        const profileHint = document.getElementById('volunteerProfileHint');
        const profileText = document.getElementById('volunteerProfileText');
        const vehicleTypeInput = document.getElementById('modal_vehicle_type');
        const vehicleNumberInput = document.getElementById('modal_vehicle_number');
        const licenseInput = document.getElementById('modal_license_number');

        volunteerSelect.addEventListener('change', function () {
            const option = this.selectedOptions[0];
            if (!option || !option.value) {
                profileHint.style.display = 'none';
                profileText.textContent = '';
                vehicleTypeInput.value = 'car';
                vehicleNumberInput.value = '';
                licenseInput.value = '';
                return;
            }

            const parts = [];
            const vehicleType = option.getAttribute('data-vehicle') || '';
            const vehicleNumber = option.getAttribute('data-vehiclenumber') || '';
            const license = option.getAttribute('data-license') || '';
            const availability = option.getAttribute('data-availability') || '';
            const availabilityLabel = availability
                ? availability.replace(/_/g, ' ').replace(/\b\w/g, letter => letter.toUpperCase())
                : '';
            const phone = option.getAttribute('data-phone') || '';
            const email = option.getAttribute('data-email') || '';

            if (vehicleType) {
                parts.push(`Vehicle: ${vehicleType}${vehicleNumber ? ' (' + vehicleNumber + ')' : ''}`);
            }
            if (license) {
                parts.push(`License: ${license}`);
            }
            if (availabilityLabel) {
                parts.push(`Preferred hours: ${availabilityLabel}`);
            }
            if (phone) {
                parts.push(`Phone: ${phone}`);
            }
            if (email) {
                parts.push(`Email: ${email}`);
            }

            if (parts.length) {
                profileHint.style.display = 'block';
                profileText.textContent = parts.join(' | ');
            } else {
                profileHint.style.display = 'none';
                profileText.textContent = '';
            }

            if (vehicleType) {
                vehicleTypeInput.value = vehicleType;
            }
            if (vehicleNumber) {
                vehicleNumberInput.value = vehicleNumber;
            }
            if (license) {
                licenseInput.value = license;
            }
        });
    }
    </script>
</body>
</html>