-- Contact form submissions.
CREATE TABLE messages (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(40) NULL,
    subject VARCHAR(190) NULL,
    message TEXT NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL,
    read_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_messages_status ON messages (status);
CREATE INDEX idx_messages_created_at ON messages (created_at);
