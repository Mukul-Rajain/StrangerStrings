<?php
require_once '../../includes/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid request method');
}

$data = json_decode(file_get_contents("php://input"));

if (!isset($data->assignment_id, $data->student_id, $data->reason, $data->requested_date)) {
    send_json_response('error', 'All fields are required');
}

$assignment_id = (int)$data->assignment_id;
$student_id = (int)$data->student_id;
$reason = sanitize_input($data->reason);
$requested_date = sanitize_input($data->requested_date);

// Check if assignment exists and is not past due date
$check_query = "SELECT due_date FROM assignments WHERE id = ? AND due_date > NOW()";
$check_stmt = $conn->prepare($check_query);
$check_stmt->bind_param("i", $assignment_id);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows === 0) {
    send_json_response('error', 'Assignment not found or past due date');
}

// Check if student already has a pending request
$check_request = "SELECT id FROM extension_requests 
                 WHERE assignment_id = ? AND student_id = ? AND status = 'pending'";
$check_req_stmt = $conn->prepare($check_request);
$check_req_stmt->bind_param("ii", $assignment_id, $student_id);
$check_req_stmt->execute();

if ($check_req_stmt->get_result()->num_rows > 0) {
    send_json_response('error', 'You already have a pending extension request');
}

// Insert the extension request
$query = "INSERT INTO extension_requests (assignment_id, student_id, reason, requested_date, status) 
         VALUES (?, ?, ?, ?, 'pending')";
$stmt = $conn->prepare($query);
$stmt->bind_param("iiss", $assignment_id, $student_id, $reason, $requested_date);

if ($stmt->execute()) {
    send_json_response('success', 'Extension request submitted successfully');
} else {
    send_json_response('error', 'Failed to submit extension request');
}
?> 