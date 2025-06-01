-- Add new columns for student details
ALTER TABLE users
ADD COLUMN first_name TEXT NULL AFTER email,
ADD COLUMN last_name TEXT NULL AFTER first_name,
ADD COLUMN middle_name TEXT NULL AFTER last_name,
ADD COLUMN suffix TEXT NULL AFTER middle_name; 