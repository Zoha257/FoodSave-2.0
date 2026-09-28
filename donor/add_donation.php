<?php
require_once '../includes/config.php';
require_once '../includes/ai_prediction.php';

if (!isLoggedIn() || getUserType() !== 'donor') {
    redirectTo('login.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = sanitizeInput($_POST['title'] ?? '');
    $description = sanitizeInput($_POST['description'] ?? '');
    $category = sanitizeInput($_POST['category'] ?? '');
    $quantity = sanitizeInput($_POST['quantity'] ?? '');
    $expiration_date = sanitizeInput($_POST['expiration_date'] ?? '');
    $pickup_address = sanitizeInput($_POST['pickup_address'] ?? '');
    $pickup_city = sanitizeInput($_POST['pickup_city'] ?? '');
    $pickup_state = sanitizeInput($_POST['pickup_state'] ?? '');
    $pickup_zip = sanitizeInput($_POST['pickup_zip'] ?? '');

    // Validation
    if (
        empty($title) ||
        empty($category) ||
        empty($quantity) ||
        empty($expiration_date) ||
        empty($pickup_address)
    ) {

        $error = 'Please fill in all required fields.';

    } elseif (strtotime($expiration_date) <= time()) {

        $error = 'Expiration date must be in the future.';

    } else {

        try {

            $pdo = getDBConnection();

            /*
             * STEP 1: Save the donation
             */

            $stmt = $pdo->prepare("
                INSERT INTO food_donations
                (
                    donor_id,
                    title,
                    description,
                    category,
                    quantity,
                    expiration_date,
                    pickup_address,
                    pickup_city,
                    pickup_state,
                    pickup_zip
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
                $_SESSION['user_id'],
                $title,
                $description,
                $category,
                $quantity,
                $expiration_date,
                $pickup_address,
                $pickup_city,
                $pickup_state,
                $pickup_zip
            ]);

            $donation_id = $pdo->lastInsertId();

            /*
             * STEP 2: Prepare AI features
             */

            // Extract numeric quantity
            $quantity_value = (float) preg_replace('/[^0-9.]/', '', $quantity);

            if ($quantity_value <= 0) {
                $quantity_value = 1;
            }

            // Calculate days until expiration
            $days_until_expiration = max(
                0,
                (strtotime($expiration_date) - time()) / 86400
            );

            $creation_hour = (int) date('G');
            $creation_day_of_week = (int) date('w');

            $pickup_requested = 0;
            $volunteer_assigned = 0;

            /*
             * STEP 3: Send donation information to the Python AI service.
             * FOODSAVE_PYTHON can be set in .env when Apache cannot find Python
             * on PATH. The core donation transaction is never blocked by AI.
             */
            $ai_input = [
                'category' => $category,
                'quantity_value' => $quantity_value,
                'days_until_expiration' => round($days_until_expiration, 2),
                'creation_hour' => $creation_hour,
                'creation_day_of_week' => $creation_day_of_week,
                'pickup_requested' => $pickup_requested,
                'volunteer_assigned' => $volunteer_assigned
            ];

            $prediction = generateDonationExpiryPrediction($pdo, $donation_id, $ai_input);
            $ai_success = $prediction !== false;

            /*
             * STEP 5: Show result
             */

            if ($ai_success) {

                $success = 'Food donation added successfully! AI expiry-risk prediction generated.';

            } else {

                $success = 'Food donation added successfully! Donation created, but AI prediction could not be generated.';

            }

            // Clear form data
            $_POST = [];

        } catch (PDOException $e) {

            $error = 'Database error. Please try again.';

        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Add Donation - FoodSave</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">

    <style>
        /* Add Donation — page components (base styles come from style.css) */
        .donation-page-header { padding-top: 2rem; padding-bottom: .5rem; margin-bottom: 1.5rem; }
        .donation-page-header h1 { font-size: 1.6rem; font-weight: 800; letter-spacing: -.025em; margin-bottom: .25rem; }
        .donation-page-header p { color: var(--fs-muted); margin: 0; }

        .donation-form-wrapper { max-width: 880px; margin: 0 0 3rem; }
        .donation-form-card {
            background: var(--fs-surface);
            border: 1px solid var(--fs-line);
            border-radius: var(--fs-radius-lg);
            box-shadow: var(--fs-shadow-sm);
            overflow: hidden;
        }
        .donation-form-card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--fs-line-2); }
        .donation-form-card-header h2 { font-size: 1.05rem; font-weight: 700; margin: 0; }
        .donation-form-card-header p { color: var(--fs-muted); margin: .3rem 0 0; font-size: .85rem; }
        .donation-form-body { padding: 1.5rem; }

        .form-section-title {
            display: flex; align-items: center; gap: .6rem;
            font-size: .95rem; font-weight: 700; margin-bottom: 1.1rem;
        }
        .form-section-title i {
            width: 2rem; height: 2rem; display: grid; place-items: center;
            border-radius: 9px; background: var(--fs-brand-100); color: var(--fs-brand-600); font-size: .85rem;
        }
        .form-label .required { color: var(--fs-danger); }
        .form-help { display: block; margin-top: .35rem; color: var(--fs-muted); font-size: .78rem; }
        .section-divider { border-top: 1px dashed var(--fs-line); margin: 1.75rem 0; }

        .ai-info-box {
            display: flex; gap: .85rem; align-items: flex-start;
            background: var(--fs-brand-50); border: 1px solid var(--fs-brand-200);
            border-radius: var(--fs-radius); padding: 1rem; margin-top: 1.5rem;
        }
        .ai-info-box i { color: var(--fs-brand-600); font-size: 1.1rem; margin-top: .15rem; }
        .ai-info-box strong { color: var(--fs-brand-800); font-size: .9rem; }
        .ai-info-box p { color: var(--fs-ink-2); font-size: .82rem; margin: .2rem 0 0; }

        .form-actions {
            display: flex; justify-content: flex-end; gap: .75rem;
            margin: 1.75rem -1.5rem -1.5rem; padding: 1rem 1.5rem;
            background: var(--fs-surface-2); border-top: 1px solid var(--fs-line-2);
        }

        @media (max-width: 767.98px) {
            .donation-form-card-header, .donation-form-body { padding: 1.15rem; }
            .form-actions { flex-direction: column-reverse; margin: 1.5rem -1.15rem -1.15rem; padding: 1rem 1.15rem; }
            .form-actions .btn { width: 100%; }
        }
    </style>
</head>

<body class="donation-page">

<div class="container-fluid">
    <div class="row">

        <!-- Sidebar -->
        <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">

            <div class="position-sticky pt-3">

                <div class="text-center mb-4">
                    <h5 class="text-white">FoodSave Donor</h5>
                    <small class="text-muted">
                        Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                    </small>
                </div>

                <ul class="nav flex-column">

                    <li class="nav-item">
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i>
                            Dashboard
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link active" href="add_donation.php">
                            <i class="fas fa-plus"></i>
                            Add Donation
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="my_donations.php">
                            <i class="fas fa-utensils"></i>
                            My Donations
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="pickup_requests.php">
                            <i class="fas fa-truck"></i>
                            Pickup Requests
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="food_safety.php">
                            <i class="fas fa-clipboard-check"></i>
                            Food Safety Guidelines
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="profile.php">
                            <i class="fas fa-user"></i>
                            Profile
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="logout.php">
                            <i class="fas fa-sign-out-alt"></i>
                            Logout
                        </a>
                    </li>

                </ul>

            </div>

        </nav>

        <!-- Main content -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 donation-main">

            <div class="donation-page-header">

                <div>
                    <h1>Add Food Donation</h1>
                    <p>Share surplus food with people and communities who need it.</p>
                </div>

            </div>

            <div class="donation-form-wrapper">

                <div class="donation-form-card">

                    <div class="donation-form-card-header">

                        <h2>
                            <i class="fas fa-box-open text-success me-2"></i>
                            Donation Details
                        </h2>

                        <p>
                            Provide accurate information so NGOs can safely collect your donation.
                        </p>

                    </div>

                    <div class="donation-form-body">

                        <?php if ($error): ?>

                            <div class="alert alert-danger">
                                <i class="fas fa-circle-exclamation me-2"></i>
                                <?php echo htmlspecialchars($error); ?>
                            </div>

                        <?php endif; ?>

                        <?php if ($success): ?>

                            <div class="alert alert-success">
                                <i class="fas fa-circle-check me-2"></i>
                                <?php echo htmlspecialchars($success); ?>
                            </div>

                        <?php endif; ?>

                        <form method="POST">

                            <!-- Food Information -->
                            <div class="form-section-title">
                                <i class="fas fa-utensils"></i>
                                Food Information
                            </div>

                            <div class="mb-3">

                                <label for="title" class="form-label">
                                    Food Title <span class="required">*</span>
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="title"
                                    name="title"
                                    value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                    placeholder="e.g. Chicken Biryani"
                                    required
                                >

                            </div>

                            <div class="mb-3">

                                <label for="description" class="form-label">
                                    Description
                                </label>

                                <textarea
                                    class="form-control"
                                    id="description"
                                    name="description"
                                    rows="3"
                                    placeholder="Add useful details about the food, packaging, preparation, or serving size..."
                                ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

                            </div>

                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label for="category" class="form-label">
                                        Category <span class="required">*</span>
                                    </label>

                                    <select
                                        class="form-select"
                                        id="category"
                                        name="category"
                                        required
                                    >

                                        <option value="">Select Category</option>

                                        <option value="vegetables"
                                            <?php echo ($_POST['category'] ?? '') === 'vegetables' ? 'selected' : ''; ?>>
                                            Vegetables
                                        </option>

                                        <option value="fruits"
                                            <?php echo ($_POST['category'] ?? '') === 'fruits' ? 'selected' : ''; ?>>
                                            Fruits
                                        </option>

                                        <option value="dairy"
                                            <?php echo ($_POST['category'] ?? '') === 'dairy' ? 'selected' : ''; ?>>
                                            Dairy
                                        </option>

                                        <option value="meat"
                                            <?php echo ($_POST['category'] ?? '') === 'meat' ? 'selected' : ''; ?>>
                                            Meat
                                        </option>

                                        <option value="grains"
                                            <?php echo ($_POST['category'] ?? '') === 'grains' ? 'selected' : ''; ?>>
                                            Grains
                                        </option>

                                        <option value="prepared_food"
                                            <?php echo ($_POST['category'] ?? '') === 'prepared_food' ? 'selected' : ''; ?>>
                                            Prepared Food
                                        </option>

                                        <option value="other"
                                            <?php echo ($_POST['category'] ?? '') === 'other' ? 'selected' : ''; ?>>
                                            Other
                                        </option>

                                    </select>

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label for="quantity" class="form-label">
                                        Quantity <span class="required">*</span>
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="quantity"
                                        name="quantity"
                                        value="<?php echo htmlspecialchars($_POST['quantity'] ?? ''); ?>"
                                        placeholder="e.g. 10 kg, 20 portions"
                                        required
                                    >

                                </div>

                            </div>

                            <div class="mb-3">

                                <label for="expiration_date" class="form-label">
                                    Expiration Date <span class="required">*</span>
                                </label>

                                <input
                                    type="date"
                                    class="form-control"
                                    id="expiration_date"
                                    name="expiration_date"
                                    value="<?php echo htmlspecialchars($_POST['expiration_date'] ?? ''); ?>"
                                    required
                                >

                                <small class="form-help">
                                    <i class="fas fa-circle-info me-1"></i>
                                    Enter the actual date the donated food expires.
                                </small>

                            </div>

                            <div class="section-divider"></div>

                            <!-- Pickup Location -->
                            <div class="form-section-title">
                                <i class="fas fa-location-dot"></i>
                                Pickup Location
                            </div>

                            <div class="mb-3">

                                <label for="pickup_address" class="form-label">
                                    Address <span class="required">*</span>
                                </label>

                                <textarea
                                    class="form-control"
                                    id="pickup_address"
                                    name="pickup_address"
                                    rows="2"
                                    placeholder="Enter the pickup address"
                                    required
                                ><?php echo htmlspecialchars($_POST['pickup_address'] ?? ''); ?></textarea>

                            </div>

                            <div class="row">

                                <div class="col-md-4 mb-3">

                                    <label for="pickup_city" class="form-label">
                                        City
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="pickup_city"
                                        name="pickup_city"
                                        value="<?php echo htmlspecialchars($_POST['pickup_city'] ?? ''); ?>"
                                        placeholder="e.g. Lahore"
                                    >

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label for="pickup_state" class="form-label">
                                        State / Province
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="pickup_state"
                                        name="pickup_state"
                                        value="<?php echo htmlspecialchars($_POST['pickup_state'] ?? ''); ?>"
                                        placeholder="e.g. Punjab"
                                    >

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label for="pickup_zip" class="form-label">
                                        ZIP / Postal Code
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="pickup_zip"
                                        name="pickup_zip"
                                        value="<?php echo htmlspecialchars($_POST['pickup_zip'] ?? ''); ?>"
                                        placeholder="e.g. 54000"
                                    >

                                </div>

                            </div>

                            <!-- AI information -->
                            <div class="ai-info-box">

                                <i class="fas fa-robot"></i>

                                <div>

                                    <strong>
                                        AI Expiry-Risk Prediction
                                    </strong>

                                    <p>
                                        After you submit the donation, FoodSave's AI service
                                        will estimate its expiry risk and assign a
                                        Low, Medium, or High risk level for NGO users.
                                    </p>

                                </div>

                            </div>

                            <!-- Actions -->
                            <div class="form-actions">

                                <a
                                    href="dashboard.php"
                                    class="btn btn-secondary"
                                >
                                    <i class="fas fa-arrow-left me-1"></i>
                                    Cancel
                                </a>

                                <button
                                    type="submit"
                                    class="btn btn-success"
                                >
                                    <i class="fas fa-plus me-1"></i>
                                    Add Donation
                                </button>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </main>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>

<script>
    // Prevent selecting today's date because the backend requires
    // the expiration date to be in the future.
    const expirationInput = document.getElementById('expiration_date');

    if (expirationInput) {

        const tomorrow = new Date();

        tomorrow.setDate(tomorrow.getDate() + 1);

        const year = tomorrow.getFullYear();
        const month = String(tomorrow.getMonth() + 1).padStart(2, '0');
        const day = String(tomorrow.getDate()).padStart(2, '0');

        expirationInput.min = `${year}-${month}-${day}`;
    }
</script>

</body>
</html>