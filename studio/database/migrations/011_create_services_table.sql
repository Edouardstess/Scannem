CREATE TABLE services (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(190) NOT NULL,
    slug VARCHAR(190) NOT NULL,
    summary VARCHAR(255) NULL,
    description TEXT NULL,
    price_from DECIMAL(10,2) NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'EUR',
    duration VARCHAR(80) NULL,
    deliverables TEXT NULL,
    image_path VARCHAR(255) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    CONSTRAINT uq_services_slug UNIQUE (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_services_status ON services (status);
