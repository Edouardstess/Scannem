-- One row per gallery opening. The IP is stored to make abuse of a leaked
-- link visible; nothing else about the visitor is collected.
CREATE TABLE gallery_views (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    gallery_id INT UNSIGNED NOT NULL,
    token_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT fk_gallery_views_gallery FOREIGN KEY (gallery_id) REFERENCES galleries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_gallery_views_gallery_id ON gallery_views (gallery_id);
CREATE INDEX idx_gallery_views_created_at ON gallery_views (created_at);
