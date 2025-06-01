-- Create login attempts table
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    username VARCHAR(50),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, timestamp),
    INDEX idx_username (username)
);

-- Add security-related columns to activity_log
ALTER TABLE activity_log
ADD COLUMN ip_address VARCHAR(45) AFTER details,
ADD COLUMN user_agent VARCHAR(255) AFTER ip_address;

-- Create security_logs table for security-specific events
CREATE TABLE IF NOT EXISTS security_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    event_type ENUM('warning', 'error', 'critical') NOT NULL,
    user_id INT,
    ip_address VARCHAR(45),
    description TEXT,
    additional_data JSON,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_timestamp (timestamp)
);

-- Create user_sessions table
CREATE TABLE IF NOT EXISTS user_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_session (session_id),
    INDEX idx_user_session (user_id, session_id)
);

-- Add last_password_change to users table
ALTER TABLE users
ADD COLUMN last_password_change TIMESTAMP NULL DEFAULT NULL,
ADD COLUMN password_reset_token VARCHAR(100) NULL,
ADD COLUMN password_reset_expires TIMESTAMP NULL,
ADD COLUMN failed_login_attempts INT DEFAULT 0,
ADD COLUMN last_failed_login TIMESTAMP NULL,
ADD COLUMN account_locked_until TIMESTAMP NULL;

-- Create password_history table
CREATE TABLE IF NOT EXISTS password_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    changed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_time (user_id, changed_at)
);

-- Add indexes for security-related queries
CREATE INDEX idx_user_status ON users(id, is_active);
CREATE INDEX idx_login_attempts ON users(username, failed_login_attempts);
CREATE INDEX idx_password_reset ON users(password_reset_token, password_reset_expires); 