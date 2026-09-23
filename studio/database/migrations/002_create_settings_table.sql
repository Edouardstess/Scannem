-- Site configuration the photographer edits from /admin/settings: studio name,
-- contact details, hero copy, colours. Kept in the database rather than in
-- config files so that changing them never requires touching code or FTP.
CREATE TABLE settings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT NULL,
    setting_group VARCHAR(50) NOT NULL DEFAULT 'general',
    setting_type VARCHAR(20) NOT NULL DEFAULT 'string',
    updated_at DATETIME NULL,
    CONSTRAINT uq_settings_key UNIQUE (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_settings_group ON settings (setting_group);
