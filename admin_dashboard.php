<?php
// Include the database connection file
// Assumes connection.php contains the $conn object initialization
include 'connection.php'; 

// Fetch all tickets, prioritizing 'pending' status
$sql = "SELECT 
            id, ticket_number, event_id, user_id, ticket_type, price, 
            purchase_date, status, payment_reference, amount_paid, account_name 
        FROM tickets 
        ORDER BY FIELD(status, 'pending', 'expired', 'confirmed'), purchase_date DESC";

$result = $conn->query($sql);

// Check if there was an SQL error
if (!$result) {
    die("SQL Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Ticket Admin Dashboard</title>
    <link rel="stylesheet" href="styles.css"> 
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <h1>Admin Panel</h1>
        </div>
        <div class="nav-links">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="dashboard-container">
        
        <div class="dashboard-header">
            <h2>Ticket Purchase Verification</h2>
            <p>Review and confirm payments. Statuses: Pending, Confirmed (Accepted), Expired (Rejected).</p>
        </div>

        <?php
        // Display status messages if redirected from update_status.php
        if (isset($_GET['update']) && $_GET['update'] == 'success') {
            $status_msg = htmlspecialchars($_GET['status']);
            echo "<div class='alert alert-success'>Ticket ID " . htmlspecialchars($_GET['ticket']) . " status updated to **" . ucfirst($status_msg) . "** successfully.</div>";
        } elseif (isset($_GET['update']) && $_GET['update'] == 'error') {
            echo "<div class='alert alert-error'>Error updating ticket status. Please check server logs.</div>";
        }
        ?>

        <div class="ticket-table-container">
            <table class="ticket-table"> 
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Ticket No.</th>
                        <th>Event ID</th>
                        <th>User ID</th>
                        <th>Type</th>
                        <th>Price</th>
                        <th>Purch. Date</th>
                        <th>Ref No.</th>
                        <th>Paid</th>
                        <th>Account Name</th>
                        <th style="width: 100px;">Status</th>
                        <th style="width: 200px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($result->num_rows > 0) {
                        while($row = $result->fetch_assoc()) {
                            
                            // Map DB status to CSS class for styling and correct display
                            list($status_class, $display_status) = match ($row["status"]) {
                                'confirmed' => ['status-confirmed', 'Confirmed'],
                                'expired'   => ['status-expired', 'Expired'],
                                default     => ['status-pending', 'Pending'],
                            };

                            echo "<tr>";
                            echo "<td>" . $row["id"]. "</td>";
                            echo "<td>" . $row["ticket_number"]. "</td>";
                            echo "<td>" . $row["event_id"]. "</td>";
                            echo "<td>" . $row["user_id"]. "</td>";
                            echo "<td>" . htmlspecialchars($row["ticket_type"]). "</td>";
                            
                            // *** CHANGE HERE: Replaced $ with ₱ ***
                            echo "<td>₱" . number_format($row["price"], 2). "</td>";
                            
                            echo "<td>" . date("Y-m-d", strtotime($row["purchase_date"])). "</td>";
                            echo "<td>" . htmlspecialchars($row["payment_reference"]). "</td>";
                            
                            // *** CHANGE HERE: Replaced $ with ₱ ***
                            echo "<td>₱" . number_format($row["amount_paid"], 2). "</td>";
                            
                            echo "<td>" . htmlspecialchars($row["account_name"]). "</td>";
                            
                            // Highlighted Status using the CSS class
                            echo "<td><strong class='{$status_class}'>" . $display_status . "</strong></td>"; 
                            
                            // The core functionality buttons using GET parameters
                            echo "<td>";
                            echo "<div class='action-links'>"; 
                            
                            // ACCEPT: Maps to 'confirmed' in DB
                            echo "<a href='update_status.php?id=" . $row["id"] . "&action=accept' class='action-accept'>Confirm</a>";
                            
                            // REJECT: Maps to 'expired' in DB
                            echo "<a href='update_status.php?id=" . $row["id"] . "&action=reject' class='action-reject'>Reject</a>";
                            
                            // PENDING: Maps to 'pending' in DB
                            echo "<a href='update_status.php?id=" . $row["id"] . "&action=pending' class='action-pending'>Pending</a>";
                            
                            echo "</div>"; 
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='12' style='text-align: center; padding: 20px; color: #ccc;'>No ticket requests found.</td></tr>";
                    }
                    $conn->close();
                    ?>
                </tbody>
            </table>
        </div> </div> </body>
</html>