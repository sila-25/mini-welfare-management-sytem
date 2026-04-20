-- Create database
CREATE DATABASE IF NOT EXISTS careway_db;
USE careway_db;

-- Groups table (tenants)
CREATE TABLE groups (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_name VARCHAR(100) NOT NULL,
    group_code VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    address TEXT,
    subscription_plan ENUM('monthly', 'quarterly', 'biannual', 'annual') DEFAULT 'monthly',
    subscription_status ENUM('active', 'expired', 'suspended') DEFAULT 'active',
    subscription_start DATE,
    subscription_end DATE,
    max_members INT DEFAULT 100,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (subscription_status),
    INDEX idx_group_code (group_code)
);

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    role ENUM('super_admin', 'chairperson', 'treasurer', 'secretary', 'vice_secretary', 'member') DEFAULT 'member',
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    INDEX idx_group (group_id),
    INDEX idx_email (email),
    INDEX idx_role (role),
    UNIQUE KEY unique_email_group (email, group_id)
);

-- Members table
CREATE TABLE members (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    member_number VARCHAR(20) UNIQUE NOT NULL,
    user_id INT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100),
    phone VARCHAR(20),
    id_number VARCHAR(20),
    date_of_birth DATE,
    gender ENUM('male', 'female', 'other'),
    occupation VARCHAR(100),
    physical_address TEXT,
    emergency_contact VARCHAR(100),
    emergency_phone VARCHAR(20),
    join_date DATE DEFAULT CURRENT_DATE,
    status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_group (group_id),
    INDEX idx_member_number (member_number),
    INDEX idx_status (status)
);

-- Contributions table
CREATE TABLE contributions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    member_id INT NOT NULL,
    contribution_type ENUM('monthly', 'welfare', 'special', 'registration') DEFAULT 'monthly',
    amount DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'mobile_money', 'cheque') DEFAULT 'cash',
    reference_number VARCHAR(100),
    receipt_number VARCHAR(50) UNIQUE,
    notes TEXT,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_group (group_id),
    INDEX idx_member (member_id),
    INDEX idx_date (payment_date),
    INDEX idx_type (contribution_type)
);

-- Loans table
CREATE TABLE loans (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    member_id INT NOT NULL,
    loan_number VARCHAR(50) UNIQUE NOT NULL,
    principal_amount DECIMAL(10,2) NOT NULL,
    interest_rate DECIMAL(5,2) DEFAULT 0,
    total_repayable DECIMAL(10,2) NOT NULL,
    duration_months INT NOT NULL,
    purpose TEXT,
    status ENUM('pending', 'approved', 'disbursed', 'rejected', 'completed', 'defaulted') DEFAULT 'pending',
    application_date DATE NOT NULL,
    approval_date DATE,
    disbursement_date DATE,
    due_date DATE,
    approved_by INT,
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id),
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_group (group_id),
    INDEX idx_member (member_id),
    INDEX idx_status (status),
    INDEX idx_loan_number (loan_number)
);

-- Loan repayments table
CREATE TABLE loan_repayments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    loan_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    principal_paid DECIMAL(10,2) NOT NULL,
    interest_paid DECIMAL(10,2) NOT NULL,
    payment_date DATE NOT NULL,
    payment_method ENUM('cash', 'bank_transfer', 'mobile_money') DEFAULT 'cash',
    receipt_number VARCHAR(50),
    recorded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (loan_id) REFERENCES loans(id) ON DELETE CASCADE,
    FOREIGN KEY (recorded_by) REFERENCES users(id),
    INDEX idx_loan (loan_id)
);

-- Meetings table
CREATE TABLE meetings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    meeting_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    venue VARCHAR(200),
    agenda TEXT,
    minutes TEXT,
    status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_group (group_id),
    INDEX idx_date (meeting_date)
);

-- Meeting attendance table
CREATE TABLE meeting_attendance (
    id INT PRIMARY KEY AUTO_INCREMENT,
    meeting_id INT NOT NULL,
    member_id INT NOT NULL,
    status ENUM('present', 'absent', 'excused') DEFAULT 'absent',
    check_in_time TIME,
    notes TEXT,
    marked_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id),
    UNIQUE KEY unique_attendance (meeting_id, member_id)
);

-- Elections table
CREATE TABLE elections (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    position VARCHAR(100) NOT NULL,
    election_date DATE NOT NULL,
    start_time TIME,
    end_time TIME,
    status ENUM('upcoming', 'ongoing', 'completed', 'cancelled') DEFAULT 'upcoming',
    voting_method ENUM('online', 'manual', 'both') DEFAULT 'both',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Candidates table
CREATE TABLE candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    election_id INT NOT NULL,
    member_id INT NOT NULL,
    manifesto TEXT,
    status ENUM('nominated', 'approved', 'rejected') DEFAULT 'nominated',
    votes INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_candidate (election_id, member_id)
);

-- Votes table
CREATE TABLE votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    election_id INT NOT NULL,
    candidate_id INT NOT NULL,
    voter_id INT NOT NULL,
    vote_hash VARCHAR(255) UNIQUE NOT NULL,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (voter_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_vote (election_id, voter_id)
);

-- Accounts/Banks table
CREATE TABLE accounts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    account_name VARCHAR(100) NOT NULL,
    account_type ENUM('bank', 'cash', 'mobile_money') DEFAULT 'bank',
    bank_name VARCHAR(100),
    account_number VARCHAR(50),
    opening_balance DECIMAL(10,2) DEFAULT 0,
    current_balance DECIMAL(10,2) DEFAULT 0,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

-- Transactions table
CREATE TABLE transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    account_id INT NOT NULL,
    transaction_type ENUM('income', 'expense', 'transfer') NOT NULL,
    category VARCHAR(50),
    amount DECIMAL(10,2) NOT NULL,
    description TEXT,
    transaction_date DATE NOT NULL,
    reference_number VARCHAR(100),
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_group (group_id),
    INDEX idx_date (transaction_date)
);

-- Investments table
CREATE TABLE investments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    investment_name VARCHAR(100) NOT NULL,
    investment_type ENUM('land', 'shares', 'bonds', 'business', 'other') NOT NULL,
    amount_invested DECIMAL(10,2) NOT NULL,
    current_value DECIMAL(10,2),
    expected_returns DECIMAL(10,2),
    purchase_date DATE,
    description TEXT,
    status ENUM('active', 'liquidated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE
);

-- Audit logs table
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    group_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_data JSON,
    new_data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_group (group_id),
    INDEX idx_created (created_at)
);

-- Subscriptions table
CREATE TABLE subscriptions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    plan ENUM('monthly', 'quarterly', 'biannual', 'annual') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
    payment_reference VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    INDEX idx_group (group_id),
    INDEX idx_dates (start_date, end_date)
);

-- Insert default super admin
INSERT INTO users (username, email, password_hash, first_name, role) 
VALUES ('superadmin', 'admin@careway.com', '$2y$10$YourHashedPasswordHere', 'System', 'super_admin');

-- Insert sample group
INSERT INTO groups (group_name, group_code, email, subscription_plan, subscription_status, subscription_start, subscription_end) 
VALUES ('Demo Welfare Group', 'DEMO001', 'demo@careway.com', 'monthly', 'active', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 MONTH));