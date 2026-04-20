-- Additional tables for enhanced loan management
CREATE TABLE IF NOT EXISTS loan_guarantors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    loan_id INT NOT NULL,
    guarantor_id INT NOT NULL,
    amount_guaranteed DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    FOREIGN KEY (guarantor_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_loan (loan_id)
);

CREATE TABLE IF NOT EXISTS loan_repayment_schedule (
    id INT PRIMARY KEY AUTO_INCREMENT,
    loan_id INT NOT NULL,
    installment_number INT NOT NULL,
    due_date DATE NOT NULL,
    principal_due DECIMAL(12,2) NOT NULL,
    interest_due DECIMAL(12,2) NOT NULL,
    total_due DECIMAL(12,2) NOT NULL,
    status ENUM('pending', 'paid', 'overdue') DEFAULT 'pending',
    paid_date DATE,
    paid_amount DECIMAL(12,2),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    INDEX idx_loan (loan_id),
    INDEX idx_status (status)
);

ALTER TABLE loans ADD COLUMN IF NOT EXISTS application_reason TEXT;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS rejection_reason TEXT;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS guarantor_required BOOLEAN DEFAULT FALSE;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS processing_fee DECIMAL(10,2) DEFAULT 0;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS late_penalty_rate DECIMAL(5,2) DEFAULT 5;
ALTER TABLE loans ADD COLUMN IF NOT EXISTS repayment_frequency ENUM('weekly', 'monthly', 'quarterly') DEFAULT 'monthly';
ALTER TABLE loans ADD COLUMN IF NOT EXISTS approved_by_name VARCHAR(100);
ALTER TABLE loans ADD COLUMN IF NOT EXISTS disbursed_by_name VARCHAR(100);
ALTER TABLE loans ADD COLUMN IF NOT EXISTS next_payment_date DATE;