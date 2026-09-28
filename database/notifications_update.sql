-- Notifications table for system-wide notifications
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('alert', 'info', 'success', 'warning') NOT NULL,
    related_to ENUM('donation', 'pickup', 'expiration', 'system') NOT NULL,
    related_id INT,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add volunteers table for delivery management
CREATE TABLE volunteers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ngo_id INT NOT NULL,
    vehicle_type ENUM('car', 'van', 'truck', 'bicycle', 'motorcycle') NOT NULL,
    vehicle_number VARCHAR(20),
    license_number VARCHAR(20),
    availability_status ENUM('available', 'busy', 'inactive') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (ngo_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Add delivery assignments table
CREATE TABLE delivery_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pickup_request_id INT NOT NULL,
    volunteer_id INT NOT NULL,
    status ENUM('assigned', 'picked_up', 'delivered', 'cancelled') DEFAULT 'assigned',
    pickup_time DATETIME,
    delivery_time DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(id) ON DELETE CASCADE
);

-- Add delivery tracking table
CREATE TABLE delivery_tracking (
    id INT AUTO_INCREMENT PRIMARY KEY,
    delivery_assignment_id INT NOT NULL,
    status_update ENUM('started', 'at_pickup', 'picked_up', 'in_transit', 'at_delivery', 'delivered') NOT NULL,
    latitude DECIMAL(10, 8),
    longitude DECIMAL(11, 8),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_assignment_id) REFERENCES delivery_assignments(id) ON DELETE CASCADE
);