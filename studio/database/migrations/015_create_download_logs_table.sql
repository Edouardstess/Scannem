CREATE TABLE download_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    gallery_id INT UNSIGNED NOT NULL,
    photo_id INT UNSIGNED NULL,
    token_id INT UNSIGNED NULL,
    kind VARCHAR(20) NOT NULL DEFAULT 'single',
    photo_count INT UNSIGNED NOT NULL DEFAULT 1,
    bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_download_logs_gallery FOREIGN KEY (gallery_id) REFERENCES galleries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_download_logs_gallery_id ON download_logs (gallery_id);
CREATE INDEX idx_download_logs_photo_id ON download_logs (photo_id);
CREATE INDEX idx_download_logs_created_at ON download_logs (created_at);
