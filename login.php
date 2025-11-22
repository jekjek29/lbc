<?php
include('connection.php');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_or_email = mysqli_real_escape_string($conn, $_POST['username_email']);
    $password = $_POST['password'];

    $sql = "SELECT * FROM login WHERE username = '$username_or_email' OR email = '$username_or_email'";
    $result = mysqli_query($conn, $sql);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);
        
        if (password_verify($password, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['first_name'] . " " . $row['last_name'];
            $_SESSION['first_name'] = $row['first_name'];
            $_SESSION['last_name'] = $row['last_name'];
            $_SESSION['email'] = $row['email'];
            $_SESSION['role'] = $row['role'];
            
            header("Location: dashboard.php");
            exit();
        } else {
            $error = "Incorrect username/email or password!";
        }
    } else {
        $error = "Incorrect username/email or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LBC Ticket System</title>
    <link rel="stylesheet" href="user-style.css">
</head>
<body>
    <div class="main-container">
        <div class="login-card">
            <img src="images/logo.jpg" alt="Logo" class="login-logo">
            <h2>Login to your Account</h2>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username_email">Username or Email</label>
                    <input type="text" id="username_email" name="username_email" required>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit">Login</button>
            </form>
            
            <p class="signup-text">Don't have an account? <a href="register.php">Sign Up</a></p>
        </div>
    </div>
</body>
</html>
