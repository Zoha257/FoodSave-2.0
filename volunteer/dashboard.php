<?php
require_once(__DIR__ . '/../includes/config.php');

if (!isLoggedIn() || getUserType() !== 'volunteer') {
    redirectTo('../login.php');
}

try {
    $pdo = getDBConnection();
    $volunteer_id = $_SESSION['user_id'];

    // Get volunteer assignments
    $stmt = $pdo->prepare("SELECT da.*, pr.pickup_location, pr.dropoff_location, pr.status as request_status
                           FROM delivery_assignments da
                           JOIN pickup_requests pr ON da.pickup_request_id = pr.id
                           WHERE da.volunteer_id = ?");
    $stmt->execute([$volunteer_id]);
    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Dashboard - FoodSave</title>
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
                        <h5 class="text-white">FoodSave Volunteer</h5>
                        <small class="text-muted">Welcome, <?php echo $_SESSION['full_name']; ?></small>
                    </div>

                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
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

            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Dashboard</h1>
                </div>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"> <?php echo $error; ?> </div>
                <?php endif; ?>

                <h4>Your Assignments</h4>
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Pickup Location</th>
                            <th>Dropoff Location</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($assignments as $assignment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($assignment['pickup_location']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['dropoff_location']); ?></td>
                                <td><?php echo htmlspecialchars($assignment['request_status']); ?></td>
                                <td>
                                    <a href="update_delivery_status.php?assignment_id=<?php echo $assignment['id']; ?>" class="btn btn-primary btn-sm">Update Status</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </main>
        </div>
    </div>
    <script src="../assets/js/app.js?v=3"></script>
</body>
</html>