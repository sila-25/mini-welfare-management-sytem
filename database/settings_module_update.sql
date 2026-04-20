-- Group settings table
CREATE TABLE IF NOT EXISTS group_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT,
    setting_type ENUM('text', 'number', 'boolean', 'json', 'array') DEFAULT 'text',
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    UNIQUE KEY unique_setting (group_id, setting_key),
    INDEX idx_key (setting_key)
);

-- Positions table for dynamic roles
CREATE TABLE IF NOT EXISTS group_positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    position_name VARCHAR(100) NOT NULL,
    description TEXT,
    hierarchy_level INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    UNIQUE KEY unique_position (group_id, position_name)
);

-- Position permissions mapping
CREATE TABLE IF NOT EXISTS position_permissions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    position_id INT NOT NULL,
    permission_key VARCHAR(100) NOT NULL,
    can_view BOOLEAN DEFAULT FALSE,
    can_create BOOLEAN DEFAULT FALSE,
    can_edit BOOLEAN DEFAULT FALSE,
    can_delete BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (position_id) REFERENCES group_positions(id) ON DELETE CASCADE,
    UNIQUE KEY unique_permission (position_id, permission_key)
);

-- Subscription plans
CREATE TABLE IF NOT EXISTS subscription_plans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    plan_name VARCHAR(50) NOT NULL,
    plan_code VARCHAR(20) UNIQUE NOT NULL,
    duration_days INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    features JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Payment transactions
CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    subscription_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50),
    transaction_id VARCHAR(100) UNIQUE,
    payment_date DATETIME,
    status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
    receipt_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (subscription_id) REFERENCES subscriptions(id),
    INDEX idx_group (group_id),
    INDEX idx_status (status)
);

-- Insert default subscription plans
INSERT INTO subscription_plans (plan_name, plan_code, duration_days, price, features, is_active) VALUES
('Monthly', 'monthly', 30, 29.99, '{"max_members": 100, "max_loans": 50, "reports": true, "support": "email"}', 1),
('Quarterly', 'quarterly', 90, 79.99, '{"max_members": 250, "max_loans": 150, "reports": true, "support": "priority_email"}', 1),
('Biannual', 'biannual', 180, 149.99, '{"max_members": 500, "max_loans": 300, "reports": true, "support": "phone"}', 1),
('Annual', 'annual', 365, 299.99, '{"max_members": 1000, "max_loans": 500, "reports": true, "support": "dedicated"}', 1);

-- Insert default positions
INSERT INTO group_positions (group_id, position_name, description, hierarchy_level) VALUES
(1, 'Chairperson', 'Overall group leader and decision maker', 1),
(1, 'Vice Chairperson', 'Assists the chairperson', 2),
(1, 'Secretary', 'Manages records and communications', 3),
(1, 'Vice Secretary', 'Assists the secretary', 4),
(1, 'Treasurer', 'Manages finances', 5),
(1, 'Member', 'Regular group member', 10);