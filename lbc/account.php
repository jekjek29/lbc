    <?php
    include('connection.php');

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];
    $success_message = "";
    $error_message = "";

    // Fetch user data
    $user_query = $conn->prepare("SELECT * FROM login WHERE id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_result = $user_query->get_result();
    $user = $user_result->fetch_assoc();
    $user_query->close();

    // Handle profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
        $first_name = mysqli_real_escape_string($conn, $_POST['first_name']);
        $last_name = mysqli_real_escape_string($conn, $_POST['last_name']);
        $email = mysqli_real_escape_string($conn, $_POST['email']);
        
        // Check if email already exists for other users
        $check_email = $conn->prepare("SELECT id FROM login WHERE email = ? AND id != ?");
        $check_email->bind_param("si", $email, $user_id);
        $check_email->execute();
        $email_result = $check_email->get_result();
        
        if ($email_result->num_rows > 0) {
            $error_message = "Email already exists for another account!";
        } else {
            // FIXED: Removed trailing comma and removed phone parameter
            $update_query = $conn->prepare("UPDATE login SET first_name = ?, last_name = ?, email = ? WHERE id = ?");
            $update_query->bind_param("sssi", $first_name, $last_name, $email, $user_id);
            
            if ($update_query->execute()) {
                $_SESSION['user_name'] = $first_name . " " . $last_name;
                $_SESSION['first_name'] = $first_name;
                $_SESSION['last_name'] = $last_name;
                $_SESSION['email'] = $email;
                $success_message = "Profile updated successfully!";
                
                // Refresh user data
                $user_query = $conn->prepare("SELECT * FROM login WHERE id = ?");
                $user_query->bind_param("i", $user_id);
                $user_query->execute();
                $user_result = $user_query->get_result();
                $user = $user_result->fetch_assoc();
                $user_query->close();
            } else {
                $error_message = "Error updating profile: " . $conn->error;
            }
            $update_query->close();
        }
        $check_email->close();
    }

    // Handle password change
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (password_verify($current_password, $user['password_hash'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_password = $conn->prepare("UPDATE login SET password_hash = ? WHERE id = ?");
                    $update_password->bind_param("si", $new_password_hash, $user_id);
                    
                    if ($update_password->execute()) {
                        $success_message = "Password changed successfully!";
                    } else {
                        $error_message = "Error changing password: " . $conn->error;
                    }
                    $update_password->close();
                } else {
                    $error_message = "Password must be at least 6 characters!";
                }
            } else {
                $error_message = "New passwords do not match!";
            }
        } else {
            $error_message = "Current password is incorrect!";
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>My Account - LBC</title>
        <link rel="stylesheet" href="user-style.css">
        <style>
            .account-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 2rem;
                margin-top: 2rem;
            }
            
            .account-card {
                background: rgba(30, 30, 30, 0.95);
                padding: 2rem;
                border-radius: 1rem;
                border: 2px solid rgba(141, 222, 241, 0.2);
            }
            
            .account-card h3 {
                color: var(--light-cyan);
                margin-bottom: 1.5rem;
                font-size: 1.5rem;
            }
            
            .profile-info {
                background: rgba(30, 30, 30, 0.95);
                padding: 2rem;
                border-radius: 1rem;
                border: 2px solid rgba(141, 222, 241, 0.2);
                margin-bottom: 2rem;
                text-align: center;
            }
            
            .profile-avatar {
                width: 120px;
                height: 120px;
                border-radius: 50%;
                margin: 0 auto 1.5rem;
                background: linear-gradient(135deg, #3768AD, #59A5D9);
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 3rem;
                color: #FFFFFF;
                font-weight: 700;
            }
            
            .profile-name {
                font-size: 2rem;
                color: #8DDEF1;
                margin-bottom: 0.5rem;
            }
            
            .profile-email {
                color: #bdbdbd;
                font-size: 1.1rem;
            }
            
            .profile-username {
                color: #bdbdbd;
                font-size: 0.95rem;
                margin-top: 0.5rem;
            }
            
            @media (max-width: 768px) {
                .account-grid {
                    grid-template-columns: 1fr;
                }
            }
        </style>
    </head>
    <body>
        <!-- Standardized Navbar -->
        <nav class="navbar">
            <div class="logo">
                <img src="images/logo.jpg" alt="LBC Logo" class="navbar-logo">
                <h1>LOST BOYS CLUB</h1>
            </div>
            
            <div class="nav-links">
                <a href="dashboard.php">Home</a>
                <a href="ticket.php">Ticket</a>
                <a href="account.php">Account</a>
                
                <div class="user-info">
                    <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['user_name']); ?></strong></p>
                    <a href="logout.php" class="logout-btn">Logout</a>
                </div>
            </div>
        </nav>
        
        <!-- Main Container -->
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h2>MY ACCOUNT</h2>
                <p>Manage your profile and security settings</p>
            </div>
            
            <!-- Alerts -->
            <?php if ($success_message): ?>
                <div class="alert alert-success">✅ <?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">❌ <?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <!-- Profile Info Card -->
            <div class="profile-info">
                <div class="profile-avatar">
                    <?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?>
                </div>
                <h2 class="profile-name"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h2>
                <p class="profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
                <p class="profile-username">@<?php echo htmlspecialchars($user['first_name']); ?></p>
            </div>
            
            <!-- Account Management Grid -->
            <div class="account-grid">
                <!-- Update Profile Card -->
                <div class="account-card">
                    <h3>📝 Update Profile</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn-primary">Update Profile</button>
                    </form>
                </div>
                
                <!-- Change Password Card -->
                <div class="account-card">
                    <h3>🔒 Change Password</h3>
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6">
                            <span class="form-hint">Minimum 6 characters</span>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>
                        
                        <button type="submit" name="change_password" class="btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
            
            <!-- Account Statistics -->
            <div class="dashboard-grid" style="margin-top: 2rem;">
                <div class="dashboard-card">
                    <h3>📊 Account Statistics</h3>
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username']); ?></p>
                    <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
                    <p><strong>Last Updated:</strong> <?php echo date('F j, Y', strtotime($user['updated_at'])); ?></p>
                    <p><strong>Status:</strong> <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?></p>
                </div>
                
                <div class="dashboard-card">
                    <h3>🎫 Quick Actions</h3>
                    <a href="ticket.php" class="btn-primary" style="text-decoration: none; display: block; text-align: center; margin-bottom: 1rem;">View My Tickets</a>
                    <a href="dashboard.php" style="text-decoration: none; display: block; text-align: center; padding: 0.875rem 1.5rem; background-color: rgba(89, 165, 217, 0.2); color: #8DDEF1; border: 2px solid #59A5D9; border-radius: 0.5rem; font-weight: 700; transition: all 0.3s ease;">Browse Events</a>
                </div>
            </div>
        </div>
    </body>
    </html>
