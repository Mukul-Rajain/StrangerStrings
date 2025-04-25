<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once '../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $role = isset($_GET['role']) ? $_GET['role'] : '';
    $user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    
    if ($role === 'teacher') {
        // Teachers see their own assignments
        $query = "SELECT a.*, COUNT(s.id) as submission_count 
                 FROM assignments a 
                 LEFT JOIN submissions s ON a.id = s.assignment_id 
                 WHERE a.teacher_id = ? 
                 GROUP BY a.id 
                 ORDER BY a.due_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
    } else {
        // Students see all assignments with their submission status
        $query = "SELECT a.*, 
                 CASE WHEN s.id IS NOT NULL THEN 'submitted' ELSE 'pending' END as status,
                 s.submitted_at,
                 f.feedback_text,
                 f.points
                 FROM assignments a 
                 LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
                 LEFT JOIN feedback f ON s.id = f.submission_id
                 ORDER BY a.due_date DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $assignments = [];
    
    while ($row = $result->fetch_assoc()) {
        $assignments[] = $row;
    }
    
    echo json_encode([
        'status' => 'success',
        'data' => $assignments
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?> 