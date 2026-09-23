-- storage_path is RELATIVE to the private storage root and is never sent to
-- the browser. FileStorage resolves it and refuses anything escaping the root.
CREATE TABLE photos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    gallery_id INT UNSIGNED NOT NULL,
    filename VARCHAR(190) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    orientation VARCHAR(16) NULL,
    taken_at DATETIME NULL,
    checksum CHAR(64) NULL,
    downloadable TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'ready',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    CONSTRAINT fk_photos_gallery FOREIGN KEY (gallery_id) REFERENCES galleries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_photos_gallery_id ON photos (gallery_id);
CREATE INDEX idx_photos_sort ON photos (gallery_id, sort_order);
CREATE INDEX idx_photos_status ON photos (status);
