<?php
require_once '../../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // List extension requests
    $teacher_id = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
    
    if (!$teacher_id) {
        send_json_response('error', 'Teacher ID is required');
    }
    
    $query = "SELECT er.*, 
              a.title as assignment_title, 
              a.due_date as original_due_date,
              u.name as student_name
              FROM extension_requests er
              JOIN assignments a ON er.assignment_id = a.id
              JOIN users u ON er.student_id = u.id
              WHERE a.teacher_id = ?
              ORDER BY er.created_at DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $teacher_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
    
    send_json_response('success', 'Extension requests retrieved successfully', $requests);
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Update extension request status
    $data = json_decode(file_get_contents("php://input"));
    
    if (!isset($data->request_id, $data->status, $data->teacher_id)) {
        send_json_response('error', 'All fields are required');
    }
    
    $request_id = (int)$data->request_id;
    $status = sanitize_input($data->status);
    $teacher_id = (int)$data->teacher_id;
    
    // Verify teacher owns the assignment
    $verify_query = "SELECT a.teacher_id 
                    FROM extension_requests er
                    JOIN assignments a ON er.assignment_id = a.id
                    WHERE er.id = ?";
    $verify_stmt = $conn->prepare($verify_query);
    $verify_stmt->bind_param("i", $request_id);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result()->fetch_assoc();
    
    if (!$verify_result || $verify_result['teacher_id'] !== $teacher_id) {
        send_json_response('error', 'Unauthorized to manage this request');
    }
    
    // Update request status
    $query = "UPDATE extension_requests SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $status, $request_id);
    
    if ($stmt->execute()) {
        // If approved, update assignment due date for this student
        if ($status === 'approved') {
            $update_query = "UPDATE assignments a
                           JOIN extension_requests er ON a.id = er.assignment_id
                           SET a.extended_due_date = er.requested_date
                           WHERE er.id = ?";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bind_param("i", $request_id);
            $update_stmt->execute();
        }
        
        send_json_response('success', 'Extension request updated successfully');
    } else {
        send_json_response('error', 'Failed to update extension request');
    }
} else {
    send_json_response('error', 'Invalid request method');
}
?> 