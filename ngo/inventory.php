<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/classes/Inventory.php';

if (!isLoggedIn() || !in_array(getUserType(), ['ngo', 'admin'], true)) {
    redirectTo('login.php');
}

$activePage = 'inventory';
$error = '';
$summary = [
    'totals' => [
        'available' => 0,
        'pending' => 0,
        'requested' => 0,
        'picked_up' => 0,
        'completed' => 0,
        'cancelled' => 0,
        'expired' => 0,
        'total_active' => 0,
        'approx_available_units' => 0.0
    ],
    'by_category' => [],
    'by_city' => [],
    'expiring_soon' => [],
    'last_updated' => gmdate('c')
];

try {
    $pdo = getDBConnection();
    $inventoryService = new Inventory($pdo);
    $summary = $inventoryService->getSummary();
} catch (Throwable $e) {
    $error = 'Unable to load the most recent inventory snapshot. Attempting to refresh automatically.';
}

$summaryJson = json_encode($summary, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Tracker - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">Inventory Tracker</h1>
                        <p class="text-muted mb-0">Live snapshot of available donations and requests</p>
                    </div>
                    <span class="badge bg-secondary" id="inventory-last-updated">Just now</span>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-warning" id="inventory-error" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning d-none" id="inventory-error" role="alert"></div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-boxes fa-2x mb-2"></i>
                                <h4 id="inventory-available" style="color:white;">0</h4>
                                <p class="mb-0">Available Donations</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-tasks fa-2x mb-2"></i>
                                <h4 id="inventory-active" style="color:white;">0</h4>
                                <p class="mb-0">Active Pipeline</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-hand-holding-heart fa-2x mb-2"></i>
                                <h4 id="inventory-requested" style="color:white;">0</h4>
                                <p class="mb-0">Requested Items</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card h-100">
                            <div class="card-body text-center">
                                <i class="fas fa-balance-scale fa-2x mb-2"></i>
                                <h4 id="inventory-approx" style="color:white;">0</h4>
                                <p class="mb-0">Approx. Units Available</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-lg-8">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 text-black">Inventory by Category</h5>
                                <small class="text-muted">Top categories by availability</small>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle" id="inventory-category-table">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th class="text-center">Available</th>
                                                <th class="text-center">Pending</th>
                                                <th class="text-center">Requested</th>
                                                <th class="text-center">Picked Up</th>
                                                <th class="text-center">Approx. Units</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted">Loading...</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0 text-black">Expiring Soon</h5>
                                <small class="text-muted">Next 3 days</small>
                            </div>
                            <div class="card-body">
                                <ul class="list-group list-group-flush" id="inventory-expiring-list">
                                    <li class="list-group-item text-muted">Checking upcoming expirations...</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-black">Inventory by City</h5>
                        <small class="text-muted">Distribution of active donations</small>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle" id="inventory-city-table">
                                <thead>
                                    <tr>
                                        <th>City</th>
                                        <th class="text-center">Available</th>
                                        <th class="text-center">Pending</th>
                                        <th class="text-center">Requested</th>
                                        <th class="text-center">Picked Up</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Loading distribution...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        const initialInventory = <?php echo $summaryJson ?: 'null'; ?>;
        document.addEventListener('DOMContentLoaded', () => {
            const state = {
                refreshInterval: 30000,
                endpoint: '../api/inventory',
                timer: null
            };

            const elements = {
                lastUpdated: document.getElementById('inventory-last-updated'),
                available: document.getElementById('inventory-available'),
                active: document.getElementById('inventory-active'),
                requested: document.getElementById('inventory-requested'),
                approx: document.getElementById('inventory-approx'),
                categoryTable: document.getElementById('inventory-category-table').querySelector('tbody'),
                cityTable: document.getElementById('inventory-city-table').querySelector('tbody'),
                expiringList: document.getElementById('inventory-expiring-list'),
                error: document.getElementById('inventory-error')
            };

            const formatNumber = value => new Intl.NumberFormat().format(value ?? 0);

            const formatRelativeTime = isoString => {
                if (!isoString) {
                    return 'Unknown';
                }
                const updatedAt = new Date(isoString);
                const now = new Date();
                const diffMs = now.getTime() - updatedAt.getTime();
                const diffMinutes = Math.round(diffMs / 60000);

                if (diffMinutes <= 1) {
                    return 'Just now';
                }
                if (diffMinutes < 60) {
                    return `${diffMinutes} minute${diffMinutes === 1 ? '' : 's'} ago`;
                }
                const diffHours = Math.round(diffMinutes / 60);
                if (diffHours < 24) {
                    return `${diffHours} hour${diffHours === 1 ? '' : 's'} ago`;
                }
                const diffDays = Math.round(diffHours / 24);
                return `${diffDays} day${diffDays === 1 ? '' : 's'} ago`;
            };

            const renderTotals = totals => {
                elements.available.textContent = formatNumber(totals.available ?? 0);
                elements.active.textContent = formatNumber(totals.total_active ?? 0);
                elements.requested.textContent = formatNumber(totals.requested ?? 0);
                elements.approx.textContent = formatNumber(totals.approx_available_units ?? 0);
            };

            const renderCategoryTable = categories => {
                if (!Array.isArray(categories) || categories.length === 0) {
                    elements.categoryTable.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No active donations found</td></tr>';
                    return;
                }

                const rows = categories.map(category => `
                    <tr>
                        <td>${category.category ? category.category.replace('_', ' ') : 'Uncategorized'}</td>
                        <td class="text-center">${formatNumber(category.available)}</td>
                        <td class="text-center">${formatNumber(category.pending)}</td>
                        <td class="text-center">${formatNumber(category.requested)}</td>
                        <td class="text-center">${formatNumber(category.picked_up)}</td>
                        <td class="text-center">${formatNumber(category.approx_units_available)}</td>
                    </tr>
                `).join('');

                elements.categoryTable.innerHTML = rows;
            };

            const renderCityTable = cities => {
                if (!Array.isArray(cities) || cities.length === 0) {
                    elements.cityTable.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No active donations found</td></tr>';
                    return;
                }

                const rows = cities.map(city => `
                    <tr>
                        <td>${city.city || 'Unknown'}</td>
                        <td class="text-center">${formatNumber(city.available)}</td>
                        <td class="text-center">${formatNumber(city.pending)}</td>
                        <td class="text-center">${formatNumber(city.requested)}</td>
                        <td class="text-center">${formatNumber(city.picked_up)}</td>
                    </tr>
                `).join('');

                elements.cityTable.innerHTML = rows;
            };

            const renderExpiringSoon = items => {
                if (!Array.isArray(items) || items.length === 0) {
                    elements.expiringList.innerHTML = '<li class="list-group-item text-muted">No donations expiring in the next 3 days</li>';
                    return;
                }

                const rows = items.map(item => {
                    const expires = new Date(item.expiration_date);
                    const dayLabel = expires.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
                    const urgencyClass = item.days_until_expiry <= 1 ? 'text-danger fw-bold' : 'text-muted';
                    return `
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">${item.title}</h6>
                                    <small class="text-muted">${item.quantity} &middot; ${item.city}</small>
                                </div>
                                <span class="${urgencyClass}">${dayLabel}</span>
                            </div>
                        </li>
                    `;
                }).join('');

                elements.expiringList.innerHTML = rows;
            };

            const renderSummary = data => {
                if (!data || typeof data !== 'object') {
                    return;
                }

                renderTotals(data.totals ?? {});
                renderCategoryTable(data.by_category ?? []);
                renderCityTable(data.by_city ?? []);
                renderExpiringSoon(data.expiring_soon ?? []);
                elements.lastUpdated.textContent = formatRelativeTime(data.last_updated);
            };

            const showError = message => {
                if (!elements.error) {
                    return;
                }
                elements.error.textContent = message;
                elements.error.classList.remove('d-none');
            };

            const hideError = () => {
                if (!elements.error) {
                    return;
                }
                elements.error.textContent = '';
                elements.error.classList.add('d-none');
            };

            const fetchInventory = async () => {
                try {
                    const response = await fetch(state.endpoint, {
                        headers: { 'Accept': 'application/json' },
                        cache: 'no-store'
                    });

                    if (!response.ok) {
                        throw new Error('Inventory endpoint returned an error');
                    }

                    const payload = await response.json();
                    if (!payload.success) {
                        throw new Error(payload.message || 'Unexpected response from inventory endpoint');
                    }

                    hideError();
                    renderSummary(payload.data);
                } catch (err) {
                    console.error('Inventory refresh failed:', err);
                    showError('Live refresh failed. Retrying shortly...');
                }
            };

            if (initialInventory) {
                renderSummary(initialInventory);
            }

            state.timer = setInterval(fetchInventory, state.refreshInterval);
            fetchInventory();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
</body>
</html>
