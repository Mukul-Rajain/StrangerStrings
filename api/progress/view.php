<?php
require_once '../../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    send_json_response('error', 'Invalid request method');
}

$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : 0;

if (!$student_id) {
    send_json_response('error', 'Student ID is required');
}

// Get all assignments and submissions for the student
$query = "SELECT 
    a.id as assignment_id,
    a.title,
    a.description,
    a.due_date,
    s.submitted_at,
    CASE 
        WHEN s.id IS NULL THEN 'pending'
        WHEN s.submitted_at > a.due_date THEN 'late'
        ELSE 'submitted'
    END as status,
    f.points,
    f.feedback_text
    FROM assignments a
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
    LEFT JOIN feedback f ON s.id = f.submission_id
    ORDER BY a.due_date DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$progress = [
    'total_assignments' => 0,
    'completed' => 0,
    'pending' => 0,
    'late' => 0,
    'average_score' => 0,
    'assignments' => []
];

$total_points = 0;
$scored_assignments = 0;

while ($row = $result->fetch_assoc()) {
    $progress['total_assignments']++;
    
    switch ($row['status']) {
        case 'submitted':
            $progress['completed']++;
            break;
        case 'pending':
            $progress['pending']++;
            break;
        case 'late':
            $progress['late']++;
            break;
    }
    
    if ($row['points'] !== null) {
        $total_points += $row['points'];
        $scored_assignments++;
    }
    
    $progress['assignments'][] = $row;
}

$progress['average_score'] = $scored_assignments > 0 ? 
    round($total_points / $scored_assignments, 2) : 0;

send_json_response('success', 'Progress retrieved successfully', $progress);
?> 