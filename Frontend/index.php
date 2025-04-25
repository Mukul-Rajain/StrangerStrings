<?php
// ==================== BACKEND PHP CODE ====================

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'hawkins_lab_portal');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_TABLE_PREFIX', 'hawkins_');

// Establish database connection
function getDBConnection() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        return new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log("Database connection failed: " . $e->getMessage());
        die("A database error occurred. Please try again later.");
    }
}

// Initialize session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security headers
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        $db = getDBConnection();
        
        // Create tables if they don't exist
        $db->exec("CREATE TABLE IF NOT EXISTS ".DB_TABLE_PREFIX."users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            email VARCHAR(100) NOT NULL UNIQUE,
            password_hash VARCHAR(255) NOT NULL,
            clearance_level ENUM('student', 'teacher', 'admin') DEFAULT 'student',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login TIMESTAMP NULL,
            is_active BOOLEAN DEFAULT TRUE
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS ".DB_TABLE_PREFIX."login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_successful BOOLEAN DEFAULT FALSE
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS ".DB_TABLE_PREFIX."password_reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            is_used BOOLEAN DEFAULT FALSE
        )");
        
        if ($action === 'login') {
            // Login processing
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            if (empty($username) || empty($password)) {
                $loginError = 'Username and password are required';
            } else {
                // Check if user exists
                $stmt = $db->prepare("SELECT id, username, password_hash, clearance_level, is_active FROM ".DB_TABLE_PREFIX."users WHERE username = ?");
                $stmt->execute([$username]);
                $user = $stmt->fetch();
                
                // Record login attempt
                $ip = $_SERVER['REMOTE_ADDR'];
                $isSuccessful = false;
                
                if ($user && password_verify($password, $user['password_hash'])) {
                    if (!$user['is_active']) {
                        $loginError = "Your account has been deactivated.";
                    } else {
                        $isSuccessful = true;
                        // Update last login
                        $update = $db->prepare("UPDATE ".DB_TABLE_PREFIX."users SET last_login = NOW() WHERE id = ?");
                        $update->execute([$user['id']]);
                        
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['clearance'] = $user['clearance_level'];
                        $_SESSION['last_activity'] = time();
                        
                        // Regenerate session ID to prevent fixation
                        session_regenerate_id(true);
                        
                        // Redirect to dashboard
                        header("Location: ".($user['clearance_level'] === 'teacher' ? 'TeacherDashboard.html' : 'StudentDashboard.html'));
                        exit;
                    }
                } else {
                    $loginError = "Invalid username or password";
                }
                
                // Record attempt
                $record = $db->prepare("INSERT INTO ".DB_TABLE_PREFIX."login_attempts (user_id, ip_address, is_successful) VALUES (?, ?, ?)");
                $record->execute([$user['id'] ?? NULL, $ip, $isSuccessful]);
            }
            
        } elseif ($action === 'register') {
            // Registration processing
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirmPassword'] ?? '';
            
            $errors = [];
            
            if (empty($username)) {
                $errors['username'] = 'Username is required';
            } elseif (!preg_match('/^[a-zA-Z0-9_]{4,50}$/', $username)) {
                $errors['username'] = 'Username must be 4-50 characters (letters, numbers, underscores)';
            }
            
            if (empty($email)) {
                $errors['email'] = 'Email is required';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Invalid email format';
            }
            
            if (empty($password)) {
                $errors['password'] = 'Password is required';
            } elseif (strlen($password) < 8) {
                $errors['password'] = 'Password must be at least 8 characters';
            }
            
            if ($password !== $confirmPassword) {
                $errors['confirmPassword'] = 'Passwords do not match';
            }
            
            if (empty($errors)) {
                // Check if username or email already exists
                $check = $db->prepare("SELECT COUNT(*) FROM ".DB_TABLE_PREFIX."users WHERE username = ? OR email = ?");
                $check->execute([$username, $email]);
                $exists = $check->fetchColumn();
                
                if ($exists) {
                    $registerError = 'Username or email already in use';
                } else {
                    // Hash password
                    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    
                    // Insert new user
                    $stmt = $db->prepare("INSERT INTO ".DB_TABLE_PREFIX."users (username, email, password_hash) VALUES (?, ?, ?)");
                    $stmt->execute([$username, $email, $passwordHash]);
                    
                    // Get the new user ID
                    $userId = $db->lastInsertId();
                    
                    // Set session variables
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $username;
                    $_SESSION['clearance'] = 'student';
                    $_SESSION['last_activity'] = time();
                    
                    // Regenerate session ID
                    session_regenerate_id(true);
                    
                    // Redirect to dashboard
                    header("Location: StudentDashboard.html");
                    exit;
                }
            } else {
                $registerErrors = $errors;
            }
            
        } elseif ($action === 'forgot_password') {
            // Password reset request
            $email = trim($_POST['email'] ?? '');
            
            if (empty($email)) {
                $passwordResetError = 'Email is required';
            } else {
                // Check if user exists
                $stmt = $db->prepare("SELECT id FROM ".DB_TABLE_PREFIX."users WHERE email = ?");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Generate token (64 characters)
                    $token = bin2hex(random_bytes(32));
                    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Delete any existing tokens for this user
                    $delete = $db->prepare("DELETE FROM ".DB_TABLE_PREFIX."password_reset_tokens WHERE user_id = ?");
                    $delete->execute([$user['id']]);
                    
                    // Store new token
                    $insert = $db->prepare("INSERT INTO ".DB_TABLE_PREFIX."password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
                    $insert->execute([$user['id'], $token, $expires]);
                    
                    $passwordResetSuccess = "If this email exists in our system, a reset link has been sent (token: $token)";
                } else {
                    // Don't reveal whether email exists
                    $passwordResetSuccess = "If this email exists in our system, a reset link has been sent";
                }
            }
            
        } elseif ($action === 'reset_password') {
            // Password reset processing
            $token = $_POST['token'] ?? '';
            $newPassword = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirmPassword'] ?? '';
            
            if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
                $passwordResetError = 'All fields are required';
            } elseif ($newPassword !== $confirmPassword) {
                $passwordResetError = 'Passwords do not match';
            } else {
                // Validate token
                $stmt = $db->prepare("SELECT user_id FROM ".DB_TABLE_PREFIX."password_reset_tokens WHERE token = ? AND expires_at > NOW() AND is_used = FALSE");
                $stmt->execute([$token]);
                $tokenData = $stmt->fetch();
                
                if (!$tokenData) {
                    $passwordResetError = 'Invalid or expired token';
                } else {
                    // Update password
                    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);
                    $update = $db->prepare("UPDATE ".DB_TABLE_PREFIX."users SET password_hash = ? WHERE id = ?");
                    $update->execute([$passwordHash, $tokenData['user_id']]);
                    
                    // Mark token as used
                    $markUsed = $db->prepare("UPDATE ".DB_TABLE_PREFIX."password_reset_tokens SET is_used = TRUE WHERE token = ?");
                    $markUsed->execute([$token]);
                    
                    $passwordResetSuccess = 'Password updated successfully';
                }
            }
        }
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $dbError = "A database error occurred. Please try again later.";
    }
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to appropriate dashboard
    $redirect = isset($_SESSION['clearance']) && $_SESSION['clearance'] === 'teacher' ? 'TeacherDashboard.html' : 'StudentDashboard.html';
    header("Location: $redirect");
    exit;
}

// ==================== FRONTEND HTML ====================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hawkins Lab - Access Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Share+Tech+Mono&display=swap');

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Share Tech Mono', monospace;
        }

        :root {
            --dark: #0c0c1e;
            --darker: #060611;
            --red: #e63946;
            --blue: #42aaf4;
            --neon: #08f7fe;
            --light: #f0f0f0;
            --purple: #6c5ce7;
            --pink: #ff5dcd;
        }

        body {
            background-color: var(--dark);
            color: var(--light);
            min-height: 100vh;
            display: grid;
            place-content: center;
            background-image: 
                linear-gradient(rgba(12, 12, 30, 0.9), rgba(12, 12, 30, 0.9)),
                url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100"><rect width="2" height="2" x="50" y="50" fill="%2308f7fe" opacity="0.3"/></svg>');
            background-size: 40px 40px;
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                to right,
                rgba(231, 57, 70, 0.1),
                rgba(66, 170, 244, 0.1),
                rgba(8, 247, 254, 0.1),
                rgba(255, 93, 205, 0.1)
            );
            z-index: -1;
        }

        .wrapper {
            position: relative;
            width: 350px;
            height: 500px;
            perspective: 1000px;
        }

        @media(min-width:540px) {
            .wrapper {
                width: 400px;
            }
        }

        .form-container {
            position: absolute;
            top: 0;
            left: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 100%;
            background-color: rgba(20, 20, 40, 0.8);
            border-radius: 5px;
            box-shadow: 0 0 20px rgba(8, 247, 254, 0.3);
            border: 1px solid var(--blue);
            backdrop-filter: blur(5px);
            overflow: hidden;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        .form-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(
                90deg,
                transparent,
                rgba(8, 247, 254, 0.1),
                transparent
            );
            transition: 0.5s;
        }

        .form-container:hover::before {
            left: 100%;
        }

        .form-container form {
            width: 100%;
            padding: 0 40px;
        }

        .form-container h2 {
            font-size: 30px;
            color: var(--neon);
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 30px;
            text-shadow: 0 0 10px var(--neon);
        }

        .form-group {
            position: relative;
            margin: 30px 0;
            border-bottom: 2px solid var(--blue);
        }

        .form-group input {
            width: 100%;
            height: 40px;
            background: transparent;
            border: none;
            outline: none;
            font-size: 16px;
            color: var(--light);
            padding: 0 35px 0 5px;
        }

        .form-group label {
            position: absolute;
            top: 50%;
            left: 5px;
            transform: translateY(-50%);
            color: var(--blue);
            font-size: 16px;
            pointer-events: none;
            transition: 0.5s;
        }

        .form-group i {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--blue);
            font-size: 18px;
        }

        .form-group input:focus ~ label,
        .form-group input:valid ~ label {
            top: -5px;
            color: var(--neon);
            font-size: 12px;
        }

        .forgot-pass {
            margin: -15px 0 15px;
            text-align: right;
        }

        .forgot-pass a {
            color: var(--blue);
            font-size: 14px;
            text-decoration: none;
            transition: 0.3s;
        }

        .forgot-pass a:hover {
            color: var(--neon);
            text-decoration: underline;
        }

        .btn {
            width: 100%;
            height: 40px;
            background: var(--red);
            border: none;
            outline: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            color: var(--light);
            font-weight: bold;
            letter-spacing: 1px;
            margin-top: 20px;
            transition: 0.3s;
            box-shadow: 0 0 10px rgba(231, 57, 70, 0.5);
        }

        .btn:hover {
            background: #c1121f;
            box-shadow: 0 0 15px rgba(231, 57, 70, 0.8);
        }

        .link {
            font-size: 14px;
            text-align: center;
            margin: 25px 0;
            color: var(--blue);
        }

        .link a {
            color: var(--pink);
            text-decoration: none;
            font-weight: bold;
            transition: 0.3s;
        }

        .link a:hover {
            color: var(--neon);
            text-decoration: underline;
        }

        .sign-up {
            transform: rotateY(180deg);
            backface-visibility: hidden;
        }

        .wrapper.animated-signin .sign-in {
            animation: signin-flip 0.7s ease-in-out forwards;
        }

        @keyframes signin-flip {
            0% {
                transform: rotateY(0deg);
            }
            100% {
                transform: rotateY(180deg);
            }
        }

        .wrapper.animated-signin .sign-up {
            animation: signup-flip 0.7s ease-in-out forwards;
            animation-delay: 0.3s;
        }

        @keyframes signup-flip {
            0% {
                transform: rotateY(180deg);
            }
            100% {
                transform: rotateY(0deg);
            }
        }

        .wrapper.animated-signup .sign-up {
            animation: signup-flip-reverse 0.7s ease-in-out forwards;
        }

        @keyframes signup-flip-reverse {
            0% {
                transform: rotateY(0deg);
            }
            100% {
                transform: rotateY(180deg);
            }
        }

        .wrapper.animated-signup .sign-in {
            animation: signin-flip-reverse 0.7s ease-in-out forwards;
            animation-delay: 0.3s;
        }

        @keyframes signin-flip-reverse {
            0% {
                transform: rotateY(180deg);
            }
            100% {
                transform: rotateY(0deg);
            }
        }

        /* Scanline effect */
        .scanline {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--red), var(--blue), var(--neon), var(--pink), var(--red));
            background-size: 400% 100%;
            animation: scanline 3s linear infinite;
            z-index: 10;
        }

        @keyframes scanline {
            0% { background-position: 0% 0%; }
            100% { background-position: 100% 0%; }
        }

        /* Hawkins Lab logo */
        .logo {
            position: absolute;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            color: var(--neon);
            font-size: 24px;
            font-weight: bold;
            letter-spacing: 2px;
            text-shadow: 0 0 10px var(--neon);
            z-index: 1;
        }

        /* Loading animation */
        .loading {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(12, 12, 30, 0.9);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 100;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.5s;
        }

        .loading.active {
            opacity: 1;
            pointer-events: all;
        }

        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid rgba(8, 247, 254, 0.3);
            border-radius: 50%;
            border-top-color: var(--neon);
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        }

        .loading-text {
            color: var(--neon);
            font-size: 18px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Error messages */
        .error-message {
            color: var(--red);
            font-size: 14px;
            margin-top: 5px;
            text-align: center;
            text-shadow: 0 0 5px rgba(230, 57, 70, 0.5);
        }

        .success-message {
            color: var(--neon);
            font-size: 14px;
            margin-top: 5px;
            text-align: center;
            text-shadow: 0 0 5px rgba(8, 247, 254, 0.5);
        }

        /* Password reset modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(6, 6, 17, 0.9);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: rgba(20, 20, 40, 0.9);
            padding: 30px;
            border-radius: 5px;
            border: 1px solid var(--blue);
            box-shadow: 0 0 20px rgba(8, 247, 254, 0.3);
            width: 90%;
            max-width: 400px;
            position: relative;
        }

        .close-modal {
            position: absolute;
            top: 10px;
            right: 10px;
            color: var(--blue);
            font-size: 24px;
            cursor: pointer;
        }

        .close-modal:hover {
            color: var(--neon);
        }
    </style>
</head>
<body>
    <div class="logo" style="text-align: center; margin-top: 20px;">
        <img src="imagefolder/Student-Login-4-18-2025.png" alt="Hawkins Lab Logo" 
             class="logo-img" 
             style="width: 180px; filter: drop-shadow(0 0 15px rgba(0, 153, 255, 0.8)); transition: transform 0.3s ease, filter 0.3s ease;">
    </div>
    <div class="scanline"></div>
    
    <div class="loading" id="loading">
        <div class="loading-spinner"></div>
        <div class="loading-text">ACCESS GRANTED</div>
    </div>
    
    <!-- Password Reset Modal -->
    <div class="modal" id="passwordResetModal">
        <div class="modal-content">
            <span class="close-modal" id="closeModal">&times;</span>
            <h2 style="color: var(--neon); text-align: center; margin-bottom: 20px;">PASSWORD RESET</h2>
            
            <div id="resetRequestForm">
                <p style="color: var(--blue); margin-bottom: 20px;">Enter your email to receive a reset link</p>
                <form method="post">
                    <input type="hidden" name="action" value="forgot_password">
                    <div class="form-group">
                        <input type="email" id="resetEmail" name="email" required>
                        <label>Email</label>
                        <i class="fas fa-envelope"></i>
                    </div>
                    <?php if (isset($passwordResetError)): ?>
                        <div class="error-message"><?php echo htmlspecialchars($passwordResetError); ?></div>
                    <?php elseif (isset($passwordResetSuccess)): ?>
                        <div class="success-message"><?php echo htmlspecialchars($passwordResetSuccess); ?></div>
                    <?php endif; ?>
                    <button type="submit" class="btn">SEND RESET LINK</button>
                </form>
            </div>
            
            <div id="resetForm" style="display: none;">
                <p style="color: var(--blue); margin-bottom: 20px;">Enter your new password</p>
                <form method="post">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" id="resetToken" name="token" value="">
                    <div class="form-group">
                        <input type="password" id="newPassword" name="password" required>
                        <label>New Password</label>
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="form-group">
                        <input type="password" id="confirmNewPassword" name="confirmPassword" required>
                        <label>Confirm Password</label>
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="error-message" id="resetError"></div>
                    <button type="submit" class="btn">RESET PASSWORD</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="wrapper" id="wrapper">
        <div class="form-container sign-in">
            <form method="post">
                <input type="hidden" name="action" value="login">
                <h2>Access Portal</h2>
                <?php if (isset($dbError)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($dbError); ?></div>
                <?php elseif (isset($loginError)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($loginError); ?></div>
                <?php endif; ?>
                <div class="form-group">
                    <input type="text" id="loginUsername" name="username" required>
                    <label>Username</label>
                    <i class="fas fa-user"></i>
                </div>
                <div class="form-group">
                    <input type="password" id="loginPassword" name="password" required>
                    <label>Password</label>
                    <i class="fas fa-lock"></i>
                </div>
                <div class="forgot-pass">
                    <a href="#" id="forgotPassword">Forgot password?</a>
                </div>
                <button type="submit" class="btn">LOGIN</button>
                <div class="link">
                    <p>New recruit? <a href="#" class="signup-link">Request access</a></p>
                </div>
            </form>
        </div>
        <div class="form-container sign-up">
            <form method="post">
                <input type="hidden" name="action" value="register">
                <h2>Clearance Request</h2>
                <?php if (isset($registerError)): ?>
                    <div class="error-message"><?php echo htmlspecialchars($registerError); ?></div>
                <?php endif; ?>
                <div class="form-group">
                    <input type="text" id="signupUsername" name="username" required 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    <label>Username</label>
                    <i class="fas fa-user"></i>
                    <?php if (isset($registerErrors['username'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($registerErrors['username']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <input type="email" id="signupEmail" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                    <label>Email</label>
                    <i class="fas fa-at"></i>
                    <?php if (isset($registerErrors['email'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($registerErrors['email']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <input type="password" id="signupPassword" name="password" required>
                    <label>Password</label>
                    <i class="fas fa-lock"></i>
                    <?php if (isset($registerErrors['password'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($registerErrors['password']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="form-group">
                    <input type="password" id="signupConfirmPassword" name="confirmPassword" required>
                    <label>Confirm Password</label>
                    <i class="fas fa-lock"></i>
                    <?php if (isset($registerErrors['confirmPassword'])): ?>
                        <div class="error-message"><?php echo htmlspecialchars($registerErrors['confirmPassword']); ?></div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn">REQUEST ACCESS</button>
                <div class="link">
                    <p>Already have clearance? <a href="#" class="signin-link">Login</a></p>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Form elements
        const wrapper = document.getElementById('wrapper');
        const loadingScreen = document.getElementById('loading');
        const passwordResetModal = document.getElementById('passwordResetModal');
        const resetRequestForm = document.getElementById('resetRequestForm');
        const resetForm = document.getElementById('resetForm');
        const closeModal = document.getElementById('closeModal');
        const forgotPassword = document.getElementById('forgotPassword');
        
        // Animation elements
        const signUpLink = document.querySelector('.signup-link');
        const signInLink = document.querySelector('.signin-link');

        // Form toggle animation
        signUpLink.addEventListener('click', (e) => {
            e.preventDefault();
            wrapper.classList.add('animated-signin');
            wrapper.classList.remove('animated-signup');
        });

        signInLink.addEventListener('click', (e) => {
            e.preventDefault();
            wrapper.classList.add('animated-signup');
            wrapper.classList.remove('animated-signin');
        });

        // Add flickering effect to simulate lab lighting
        setInterval(() => {
            if (Math.random() > 0.9) {
                document.body.style.filter = 'brightness(1.2)';
                setTimeout(() => {
                    document.body.style.filter = 'brightness(1)';
                }, 100);
            }
        }, 3000);

        // Password reset modal
        forgotPassword.addEventListener('click', (e) => {
            e.preventDefault();
            passwordResetModal.style.display = 'flex';
            resetRequestForm.style.display = 'block';
            resetForm.style.display = 'none';
        });

        closeModal.addEventListener('click', () => {
            passwordResetModal.style.display = 'none';
        });

        // Check for token in URL (for password reset)
        const urlParams = new URLSearchParams(window.location.search);
        const token = urlParams.get('token');
        
        if (token) {
            document.getElementById('resetToken').value = token;
            passwordResetModal.style.display = 'flex';
            resetRequestForm.style.display = 'none';
            resetForm.style.display = 'block';
        }

        // Form submissions
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                // Show loading screen
                loadingScreen.classList.add('active');
                
                // For demo purposes, we'll let the form submit normally
                // In a real app, you might want to use AJAX here
            });
        });

        // Simulate loading screen for demo
        setTimeout(() => {
            loadingScreen.classList.remove('active');
        }, 2000);
    </script>
</body>
</html>