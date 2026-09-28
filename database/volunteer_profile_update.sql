-- Volunteer profile details for self-service onboarding
CREATE TABLE IF NOT EXISTS volunteer_profiles (
    user_id INT PRIMARY KEY,
    vehicle_type ENUM('car', 'van', 'truck', 'bicycle', 'motorcycle') NULL,
    vehicle_number VARCHAR(50) NULL,
    license_number VARCHAR(50) NULL,
    availability_days TEXT NULL,
    availability_hours ENUM('morning', 'afternoon', 'evening', 'flexible') NULL,
    emergency_contact_name VARCHAR(150) NULL,
    emergency_contact_phone VARCHAR(30) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_volunteer_profiles_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
