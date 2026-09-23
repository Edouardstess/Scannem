-- A gallery belongs to an event. Its two access links live in gallery_tokens,
-- not here, so a link can be revoked and regenerated without touching the
-- gallery row.
CREATE TABLE galleries (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_id INT UNSIGNED NOT NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    -- No foreign key: photos.gallery_id already points back here, and a
    -- circular constraint cannot be created in one pass on either engine.
    -- PhotoRepository::delete() clears this column instead.
    cover_photo_id INT UNSIGNED NULL,
    password_hash VARCHAR(255) NULL,
    watermark_enabled TINYINT(1) NOT NULL DEFAULT 0,
    download_enabled TINYINT(1) NOT NULL DEFAULT 0,
    selection_enabled TINYINT(1) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'draft',
    expires_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    CONSTRAINT fk_galleries_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_galleries_event_id ON galleries (event_id);
CREATE INDEX idx_galleries_status ON galleries (status);
CREATE INDEX idx_galleries_expires_at ON galleries (expires_at);
