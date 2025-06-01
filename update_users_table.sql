-- Add new columns to users table
ALTER TABLE users
ADD COLUMN course VARCHAR(10) NULL AFTER email,
ADD COLUMN section VARCHAR(5) NULL AFTER course,
ADD COLUMN year_level INT NULL AFTER section;

-- Add indexes for better query performance
CREATE INDEX idx_course ON users(course);
CREATE INDEX idx_year_section ON users(year_level, section);

-- Update existing admin user
UPDATE users SET course = NULL, section = NULL, year_level = NULL WHERE role = 'admin';

-- Add constraints
ALTER TABLE users
ADD CONSTRAINT check_year_level CHECK (year_level >= 1 AND year_level <= 4),
ADD CONSTRAINT check_student_fields 
    CHECK (
        (role = 'student' AND course IS NOT NULL AND section IS NOT NULL AND year_level IS NOT NULL) OR
        (role != 'student')
    ); 