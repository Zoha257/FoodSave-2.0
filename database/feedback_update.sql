-- Add feedback_type column to feedback table
ALTER TABLE feedback 
ADD COLUMN feedback_type ENUM('donor', 'ngo') NOT NULL AFTER comments;

-- Add volunteer availability table
CREATE TABLE volunteer_availability (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id INT NOT NULL,
    day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
    time_slot ENUM('morning', 'afternoon', 'evening', 'flexible') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(id) ON DELETE CASCADE
);

-- Add volunteer ratings table for quick access to aggregated ratings
CREATE TABLE volunteer_ratings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    volunteer_id INT NOT NULL,
    average_rating DECIMAL(3,2) DEFAULT 0,
    total_ratings INT DEFAULT 0,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (volunteer_id) REFERENCES volunteers(id) ON DELETE CASCADE
);

-- Add trigger to update volunteer ratings
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