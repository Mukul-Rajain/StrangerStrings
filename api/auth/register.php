<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Access-Control-Allow-Headers, Content-Type, Access-Control-Allow-Methods, Authorization, X-Requested-With');

require_once '../../config/database.php';

$data = json_decode(file_get_contents("php://input"));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (
        isset($data->name) && 
        isset($data->email) && 
        isset($data->password) &&
        isset($data->role)
    ) {
        $name = mysqli_real_escape_string($conn, $data->name);
        $email = mysqli_real_escape_string($conn, $data->email);
        $password = mysqli_real_escape_string($conn, $data->password);
        $role = mysqli_real_escape_string($conn, $data->role);
        
        // Check if email already exists
        $check_query = "SELECT id FROM users WHERE email = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Email already exists'
            ]);
            exit();
        }
        
        // Insert new user
        $query = "INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $hashed_password = md5($password);
        $stmt->bind_param("ssss", $name, $email, $hashed_password, $role);
        
        if ($stmt->execute()) {
            echo json_encode([
                'status' => 'success',
                'message' => 'User registered successfully'
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Failed to register user'
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