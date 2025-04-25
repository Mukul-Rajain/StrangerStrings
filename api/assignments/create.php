<?php
require_once '../../includes/init.php';

$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json_response('error', 'Invalid request method');
}

if (!isset($data->title, $data->description, $data->teacher_id, $data->due_date)) {
    send_json_response('error', 'All fields are required');
}

$title = sanitize_input($data->title);
$description = sanitize_input($data->description);
$teacher_id = (int)$data->teacher_id;
$due_date = sanitize_input($data->due_date);

$query = "INSERT INTO assignments (title, description, teacher_id, due_date) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssis", $title, $description, $teacher_id, $due_date);

if ($stmt->execute()) {
    send_json_response('success', 'Assignment created successfully');
} else {
    send_json_response('error', 'Failed to create assignment');
}
?> 