-- Derived renditions (THUMBNAIL, PREVIEW). Watermarking is applied here, so
-- the original file on disk is never modified.
CREATE TABLE photo_variants (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    photo_id INT UNSIGNED NOT NULL,
    variant_type VARCHAR(20) NOT NULL,
    storage_path VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100) NOT NULL DEFAULT 'image/jpeg',
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    CONSTRAINT uq_photo_variant UNIQUE (photo_id, variant_type),
    CONSTRAINT fk_photo_variants_photo FOREIGN KEY (photo_id) REFERENCES photos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_photo_variants_photo_id ON photo_variants (photo_id);
