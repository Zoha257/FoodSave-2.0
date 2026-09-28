<?php
require_once __DIR__ . '/../includes/config.php';
if (!isLoggedIn() || getUserType() !== 'admin') {
    redirectTo('login.php');
}

function loadJsonFile($path, $fallback = []) {
    if (!is_file($path)) return $fallback;
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : $fallback;
}

$evaluation = loadJsonFile(__DIR__ . '/../ai/reports/evaluation.json');
$explanations = loadJsonFile(__DIR__ . '/../ai/reports/model_explanations.json');

$pdo = getDBConnection();
$live = [
    'total' => 0, 'expired' => 0, 'high' => 0, 'medium' => 0, 'low' => 0
];
try {
    $live['total'] = (int)$pdo->query("SELECT COUNT(*) FROM food_donations")->fetchColumn();
    $live['expired'] = (int)$pdo->query("SELECT COUNT(*) FROM food_donations WHERE status='expired'")->fetchColumn();
    $live['high'] = (int)$pdo->query("SELECT COUNT(*) FROM food_donations WHERE ai_risk_level='HIGH'")->fetchColumn();
    $live['medium'] = (int)$pdo->query("SELECT COUNT(*) FROM food_donations WHERE ai_risk_level='MEDIUM'")->fetchColumn();
    $live['low'] = (int)$pdo->query("SELECT COUNT(*) FROM food_donations WHERE ai_risk_level='LOW'")->fetchColumn();
} catch (Exception $e) {}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>FoodSave AI Research Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link href="../assets/css/style.css?v=3" rel="stylesheet">
<style>
.metric{border:1px solid #dcefe3;border-radius:14px;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.06)}.metric .value{font-size:1.8rem;font-weight:700;color:var(--fs-ink)}.matrix td{width:90px;height:70px;text-align:center;font-weight:700}.positive{color:#166534}.negative{color:#991b1b}.note{color:#475569;font-size:.88rem}
</style>
</head>
<body>
<div class="container-fluid"><div class="row">
<nav class="col-md-3 col-lg-2 d-md-block sidebar collapse"><div class="position-sticky pt-3">
<div class="text-center mb-4"><h5 class="text-white">FoodSave Admin</h5><small class="text-muted">AI & Research</small></div>
<ul class="nav flex-column">
<li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
<li class="nav-item"><a class="nav-link active" href="ai_dashboard.php"><i class="fas fa-robot"></i> AI Research</a></li>
<li class="nav-item"><a class="nav-link" href="ai_matching.php"><i class="fas fa-route"></i> Smart Matching</a></li>
<li class="nav-item"><a class="nav-link" href="manage_donations.php"><i class="fas fa-utensils"></i> Manage Donations</a></li>
<li class="nav-item"><a class="nav-link" href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
<li class="nav-item"><a class="nav-link" href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
</ul></div></nav>
<main class="col-md-9 ms-sm-auto col-lg-10 main">
<div class="d-flex justify-content-between align-items-center mb-4"><div><h1 class="h3 mb-1">AI Research Dashboard</h1><p class="text-muted mb-0">Expiry-risk prediction, evaluation and transparent decision support.</p></div>
<a href="ai_matching.php" class="btn btn-success"><i class="fas fa-route me-2"></i>Smart Matching</a></div>

<div class="alert alert-warning"><strong>Research status:</strong> prototype metrics are evidence of the current experiment only. If the training report says synthetic data, it must not be presented as real-world impact.</div>

<div class="row g-3 mb-4">
<?php foreach([
['Live donations',$live['total'],'fa-box-open'],['Observed expired',$live['expired'],'fa-triangle-exclamation'],
['Live high risk',$live['high'],'fa-fire'],['Live medium risk',$live['medium'],'fa-circle-exclamation'],['Live low risk',$live['low'],'fa-circle-check']
] as $m): ?>
<div class="col-sm-6 col-xl-2">
<div class="metric p-3 h-100"><div class="text-muted small"><?= htmlspecialchars($m[0]) ?></div><div class="value"><?= (int)$m[1] ?></div><i class="fas <?= htmlspecialchars($m[2]) ?> text-success"></i></div>
</div>
<?php endforeach; ?>
</div>

<div class="row g-4">
<div class="col-lg-7"><div class="card h-100"><div class="card-header bg-white"><h5 class="mb-0">Dataset & evaluation</h5></div><div class="card-body">
<p><strong>Training dataset source:</strong> <?= htmlspecialchars($evaluation['dataset_source'] ?? 'Not available') ?></p>
<p><strong>Dataset size:</strong> <?= (int)($evaluation['dataset_size'] ?? 0) ?></p>
<p><strong>Selected model:</strong> <?= htmlspecialchars($evaluation['selected_model'] ?? 'Not available') ?></p>
<p><strong>Evaluation:</strong> <?= htmlspecialchars($evaluation['evaluation_method'] ?? 'Not documented') ?></p>
<table class="table table-sm align-middle"><thead><tr><th>Model</th><th>Precision</th><th>Recall</th><th>F1</th><th>ROC-AUC</th></tr></thead><tbody>
<?php foreach(($evaluation['models'] ?? []) as $name=>$m): ?>
<tr><td><?= htmlspecialchars($name) ?></td><td><?= isset($m['precision'])?number_format($m['precision'],3):'—' ?></td><td><?= isset($m['recall'])?number_format($m['recall'],3):'—' ?></td><td><?= isset($m['f1'])?number_format($m['f1'],3):'—' ?></td><td><?= isset($m['roc_auc'])&&$m['roc_auc']!==null?number_format($m['roc_auc'],3):'—' ?></td></tr>
<?php endforeach; ?>
</tbody></table>
<p class="note mb-0">High-risk recall is explicitly tracked because missing an at-risk donation is operationally important. Metrics must be re-run when the dataset changes.</p>
</div></div></div>

<div class="col-lg-5"><div class="card h-100"><div class="card-header bg-white"><h5 class="mb-0">Selected model confusion matrix</h5></div><div class="card-body">
<?php $selectedModel=$evaluation['selected_model']??''; $cm=$evaluation['models'][$selectedModel]['confusion_matrix']??[[0,0],[0,0]]; ?>
<table class="table table-bordered matrix"><thead><tr><th></th><th>Predicted 0</th><th>Predicted 1</th></tr></thead>
<tbody><tr><th>Actual 0</th><td class="positive"><?= (int)$cm[0][0] ?></td><td class="negative"><?= (int)$cm[0][1] ?></td></tr>
<tr><th>Actual 1</th><td class="negative"><?= (int)$cm[1][0] ?></td><td class="positive"><?= (int)$cm[1][1] ?></td></tr></tbody></table>
<p class="note">Rows are actual classes; columns are predicted classes. This view is generated from the saved experiment report.</p>
</div></div></div>
</div>

<div class="card mt-4"><div class="card-header bg-white"><h5 class="mb-0">Feature effects / explainability</h5></div><div class="card-body table-responsive">
<p class="note"><?= htmlspecialchars($explanations['interpretation'] ?? 'Feature effects are unavailable.') ?></p>
<table class="table table-sm"><thead><tr><th>Feature</th><th>Coefficient</th><th>Absolute importance</th></tr></thead><tbody>
<?php foreach(array_slice($explanations['features']??[],0,12) as $f): ?>
<tr><td><?= htmlspecialchars($f['feature']) ?></td><td><?= number_format((float)$f['coefficient'],4) ?></td><td><?= number_format((float)$f['absolute_importance'],4) ?></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>

<div class="card mt-4"><div class="card-header bg-white"><h5 class="mb-0">Limitations & responsible use</h5></div><div class="card-body"><ul>
<?php foreach(($evaluation['limitations'] ?? []) as $lim): ?><li><?= htmlspecialchars($lim) ?></li><?php endforeach; ?>
<li>AI predictions are decision support; they do not determine food safety and should not trigger irreversible actions automatically.</li>
<li>Personal information is not required by the initial model and should be minimized in future experiments.</li>
</ul></div></div>
</main></div></div>
    <script src="../assets/js/app.js?v=3"></script>
</body></html>
