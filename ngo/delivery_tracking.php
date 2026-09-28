<?php
require_once '../includes/config.php';
require_once '../includes/classes/Volunteer.php';

if (!isLoggedIn() || getUserType() !== 'ngo') {
    redirectTo('login.php');
}

$activePage = 'tracking';

$error = '';
$deliveries = [];

try {
    $pdo = getDBConnection();
    $ngoId = $_SESSION['user_id'];
    $volunteerManager = new Volunteer($pdo);

    $volunteerDeliveries = $volunteerManager->getActiveDeliveryLocations($ngoId);
    foreach ($volunteerDeliveries as $delivery) {
        $history = $volunteerManager->getDeliveryTracking($delivery['id']);
        $timestamps = [];

        foreach ($history as $entry) {
            $statusKey = $entry['status_update'];
            if (!isset($timestamps[$statusKey])) {
                $timestamps[$statusKey] = $entry['created_at'];
            }
        }

        $deliveries[] = [
            'id' => 'volunteer-' . $delivery['id'],
            'source_id' => (int) $delivery['id'],
            'type' => 'volunteer',
            'status' => $delivery['status'],
            'name' => $delivery['volunteer_name'] ?? 'Assigned Volunteer',
            'pickup_address' => trim($delivery['pickup_address'] . ', ' . $delivery['pickup_city'] . ', ' . $delivery['pickup_state'] . ' ' . $delivery['pickup_zip']),
            'delivery_address' => trim($delivery['delivery_address'] . ', ' . $delivery['delivery_city'] . ', ' . $delivery['delivery_state'] . ' ' . $delivery['delivery_zip']),
            'current_lat' => $delivery['current_lat'],
            'current_lng' => $delivery['current_lng'],
            'last_update' => $delivery['last_update'],
            'timestamps' => $timestamps,
        ];
    }
} catch (Exception $e) {
    $error = 'Server error: ' . $e->getMessage();
}

usort($deliveries, static function (array $a, array $b) {
    return strtotime($b['last_update'] ?? '') <=> strtotime($a['last_update'] ?? '');
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Tracking - FoodSave NGO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link href="../assets/css/style.css?v=3" rel="stylesheet">
    <style>
        #deliveryMap {
            height: 420px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .delivery-card {
            margin-bottom: 24px;
        }

        .status-timeline {
            position: relative;
            padding-left: 30px;
            margin-top: 12px;
        }

        .status-timeline::before {
            content: '';
            position: absolute;
            left: 12px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }

        .status-item {
            position: relative;
            padding-bottom: 18px;
        }

        .status-item::before {
            content: '';
            position: absolute;
            left: -22px;
            top: 2px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #0d6efd;
        }

        .status-item.completed::before {
            background: #0d6efd;
        }

        .status-item:last-child {
            padding-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <?php include __DIR__ . '/partials/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Delivery Tracking</h1>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div id="deliveryMap"></div>

                <?php if (empty($deliveries)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>No active volunteer deliveries to track right now.
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($deliveries as $delivery): ?>
                            <div class="col-lg-6">
                                <div class="card delivery-card">
                                    <div class="card-header d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-0"><?php echo ucfirst($delivery['type']); ?> Delivery #<?php echo $delivery['source_id']; ?></h5>
                                            <small class="text-muted">
                                                Last update: <?php echo $delivery['last_update'] ? date('M j, Y g:i A', strtotime($delivery['last_update'])) : 'Awaiting first update'; ?>
                                            </small>
                                        </div>
                                        <span class="badge <?php
                                            echo match ($delivery['status']) {
                                                'assigned' => 'bg-info',
                                                'picked_up' => 'bg-primary',
                                                'in_transit' => 'bg-warning text-dark',
                                                'delivered' => 'bg-success',
                                                default => 'bg-secondary',
                                            };
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $delivery['status'])); ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <strong><?php echo ucfirst($delivery['type']); ?>:</strong> <?php echo htmlspecialchars($delivery['name']); ?><br>
                                            <strong>Pickup Location:</strong> <?php echo htmlspecialchars($delivery['pickup_address']); ?><br>
                                            <strong>Delivery Location:</strong> <?php echo htmlspecialchars($delivery['delivery_address']); ?>
                                        </div>

                                        <div class="status-timeline">
                                            <?php
                                            $statuses = ['assigned', 'started', 'at_pickup', 'picked_up', 'in_transit', 'at_delivery', 'delivered'];
                                            $currentIndex = array_search($delivery['status'], $statuses, true);
                                            foreach ($statuses as $index => $status):
                                                $timestamp = $delivery['timestamps'][$status] ?? null;
                                                $completed = $currentIndex !== false && $index <= $currentIndex;
                                            ?>
                                                <div class="status-item <?php echo $completed ? 'completed' : ''; ?>">
                                                    <strong><?php echo ucwords(str_replace('_', ' ', $status)); ?></strong>
                                                    <?php if ($timestamp): ?>
                                                        <br><small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($timestamp)); ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
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
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        const map = L.map('deliveryMap').setView([20.5937, 78.9629], 5);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);

        const deliveriesData = <?php echo json_encode($deliveries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
        const geocodeCache = new Map();
        const liveMarkers = new Map();
        const bounds = L.latLngBounds();

        const icons = {
            pickup: L.icon({
                iconUrl: 'https://cdn.jsdelivr.net/gh/pointhi/leaflet-color-markers@master/img/marker-icon-2x-green.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.3/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            }),
            dropoff: L.icon({
                iconUrl: 'https://cdn.jsdelivr.net/gh/pointhi/leaflet-color-markers@master/img/marker-icon-2x-orange.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.3/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            }),
            volunteer: L.icon({
                iconUrl: 'https://cdn.jsdelivr.net/gh/pointhi/leaflet-color-markers@master/img/marker-icon-2x-blue.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.3/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            }),
            volunteerLive: L.icon({
                iconUrl: 'https://cdn.jsdelivr.net/gh/pointhi/leaflet-color-markers@master/img/marker-icon-2x-blue.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.3/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            })
        };

        async function geocode(address) {
            if (!address) {
                return null;
            }

            if (geocodeCache.has(address)) {
                return geocodeCache.get(address);
            }

            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`);
                const data = await response.json();

                if (Array.isArray(data) && data.length > 0) {
                    const coords = {
                        lat: parseFloat(data[0].lat),
                        lng: parseFloat(data[0].lon)
                    };

                    geocodeCache.set(address, coords);
                    return coords;
                }
            } catch (err) {
                console.error('Failed to geocode address', address, err);
            }

            geocodeCache.set(address, null);
            return null;
        }

        async function plotDelivery(delivery) {
            const pickupCoords = await geocode(delivery.pickup_address);
            if (pickupCoords) {
                L.marker([pickupCoords.lat, pickupCoords.lng], { icon: icons.pickup })
                    .bindPopup(`<strong>Pickup</strong><br>${delivery.pickup_address}`)
                    .addTo(map);
                bounds.extend([pickupCoords.lat, pickupCoords.lng]);
            }

            const dropoffCoords = await geocode(delivery.delivery_address);
            if (dropoffCoords) {
                L.marker([dropoffCoords.lat, dropoffCoords.lng], { icon: icons.dropoff })
                    .bindPopup(`<strong>Drop-off</strong><br>${delivery.delivery_address}`)
                    .addTo(map);
                bounds.extend([dropoffCoords.lat, dropoffCoords.lng]);
            }

            if (delivery.current_lat && delivery.current_lng) {
                const liveMarker = L.marker([delivery.current_lat, delivery.current_lng], {
                    icon: icons.volunteerLive
                }).bindPopup(`<strong>Volunteer</strong><br>${delivery.name}`);

                liveMarker.addTo(map);
                liveMarkers.set(delivery.id, liveMarker);
                bounds.extend([delivery.current_lat, delivery.current_lng]);
            }
        }

        async function initMap() {
            for (const delivery of deliveriesData) {
                await plotDelivery(delivery);
                await new Promise(resolve => setTimeout(resolve, 150));
            }

            if (bounds.isValid()) {
                map.fitBounds(bounds, { padding: [45, 45] });
            }
        }

        async function refreshLiveLocations() {
            try {
                const response = await fetch('get_delivery_locations.php');
                const updates = await response.json();

                if (!Array.isArray(updates)) {
                    return;
                }

                updates.forEach(update => {
                    if (!update.current_lat || !update.current_lng) {
                        return;
                    }

                    const marker = liveMarkers.get(update.id);
                    if (marker) {
                        marker.setLatLng([update.current_lat, update.current_lng]);
                    } else {
                        const newMarker = L.marker([update.current_lat, update.current_lng], { icon: icons.volunteerLive })
                            .bindPopup('<strong>Volunteer</strong>')
                            .addTo(map);
                        liveMarkers.set(update.id, newMarker);
                    }
                });
            } catch (err) {
                console.error('Failed to refresh live locations', err);
            }
        }

        initMap().catch(err => console.error('Map initialization error:', err));

        if (deliveriesData.length > 0) {
            setInterval(refreshLiveLocations, 30000);
        }
    </script>
</body>
</html>