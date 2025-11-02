<?php
include "connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Fetch user information from database with error checking
$stmt = $conn->prepare("SELECT username, email, phone, created_at FROM users WHERE id = ?");

// Check if prepare failed
if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($username, $email, $phone, $created_at);
$stmt->fetch();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_email = trim($_POST['email']);
    $new_phone = trim($_POST['phone']);
    
    // Validate email
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        // Update profile
        $update_stmt = $conn->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?");
        
        if ($update_stmt === false) {
            $error_message = "Prepare failed: " . htmlspecialchars($conn->error);
        } else {
            $update_stmt->bind_param("ssi", $new_email, $new_phone, $user_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Profile updated successfully!";
                $email = $new_email;
                $phone = $new_phone;
            } else {
                $error_message = "Error updating profile: " . htmlspecialchars($update_stmt->error);
            }
            $update_stmt->close();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Fetch current password hash
    $pass_stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    
    if ($pass_stmt === false) {
        $error_message = "Prepare failed: " . htmlspecialchars($conn->error);
    } else {
        $pass_stmt->bind_param("i", $user_id);
        $pass_stmt->execute();
        $pass_stmt->bind_result($password_hash);
        $pass_stmt->fetch();
        $pass_stmt->close();
        
        // Verify current password
        if (!password_verify($current_password, $password_hash)) {
            $error_message = "Current password is incorrect";
        } elseif ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters long";
        } else {
            // Update password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $update_pass_stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            
            if ($update_pass_stmt === false) {
                $error_message = "Prepare failed: " . htmlspecialchars($conn->error);
            } else {
                $update_pass_stmt->bind_param("si", $new_password_hash, $user_id);
                
                if ($update_pass_stmt->execute()) {
                    $success_message = "Password changed successfully!";
                } else {
                    $error_message = "Error changing password: " . htmlspecialchars($update_pass_stmt->error);
                }
                $update_pass_stmt->close();
            }
        }
    }
}

// Get user's ticket statistics
$ticket_stmt = $conn->prepare("SELECT COUNT(*) as total_tickets, 
                                SUM(CASE WHEN order_status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed_tickets,
                                SUM(CASE WHEN order_status = 'Pending Verification' THEN 1 ELSE 0 END) as pending_tickets
                                FROM tickets WHERE user_id = ?");

if ($ticket_stmt === false) {
    // If tickets table doesn't exist yet, set defaults
    $total_tickets = 0;
    $confirmed_tickets = 0;
    $pending_tickets = 0;
} else {
    $ticket_stmt->bind_param("i", $user_id);
    $ticket_stmt->execute();
    $ticket_stmt->bind_result($total_tickets, $confirmed_tickets, $pending_tickets);
    $ticket_stmt->fetch();
    $ticket_stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Lost Boys Club</title>
    <link rel="stylesheet" href="user-style.css">
</head>
<body>
    <header class="navbar">
        <div class="logo">
            <h1>Lost Boys Club</h1>
        </div>
        <nav class="nav-links">
            <a href="dashboard.php">Home</a>
            <a href="ticket.php">Tickets</a>
            <a href="account.php">Account</a>
        </nav>
        <div class="user-info">
<span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span> 
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <main class="dashboard-container">
        <div class="dashboard-header">
            <h2>My Account</h2>
            <p>Manage your profile and view your account information</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <div class="dashboard-grid">
            <!-- Profile Information Card -->
            <div class="dashboard-card">
                <h3>Profile Information</h3>
                <form method="POST" action="">
                    <div class="profile-info">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" value="<?php echo htmlspecialchars($username); ?>" disabled>
                            <small style="color: #888;">Username cannot be changed</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($phone ?? ''); ?>" placeholder="Enter your phone number">
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>

            <!-- Account Statistics Card -->
            <div class="dashboard-card">
                <h3>Account Statistics</h3>
                <div class="profile-info">
                    <p><strong>Member Since:</strong> <?php echo date('F j, Y', strtotime($created_at)); ?></p>
                    <p><strong>Total Tickets:</strong> <?php echo $total_tickets ?? 0; ?></p>
                    <p><strong>Confirmed Tickets:</strong> <span class="status-confirmed"><?php echo $confirmed_tickets ?? 0; ?></span></p>
                    <p><strong>Pending Tickets:</strong> <span class="status-pending"><?php echo $pending_tickets ?? 0; ?></span></p>
                </div>
                <div class="action-buttons">
                    <a href="ticket.php" class="btn btn-secondary">View My Tickets</a>
                </div>
            </div>

            <!-- Change Password Card -->
            <div class="dashboard-card">
                <h3>Change Password</h3>
                <form method="POST" action="">
                    <div class="profile-info">
                        <div class="form-group">
                            <label for="current_password">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6">
                            <small style="color: #888;">At least 6 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>
                    </div>
                    
                    <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                </form>
            </div>

            <!-- Account Actions Card -->
            <div class="dashboard-card">
                <h3>Quick Actions</h3>
                <div class="activity-list">
                    <p>Manage your account and preferences</p>
                </div>
                <div class="action-buttons">
                    <a href="dashboard.php" class="btn btn-secondary">Browse Events</a>
                    <a href="ticket.php" class="btn btn-secondary">My Tickets</a>
                    <a href="logout.php" class="btn btn-secondary">Logout</a>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Auto-hide alert messages after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>
