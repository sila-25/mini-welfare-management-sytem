-- Additional tables for enhanced investment management
CREATE TABLE IF NOT EXISTS investment_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    investment_id INT NOT NULL,
    transaction_type ENUM('purchase', 'sale', 'dividend', 'expense', 'valuation') NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    transaction_date DATE NOT NULL,
    description TEXT,
    reference_number VARCHAR(100),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_investment (investment_id),
    INDEX idx_date (transaction_date)
);

CREATE TABLE IF NOT EXISTS investment_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    investment_id INT NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    document_type VARCHAR(50),
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (investment_id) REFERENCES investments(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

ALTER TABLE investments ADD COLUMN IF NOT EXISTS location VARCHAR(255);
ALTER TABLE investments ADD COLUMN IF NOT EXISTS ownership_percentage DECIMAL(5,2) DEFAULT 100;
ALTER TABLE investments ADD COLUMN IF NOT EXISTS last_valuation_date DATE;
ALTER TABLE investments ADD COLUMN IF NOT EXISTS last_valuation_amount DECIMAL(12,2);
ALTER TABLE investments ADD COLUMN IF NOT EXISTS notes TEXT;