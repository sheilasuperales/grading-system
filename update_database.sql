-- Add department field to users table
ALTER TABLE users
ADD COLUMN department VARCHAR(10) NULL AFTER email;

-- Create activity log table if it doesn't exist
CREATE TABLE IF NOT EXISTS activity_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL,
    details TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_timestamp (timestamp),
    INDEX idx_user_action (user_id, action)
);

-- Add role-based constraints
ALTER TABLE users
ADD CONSTRAINT check_instructor_department 
    CHECK (
        (role = 'instructor' AND department IS NOT NULL) OR
        (role != 'instructor')
    );

-- Create departments reference table
CREATE TABLE IF NOT EXISTS departments (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default departments
INSERT INTO departments (code, name) VALUES
('CS', 'Computer Science'),
('IT', 'Information Technology'),
('IS', 'Information Systems'),
('EMC', 'Entertainment and Multimedia Computing')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Add foreign key constraint for department
ALTER TABLE users
ADD CONSTRAINT fk_user_department
FOREIGN KEY (department) REFERENCES departments(code)
ON UPDATE CASCADE
ON DELETE RESTRICT; 