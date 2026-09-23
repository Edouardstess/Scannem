-- Access links.
--
-- token_hash is what lookups use: SHA-256 of the raw token, unique and
-- indexed. The raw value is never stored in the clear.
--
-- token_cipher holds the same raw token encrypted with APP_KEY, which lives in
-- .env and not in the database. It exists so the photographer can re-copy a
-- link months later without invalidating the one already sent to the client.
-- A stolen database alone still yields no working links; an attacker would
-- need the application's key file as well.
CREATE TABLE gallery_tokens (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    gallery_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    token_cipher VARCHAR(255) NULL,
    token_type VARCHAR(16) NOT NULL,
    expires_at DATETIME NULL,
    revoked_at DATETIME NULL,
    last_used_at DATETIME NULL,
    use_count INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    CONSTRAINT uq_gallery_tokens_hash UNIQUE (token_hash),
    CONSTRAINT fk_gallery_tokens_gallery FOREIGN KEY (gallery_id) REFERENCES galleries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_gallery_tokens_gallery_id ON gallery_tokens (gallery_id);
CREATE INDEX idx_gallery_tokens_type ON gallery_tokens (token_type);
