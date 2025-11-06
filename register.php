<?php
include "connection.php";

if (isset($_POST['registerbtn'])) {
    $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
    $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Password match check
    if ($password !== $confirm_password) {
        echo "Passwords do not match!";
        exit();
    }

    // Hash password
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    // Check if account exists (by email or username)
    $check = "SELECT * FROM login WHERE email = '$email' OR username = '$username'";
    $result = mysqli_query($conn, $check);

    if (mysqli_num_rows($result) > 0) {
        echo "Account already exists!";
    } else {
        // Default values for other fields
        $is_active = 1; // active by default
        $created_at = date("Y-m-d H:i:s");
        $updated_at = $created_at;
        $last_login = NULL;
        $failed_login_attempts = 0;
        $account_locked_until = NULL;

      $insert = "INSERT INTO login 
    (username, email, password_hash, first_name, last_name, is_active, created_at, updated_at, last_login, failed_login_attempts, account_locked_until) 
    VALUES 
    ('$username', '$email', '$password_hash', '$first_name', '$last_name', '$is_active', '$created_at', '$updated_at', NULL, '$failed_login_attempts', NULL)";


        if (mysqli_query($conn, $insert)) {
            echo "Registration Successful!";
            header("Location: login.php");
            exit();
        } else {
            echo "Error: " . mysqli_error($conn);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost Boys Club Register</title>
    <link rel="stylesheet" href="user-style.css">
</head>
<body>
    <header class="navbar">
        <div class="logo">
            <h1>Lost Boys Club</h1>
        </div>
             

        <div class="user-info">
                
                <a href="index.html" class="btn btn--outline">Home</a>
                
        </div>

    </header>

    <main class="main-container">
        <div class="register-card">
            <h2>REGISTER</h2>
            
            <form action="register.php" method="POST" class="register-form">
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" required placeholder="Enter your first name">
                </div>

                <div class="form-group">
                    <label for="last_name">Last Name:</label>
                    <input type="text" id="last_name" name="last_name" required placeholder="Enter your last name">
                </div>

                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required placeholder="Choose a username">
                </div>

                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required placeholder="Enter your email">
                </div>

                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required placeholder="Create a password">
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter your password">
                </div>
                


                <button type="submit" name="registerbtn">Register</button>
            </form>

            <p class="login-text">
                Already have an account? <a href="login.php">Login here</a>
            </p> 
        </div>
    </main>
</body>
</html>
