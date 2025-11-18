<?php
include('connection.php');

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'user';

    if ($password !== $confirm_password) {
        $message = '<div class="alert alert-error">❌ Passwords do not match!</div>';
    } else {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        $check = "SELECT * FROM login WHERE email = '$email' OR username = '$username'";
        $result = mysqli_query($conn, $check);
        
        if (mysqli_num_rows($result) > 0) {
            $message = '<div class="alert alert-error">❌ Username or Email already exists!</div>';
        } else {
            $sql = "INSERT INTO login (first_name, last_name, username, email, phone, password_hash, role) 
                    VALUES ('$first_name', '$last_name', '$username', '$email', '$phone', '$password_hash', '$role')";
            
            if (mysqli_query($conn, $sql)) {
                $message = '<div class="alert alert-success">✅ Registration successful! <a href="login.php">Login here</a></div>';
            } else {
                $message = '<div class="alert alert-error">❌ Error: ' . mysqli_error($conn) . '</div>';
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
    <title>Register - LBC Ticket System</title>
    <link rel="stylesheet" href="user-style.css">
</head>
<body>
    <div class="main-container">
        <div class="register-card">
            <h2>Create Account</h2>
            
            <?php echo $message; ?>
            
            <form method="POST" action="" class="register-form">
                <div class="form-group">
                    <label for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" required>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" required>
                </div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input type="text" id="phone" name="phone" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                
                <button type="submit">Register</button>
            </form>
            
            <p class="login-text">Already have an account? <a href="login.php">Login here</a></p>
        </div>
    </div>
</body>
</html>
