-- Additional tables for enhanced elections management
CREATE TABLE IF NOT EXISTS election_positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    position_name VARCHAR(100) NOT NULL,
    description TEXT,
    max_candidates INT DEFAULT 5,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    UNIQUE KEY unique_position (group_id, position_name)
);

CREATE TABLE IF NOT EXISTS election_voters (
    id INT PRIMARY KEY AUTO_INCREMENT,
    election_id INT NOT NULL,
    member_id INT NOT NULL,
    has_voted BOOLEAN DEFAULT FALSE,
    voted_at TIMESTAMP NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE,
    FOREIGN KEY (member_id) REFERENCES members(id) ON DELETE CASCADE,
    UNIQUE KEY unique_voter (election_id, member_id)
);

ALTER TABLE elections ADD COLUMN IF NOT EXISTS max_votes_per_voter INT DEFAULT 1;
ALTER TABLE elections ADD COLUMN IF NOT EXISTS is_anonymous BOOLEAN DEFAULT TRUE;
ALTER TABLE elections ADD COLUMN IF NOT EXISTS results_published BOOLEAN DEFAULT FALSE;
ALTER TABLE elections ADD COLUMN IF NOT EXISTS results_published_at TIMESTAMP NULL;
ALTER TABLE candidates ADD COLUMN IF NOT EXISTS party VARCHAR(100);
ALTER TABLE candidates ADD COLUMN IF NOT EXISTS slogan TEXT;
ALTER TABLE candidates ADD COLUMN IF NOT EXISTS photo VARCHAR(500);
ALTER TABLE candidates ADD COLUMN IF NOT EXISTS order_position INT DEFAULT 0;