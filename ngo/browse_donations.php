<?php
require_once '../includes/config.php';

if (!isLoggedIn() || getUserType() !== 'ngo') {
    redirectTo('login.php');
}

$activePage = 'browse';

try {
    $pdo = getDBConnection();
    
    // Get filter parameters
    $category = $_GET['category'] ?? 'all';
    $location = $_GET['location'] ?? '';
    $expiry_filter = $_GET['expiry'] ?? 'all';
    
    // Build query
$where_conditions = [
    "fd.status = 'available'",
    "fd.expiration_date >= CURDATE()"
];
    $params = [];
    
    if ($category !== 'all') {
        $where_conditions[] = "fd.category = ?";
        $params[] = $category;
    }
    
    if (!empty($location)) {
        $where_conditions[] = "(fd.pickup_city LIKE ? OR fd.pickup_state LIKE ?)";
        $params[] = "%$location%";
        $params[] = "%$location%";
    }
    
    if ($expiry_filter === 'today') {
        $where_conditions[] = "fd.expiration_date = CURDATE()";
    } elseif ($expiry_filter === 'tomorrow') {
        $where_conditions[] = "fd.expiration_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
    } elseif ($expiry_filter === 'week') {
        $where_conditions[] = "fd.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
    }
    
    $where_clause = implode(' AND ', $where_conditions);
    
    $stmt = $pdo->prepare("
        SELECT fd.*, u.full_name as donor_name, u.phone as donor_phone, u.email as donor_email,
               COUNT(pr.id) as request_count
        FROM food_donations fd 
        JOIN users u ON fd.donor_id = u.id 
        LEFT JOIN pickup_requests pr ON fd.id = pr.donation_id 
        WHERE $where_clause
        GROUP BY fd.id
        ORDER BY
                 CASE fd.ai_risk_level WHEN 'HIGH' THEN 1 WHEN 'MEDIUM' THEN 2 WHEN 'LOW' THEN 3 ELSE 4 END,
                 fd.expiration_date ASC, fd.created_at DESC
    ");
    $stmt->execute($params);
    $donations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if user has already requested any of these donations
    $ngo_id = $_SESSION['user_id'];
    $requested_donations = [];
    if (!empty($donations)) {
        $donation_ids = array_column($donations, 'id');
        $placeholders = str_repeat('?,', count($donation_ids) - 1) . '?';
        $stmt = $pdo->prepare("SELECT donation_id FROM pickup_requests WHERE ngo_id = ? AND donation_id IN ($placeholders)");
        $stmt->execute(array_merge([$ngo_id], $donation_ids));
        $requested_donations = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'donation_id');
    }
    
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Donations - FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" rel="stylesheet" crossorigin=""/>
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
    <style>
        #donationsMap {
            height: 600px;
            width: 100%;
            background: #f8f9fa;
            border-radius: 4px;
            z-index: 1;
        }
        .map-marker-popup .leaflet-popup-content {
            margin: 10px;
            min-width: 200px;
        }
        .map-marker-popup h6 {
            color: #1a73e8;
            margin-bottom: 10px;
        }
        .map-info-window {
            padding: 10px;
        }
        .map-info-window p {
            margin: 5px 0;
        }
        .leaflet-container {
            font-family: inherit;
        }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
         .ai-risk-box {
            border-radius: 8px;
            padding: 10px 12px;
            margin: 12px 0;
            border: 1px solid #dee2e6;
        }

        .ai-risk-high {
            background: #f8d7da;
            border-color: #f5c2c7;
        }

        .ai-risk-medium {
            background: #fff3cd;
            border-color: #ffecb5;
        }

        .ai-risk-low {
            background: #d1e7dd;
            border-color: #badbcc;
        }

        .ai-risk-unknown {
            background: #e9ecef;
            border-color: #dee2e6;
        }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/partials/sidebar.php'; ?>

            <!-- Main content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Browse Available Donations</h1>
                    <div class="alert alert-success mt-3">
                        <i class="fas fa-robot me-2"></i>
                        <strong>AI prioritization:</strong> donations are ordered by expiry-risk level first, then expiration date. The prediction is decision support and does not replace NGO judgment.
                    </div>
                </div>

                <!-- Search and Filter -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="category" class="form-label">Category</label>
                                <select class="form-select" id="category" name="category">
                                    <option value="all" <?php echo $category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                    <option value="vegetables" <?php echo $category === 'vegetables' ? 'selected' : ''; ?>>Vegetables</option>
                                    <option value="fruits" <?php echo $category === 'fruits' ? 'selected' : ''; ?>>Fruits</option>
                                    <option value="dairy" <?php echo $category === 'dairy' ? 'selected' : ''; ?>>Dairy</option>
                                    <option value="meat" <?php echo $category === 'meat' ? 'selected' : ''; ?>>Meat</option>
                                    <option value="grains" <?php echo $category === 'grains' ? 'selected' : ''; ?>>Grains</option>
                                    <option value="prepared_food" <?php echo $category === 'prepared_food' ? 'selected' : ''; ?>>Prepared Food</option>
                                    <option value="other" <?php echo $category === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="location" class="form-label">Location</label>
                                <input type="text" class="form-control" id="location" name="location" 
                                       value="<?php echo htmlspecialchars($location); ?>" placeholder="City or State">
                            </div>
                            <div class="col-md-3">
                                <label for="expiry" class="form-label">Expiry</label>
                                <select class="form-select" id="expiry" name="expiry">
                                    <option value="all" <?php echo $expiry_filter === 'all' ? 'selected' : ''; ?>>Any Time</option>
                                    <option value="today" <?php echo $expiry_filter === 'today' ? 'selected' : ''; ?>>Expires Today</option>
                                    <option value="tomorrow" <?php echo $expiry_filter === 'tomorrow' ? 'selected' : ''; ?>>Expires Tomorrow</option>
                                    <option value="week" <?php echo $expiry_filter === 'week' ? 'selected' : ''; ?>>Within a Week</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Search
                                    </button>
                                    <a href="browse_donations.php" class="btn btn-secondary">Reset</a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- View Toggle -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-outline-primary active" id="listBtn" onclick="toggleView('list')">
                            <i class="fas fa-list"></i> List View
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="mapBtn" onclick="toggleView('map')">
                            <i class="fas fa-map"></i> Map View
                        </button>
                    </div>
                    <small class="text-muted">
                        <?php echo count($donations); ?> donation(s) found
                    </small>
                </div>

                <!-- Donations Grid -->
                <div id="listView">
                    <?php if (empty($donations)): ?>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No donations found matching your criteria. Try adjusting your filters.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($donations as $donation): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($donation['title']); ?></h6>
                                    <span class="badge bg-<?php echo $donation['category']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $donation['category'])); ?>
                                    </span>
                                </div>
                                <div class="card-body">
                                    <?php if ($donation['description']): ?>
                                        <p class="card-text"><?php echo htmlspecialchars($donation['description']); ?></p>
                                    <?php endif; ?>
                                    
                                    <div class="mb-2">
                                        <strong>Quantity:</strong> <?php echo htmlspecialchars($donation['quantity']); ?>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <strong>Expires:</strong> 
                                        <?php 
                                        $exp_date = strtotime($donation['expiration_date']);
                                        $today = strtotime('today');
                                        $class = '';
                                        if ($exp_date <= $today) {
                                            $class = 'text-danger';
                                        } elseif ($exp_date <= strtotime('+2 days')) {
                                            $class = 'text-warning';
                                        }
                                        ?>
                                        <span class="<?php echo $class; ?>">
                                            <?php echo date('M j, Y', $exp_date); ?>
                                        </span>
                                    </div>
                                  <!-- AI Expiry Risk -->
                                    <?php
                                    $ai_risk_level = strtoupper($donation['ai_risk_level'] ?? '');
                                    $ai_risk_probability = $donation['ai_risk_probability'] ?? null;

                                    if ($ai_risk_level === 'HIGH') {
                                        $risk_class = 'ai-risk-high';
                                        $risk_icon = 'fa-triangle-exclamation';
                                    } elseif ($ai_risk_level === 'MEDIUM') {
                                        $risk_class = 'ai-risk-medium';
                                        $risk_icon = 'fa-circle-exclamation';
                                    } elseif ($ai_risk_level === 'LOW') {
                                        $risk_class = 'ai-risk-low';
                                        $risk_icon = 'fa-circle-check';
                                    } else {
                                        $risk_class = 'ai-risk-unknown';
                                        $risk_icon = 'fa-circle-question';
                                    }
                                    ?>

                                    <div class="ai-risk-box <?php echo $risk_class; ?>">
                                        <strong>
                                            <i class="fas <?php echo $risk_icon; ?>"></i>
                                            AI Expiry Risk
                                        </strong>

                                        <?php if ($ai_risk_probability !== null && $ai_risk_level !== ''): ?>
                                            <div class="mt-1">
                                                <strong><?php echo htmlspecialchars($ai_risk_level); ?> RISK</strong>
                                                —
                                                <?php echo htmlspecialchars($ai_risk_probability); ?>%
                                            </div>
                                        <?php else: ?>
                                            <div class="mt-1 text-muted">
                                                Prediction not available
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <strong>Donor:</strong> <?php echo htmlspecialchars($donation['donor_name']); ?>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <strong>Location:</strong> 
                                        <?php echo htmlspecialchars($donation['pickup_city']); ?><?php echo $donation['pickup_city'] && $donation['pickup_state'] ? ', ' : ''; ?><?php echo htmlspecialchars($donation['pickup_state']); ?>
                                    </div>
                                    
                                    <?php if ($donation['request_count'] > 0): ?>
                                        <div class="mb-2">
                                            <small class="text-muted">
                                                <i class="fas fa-users"></i> <?php echo $donation['request_count']; ?> other request(s)
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-3">
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> Posted <?php echo date('M j, Y g:i A', strtotime($donation['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                                
                                <div class="card-footer">
                                    <?php if (in_array($donation['id'], $requested_donations)): ?>
                                        <button class="btn btn-secondary w-100" disabled>
                                            <i class="fas fa-check"></i> Already Requested
                                        </button>
                                    <?php else: ?>
                                        <a href="request_pickup.php?id=<?php echo $donation['id']; ?>" class="btn btn-success w-100">
                                            <i class="fas fa-hand-paper"></i> Request Pickup
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Results Summary -->
                    <div class="alert alert-light mt-4">
                        <i class="fas fa-info-circle"></i> 
                        Showing <?php echo count($donations); ?> available donation(s)
                        <?php if ($category !== 'all' || !empty($location) || $expiry_filter !== 'all'): ?>
                            with applied filters
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Map View -->
                <div id="mapView" style="display: none;">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0">
                                <i class="fas fa-map-marker-alt"></i> Donation Locations
                            </h5>
                        </div>
                        <div class="card-body position-relative">
                            <div id="mapLoading" class="loading-overlay d-none">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading map...</span>
                                </div>
                            </div>
                            <div id="donationsMap" style="height: 600px; width: 100%; border-radius: 4px;"></div>
                            <div id="mapError" class="alert alert-warning mt-3 d-none"></div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/app.js?v=3"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        const donationsData = <?php echo json_encode($donations); ?>;
        let mapInstance = null;
        let mapMarkers = [];
        let mapInitialized = false;
        const geocodeCache = new Map();

        const defaultMarkerIcon = L.icon({
            iconUrl: 'https://cdn.jsdelivr.net/gh/pointhi/leaflet-color-markers@master/img/marker-icon-2x-blue.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.3/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        });

        function resolveCategoryIcon(category) {
            const categoryMap = {
                vegetables: 'green',
                fruits: 'orange',
                dairy: 'violet',
                meat: 'red',
                grains: 'yellow',
                prepared_food: 'grey',
                other: 'blue'
            };
            const color = categoryMap[category] || 'blue';
            return L.icon({
                iconUrl: `https://cdn.jsdelivr.net/gh/pointhi/leaflet-color-markers@master/img/marker-icon-2x-${color}.png`,
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.3/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
        }

        const mapElements = {
            listView: document.getElementById('listView'),
            mapView: document.getElementById('mapView'),
            listBtn: document.getElementById('listBtn'),
            mapBtn: document.getElementById('mapBtn'),
            mapContainer: document.getElementById('donationsMap'),
            loading: document.getElementById('mapLoading'),
            error: document.getElementById('mapError')
        };

        function escapeHtml(value) {
            if (value === null || value === undefined) {
                return '';
            }
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function formatCategory(category) {
            if (!category) {
                return 'N/A';
            }
            const normalized = category.replace(/_/g, ' ').toLowerCase();
            const titled = normalized.replace(/\b\w/g, char => char.toUpperCase());
            return escapeHtml(titled);
        }

        function setLoading(isLoading) {
            if (!mapElements.loading) {
                return;
            }
            mapElements.loading.classList.toggle('d-none', !isLoading);
        }

        function showMapError(message) {
            if (!mapElements.error) {
                return;
            }
            mapElements.error.textContent = message;
            mapElements.error.classList.remove('d-none');
        }

        function clearMapError() {
            if (!mapElements.error) {
                return;
            }
            mapElements.error.textContent = '';
            mapElements.error.classList.add('d-none');
        }

        async function geocodeAddress(address) {
            if (geocodeCache.has(address)) {
                return geocodeCache.get(address);
            }

            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&limit=1&q=${encodeURIComponent(address)}`);
                if (!response.ok) {
                    throw new Error(`Geocoding failed with status ${response.status}`);
                }
                const data = await response.json();
                if (Array.isArray(data) && data.length > 0) {
                    const coords = {
                        lat: parseFloat(data[0].lat),
                        lng: parseFloat(data[0].lon)
                    };
                    geocodeCache.set(address, coords);
                    return coords;
                }
            } catch (error) {
                console.error('Error geocoding address:', error);
            }

            geocodeCache.set(address, null);
            return null;
        }

        function resetMarkers() {
            if (!mapInstance) {
                return;
            }
            mapMarkers.forEach(marker => mapInstance.removeLayer(marker));
            mapMarkers = [];
        }

        async function initDonationsMap() {
            if (!mapElements.mapContainer || mapInitialized) {
                return;
            }

            if (!donationsData || donationsData.length === 0) {
                mapElements.mapContainer.innerHTML = '<div class="alert alert-info m-3"><i class="fas fa-info-circle me-2"></i>No donations to display on the map.</div>';
                mapInitialized = true;
                return;
            }

            setLoading(true);
            clearMapError();

            try {
                if (!mapInstance) {
                    mapInstance = L.map('donationsMap').setView([20.5937, 78.9629], 5);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 19
                    }).addTo(mapInstance);
                }

                resetMarkers();
                const bounds = L.latLngBounds();
                let successfulMarkers = 0;

                for (const donation of donationsData) {
                    const addressParts = [donation.pickup_address, donation.pickup_city, donation.pickup_state].filter(Boolean);
                    if (addressParts.length === 0) {
                        continue;
                    }
                    const address = addressParts.join(', ');

                    const coords = await geocodeAddress(address);
                    if (!coords) {
                        continue;
                    }

                    const popupHtml = `
                        <div class="map-info-window">
                            <h6 class="mb-2">${escapeHtml(donation.title)}</h6>
                            <p class="mb-1"><strong>Category:</strong> ${formatCategory(donation.category)}</p>
                            <p class="mb-1"><strong>Quantity:</strong> ${escapeHtml(donation.quantity)}</p>
                            <p class="mb-1"><strong>Expires:</strong> ${donation.expiration_date ? escapeHtml(new Date(donation.expiration_date).toLocaleDateString()) : 'N/A'}</p>
                            <p class="mb-0"><strong>Address:</strong> ${escapeHtml(address)}</p>
                        </div>
                    `;

                    const marker = L.marker([coords.lat, coords.lng], {
                        icon: resolveCategoryIcon(donation.category)
                    }).bindPopup(popupHtml);
                    marker.donationId = donation.id;
                    marker.addTo(mapInstance);
                    mapMarkers.push(marker);
                    bounds.extend([coords.lat, coords.lng]);
                    successfulMarkers += 1;

                    await new Promise(resolve => setTimeout(resolve, 300));
                }

                if (successfulMarkers > 0 && bounds.isValid()) {
                    mapInstance.fitBounds(bounds, { padding: [50, 50] });
                    mapInitialized = true;
                } else {
                    showMapError('Unable to determine the locations for these donations right now.');
                    mapInitialized = false;
                }
            } catch (error) {
                console.error('Failed to initialize donation map:', error);
                showMapError('Unable to load the map right now. Please try again later.');
                mapInitialized = false;
            } finally {
                setLoading(false);
            }
        }

        function toggleView(view) {
            if (!mapElements.listView || !mapElements.mapView || !mapElements.listBtn || !mapElements.mapBtn) {
                return;
            }

            if (view === 'map') {
                mapElements.listView.style.display = 'none';
                mapElements.mapView.style.display = 'block';
                mapElements.listBtn.classList.remove('active');
                mapElements.mapBtn.classList.add('active');

                initDonationsMap().catch(error => {
                    console.error('Map initialization error:', error);
                    showMapError('Unable to load the map right now.');
                    setLoading(false);
                });
            } else {
                mapElements.listView.style.display = 'block';
                mapElements.mapView.style.display = 'none';
                mapElements.listBtn.classList.add('active');
                mapElements.mapBtn.classList.remove('active');
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            toggleView('list');
        });
    </script>
</body>
</html>

