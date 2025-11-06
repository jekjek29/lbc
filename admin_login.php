<?php

// Redirect if already logged in
if (isset($_SESSION['admin_id']) && $_SESSION['admin_id']) {
    header('Location: admin_dashboard.php');
    exit;
}

include 'connection.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    // Input validation
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required.';
    } else {
        // Check if account is locked
        $check_lock = $conn->prepare("SELECT id, locked_until FROM admins WHERE username = ?");
        
        if (!$check_lock) {
            $error = 'Database error: ' . $conn->error;
        } else {
            $check_lock->bind_param("s", $username);
            if (!$check_lock->execute()) {
                $error = 'Database error: ' . $check_lock->error;
            } else {
                $lock_result = $check_lock->get_result();
                
                if ($lock_result->num_rows > 0) {
                    $user = $lock_result->fetch_assoc();
                    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $error = 'Account locked. Try again later.';
                    }
                }
                $check_lock->close();
            }
        }
        
        // Authenticate user if no errors yet
        if (!$error) {
            $stmt = $conn->prepare("SELECT id, password_hash, is_active, failed_attempts FROM admins WHERE username = ?");
            
            if (!$stmt) {
                $error = 'Database error: ' . $conn->error;
            } else {
                $stmt->bind_param("s", $username);
                
                if (!$stmt->execute()) {
                    $error = 'Database error: ' . $stmt->error;
                } else {
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        $user = $result->fetch_assoc();
                        
                        if (!$user['is_active']) {
                            $error = 'Account is inactive.';
                        } elseif (password_verify($password, $user['password_hash'])) {
                            // Successful login
                            $_SESSION['admin_id'] = $user['id'];
                            $_SESSION['username'] = $username;
                            session_regenerate_id(true);
                            
                            // Clear failed attempts
                            $update = $conn->prepare("UPDATE admins SET failed_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
                            if ($update) {
                                $update->bind_param("i", $user['id']);
                                $update->execute();
                                $update->close();
                            }
                            
                            // Log successful login
                            $log = $conn->prepare("INSERT INTO login_attempts (admin_id, ip_address, success, attempted_at) VALUES (?, ?, 1, NOW())");
                            if ($log) {
                                $log->bind_param("is", $user['id'], $ip_address);
                                $log->execute();
                                $log->close();
                            }
                            
                            header('Location: admin_dashboard.php');
                            exit;
                        } else {
                            // Failed login
                            $failed = $user['failed_attempts'] + 1;
                            $lock_time = NULL;
                            
                            if ($failed >= 5) {
                                $lock_time = date('Y-m-d H:i:s', time() + 900);
                                $error = 'Too many failed attempts. Account locked for 15 minutes.';
                            } else {
                                $error = 'Invalid username or password.';
                            }
                            
                            $update = $conn->prepare("UPDATE admins SET failed_attempts = ?, locked_until = ? WHERE id = ?");
                            if ($update) {
                                $update->bind_param("isi", $failed, $lock_time, $user['id']);
                                $update->execute();
                                $update->close();
                            }
                            
                            // Log failed login
                            $log = $conn->prepare("INSERT INTO login_attempts (admin_id, ip_address, success, attempted_at) VALUES (?, ?, 0, NOW())");
                            if ($log) {
                                $log->bind_param("is", $user['id'], $ip_address);
                                $log->execute();
                                $log->close();
                            }
                        }
                    } else {
                        $error = 'Invalid username or password.';
                        
                        // Log failed attempt
                        $log = $conn->prepare("INSERT INTO login_attempts (ip_address, success, attempted_at) VALUES (?, 0, NOW())");
                        if ($log) {
                            $log->bind_param("s", $ip_address);
                            $log->execute();
                            $log->close();
                        }
                    }
                    $stmt->close();
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .login-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
            padding: 40px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .login-header h1 {
            color: #2c3e50;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .login-header p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
        }
        
        .login-btn:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1>🔐 Admin Login</h1>
            <p>Ticket Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" required autocomplete="username">
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required autocomplete="current-password">
            </div>
            
            <button type="submit" class="login-btn">Login</button>
        </form>
    </div>
</body>
</html>

<?php
$conn->close();
?>
