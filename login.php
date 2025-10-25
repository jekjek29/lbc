<?php
session_start();
include "connection.php";

if (isset($_POST['loginbtn'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];

    // Find user by username
    $select = "SELECT * FROM login WHERE username = '$username' OR email = '$username'";
    $result = mysqli_query($conn, $select);

    if (mysqli_num_rows($result) > 0) {
        $row = mysqli_fetch_assoc($result);

        // Verify password
        if (password_verify($password, $row['password_hash'])) {
            // Store user info in session
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_name'] = $row['first_name'] . " " . $row['last_name'];
            $_SESSION['role'] = $row['role'];

            // Redirect to dashboard/frontpage
            header("Location: dashboard.php");
            exit();
        } else {
            echo "Incorrect username/email or password!";
        }
    } else {
        echo "Incorrect username/email or password!";
    }
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost Boys Club Login</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <header class="navbar">
        <div class="logo">
            <h1>Lost Boys Club</h1>
        </div>
    </header>

    <main class="main-container">
        <div class="login-card">
            <img src="images/logo.jpg" alt="Lost Boys Club Logo" class="login-logo">
            <h2>Login to your Account</h2>
            
            <form action="login.php" method="POST">
                <label for="username">Username:</label>
                <input type="text" id="username" name="username" required placeholder="Enter username or email">

                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required placeholder="Enter your password">

                <button type="submit" name="loginbtn">Login</button>
            </form>

            <p class="signup-text">
                Don't have an account? <a href="register.php">Sign Up</a>
            </p> 
        </div>
    </main>
</body>
</html>


