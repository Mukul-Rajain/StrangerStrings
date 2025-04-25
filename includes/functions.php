<?php
// Security functions
function sanitize_input($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

function validate_user_role($role) {
    return in_array($role, ['student', 'teacher', 'admin']);
}

// Response functions
function send_json_response($status, $message, $data = null) {
    $response = [
        'status' => $status,
        'message' => $message
    ];
    
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// File handling functions
function handle_file_upload($file, $directory = 'uploads/submissions/') {
    $upload_dir = __DIR__ . '/../public/' . $directory;
    
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $file_name = time() . '_' . basename($file['name']);
    $file_path = $upload_dir . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return $directory . $file_name;
    }
    
    return false;
}
?> 