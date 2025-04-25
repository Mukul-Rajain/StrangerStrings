<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        isset($data->assignment_id) && 
        isset($data->student_id) && 
        (isset($data->submission_text) || isset($_FILES['file']))
    ) {
        $assignment_id = mysqli_real_escape_string($conn, $data->assignment_id);
        $student_id = mysqli_real_escape_string($conn, $data->student_id);
        $submission_text = isset($data->submission_text) ? mysqli_real_escape_string($conn, $data->submission_text) : '';
        $file_path = '';
        
        // Handle file upload if present
        if (isset($_FILES['file'])) {
            $upload_dir = '../../uploads/submissions/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = time() . '_' . $_FILES['file']['name'];
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
                $file_path = 'uploads/submissions/' . $file_name;
            }
        }
        
        // Check if submission already exists
        $check_query = "SELECT id FROM submissions WHERE assignment_id = ? AND student_id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("ii", $assignment_id, $student_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            // Update existing submission
            $query = "UPDATE submissions SET submission_text = ?, file_path = ?, submitted_at = CURRENT_TIMESTAMP WHERE assignment_id = ? AND student_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssii", $submission_text, $file_path, $assignment_id, $student_id);
        } else {
            // Create new submission
            $query = "INSERT INTO submissions (assignment_id, student_id, submission_text, file_path) VALUES (?, ?, ?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("iiss", $assignment_id, $student_id, $submission_text, $file_path);
        }
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Assignment submitted successfully'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to submit assignment'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Required fields are missing'
        ]);
    }
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?> 