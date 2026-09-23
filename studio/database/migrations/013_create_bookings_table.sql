-- Session requests. Version 1 records the request; the columns a calendar,
-- deposit or online payment would need are added in V2 without changing this
-- table's meaning.
CREATE TABLE bookings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    service_id INT UNSIGNED NULL,
    service_label VARCHAR(190) NULL,
    preferred_date DATE NULL,
    location VARCHAR(190) NULL,
    message TEXT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    handled_at DATETIME NULL,
    CONSTRAINT fk_bookings_service FOREIGN KEY (service_id) REFERENCES services (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_bookings_status ON bookings (status);
CREATE INDEX idx_bookings_created_at ON bookings (created_at);
