-- FoodSave 2.0 AI + research migration
-- Run once on an existing FoodSave database.
-- The IF NOT EXISTS guards make this migration safer to rerun on MySQL 8+.

ALTER TABLE food_donations
    ADD COLUMN IF NOT EXISTS ai_risk_probability DECIMAL(5,2) NULL,
    ADD COLUMN IF NOT EXISTS ai_risk_level ENUM('LOW','MEDIUM','HIGH') NULL,
    ADD COLUMN IF NOT EXISTS ai_predicted_at DATETIME NULL,
    ADD COLUMN IF NOT EXISTS ai_model_version VARCHAR(50) NULL;

CREATE TABLE IF NOT EXISTS ai_prediction_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    donation_id INT NOT NULL,
    model_version VARCHAR(50) NOT NULL,
    risk_probability DECIMAL(5,2) NOT NULL,
    risk_level ENUM('LOW','MEDIUM','HIGH') NOT NULL,
    feature_snapshot JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_prediction_donation (donation_id),
    INDEX idx_ai_prediction_created (created_at),
    CONSTRAINT fk_ai_prediction_donation
        FOREIGN KEY (donation_id) REFERENCES food_donations(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS ai_experiments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    experiment_name VARCHAR(150) NOT NULL,
    dataset_version VARCHAR(80) NOT NULL,
    model_name VARCHAR(100) NOT NULL,
    metrics_json JSON NOT NULL,
    confusion_matrix_json JSON NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
