<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');     // Replace with your MySQL username
define('DB_PASS', '');         // Replace with your MySQL password
define('DB_NAME', 'student_management');

// Create connection
try {
    $conn = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
    );
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?> 