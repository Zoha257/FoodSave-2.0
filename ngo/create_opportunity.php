<?php
require_once '../includes/config.php';
require_once '../includes/classes/VolunteerOpportunity.php';

$pdo = getDBConnection();

// Check if user is logged in and is NGO
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'ngo') {
    header('Location: ../login.php');
    exit();
}

// Initialize the VolunteerOpportunity class
$volunteerOpp = new VolunteerOpportunity($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $required_fields = [
        'title', 'description', 'type', 'required_volunteers',
        'location_address', 'location_city', 'location_state', 'location_zip',
        'start_date', 'end_date'
    ];

    $errors = [];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }

    if (empty($errors)) {
        try {
            $result = $volunteerOpp->create($_SESSION['user_id'], $_POST);
            if ($result) {
                $_SESSION['success'] = 'Volunteer opportunity created successfully!';
                header('Location: dashboard.php');
                exit();
            } else {
                $errors[] = 'Error creating volunteer opportunity';
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Volunteer Opportunity - FoodSave</title>
    <link rel="stylesheet" href="../assets/css/style.css?v=3">
</head>
<body>
    <div class="container">
        <h1>Create Volunteer Opportunity</h1>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" class="form">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" value="<?php echo $_POST['title'] ?? ''; ?>" required>
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4" required><?php echo $_POST['description'] ?? ''; ?></textarea>
            </div>

            <div class="form-group">
                <label for="type">Type</label>
                <select id="type" name="type" required>
                    <option value="">Select Type</option>
                    <option value="food sorting">Food Sorting</option>
                    <option value="delivery">Delivery</option>
                    <option value="event support">Event Support</option>
                    <option value="kitchen help">Kitchen Help</option>
                    <option value="other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label for="required_volunteers">Number of Volunteers Needed</label>
                <input type="number" id="required_volunteers" name="required_volunteers" 
                       value="<?php echo $_POST['required_volunteers'] ?? ''; ?>" min="1" required>
            </div>

            <div class="form-group">
                <label for="location_address">Address</label>
                <input type="text" id="location_address" name="location_address" 
                       value="<?php echo $_POST['location_address'] ?? ''; ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="location_city">City</label>
                    <input type="text" id="location_city" name="location_city" 
                           value="<?php echo $_POST['location_city'] ?? ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="location_state">State</label>
                    <input type="text" id="location_state" name="location_state" 
                           value="<?php echo $_POST['location_state'] ?? ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="location_zip">ZIP Code</label>
                    <input type="text" id="location_zip" name="location_zip" 
                           value="<?php echo $_POST['location_zip'] ?? ''; ?>" required>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="start_date">Start Date</label>
                    <input type="date" id="start_date" name="start_date" 
                           value="<?php echo $_POST['start_date'] ?? ''; ?>" required>
                </div>

                <div class="form-group">
                    <label for="end_date">End Date</label>
                    <input type="date" id="end_date" name="end_date" 
                           value="<?php echo $_POST['end_date'] ?? ''; ?>" required>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Create Opportunity</button>
                <a href="dashboard.php" class="btn">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        // Add date validation
        document.getElementById('end_date').addEventListener('change', function() {
            var startDate = new Date(document.getElementById('start_date').value);
            var endDate = new Date(this.value);
            
            if (endDate < startDate) {
                alert('End date cannot be earlier than start date');
                this.value = document.getElementById('start_date').value;
            }
        });
    </script>
</body>
</html>