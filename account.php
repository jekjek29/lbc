<?php
include "connection.php";

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';
$show_logout_modal = false;
$show_password_success_modal = false;

// Fetch user information from login table
$stmt = $conn->prepare("SELECT username, email, first_name, last_name, created_at FROM login WHERE id = ?");

if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
    $username = $user_data['username'];
    $email = $user_data['email'] ?? '';
    $first_name = $user_data['first_name'] ?? '';
    $last_name = $user_data['last_name'] ?? '';
    $created_at = $user_data['created_at'];
} else {
    die("User not found in login table.");
}
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $new_email = trim($_POST['email']);
    $new_first_name = trim($_POST['first_name']);
    $new_last_name = trim($_POST['last_name']);
    
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format";
    } else {
        $update_stmt = $conn->prepare("UPDATE login SET email = ?, first_name = ?, last_name = ? WHERE id = ?");
        
        if ($update_stmt === false) {
            $error_message = "Prepare failed: " . htmlspecialchars($conn->error);
        } else {
            $update_stmt->bind_param("sssi", $new_email, $new_first_name, $new_last_name, $user_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Profile updated successfully!";
                $show_logout_modal = true;
                $email = $new_email;
                $first_name = $new_first_name;
                $last_name = $new_last_name;
            } else {
                $error_message = "Error updating profile: " . htmlspecialchars($update_stmt->error);
            }
            $update_stmt->close();
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required";
    } else {
        $pass_stmt = $conn->prepare("SELECT password_hash FROM login WHERE id = ?");
        
        if ($pass_stmt === false) {
            $error_message = "Database error: " . htmlspecialchars($conn->error);
        } else {
            $pass_stmt->bind_param("i", $user_id);
            $pass_stmt->execute();
            $pass_result = $pass_stmt->get_result();
            
            if ($pass_result->num_rows > 0) {
                $pass_data = $pass_result->fetch_assoc();
                $stored_password_hash = $pass_data['password_hash'];
                
                if (!password_verify($current_password, $stored_password_hash)) {
                    $error_message = "Current password is incorrect.";
                } elseif ($new_password !== $confirm_password) {
                    $error_message = "New passwords do not match.";
                } elseif (strlen($new_password) < 6) {
                    $error_message = "New password must be at least 6 characters long.";
                } elseif ($current_password === $new_password) {
                    $error_message = "New password must be different from current password.";
                } else {
                    $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_pass_stmt = $conn->prepare("UPDATE login SET password_hash = ? WHERE id = ?");
                    
                    if ($update_pass_stmt === false) {
                        $error_message = "Database error: " . htmlspecialchars($conn->error);
                    } else {
                        $update_pass_stmt->bind_param("si", $new_password_hash, $user_id);
                        
                        if ($update_pass_stmt->execute()) {
                            $success_message = "Password changed successfully!";
                            $show_password_success_modal = true;
                        } else {
                            $error_message = "Error changing password: " . htmlspecialchars($update_pass_stmt->error);
                        }
                        $update_pass_stmt->close();
                    }
                }
            }
            $pass_stmt->close();
        }
    }
}

// Pagination for tickets table
$records_per_page = 10;
$current_page = isset($_GET['ticket_page']) ? (int)$_GET['ticket_page'] : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $records_per_page;

// Handle ticket status filter
$ticket_status_filter = $_GET['ticket_status'] ?? '';

// Build ticket query with filter
$ticket_sql = "SELECT * FROM tickets WHERE user_id = ?";
$ticket_params = "i";
$ticket_bind_values = [$user_id];

if (!empty($ticket_status_filter) && in_array($ticket_status_filter, ['confirmed', 'pending', 'expired'])) {
    $ticket_sql .= " AND status = ?";
    $ticket_params .= "s";
    $ticket_bind_values[] = $ticket_status_filter;
}

// Count total matching tickets
$count_ticket_sql = str_replace("SELECT *", "SELECT COUNT(*) as total", $ticket_sql);
$count_ticket_stmt = $conn->prepare($count_ticket_sql);
$count_ticket_stmt->bind_param($ticket_params, ...$ticket_bind_values);
$count_ticket_stmt->execute();
$count_ticket_result = $count_ticket_stmt->get_result();
$total_ticket_records = $count_ticket_result->fetch_assoc()['total'];
$count_ticket_stmt->close();

$total_ticket_pages = ceil($total_ticket_records / $records_per_page);

// Fetch tickets with pagination
$ticket_sql .= " ORDER BY purchase_date DESC LIMIT ? OFFSET ?";
$ticket_params .= "ii";
$ticket_bind_values[] = $records_per_page;
$ticket_bind_values[] = $offset;

$user_tickets = [];
$tickets_query = $conn->prepare($ticket_sql);
$tickets_query->bind_param($ticket_params, ...$ticket_bind_values);
$tickets_query->execute();
$tickets_result = $tickets_query->get_result();
$user_tickets = $tickets_result->fetch_all(MYSQLI_ASSOC);
$tickets_query->close();

// Calculate total spent
$total_spent = 0;
foreach ($user_tickets as $ticket) {
    $total_spent += ($ticket['price'] ?? 0);
}

// Function to generate ticket pagination URL
function getTicketPaginationUrl($page, $status_filter) {
    $params = ['ticket_page' => $page];
    if (!empty($status_filter)) $params['ticket_status'] = $status_filter;
    return 'account.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Account - Lost Boys Club</title>
    <link rel="stylesheet" href="user-style.css">
    <style>
        /* Enhanced Table Styling */
        .tickets-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .tickets-table th {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .tickets-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #f0f0f0;
            color: #333;
            font-size: 14px;
        }
        
        .tickets-table tbody tr {
            transition: all 0.3s ease;
        }
        
        .tickets-table tbody tr:hover {
            background: #f8f9ff;
            transform: translateX(2px);
        }
        
        .tickets-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-confirmed {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
        }
        
        .status-pending {
            background: linear-gradient(135deg, #f2994a 0%, #f2c94c 100%);
            color: white;
        }
        
        .status-expired {
            background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
            color: white;
        }
        
        /* Filter Section */
        .ticket-filter-section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #555;
        }
        
        .filter-group select {
            padding: 8px 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        
        .filter-group select:focus {
            border-color: #667eea;
            outline: none;
        }
        
        /* Pagination */
        .pagination-container {
            margin: 30px 0;
            display: flex;
            justify-content: center;
        }
        
        .pagination {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .pagination a,
        .pagination span {
            padding: 10px 15px;
            text-decoration: none;
            background: #f0f0f0;
            color: #333;
            border-radius: 6px;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .pagination a:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
        
        .pagination .active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 700;
        }
        
        .pagination .disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        /* Summary Box */
        .summary-box {
            margin-top: 25px;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* No Tickets */
        .no-tickets {
            text-align: center;
            padding: 60px 40px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            border-radius: 12px;
            margin-top: 20px;
        }
        
        /* Modals - reusing previous styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: white;
            margin: 8% auto;
            padding: 0;
            border-radius: 16px;
            width: 90%;
            max-width: 520px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        
        .modal-body {
            padding: 40px 30px;
            text-align: center;
        }
        
        .modal-icon {
            font-size: 72px;
            margin-bottom: 20px;
        }
        
        .modal-footer {
            padding: 0 30px 35px;
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        
        .modal-btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 14px 35px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        
        .modal-btn-secondary {
            background: #f5f5f5;
            color: #333;
            padding: 14px 35px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
    </style>
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
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['user_name'] ?? $username); ?></span> 
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </header>

    <main class="dashboard-container">
        <div class="dashboard-header">
            <h2>My Account</h2>
            <p>Manage your profile and view your account information</p>
        </div>

        <?php if ($success_message && !$show_logout_modal && !$show_password_success_modal): ?>
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
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($first_name); ?>" placeholder="Enter your first name">
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($last_name); ?>" placeholder="Enter your last name">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
                        </div>
                    </div>
                    
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>

           
            <!-- Change Password Card -->
            <div class="dashboard-card">
                <h3>Change Password</h3>
                <form method="POST" action="" id="passwordForm">
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

        <!-- Enhanced My Tickets Section with Pagination -->
        <div class="dashboard-card" id="my-tickets" style="margin-top: 30px;">
            <h3>My Purchased Tickets</h3>
            
            <!-- Filter Section -->
            <div class="ticket-filter-section">
                <form method="GET" action="account.php#my-tickets" style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <div class="filter-group">
                        <label for="ticket_status">Filter by Status:</label>
                        <select id="ticket_status" name="ticket_status" onchange="this.form.submit()">
                            <option value="">All Tickets</option>
                            <option value="confirmed" <?php echo ($ticket_status_filter == 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="pending" <?php echo ($ticket_status_filter == 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="expired" <?php echo ($ticket_status_filter == 'expired') ? 'selected' : ''; ?>>Expired</option>
                        </select>
                    </div>
                </form>
                
                <?php if ($total_ticket_records > 0): ?>
                    <div style="color: #666; font-size: 14px;">
                        Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $records_per_page, $total_ticket_records); ?> 
                        of <?php echo $total_ticket_records; ?> tickets
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if (count($user_tickets) > 0): ?>
                <div style="overflow-x: auto;">
                    <table class="tickets-table">
                        <thead>
                            <tr>
                                <th>Ticket #</th>
                                <th>Event ID</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Price</th>
                                <th>Payment Ref</th>
                                <th>Purchase Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($user_tickets as $ticket): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($ticket['ticket_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ticket['event_id']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['ticket_type'] ?? 'Standard'); ?></td>
                                    <td>
                                        <?php 
                                        $status = strtolower($ticket['status'] ?? 'pending');
                                        $status_class = 'status-badge ';
                                        
                                        if ($status == 'confirmed') {
                                            $status_class .= 'status-confirmed';
                                        } elseif ($status == 'pending') {
                                            $status_class .= 'status-pending';
                                        } else {
                                            $status_class .= 'status-expired';
                                        }
                                        ?>
                                        <span class="<?php echo $status_class; ?>">
                                            <?php echo htmlspecialchars(ucfirst($status)); ?>
                                        </span>
                                    </td>
                                    <td><strong>$<?php echo number_format($ticket['price'] ?? 0, 2); ?></strong></td>
                                    <td><?php echo htmlspecialchars($ticket['payment_reference'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php 
                                        if (isset($ticket['purchase_date'])) {
                                            echo date('M d, Y', strtotime($ticket['purchase_date']));
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination Controls -->
                <?php if ($total_ticket_pages > 1): ?>
                    <div class="pagination-container">
                        <div class="pagination">
                            <!-- First Page -->
                            <?php if ($current_page > 1): ?>
                                <a href="<?php echo getTicketPaginationUrl(1, $ticket_status_filter); ?>#my-tickets">&laquo;&laquo; First</a>
                            <?php else: ?>
                                <span class="disabled">&laquo;&laquo; First</span>
                            <?php endif; ?>

                            <!-- Previous Page -->
                            <?php if ($current_page > 1): ?>
                                <a href="<?php echo getTicketPaginationUrl($current_page - 1, $ticket_status_filter); ?>#my-tickets">&laquo; Prev</a>
                            <?php else: ?>
                                <span class="disabled">&laquo; Prev</span>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php
                            $start_page = max(1, $current_page - 2);
                            $end_page = min($total_ticket_pages, $current_page + 2);
                            
                            for ($page = $start_page; $page <= $end_page; $page++): ?>
                                <?php if ($page == $current_page): ?>
                                    <span class="active"><?php echo $page; ?></span>
                                <?php else: ?>
                                    <a href="<?php echo getTicketPaginationUrl($page, $ticket_status_filter); ?>#my-tickets"><?php echo $page; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Next Page -->
                            <?php if ($current_page < $total_ticket_pages): ?>
                                <a href="<?php echo getTicketPaginationUrl($current_page + 1, $ticket_status_filter); ?>#my-tickets">Next &raquo;</a>
                            <?php else: ?>
                                <span class="disabled">Next &raquo;</span>
                            <?php endif; ?>

                            <!-- Last Page -->
                            <?php if ($current_page < $total_ticket_pages): ?>
                                <a href="<?php echo getTicketPaginationUrl($total_ticket_pages, $ticket_status_filter); ?>#my-tickets">Last &raquo;&raquo;</a>
                            <?php else: ?>
                                <span class="disabled">Last &raquo;&raquo;</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Summary Box -->
                <div class="summary-box">
                    <div>
                        <strong>Total Tickets:</strong> <?php echo $total_ticket_records; ?>
                    </div>
                    <div>
                        <strong>Total Spent:</strong> <span style="font-size: 20px; font-weight: 700;">$<?php echo number_format($total_spent, 2); ?></span>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="no-tickets">
                    <p style="font-size: 64px;">🎫</p>
                    <p style="font-size: 20px; font-weight: 700; color: #555;"><strong>No tickets found</strong></p>
                    <p style="font-size: 16px;">
                        <?php if ($ticket_status_filter): ?>
                            No <?php echo htmlspecialchars($ticket_status_filter); ?> tickets found. Try a different filter.
                        <?php else: ?>
                            You haven't purchased any tickets yet. Start browsing events!
                        <?php endif; ?>
                    </p>
                    <br>
                    <a href="dashboard.php" class="btn btn-primary" style="text-decoration: none;">Browse Events</a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modals (Profile Update and Password Success) -->
    <div id="logoutModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✓ Profile Updated Successfully!</h2>
            </div>
            <div class="modal-body">
                <div class="modal-icon">🔄</div>
                <p><strong>Your profile has been updated.</strong></p>
                <p>Please log out and log back in to see all changes reflected.</p>
            </div>
            <div class="modal-footer">
                <a href="logout.php" class="modal-btn-primary">Logout Now</a>
                <button onclick="closeModal('logoutModal')" class="modal-btn-secondary">Stay Logged In</button>
            </div>
        </div>
    </div>

    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>✓ Password Changed Successfully!</h2>
            </div>
            <div class="modal-body">
                <div class="modal-icon">🔐</div>
                <p><strong>Your password has been updated.</strong></p>
            </div>
            <div class="modal-footer">
                <button onclick="closeModal('passwordModal')" class="modal-btn-primary">Got It!</button>
            </div>
        </div>
    </div>

    <script>
        // Auto-hide alerts
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);

        <?php if ($show_logout_modal): ?>
        window.addEventListener('load', function() {
            document.getElementById('logoutModal').style.display = 'block';
        });
        <?php endif; ?>

        <?php if ($show_password_success_modal): ?>
        window.addEventListener('load', function() {
            document.getElementById('passwordModal').style.display = 'block';
            document.getElementById('passwordForm').reset();
        });
        <?php endif; ?>

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
