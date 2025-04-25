-- Create the database if it doesn't exist
CREATE DATABASE IF NOT EXISTS student_management;
USE student_management;

-- Create students table
CREATE TABLE IF NOT EXISTS students (
    student_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    date_of_birth DATE,
    enrollment_date DATE NOT NULL,
    status ENUM('active', 'inactive', 'graduated') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create courses table
CREATE TABLE IF NOT EXISTS courses (
    course_id INT PRIMARY KEY AUTO_INCREMENT,
    course_code VARCHAR(10) UNIQUE NOT NULL,
    course_name VARCHAR(100) NOT NULL,
    credits INT NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create enrollments table (junction table for students and courses)
CREATE TABLE IF NOT EXISTS enrollments (
    enrollment_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    enrollment_date DATE NOT NULL,
    grade DECIMAL(4,2),
    status ENUM('enrolled', 'completed', 'dropped') DEFAULT 'enrolled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id),
    FOREIGN KEY (course_id) REFERENCES courses(course_id)
);

-- Insert sample data for students
INSERT INTO students (first_name, last_name, email, date_of_birth, enrollment_date) VALUES
('John', 'Doe', 'john.doe@example.com', '2000-05-15', '2023-09-01'),
('Jane', 'Smith', 'jane.smith@example.com', '2001-03-22', '2023-09-01'),
('Michael', 'Johnson', 'michael.j@example.com', '2000-11-30', '2023-09-01');

-- Insert sample data for courses
INSERT INTO courses (course_code, course_name, credits, description) VALUES
('CS101', 'Introduction to Programming', 3, 'Basic programming concepts and algorithms'),
('MATH201', 'Advanced Mathematics', 4, 'Calculus and linear algebra'),
('ENG101', 'English Composition', 3, 'Academic writing and communication skills');

-- Insert sample enrollments
INSERT INTO enrollments (student_id, course_id, enrollment_date) VALUES
(1, 1, '2023-09-01'),
(1, 2, '2023-09-01'),
(2, 1, '2023-09-01'),
(2, 3, '2023-09-01'),
(3, 2, '2023-09-01');

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('student', 'teacher', 'admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Assignments table
CREATE TABLE IF NOT EXISTS assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    teacher_id INT NOT NULL,
    due_date DATETIME NOT NULL,
    extended_due_date DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);

-- Submissions table
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    submission_text TEXT,
    file_path VARCHAR(255),
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

-- Feedback table
CREATE TABLE IF NOT EXISTS feedback (
    id INT AUTO_INCREMENT PRIMARY KEY,
    submission_id INT NOT NULL,
    teacher_id INT NOT NULL,
    feedback_text TEXT NOT NULL,
    points INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (submission_id) REFERENCES submissions(id),
    FOREIGN KEY (teacher_id) REFERENCES users(id)
);

-- Extension requests table
CREATE TABLE IF NOT EXISTS extension_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    student_id INT NOT NULL,
    reason TEXT NOT NULL,
    requested_date DATETIME NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES assignments(id),
    FOREIGN KEY (student_id) REFERENCES users(id)
);

-- Insert sample users (password is 'password123' for all users)
INSERT INTO users (name, email, password, role) VALUES
('John Smith', 'john.smith@teacher.com', MD5('password123'), 'teacher'),
('Sarah Johnson', 'sarah.johnson@teacher.com', MD5('password123'), 'teacher'),
('Mike Brown', 'mike.brown@student.com', MD5('password123'), 'student'),
('Emma Wilson', 'emma.wilson@student.com', MD5('password123'), 'student'),
('Alex Davis', 'alex.davis@student.com', MD5('password123'), 'student');

-- Insert sample assignments
INSERT INTO assignments (title, description, teacher_id, due_date) VALUES
('Web Development Basics', 'Create a simple responsive website using HTML, CSS, and JavaScript', 1, '2024-03-30 23:59:59'),
('Database Design Project', 'Design and implement a database schema for a library management system', 1, '2024-04-15 23:59:59'),
('Python Programming', 'Build a command-line task management application', 2, '2024-04-10 23:59:59'),
('Data Structures', 'Implement a binary search tree with basic operations', 2, '2024-04-05 23:59:59');

-- Insert sample submissions
INSERT INTO submissions (assignment_id, student_id, submission_text) VALUES
(1, 3, 'My submission for web development project: [GitHub Link]'),
(1, 4, 'Here is my responsive website project: [Project Link]'),
(2, 3, 'Database schema and implementation attached'),
(3, 5, 'Python task manager application completed');

-- Insert sample feedback
INSERT INTO feedback (submission_id, teacher_id, feedback_text, points) VALUES
(1, 1, 'Good work on responsiveness. Could improve code organization.', 85),
(2, 1, 'Excellent project! Great use of modern CSS features.', 95),
(3, 1, 'Database schema is well-designed. Add more documentation.', 88),
(4, 2, 'Good functionality but needs better error handling.', 82);

-- Insert sample extension requests
INSERT INTO extension_requests (assignment_id, student_id, reason, requested_date, status) VALUES
(1, 3, 'Family emergency, need additional time to complete', '2024-04-02 23:59:59', 'approved'),
(2, 4, 'Technical issues with development environment', '2024-04-17 23:59:59', 'pending'),
(3, 5, 'Sick with flu, doctor note attached', '2024-04-12 23:59:59', 'pending'); 