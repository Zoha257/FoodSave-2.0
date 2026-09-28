-- FoodSave Database Schema
-- Import this file into phpMyAdmin to create the database

CREATE DATABASE IF NOT EXISTS foodsave_db;
USE foodsave_db;

-- Users table (for all user types)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    user_type ENUM('admin', 'donor', 'ngo', 'volunteer', 'driver') NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    organization_name VARCHAR(150),
    phone VARCHAR(20),
    address TEXT,
    city VARCHAR(50),
    state VARCHAR(50),
    zip_code VARCHAR(10),
    status ENUM('active', 'pending', 'inactive') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Food donations table
CREATE TABLE food_donations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donor_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    category ENUM('vegetables', 'fruits', 'dairy', 'meat', 'grains', 'prepared_food', 'other') NOT NULL,
    quantity VARCHAR(50) NOT NULL,
    expiration_date DATE NOT NULL,
    pickup_address TEXT NOT NULL,
    pickup_city VARCHAR(50) NOT NULL,
    pickup_state VARCHAR(50) NOT NULL,
    pickup_zip VARCHAR(10) NOT NULL,
    status ENUM('available', 'pending', 'requested', 'picked_up', 'completed', 'cancelled', 'expired') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ai_risk_probability DECIMAL(5,2) NULL,
    ai_risk_level ENUM('LOW','MEDIUM','HIGH') NULL,
    ai_predicted_at DATETIME NULL,
    ai_model_version VARCHAR(50) NULL,
    FOREIGN KEY (donor_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Pickup requests table
CREATE TABLE pickup_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    ngo_id INT NOT NULL,
    request_message TEXT,
    pickup_date DATE,
    pickup_time TIME,
    preferred_pickup_time DATETIME,
    delivery_address VARCHAR(255),
    delivery_city VARCHAR(100),
    delivery_state VARCHAR(50),
    delivery_zip VARCHAR(10),
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (donation_id) REFERENCES food_donations(id) ON DELETE CASCADE,
    FOREIGN KEY (ngo_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Feedback table
CREATE TABLE feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pickup_request_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comments TEXT,
    feedback_type ENUM('donor', 'ngo') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE
);

-- Public contact messages
CREATE TABLE contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(254) NOT NULL,
    subject VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('new', 'read', 'closed') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_contact_status_created (status, created_at)
);

-- Security and rate limiting tables
CREATE TABLE rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    identifier VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rate_limit (action, identifier, created_at)
);

CREATE TABLE security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event VARCHAR(100) NOT NULL,
    user_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts (username, ip_address, attempt_time)
);

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    INDEX idx_password_reset (email, token)
);

-- Notifications and volunteer management
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

CREATE TABLE volunteer_opportunities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ngo_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,
    required_volunteers INT NOT NULL,
    location_address VARCHAR(255) NOT NULL,
    location_city VARCHAR(100) NOT NULL,
    location_state VARCHAR(50) NOT NULL,
    location_zip VARCHAR(10) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'completed', 'cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ngo_id) REFERENCES users(id)
);

CREATE TABLE volunteer_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    opportunity_id INT NOT NULL,
    volunteer_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (opportunity_id) REFERENCES volunteer_opportunities(id),
    FOREIGN KEY (volunteer_id) REFERENCES users(id),
    UNIQUE KEY unique_volunteer_opportunity (volunteer_id, opportunity_id)
);

CREATE TABLE delivery_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pickup_request_id INT NOT NULL,
    volunteer_id INT NOT NULL,
    status ENUM('assigned', 'picked_up', 'in_transit', 'delivered', 'cancelled') DEFAULT 'assigned',
    pickup_time DATETIME,
    delivery_time DATETIME,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(id) ON DELETE CASCADE
);

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

CREATE TABLE volunteer_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id INT NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    time_slot ENUM('morning', 'afternoon', 'evening', 'flexible') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(id) ON DELETE CASCADE
);

CREATE TABLE volunteer_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id INT NOT NULL,
    average_rating DECIMAL(3,2) DEFAULT 0,
    total_ratings INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(id) ON DELETE CASCADE
);



-- Driver and driver-delivery management (required by the delivery requirements)
CREATE TABLE drivers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    vehicle_type ENUM('car','van','truck','bicycle','motorcycle') NOT NULL,
    vehicle_number VARCHAR(20) NOT NULL,
    license_number VARCHAR(30) NOT NULL,
    status ENUM('available','busy','offline') NOT NULL DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_driver_user (user_id),
    UNIQUE KEY unique_driver_vehicle (vehicle_number),
    UNIQUE KEY unique_driver_license (license_number)
) ENGINE=InnoDB;

CREATE TABLE delivery_tasks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    pickup_request_id INT NOT NULL,
    driver_id INT NULL,
    status ENUM('pending','assigned','picked_up','in_transit','completed','cancelled') NOT NULL DEFAULT 'pending',
    pickup_time DATETIME NULL,
    delivery_time DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pickup_request_id) REFERENCES pickup_requests(id) ON DELETE CASCADE,
    FOREIGN KEY (driver_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_delivery_task_request (pickup_request_id),
    INDEX idx_delivery_task_driver_status (driver_id,status)
) ENGINE=InnoDB;

CREATE TABLE driver_tracking (
    id INT PRIMARY KEY AUTO_INCREMENT,
    delivery_task_id INT NOT NULL,
    status_update ENUM('picked_up','in_transit','completed','cancelled') NOT NULL,
    latitude DECIMAL(10,8) NULL,
    longitude DECIMAL(11,8) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (delivery_task_id) REFERENCES delivery_tasks(id) ON DELETE CASCADE,
    INDEX idx_driver_tracking_task_created (delivery_task_id,created_at)
) ENGINE=InnoDB;


-- FoodSave 2.0 AI prediction and experiment audit tables
CREATE TABLE ai_prediction_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    model_version VARCHAR(50) NOT NULL,
    risk_probability DECIMAL(5,2) NOT NULL,
    risk_level ENUM('LOW','MEDIUM','HIGH') NOT NULL,
    feature_snapshot JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_prediction_donation (donation_id),
    INDEX idx_ai_prediction_created (created_at),
    FOREIGN KEY (donation_id) REFERENCES food_donations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ai_experiments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_name VARCHAR(150) NOT NULL,
    dataset_version VARCHAR(80) NOT NULL,
    model_name VARCHAR(100) NOT NULL,
    metrics_json JSON NOT NULL,
    confusion_matrix_json JSON NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DELIMITER //
CREATE TRIGGER update_volunteer_rating AFTER INSERT ON feedback
FOR EACH ROW
BEGIN
    UPDATE volunteer_ratings vr
    SET average_rating = (
        SELECT AVG(f.rating)
        FROM feedback f
        JOIN pickup_requests pr ON f.pickup_request_id = pr.id
        JOIN delivery_assignments da ON pr.id = da.pickup_request_id
        WHERE da.volunteer_id = vr.volunteer_id
    ),
    total_ratings = (
        SELECT COUNT(*)
        FROM feedback f
        JOIN pickup_requests pr ON f.pickup_request_id = pr.id
        JOIN delivery_assignments da ON pr.id = da.pickup_request_id
        WHERE da.volunteer_id = vr.volunteer_id
    )
    WHERE vr.volunteer_id IN (
        SELECT da.volunteer_id
        FROM delivery_assignments da
        WHERE da.pickup_request_id = NEW.pickup_request_id
    );
END //
DELIMITER ;

-- Insert default admin user
INSERT INTO users (username, email, password, user_type, full_name, status) 
VALUES ('admin', 'admin@foodsave.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'System Administrator', 'active');

-- Insert sample data for testing
INSERT INTO users (username, email, password, user_type, full_name, phone, address, city, state, zip_code, status) VALUES
('restaurant1', 'restaurant@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'donor', 'Green Valley Restaurant', '555-0101', '123 Main St', 'Springfield', 'IL', '62701', 'active'),
('grocery1', 'grocery@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'donor', 'Fresh Market Grocery', '555-0102', '456 Oak Ave', 'Springfield', 'IL', '62702', 'active'),
('foodbank1', 'foodbank@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ngo', 'Springfield Food Bank', '555-0201', '789 Elm St', 'Springfield', 'IL', '62703', 'active'),
('shelter1', 'shelter@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'ngo', 'Hope Shelter', '555-0202', '321 Pine St', 'Springfield', 'IL', '62704', 'active'),
('delivery_mgr1', 'delivery@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'driver', 'FoodSave Delivery Driver', '555-0301', '10 Route Ave', 'Springfield', 'IL', '62705', 'active');

-- Insert sample food donations
INSERT INTO food_donations (donor_id, title, description, category, quantity, expiration_date, pickup_address, pickup_city, pickup_state, pickup_zip, status) VALUES
(2, 'Fresh Vegetables', 'Mixed vegetables including carrots, broccoli, and lettuce', 'vegetables', '20 lbs', '2026-09-10', '123 Main St', 'Springfield', 'IL', '62701', 'available'),
(3, 'Bread and Pastries', 'Day-old bread and pastries from bakery section', 'grains', '15 loaves', '2026-09-11', '456 Oak Ave', 'Springfield', 'IL', '62702', 'available'),
(2, 'Prepared Meals', 'Leftover prepared meals from lunch service', 'prepared_food', '30 portions', '2026-09-09', '123 Main St', 'Springfield', 'IL', '62701', 'available');

-- Ensure NGO organization names are recorded
UPDATE users SET organization_name = full_name WHERE user_type = 'ngo';


INSERT INTO drivers (user_id, vehicle_type, vehicle_number, license_number, status) SELECT id, 'van', 'FS-DEMO-01', 'FS-LIC-001', 'available' FROM users WHERE username='delivery_mgr1' AND NOT EXISTS (SELECT 1 FROM drivers WHERE user_id=(SELECT id FROM users WHERE username='delivery_mgr1'));
