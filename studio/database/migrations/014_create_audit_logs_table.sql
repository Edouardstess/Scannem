-- Append-only trail of privileged and client-facing actions.
-- user_id is NULL for actions performed by a gallery visitor, who has no account.
CREATE TABLE audit_logs (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    gallery_id INT UNSIGNED NULL,
    photo_id INT UNSIGNED NULL,
    action VARCHAR(60) NOT NULL,
    context TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_audit_logs_user_id ON audit_logs (user_id);
CREATE INDEX idx_audit_logs_gallery_id ON audit_logs (gallery_id);
CREATE INDEX idx_audit_logs_action ON audit_logs (action);
CREATE INDEX idx_audit_logs_created_at ON audit_logs (created_at);
