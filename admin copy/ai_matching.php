<?php
require_once __DIR__ . '/../includes/config.php';

if (!isLoggedIn() || getUserType() !== 'admin') {
    redirectTo('login.php');
}

$pdo = getDBConnection();
$donations = $pdo->query("
    SELECT fd.id, fd.title, fd.category, fd.pickup_city, fd.expiration_date,
           fd.ai_risk_level, fd.ai_risk_probability, u.full_name AS donor_name
    FROM food_donations fd
    JOIN users u ON u.id = fd.donor_id
    WHERE fd.status IN ('available','pending','requested')
    ORDER BY
      CASE fd.ai_risk_level WHEN 'HIGH' THEN 1 WHEN 'MEDIUM' THEN 2 ELSE 3 END,
      fd.expiration_date ASC
")->fetchAll(PDO::FETCH_ASSOC);

$selected_id = (int)($_GET['donation_id'] ?? ($_POST['donation_id'] ?? 0));
$selected = null;
$matches = [];

if ($selected_id) {
    $stmt = $pdo->prepare("
        SELECT fd.*, u.full_name AS donor_name
        FROM food_donations fd
        JOIN users u ON u.id = fd.donor_id
        WHERE fd.id = ?
    ");
    $stmt->execute([$selected_id]);
    $selected = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($selected) {
        // Transparent weighted scoring. This is a ranking aid, not an autonomous assignment.
        $ngoStmt = $pdo->query("
            SELECT
                u.id, u.full_name, u.organization_name, u.city, u.state,
                COUNT(DISTINCT pr.id) AS total_requests,
                SUM(CASE WHEN pr.status = 'completed' THEN 1 ELSE 0 END) AS completed_requests,
                SUM(CASE WHEN pr.status = 'pending' THEN 1 ELSE 0 END) AS pending_requests,
                AVG(CASE WHEN pr.status = 'completed'
                         THEN TIMESTAMPDIFF(HOUR, pr.created_at, pr.updated_at) END) AS avg_completion_hours
            FROM users u
            LEFT JOIN pickup_requests pr ON pr.ngo_id = u.id
            WHERE u.user_type = 'ngo' AND u.status = 'active'
            GROUP BY u.id
            ORDER BY u.full_name
        ");
        $ngos = $ngoStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($ngos as $ngo) {
            $city_match = strcasecmp(trim((string)$ngo['city']), trim((string)$selected['pickup_city'])) === 0 ? 100 : 20;
            $total = (int)$ngo['total_requests'];
            $completed = (int)$ngo['completed_requests'];
            $completion = $total > 0 ? ($completed / $total) * 100 : 50;
            $pending = (int)$ngo['pending_requests'];
            $feasibility = max(0, 100 - min(100, $pending * 15));
            $risk = strtoupper((string)($selected['ai_risk_level'] ?? ''));
            $urgency = $risk === 'HIGH' ? 100 : ($risk === 'MEDIUM' ? 70 : 40);

            $categoryStmt = $pdo->prepare("
                SELECT COUNT(*) FROM pickup_requests pr
                JOIN food_donations fd ON fd.id = pr.donation_id
                WHERE pr.ngo_id = ? AND fd.category = ? AND pr.status = 'completed'
            ");
            $categoryStmt->execute([(int)$ngo['id'], $selected['category']]);
            $category_count = (int)$categoryStmt->fetchColumn();
            $category_fit = $category_count > 0 ? 100 : 50;

            $score = ($city_match * 0.30) + ($category_fit * 0.20) + ($completion * 0.15) + ($feasibility * 0.15) + ($urgency * 0.20);
            $matches[] = [
                'id' => (int)$ngo['id'],
                'name' => $ngo['organization_name'] ?: $ngo['full_name'],
                'city' => $ngo['city'] ?: '—',
                'score' => round($score, 1),
                'location_fit' => round($city_match, 1),
                'category_fit' => round($category_fit, 1),
                'completion_rate' => round($completion, 1),
                'feasibility' => round($feasibility, 1),
                'pending_requests' => $pending,
                'reason' => $city_match >= 100 ? 'Same pickup city' : 'Different city; verify transport feasibility'
            ];
        }

        usort($matches, fn($a, $b) => $b['score'] <=> $a['score']);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>AI Donation Matching - FoodSave</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="../assets/css/style.css?v=3" rel="stylesheet">
<style>
.score{font-weight:700;color:var(--fs-brand-700)}.small-note{font-size:.84rem;color:#475569}
</style>
</head>
<body>
<div class="container-fluid">
<div class="row">
<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
<div class="position-sticky pt-3">
<div class="text-center mb-4"><h5 class="text-white">FoodSave Admin</h5><small class="text-muted">AI & Research</small></div>
<ul class="nav flex-column">
<li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
<li class="nav-item"><a class="nav-link active" href="ai_dashboard.php"><i class="fas fa-robot"></i> AI Research</a></li>
<li class="nav-item"><a class="nav-link" href="ai_matching.php"><i class="fas fa-route"></i> Smart Matching</a></li>
<li class="nav-item"><a class="nav-link" href="manage_donations.php"><i class="fas fa-utensils"></i> Manage Donations</a></li>
<li class="nav-item"><a class="nav-link" href="manage_users.php"><i class="fas fa-users"></i> Manage Users</a></li>
<li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
<li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul>
</div>
</nav>
<main class="col-md-9 ms-sm-auto col-lg-10 main">
<h1 class="h3 mb-2">Smart Donation–NGO Matching</h1>
<p class="text-muted">Transparent weighted ranking to help authorized staff decide which NGO to contact first.</p>
<div class="alert alert-warning"><strong>Decision support:</strong> rankings do not automatically assign donations and do not replace NGO/admin judgment.</div>

<div class="card mb-4"><div class="card-body">
<form method="get" class="row g-3 align-items-end">
<div class="col-md-9"><label class="form-label fw-semibold">Donation</label>
<select name="donation_id" class="form-select" required>
<option value="">Choose a donation</option>
<?php foreach($donations as $d): ?>
<option value="<?= (int)$d['id'] ?>" <?= $selected_id===(int)$d['id']?'selected':'' ?>>
#<?= (int)$d['id'] ?> — <?= htmlspecialchars($d['title']) ?> — <?= htmlspecialchars($d['pickup_city']) ?> — <?= htmlspecialchars($d['ai_risk_level'] ?: 'AI unavailable') ?>
</option>
<?php endforeach; ?>
</select></div>
<div class="col-md-3"><button class="btn btn-success w-100"><i class="fas fa-ranking-star me-2"></i>Rank NGOs</button></div>
</form>
</div></div>

<?php if($selected): ?>
<div class="card mb-4"><div class="card-body">
<h5><?= htmlspecialchars($selected['title']) ?></h5>
<div class="row">
<div class="col-md-3"><small class="text-muted">Category</small><div><?= htmlspecialchars(ucwords(str_replace('_',' ',$selected['category']))) ?></div></div>
<div class="col-md-3"><small class="text-muted">Pickup city</small><div><?= htmlspecialchars($selected['pickup_city']) ?></div></div>
<div class="col-md-3"><small class="text-muted">Expires</small><div><?= htmlspecialchars($selected['expiration_date']) ?></div></div>
<div class="col-md-3"><small class="text-muted">AI risk</small><div><strong><?= htmlspecialchars($selected['ai_risk_level'] ?: 'Unavailable') ?></strong> <?= $selected['ai_risk_probability']!==null ? '(' . htmlspecialchars($selected['ai_risk_probability']) . '%)' : '' ?></div></div>
</div></div></div>

<div class="card"><div class="card-header bg-white"><h5 class="mb-0">Ranked candidates</h5></div><div class="card-body table-responsive">
<table class="table align-middle">
<thead><tr><th>#</th><th>NGO</th><th>Score</th><th>Location fit</th><th>Category fit</th><th>Completion history</th><th>Pickup feasibility</th><th>Notes</th></tr></thead>
<tbody>
<?php foreach($matches as $i=>$m): ?>
<tr>
<td><?= $i+1 ?></td><td><strong><?= htmlspecialchars($m['name']) ?></strong><br><small class="text-muted"><?= htmlspecialchars($m['city']) ?></small></td>
<td class="score"><?= number_format($m['score'],1) ?>/100</td>
<td><?= number_format($m['location_fit'],0) ?>%</td>
<td><?= number_format($m['category_fit'],0) ?>%</td>
<td><?= number_format($m['completion_rate'],0) ?>%</td>
<td><?= number_format($m['feasibility'],0) ?>%</td>
<td class="small-note"><?= htmlspecialchars($m['reason']) ?><br>Pending requests: <?= (int)$m['pending_requests'] ?></td>
</tr>
<?php endforeach; ?>
<?php if(!$matches): ?><tr><td colspan="8" class="text-muted">No active NGOs available.</td></tr><?php endif; ?>
</tbody></table>
<p class="small-note mb-0">Current scoring uses location fit (30%), category relevance (20%), historical completion rate (15%), pickup feasibility (15%), and expiry urgency (20%). It is intentionally explicit and replaceable as better data becomes available.</p>
</div></div>
<?php endif; ?>
</main></div></div>
    <script src="../assets/js/app.js?v=3"></script>
</body></html>
