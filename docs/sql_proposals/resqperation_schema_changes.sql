CREATE TABLE IF NOT EXISTS request_validations (
    validation_id INT NOT NULL AUTO_INCREMENT,
    request_id VARCHAR(255) NOT NULL,
    validation_status VARCHAR(30) NOT NULL,
    validator_user_id VARCHAR(255) NULL,
    validation_notes TEXT NULL,
    missing_information TEXT NULL,
    duplicate_request_id VARCHAR(255) NULL,
    validated_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (validation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accommodation_types (
    type_id INT NOT NULL,
    type_key VARCHAR(50) NULL,
    type_label VARCHAR(100) NULL,
    PRIMARY KEY (type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS accommodation_units (
    unit_id INT NOT NULL AUTO_INCREMENT,
    center_id VARCHAR(255) NULL,
    name VARCHAR(100) NULL,
    type_id INT NULL,
    max_capacity INT NULL,
    created_at DATETIME NULL,
    deleted_at DATETIME NULL,
    PRIMARY KEY (unit_id),
    KEY idx_accommodation_units_type_id (type_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO resource_request_status (status_id, status_key, status_label)
VALUES
    (1, 'pending', 'Pending'),
    (2, 'acknowledged', 'Acknowledged'),
    (3, 'approved', 'Approved'),
    (4, 'rejected', 'Rejected'),
    (5, 'delivered', 'Delivered');
