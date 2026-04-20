-- Additional tables for enhanced member management
CREATE TABLE IF NOT EXISTS member_documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    member_id INT NOT NULL,
    document_type ENUM('id_card', 'passport', 'driving_license', 'profile_photo', 'other') NOT NULL,
    document_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT,
    mime_type VARCHAR(100),
    uploaded_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_member (member_id)
);

CREATE TABLE IF NOT EXISTS member_family (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    member_id INT NOT NULL,
    relationship ENUM('spouse', 'child', 'parent', 'sibling', 'other') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    date_of_birth DATE,
    phone VARCHAR(20),
    is_dependent BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    INDEX idx_member (member_id)
);

CREATE TABLE IF NOT EXISTS member_notes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    member_id INT NOT NULL,
    note_type ENUM('general', 'complaint', 'achievement', 'warning', 'other') DEFAULT 'general',
    note TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_member (member_id)
);

ALTER TABLE members ADD COLUMN IF NOT EXISTS profile_photo VARCHAR(500);
ALTER TABLE members ADD COLUMN IF NOT EXISTS bio TEXT;
ALTER TABLE members ADD COLUMN IF NOT EXISTS total_contributions DECIMAL(12,2) DEFAULT 0;
ALTER TABLE members ADD COLUMN IF NOT EXISTS total_loans_taken DECIMAL(12,2) DEFAULT 0;
ALTER TABLE members ADD COLUMN IF NOT EXISTS last_contribution_date DATE;