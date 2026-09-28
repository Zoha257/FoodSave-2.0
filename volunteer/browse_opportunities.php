<?php
require_once '../includes/config.php';
require_once '../includes/classes/VolunteerOpportunity.php';

$error = '';
$pdo = null;
$opportunities = [];
$types = [];
$cities = [];
$filters = [];
$selectedType = '';
$selectedCity = '';
$isVolunteer = isLoggedIn() && getUserType() === 'volunteer';

try {
    $pdo = getDBConnection();
    $volunteerOpp = new VolunteerOpportunity($pdo);

    if (!empty($_GET['type'])) {
        $selectedType = sanitizeInput($_GET['type']);
        $filters['type'] = $selectedType;
    }

    if (!empty($_GET['city'])) {
        $selectedCity = sanitizeInput($_GET['city']);
        $filters['city'] = $selectedCity;
    }

    $opportunities = $volunteerOpp->listOpportunities($filters);

    if (!empty($opportunities)) {
        $types = array_values(array_unique(array_filter(array_column($opportunities, 'type'))));
        $cities = array_values(array_unique(array_filter(array_column($opportunities, 'location_city'))));
        sort($types);
        sort($cities);
    }
} catch (Exception $e) {
    $error = 'Unable to load opportunities right now.';
    error_log('Volunteer browse opportunities error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Opportunities - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php $activePage = 'browse'; include __DIR__ . '/partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">Volunteer Opportunities</h1>
                        <p class="text-muted mb-0">Discover upcoming events and deliveries where your help is needed.</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-triangle-exclamation me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="card mb-4">
                    <div class="card-body">
                        <form class="row g-3 align-items-end" method="GET">
                            <div class="col-md-4">
                                <label for="type" class="form-label">Opportunity Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <?php foreach ($types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $selectedType === $type ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $type))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="city" class="form-label">City</label>
                                <select class="form-select" id="city" name="city">
                                    <option value="">All Cities</option>
                                    <?php foreach ($cities as $city): ?>
                                        <option value="<?php echo htmlspecialchars($city); ?>" <?php echo $selectedCity === $city ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($city); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 d-flex gap-2">
                                <button type="submit" class="btn btn-success flex-grow-1"><i class="fas fa-filter me-2"></i>Apply Filters</button>
                                <a href="browse_opportunities.php" class="btn btn-outline-secondary"><i class="fas fa-rotate-right"></i></a>
                            </div>
                        </form>
                    </div>
                </div>

                <?php if (empty($opportunities)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No volunteer opportunities available at this time.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($opportunities as $opportunity): ?>
                            <div class="col-xl-4 col-lg-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body d-flex flex-column">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title mb-0"><?php echo htmlspecialchars($opportunity['title']); ?></h5>
                                            <span class="badge bg-success"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $opportunity['type']))); ?></span>
                                        </div>
                                        <h6 class="card-subtitle mb-2 text-muted">Hosted by <?php echo htmlspecialchars($opportunity['ngo_name']); ?></h6>
                                        <p class="card-text flex-grow-1"><?php echo nl2br(htmlspecialchars($opportunity['description'])); ?></p>

                                        <ul class="list-unstyled small text-muted mb-3">
                                            <li><i class="fas fa-location-dot me-2"></i><?php echo htmlspecialchars($opportunity['location_city'] . ', ' . $opportunity['location_state']); ?></li>
                                            <li><i class="fas fa-calendar me-2"></i><?php echo date('M j, Y', strtotime($opportunity['start_date'])); ?> – <?php echo date('M j, Y', strtotime($opportunity['end_date'])); ?></li>
                                            <li><i class="fas fa-users me-2"></i><?php echo max(0, (int)$opportunity['required_volunteers'] - (int)$opportunity['current_volunteers']); ?> spots remaining</li>
                                        </ul>

                                        <?php if ($isVolunteer): ?>
                                            <form method="POST" action="apply_volunteer.php" class="mt-auto">
                                                <input type="hidden" name="opportunity_id" value="<?php echo $opportunity['id']; ?>">
                                                <button type="submit" class="btn btn-success w-100">
                                                    <i class="fas fa-handshake-angle me-2"></i>Apply Now
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <a class="btn btn-outline-light w-100" href="login.php">
                                                <i class="fas fa-sign-in-alt me-2"></i>Login to Apply
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
</body>
</html>
        // Add any JavaScript for dynamic filtering or UI enhancements
    </script>
</body>
</html>