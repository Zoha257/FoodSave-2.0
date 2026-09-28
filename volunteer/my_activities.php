<?php
require_once '../includes/config.php';
require_once '../includes/classes/VolunteerOpportunity.php';

if (!isLoggedIn() || getUserType() !== 'volunteer') {
    redirectTo('login.php');
}

$error = '';
$groupedAssignments = [
    'pending' => [],
    'approved' => [],
    'completed' => [],
    'rejected' => []
];

try {
    $pdo = getDBConnection();
    $volunteerOpp = new VolunteerOpportunity($pdo);
    $assignments = $volunteerOpp->getVolunteerAssignments($_SESSION['user_id']);

    foreach ($assignments as $assignment) {
        $status = $assignment['status'] ?? 'pending';
        if (!isset($groupedAssignments[$status])) {
            $groupedAssignments[$status] = [];
        }
        $groupedAssignments[$status][] = $assignment;
    }
} catch (Exception $e) {
    $error = 'Unable to load your volunteer activities right now.';
    error_log('Volunteer activities error: ' . $e->getMessage());
}

$tabOrder = [
    'pending' => 'Pending',
    'approved' => 'Approved',
    'completed' => 'Completed',
    'rejected' => 'Rejected'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Volunteer Activities - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php $activePage = 'activities'; include __DIR__ . '/partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">My Volunteer Activities</h1>
                        <p class="text-muted mb-0">Keep track of every opportunity you have applied for or completed.</p>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="fas fa-triangle-exclamation me-2"></i><?php echo $error; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <ul class="nav nav-pills mb-3" id="activityTabs" role="tablist">
                    <?php $first = true; ?>
                    <?php foreach ($tabOrder as $key => $label): ?>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link <?php echo $first ? 'active' : ''; ?>" id="<?php echo $key; ?>-tab" data-bs-toggle="tab" data-bs-target="#<?php echo $key; ?>" type="button" role="tab">
                                <?php echo $label; ?>
                                <span class="badge bg-secondary ms-2"><?php echo count($groupedAssignments[$key]); ?></span>
                            </button>
                        </li>
                        <?php $first = false; ?>
                    <?php endforeach; ?>
                </ul>

                <div class="tab-content" id="activityTabsContent">
                    <?php $first = true; ?>
                    <?php foreach ($tabOrder as $key => $label): ?>
                        <?php $assignments = $groupedAssignments[$key]; ?>
                        <div class="tab-pane fade <?php echo $first ? 'show active' : ''; ?>" id="<?php echo $key; ?>" role="tabpanel">
                            <?php if (empty($assignments)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle me-2"></i>No <?php echo strtolower($label); ?> volunteer activities yet.
                                </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($assignments as $assignment): ?>
                                        <div class="col-xl-4 col-lg-6 mb-4">
                                            <div class="card h-100 shadow-sm">
                                                <div class="card-body d-flex flex-column">
                                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($assignment['title']); ?></h5>
                                                    <h6 class="card-subtitle text-muted mb-3">Hosted by <?php echo htmlspecialchars($assignment['ngo_name']); ?></h6>
                                                    <p class="card-text flex-grow-1"><?php echo nl2br(htmlspecialchars($assignment['description'])); ?></p>

                                                    <ul class="list-unstyled small text-muted mb-3">
                                                        <li><i class="fas fa-location-dot me-2"></i><?php echo htmlspecialchars($assignment['location_city'] . ', ' . $assignment['location_state']); ?></li>
                                                        <li><i class="fas fa-calendar me-2"></i><?php echo date('M j, Y', strtotime($assignment['start_date'])); ?> – <?php echo date('M j, Y', strtotime($assignment['end_date'])); ?></li>
                                                        <li><i class="fas fa-clock me-2"></i>Status: <?php echo ucfirst($assignment['status']); ?></li>
                                                    </ul>

                                                    <div class="mt-auto">
                                                        <?php if ($assignment['status'] === 'approved'): ?>
                                                            <div class="alert alert-success py-2 mb-0"><i class="fas fa-check-circle me-2"></i>You have been approved for this opportunity.</div>
                                                        <?php elseif ($assignment['status'] === 'pending'): ?>
                                                            <div class="alert alert-warning py-2 mb-0"><i class="fas fa-hourglass-half me-2"></i>Awaiting NGO review.</div>
                                                        <?php elseif ($assignment['status'] === 'rejected'): ?>
                                                            <div class="alert alert-danger py-2 mb-0"><i class="fas fa-times-circle me-2"></i>Your application was not approved.</div>
                                                        <?php else: ?>
                                                            <div class="alert alert-info py-2 mb-0"><i class="fas fa-flag-checkered me-2"></i>Thank you for completing this activity!</div>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php $first = false; ?>
                    <?php endforeach; ?>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
</body>
</html>