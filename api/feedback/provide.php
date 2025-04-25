<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        isset($data->submission_id) && 
        isset($data->teacher_id) && 
        isset($data->feedback_text) &&
        isset($data->points)
    ) {
        $submission_id = mysqli_real_escape_string($conn, $data->submission_id);
        $teacher_id = mysqli_real_escape_string($conn, $data->teacher_id);
        $feedback_text = mysqli_real_escape_string($conn, $data->feedback_text);
        $points = mysqli_real_escape_string($conn, $data->points);
        
        // Check if feedback already exists
        $check_query = "SELECT id FROM feedback WHERE submission_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $submission_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing feedback
            $query = "UPDATE feedback SET feedback_text = ?, points = ? WHERE submission_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("sii", $feedback_text, $points, $submission_id);
        } else {
            // Create new feedback
            $query = "INSERT INTO feedback (submission_id, teacher_id, feedback_text, points) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iisi", $submission_id, $teacher_id, $feedback_text, $points);
        }
        
        if ($stmt->execute()) {
            // Notify student about new feedback (you can implement email notifications here)
            echo json_encode([
                'status' => 'success',
                'message' => 'Feedback provided successfully'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to provide feedback'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'All fields are required'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?> 