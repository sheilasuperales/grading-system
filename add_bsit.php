<?php
require_once 'config.php';

try {
    $db = getDB();
    
    // Add BSIT course
    $stmt = $db->prepare("INSERT INTO courses (course_code, course_name, description) VALUES (?, ?, ?)");
    $stmt->execute(['BSIT', 'Bachelor of Science in Information Technology', 'A four-year degree program that focuses on utilizing computer technology']);
    $course_id = $db->lastInsertId();
    
    // Add BSIT subjects
    $subjects = [
        // First Year - First Semester
        ['IT101', 'Introduction to Information Technology', 'Basic concepts and fundamentals of IT', 3, 1, 1],
        ['IT102', 'Computer Programming 1', 'Introduction to programming concepts', 3, 1, 1],
        ['GE101', 'Mathematics in the Modern World', 'Mathematical concepts', 3, 1, 1],
        ['GE102', 'Purposive Communication', 'Effective communication skills', 3, 1, 1],
        ['PE1', 'Physical Education 1', 'Basic physical fitness', 2, 1, 1],
        
        // First Year - Second Semester
        ['IT103', 'Computer Programming 2', 'Advanced programming concepts', 3, 1, 2],
        ['IT104', 'Platform Technologies', 'Computing platforms', 3, 1, 2],
        ['GE103', 'Art Appreciation', 'Understanding various art forms', 3, 1, 2],
        ['PE2', 'Physical Education 2', 'Sports and games', 2, 1, 2],
        
        // Second Year - First Semester
        ['IT201', 'Data Structures and Algorithms', 'Fundamental data structures', 3, 2, 1],
        ['IT202', 'Information Management', 'Database concepts', 3, 2, 1],
        ['IT203', 'Object-Oriented Programming', 'OOP concepts and applications', 3, 2, 1],
        ['PE3', 'Physical Education 3', 'Individual and dual sports', 2, 2, 1],
        
        // Second Year - Second Semester
        ['IT204', 'Web Development', 'Web technologies', 3, 2, 2],
        ['IT205', 'Systems Analysis and Design', 'Software development', 3, 2, 2],
        ['IT206', 'Networking 1', 'Basic networking concepts', 3, 2, 2],
        ['PE4', 'Physical Education 4', 'Team sports', 2, 2, 2],
        
        // Third Year - First Semester
        ['IT301', 'Mobile Development', 'Mobile app development', 3, 3, 1],
        ['IT302', 'Information Security', 'Security concepts', 3, 3, 1],
        ['IT303', 'Software Engineering', 'Software development lifecycle', 3, 3, 1],
        ['IT304', 'Web Systems', 'Advanced web development', 3, 3, 1],
        
        // Third Year - Second Semester
        ['IT305', 'Cloud Computing', 'Cloud technologies', 3, 3, 2],
        ['IT306', 'IT Project Management', 'Project management', 3, 3, 2],
        ['IT307', 'Systems Integration', 'System integration methods', 3, 3, 2],
        ['IT308', 'Research Methods', 'IT research methodology', 3, 3, 2],
        
        // Fourth Year - First Semester
        ['IT401', 'Capstone Project 1', 'Project planning and development', 3, 4, 1],
        ['IT402', 'IT Quality Assurance', 'Software testing', 3, 4, 1],
        ['IT403', 'Professional Ethics', 'IT ethics and practices', 3, 4, 1],
        
        // Fourth Year - Second Semester
        ['IT404', 'Capstone Project 2', 'Project implementation', 3, 4, 2],
        ['IT405', 'Practicum', 'Industry internship', 6, 4, 2]
    ];
    
    $stmt = $db->prepare("INSERT INTO subjects (course_id, subject_code, subject_name, description, units, year_level, semester) VALUES (?, ?, ?, ?, ?, ?, ?)");
    
    foreach ($subjects as $subject) {
        $stmt->execute([
            $course_id,
            $subject[0], // subject_code
            $subject[1], // subject_name
            $subject[2], // description
            $subject[3], // units
            $subject[4], // year_level
            $subject[5]  // semester
        ]);
    }
    
    echo "Successfully added BSIT course and all subjects!";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
} 