CREATE TABLE portfolio_categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(140) NOT NULL,
    description TEXT NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    CONSTRAINT uq_portfolio_categories_slug UNIQUE (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_portfolio_categories_status ON portfolio_categories (status);

-- Portfolio images are public by design, so unlike client photos they live
-- under public/assets/portfolio. image_path is relative to that directory.
CREATE TABLE portfolio_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    title VARCHAR(190) NOT NULL,
    description TEXT NULL,
    image_path VARCHAR(255) NOT NULL,
    thumbnail_path VARCHAR(255) NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    featured TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    CONSTRAINT fk_portfolio_items_category FOREIGN KEY (category_id) REFERENCES portfolio_categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_portfolio_items_category_id ON portfolio_items (category_id);
CREATE INDEX idx_portfolio_items_status ON portfolio_items (status);
CREATE INDEX idx_portfolio_items_featured ON portfolio_items (featured);
