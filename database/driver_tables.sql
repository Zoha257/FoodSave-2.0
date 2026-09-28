-- FoodSave driver/delivery migration for an existing database.
-- Run this once after importing the main FoodSave schema.
ALTER TABLE users MODIFY COLUMN user_type ENUM('admin','donor','ngo','volunteer','driver') NOT NULL;

CREATE TABLE IF NOT EXISTS drivers (
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

CREATE TABLE IF NOT EXISTS delivery_tasks (
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

CREATE TABLE IF NOT EXISTS driver_tracking (
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

-- Backfill a delivery task for already-approved pickup requests.
INSERT INTO delivery_tasks (pickup_request_id,status,pickup_time)
SELECT pr.id,'pending',pr.preferred_pickup_time
FROM pickup_requests pr
LEFT JOIN delivery_tasks dt ON dt.pickup_request_id=pr.id
WHERE pr.status='approved' AND dt.id IS NULL;
