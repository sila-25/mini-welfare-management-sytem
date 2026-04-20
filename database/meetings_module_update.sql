-- Additional tables for enhanced meeting management
CREATE TABLE IF NOT EXISTS meeting_agenda_items (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    meeting_id INT NOT NULL,
    item_order INT DEFAULT 0,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    presenter VARCHAR(100),
    duration_minutes INT DEFAULT 0,
    status ENUM('pending', 'discussed', 'deferred') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    INDEX idx_meeting (meeting_id)
);

CREATE TABLE IF NOT EXISTS meeting_decisions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    meeting_id INT NOT NULL,
    agenda_item_id INT,
    decision TEXT NOT NULL,
    action_items TEXT,
    responsible_person INT,
    deadline DATE,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (agenda_item_id) REFERENCES meeting_agenda_items(id) ON DELETE SET NULL,
    FOREIGN KEY (responsible_person) REFERENCES members(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS meeting_minutes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    group_id INT NOT NULL,
    meeting_id INT NOT NULL,
    section VARCHAR(100),
    content TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY (meeting_id) REFERENCES meetings(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id)
);

ALTER TABLE meetings ADD COLUMN IF NOT EXISTS meeting_type ENUM('regular', 'special', 'annual', 'emergency') DEFAULT 'regular';
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS expected_attendance INT DEFAULT 0;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS actual_attendance INT DEFAULT 0;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS quorum INT DEFAULT 0;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS location_link VARCHAR(500);
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS online_link VARCHAR(500);
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS recorded_by INT;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS minutes_approved BOOLEAN DEFAULT FALSE;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS minutes_approved_at TIMESTAMP NULL;
ALTER TABLE meetings ADD COLUMN IF NOT EXISTS minutes_approved_by INT;
ALTER TABLE meeting_attendance ADD COLUMN IF NOT EXISTS arrival_time TIME;
ALTER TABLE meeting_attendance ADD COLUMN IF NOT EXISTS departure_time TIME;
ALTER TABLE meeting_attendance ADD COLUMN IF NOT EXISTS signature VARCHAR(255);
ALTER TABLE meeting_attendance ADD COLUMN IF NOT EXISTS notes TEXT;