-- Client favourites, when the gallery has selection enabled. Scoped by token
-- so two people given the same link do not overwrite each other's choices.
CREATE TABLE photo_selections (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    gallery_id INT UNSIGNED NOT NULL,
    photo_id INT UNSIGNED NOT NULL,
    token_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    CONSTRAINT uq_photo_selection UNIQUE (photo_id, token_id),
    CONSTRAINT fk_photo_selections_gallery FOREIGN KEY (gallery_id) REFERENCES galleries (id) ON DELETE CASCADE,
    CONSTRAINT fk_photo_selections_photo FOREIGN KEY (photo_id) REFERENCES photos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_photo_selections_gallery_id ON photo_selections (gallery_id);
