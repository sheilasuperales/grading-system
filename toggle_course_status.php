<?php
session_start();
require_once 'config.php';

// Ensure only instructor can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'instructor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$course_id = $data['course_id'] ?? null;

if (!$course_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Course ID is required']);
    exit();
}

try {
    $db = getDB();
    
    // First check if the course belongs to the instructor
    $stmt = $db->prepare("SELECT status FROM courses WHERE id = ? AND instructor_id = ?");
    $stmt->execute([$course_id, $_SESSION['user_id']]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Course not found or access denied']);
        exit();
    }
    
    // Toggle status
    $new_status = $course['status'] === 'active' ? 'inactive' : 'active';
    
    // Update course status
    $stmt = $db->prepare("UPDATE courses SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $course_id]);
    
    // Log the status change
    $stmt = $db->prepare("
        INSERT INTO activity_log (user_id, action, entity_type, entity_id, details)
        VALUES (?, 'update', 'course', ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $course_id,
        json_encode(['status' => $new_status])
    ]);
    
    echo json_encode([
        'success' => true,
        'new_status' => $new_status,
        'message' => 'Course status updated successfully'
    ]);
    
} catch (Exception $e) {
    error_log("Error updating course status: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating course status'
    ]);
}
?> 