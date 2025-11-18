<?php
include('connection.php');

// Fetch statistics
$stats = [
    'total_tickets' => $conn->query("SELECT COUNT(*) as count FROM tickets")->fetch_assoc()['count'],
    'pending' => $conn->query("SELECT COUNT(*) as count FROM tickets WHERE status = 'pending'")->fetch_assoc()['count'],
    'confirmed' => $conn->query("SELECT COUNT(*) as count FROM tickets WHERE status = 'confirmed'")->fetch_assoc()['count'],
    'rejected' => $conn->query("SELECT COUNT(*) as count FROM tickets WHERE status = 'rejected'")->fetch_assoc()['count'], // <-- Add this
    'total_events' => $conn->query("SELECT COUNT(*) as count FROM events")->fetch_assoc()['count']
];

// Fetch recent tickets
$result = $conn->query("
    SELECT t.*, e.title, l.first_name, l.last_name, l.email
    FROM tickets t
    JOIN events e ON t.event_id = e.id
    JOIN login l ON t.user_id = l.id
    ORDER BY t.id DESC
    LIMIT 20
");

if (!$result) {
    die('Query failed: ' . $conn->error);
}

$recent_tickets = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - LBC</title>
    <link rel="stylesheet" href="admin-style.css">
    <style>
        .action-btn[disabled] {
            opacity: 0.5;
            pointer-events: none;
            cursor: not-allowed;
        }
        .action-buttons form, .action-buttons button { display: inline; }
    </style>
</head>
<body>
    
    <div class="admin-container">
        <div class="admin-header">
            <h1>📊 Admin Dashboard</h1>
            <p>Manage tickets and monitor system activity</p>
        </div>
        
        <?php if (isset($_GET['update']) && $_GET['update'] == 'success'): ?>
        <div class="alert alert-success">
            Ticket ID <?php echo htmlspecialchars($_GET['ticket']); ?> status updated to 
            <strong><?php echo ucfirst(htmlspecialchars($_GET['status'])); ?></strong> successfully.
        </div>
        <?php elseif (isset($_GET['update']) && $_GET['update'] == 'error'): ?>
        <div class="alert alert-error">
            Error updating ticket status. Please check server logs.
        </div>
        <?php endif; ?>

        <div class="admin-dashboard-grid">
            <div class="stats-card">
                <div class="stats-icon">🎫</div>
                <h3>Total Tickets</h3>
                <div class="stats-number"><?php echo $stats['total_tickets']; ?></div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon">⏳</div>
                <h3>Pending</h3>
                <div class="stats-number"><?php echo $stats['pending']; ?></div>
            </div>

            <div class="stats-card">
    <div class="stats-icon">❌</div>
    <h3>Rejected</h3>
    <div class="stats-number"><?php echo $stats['rejected']; ?></div>
</div>

            
            <div class="stats-card">
                <div class="stats-icon">✅</div>
                <h3>Confirmed</h3>
                <div class="stats-number"><?php echo $stats['confirmed']; ?></div>
            </div>
            
            <div class="stats-card">
                <div class="stats-icon">🎉</div>
                <h3>Total Events</h3>
                <div class="stats-number"><?php echo $stats['total_events']; ?></div>
            </div>
        </div>
        
        <div class="admin-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Customer</th>
                        <th>Event</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_tickets as $ticket): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                            <td>
                                <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
                                <br><small style="color: var(--text-secondary);"><?php echo htmlspecialchars($ticket['email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                            <td>₱<?php echo number_format($ticket['price'], 2); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo strtolower($ticket['status']); ?>">
                                    <?php echo htmlspecialchars($ticket['status']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <form method="POST" action="update_status.php" style="display:inline;">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                        <input type="hidden" name="action" value="accept">
                                        <button type="submit" class="action-btn action-btn-accept"
                                            <?php if($ticket['status'] === 'confirmed') echo 'disabled'; ?>>
                                            Accept
                                        </button>
                                    </form>
                                    <form method="POST" action="update_status.php" style="display:inline;">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                        <input type="hidden" name="action" value="reject">
                                        <button type="submit" class="action-btn action-btn-reject"
                                            <?php if($ticket['status'] === 'rejected' || $ticket['status'] === 'expired') echo 'disabled'; ?>>
                                            Reject
                                        </button>
                                    </form>
                                    <form method="POST" action="update_status.php" style="display:inline;">
                                        <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                                        <input type="hidden" name="action" value="pending">
                                        <button type="submit" class="action-btn action-btn-pending"
                                            <?php if($ticket['status'] === 'pending') echo 'disabled'; ?>>
                                            Reset Pending
                                        </button>
                                    </form>
                                    <button class="action-btn action-btn-view">View</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
