<?php
require_once '../includes/config.php';

if (!isLoggedIn() || getUserType() !== 'donor') {
    redirectTo('login.php');
}

$donation_id = $_GET['id'] ?? null;
if (!$donation_id || !is_numeric($donation_id)) {
    redirectTo('my_donations.php');
}

$message = '';
$error = '';

try {
    $pdo = getDBConnection();
    
    // Get donation details (only if it belongs to this donor)
    $stmt = $pdo->prepare("SELECT * FROM food_donations WHERE id = ? AND donor_id = ?");
    $stmt->execute([$donation_id, $_SESSION['user_id']]);
    $donation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$donation) {
        redirectTo('my_donations.php');
    }
    
    // Check if donation can be edited (only available donations)
    if ($donation['status'] !== 'available') {
        $error = 'This donation cannot be edited because it has already been requested or completed.';
    }
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $category = sanitizeInput($_POST['category']);
    $quantity = sanitizeInput($_POST['quantity']);
    $expiration_date = sanitizeInput($_POST['expiration_date']);
    
    // Validation
    if (empty($title) || empty($category) || empty($quantity) || empty($expiration_date)) {
        $error = 'Please fill in all required fields.';
    } elseif (strtotime($expiration_date) <= time()) {
        $error = 'Expiration date must be in the future.';
    } else {
        try {
            $stmt = $pdo->prepare("
                UPDATE food_donations 
                SET title = ?, description = ?, category = ?, quantity = ?, expiration_date = ?, updated_at = NOW()
                WHERE id = ? AND donor_id = ?
            ");
            $stmt->execute([$title, $description, $category, $quantity, $expiration_date, $donation_id, $_SESSION['user_id']]);
            
            $message = 'Donation updated successfully!';
            
            // Refresh donation data
            $stmt = $pdo->prepare("SELECT * FROM food_donations WHERE id = ? AND donor_id = ?");
            $stmt->execute([$donation_id, $_SESSION['user_id']]);
            $donation = $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            $error = 'Error updating donation: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Donation - FoodSave</title>
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
                            <a class="nav-link active" href="my_donations.php">
                                <i class="fas fa-utensils"></i> My Donations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pickup_requests.php">
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
                    <h1 class="h2">Edit Donation</h1>
                    <a href="my_donations.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to My Donations
                    </a>
                </div>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!$error || $donation['status'] === 'available'): ?>
                <div class="row justify-content-center">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-edit"></i> Edit Donation Details
                                </h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="title" class="form-label">Donation Title *</label>
                                                <input type="text" class="form-control" id="title" name="title" 
                                                       value="<?php echo htmlspecialchars($donation['title']); ?>" required>
                                                <div class="form-text">Give your donation a descriptive title</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="category" class="form-label">Category *</label>
                                                <select class="form-select" id="category" name="category" required>
                                                    <option value="">Select Category</option>
                                                    <option value="vegetables" <?php echo $donation['category'] === 'vegetables' ? 'selected' : ''; ?>>Vegetables</option>
                                                    <option value="fruits" <?php echo $donation['category'] === 'fruits' ? 'selected' : ''; ?>>Fruits</option>
                                                    <option value="dairy" <?php echo $donation['category'] === 'dairy' ? 'selected' : ''; ?>>Dairy Products</option>
                                                    <option value="meat" <?php echo $donation['category'] === 'meat' ? 'selected' : ''; ?>>Meat & Poultry</option>
                                                    <option value="grains" <?php echo $donation['category'] === 'grains' ? 'selected' : ''; ?>>Grains & Cereals</option>
                                                    <option value="prepared_food" <?php echo $donation['category'] === 'prepared_food' ? 'selected' : ''; ?>>Prepared Food</option>
                                                    <option value="other" <?php echo $donation['category'] === 'other' ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="description" class="form-label">Description</label>
                                        <textarea class="form-control" id="description" name="description" rows="3" 
                                                  placeholder="Provide additional details about the food donation..."><?php echo htmlspecialchars($donation['description']); ?></textarea>
                                        <div class="form-text">Optional: Add more details about the food condition, preparation, etc.</div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="quantity" class="form-label">Quantity *</label>
                                                <input type="text" class="form-control" id="quantity" name="quantity" 
                                                       value="<?php echo htmlspecialchars($donation['quantity']); ?>" 
                                                       placeholder="e.g., 5 kg, 10 servings, 2 boxes" required>
                                                <div class="form-text">Specify quantity with units (kg, servings, boxes, etc.)</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="expiration_date" class="form-label">Expiration Date *</label>
                                                <input type="date" class="form-control" id="expiration_date" name="expiration_date" 
                                                       value="<?php echo $donation['expiration_date']; ?>" 
                                                       min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                                                <div class="form-text">When does this food expire or need to be consumed by?</div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle"></i>
                                        <strong>Note:</strong> You can only edit donations that are still available. 
                                        Once a pickup request has been approved, the donation cannot be modified.
                                    </div>

                                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                        <a href="my_donations.php" class="btn btn-secondary">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save"></i> Update Donation
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Current Status -->
                <div class="row justify-content-center mt-4">
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-info-circle"></i> Current Status
                                </h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong>Status:</strong>
                                        <span class="badge status-<?php echo $donation['status']; ?> ms-2">
                                            <?php echo ucfirst(str_replace('_', ' ', $donation['status'])); ?>
                                        </span>
                                    </div>
                                    <div class="col-md-6">
                                        <strong>Created:</strong>
                                        <?php echo date('M j, Y g:i A', strtotime($donation['created_at'])); ?>
                                    </div>
                                </div>
                                <?php if ($donation['updated_at'] && $donation['updated_at'] !== $donation['created_at']): ?>
                                <div class="row mt-2">
                                    <div class="col-md-6">
                                        <strong>Last Updated:</strong>
                                        <?php echo date('M j, Y g:i A', strtotime($donation['updated_at'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
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
        // Set minimum date to tomorrow
        document.addEventListener('DOMContentLoaded', function() {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            const minDate = tomorrow.toISOString().split('T')[0];
            document.getElementById('expiration_date').setAttribute('min', minDate);
        });
    </script>
</body>
</html>

