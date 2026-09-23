-- Brute-force and spam throttling. Stored in the database rather than in the
-- session: an attacker controls their own session, so a session counter stops
-- nobody.
CREATE TABLE rate_limits (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    rate_key VARCHAR(190) NOT NULL,
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT uq_rate_limits_key UNIQUE (rate_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_rate_limits_expires_at ON rate_limits (expires_at);
