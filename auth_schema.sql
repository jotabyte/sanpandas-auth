-- San Pandas Language Center: Global Authentication MySQL Schema
-- Deploy this to the 'auth_sanpandas_db' database

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    role VARCHAR(50) DEFAULT 'student'
);

CREATE TABLE IF NOT EXISTS magic_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed Initial Superadmin
INSERT IGNORE INTO users (email, role) VALUES ('superadmin@sanpandas.com', 'admin');
