<?php
// db.php - database connection and helper functions for the grading system.

define('DB_FILE', __DIR__.'/grading_system.sqlite');

function getDB() {
    static $db;
    if ($db === null) {
        $isNew = !file_exists(DB_FILE);
        $db = new PDO('sqlite:'.DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        if ($isNew) {
            initializeDatabase($db);
        }
    }
    return $db;
}

// Initialize database schema on a new database file
function initializeDatabase(PDO $db) {
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            role TEXT NOT NULL CHECK(role IN ('student','instructor','admin')),
            fullname TEXT DEFAULT '',
            email TEXT DEFAULT ''
        );
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS grades (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            instructor_id INTEGER NOT NULL,
            subject TEXT NOT NULL,
            grade REAL NOT NULL CHECK (grade >= 0 AND grade <= 100),
            submitted INTEGER NOT NULL DEFAULT 0,
            FOREIGN KEY(student_id) REFERENCES users(id),
            FOREIGN KEY(instructor_id) REFERENCES users(id)
        );
    ");
}

// Helper to escape output for HTML
function e($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// Helper to hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Helper to verify password hash
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

?>
