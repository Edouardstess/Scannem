CREATE TABLE clients (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(190) NULL,
    phone VARCHAR(40) NULL,
    company VARCHAR(150) NULL,
    notes TEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Not UNIQUE: two people in the same family legitimately share an address,
-- and a client record without an e-mail is allowed.
CREATE INDEX idx_clients_email ON clients (email);
CREATE INDEX idx_clients_last_name ON clients (last_name);
