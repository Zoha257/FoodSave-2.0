-- Volunteer opportunities table
CREATE TABLE volunteer_opportunities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ngo_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    type VARCHAR(50) NOT NULL,  -- e.g., 'food sorting', 'delivery', 'event support'
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
) ENGINE=InnoDB;

-- Volunteer assignments table
CREATE TABLE volunteer_assignments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    opportunity_id INT NOT NULL,
    volunteer_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (opportunity_id) REFERENCES volunteer_opportunities(id),
    FOREIGN KEY (volunteer_id) REFERENCES users(id),
    UNIQUE KEY unique_volunteer_opportunity (volunteer_id, opportunity_id)
) ENGINE=InnoDB;

-- Add delivery details to pickup requests
ALTER TABLE pickup_requests
ADD COLUMN delivery_address VARCHAR(255) NOT NULL AFTER status,
ADD COLUMN delivery_city VARCHAR(100) NOT NULL AFTER delivery_address,
ADD COLUMN delivery_state VARCHAR(50) NOT NULL AFTER delivery_city,
ADD COLUMN delivery_zip VARCHAR(10) NOT NULL AFTER delivery_state,
ADD COLUMN preferred_pickup_time DATETIME NULL AFTER delivery_zip;