<?php
include 'connection.php';

// -----------------------------------------------------------------------------
// Helper functions
// -----------------------------------------------------------------------------
function is_active_status(string $val, string $status_filter): string
{
    return ($val === $status_filter) ? ' active' : '';
}

// -----------------------------------------------------------------------------
// Status filter
// -----------------------------------------------------------------------------
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Current URL (used for redirect after actions)
$current_url = $_SERVER['REQUEST_URI'] ?? 'admin_dashboard.php';

// -----------------------------------------------------------------------------
// Fetch statistics
// -----------------------------------------------------------------------------
$stats = [
    'total_tickets' => $conn->query(
        "SELECT COUNT(*) AS count FROM tickets"
    )->fetch_assoc()['count'],

    'pending' => $conn->query(
        "SELECT COUNT(*) AS count FROM tickets WHERE status = 'pending'"
    )->fetch_assoc()['count'],

    'confirmed' => $conn->query(
        "SELECT COUNT(*) AS count FROM tickets WHERE status = 'confirmed'"
    )->fetch_assoc()['count'],

    'rejected' => $conn->query(
        "SELECT COUNT(*) AS count FROM tickets WHERE status = 'rejected'"
    )->fetch_assoc()['count'],

    'total_events' => $conn->query(
        "SELECT COUNT(*) AS count FROM events"
    )->fetch_assoc()['count'],
];

// -----------------------------------------------------------------------------
// Fetch recent tickets with optional filtering
// -----------------------------------------------------------------------------
$query = "
    SELECT 
        t.*, 
        e.title, 
        l.first_name, 
        l.last_name, 
        l.email
    FROM tickets t
    JOIN events e ON t.event_id = e.id
    JOIN login  l ON t.user_id = l.id
";

if ($status_filter && in_array($status_filter, ['pending', 'confirmed', 'rejected'], true)) {
    $safe_status = $conn->real_escape_string($status_filter);
    $query .= " WHERE t.status = '{$safe_status}'";
}

$query  .= " ORDER BY t.id DESC LIMIT 20";
$result  = $conn->query($query);

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

    <script>
    window.onload = function () {
        // --- restore scroll position ---
        const savedY = sessionStorage.getItem('scrollY');
        if (savedY !== null) {
            window.scrollTo(0, parseInt(savedY, 10));
            sessionStorage.removeItem('scrollY');
        }

        const urlParams     = new URLSearchParams(window.location.search);
        const update        = urlParams.get('update');
        const ticketNumber  = urlParams.get('ticket_number');
        const updatedStatus = urlParams.get('updated_status');

        const modal     = document.getElementById('successModal');
        const modalText = document.getElementById('modalText');

        if (update === 'success' && ticketNumber && updatedStatus) {
            modal.style.display = 'flex';
            modalText.innerText =
                'Ticket Number ' + ticketNumber +
                ' status updated to ' + updatedStatus.toUpperCase() + '.';

            setTimeout(function () {
                modal.style.display = 'none';
            }, 3000);

            // Clean URL (remove update params but keep path and any status filter)
            window.history.replaceState({}, document.title, window.location.pathname + window.location.search.split('?')[0]);
        }

        if (update === 'error') {
            modal.style.display = 'flex';
            const msg = urlParams.get('message') || 'There was an error updating the ticket.';
            modalText.innerText = msg;

            window.history.replaceState({}, document.title, window.location.pathname + window.location.search.split('?')[0]);
        }

        // Close modal on ESC
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                modal.style.display = 'none';
            }
        });
    };

    // --- save scroll position before leaving the page ---
    window.addEventListener('beforeunload', function () {
        sessionStorage.setItem('scrollY', window.scrollY.toString());
    });
</script>

</head>
<body>

<!-- Modal -->
<div id="successModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <span class="close"
                  onclick="document.getElementById('successModal').style.display='none'">&times;</span>
            <h2>Notification</h2>
        </div>
        <p id="modalText" class="modal-message"></p>
    </div>
</div>

<div class="admin-container">
    <div class="admin-header">
        <h1>📊 Admin Dashboard</h1>
        <p>Manage tickets and monitor system activity</p>
    </div>

    <div class="admin-dashboard-grid">
        <div class="stats-card<?php echo !$status_filter ? ' active' : ''; ?>"
             onclick="window.location='?';">
            <div class="stats-icon">🎫</div>
            <h3>Total Tickets</h3>
            <div class="stats-number"><?php echo $stats['total_tickets']; ?></div>
        </div>

        <div class="stats-card<?php echo is_active_status('pending', $status_filter); ?>"
             onclick="window.location='?status=pending';">
            <div class="stats-icon">⏳</div>
            <h3>Pending</h3>
            <div class="stats-number"><?php echo $stats['pending']; ?></div>
        </div>

        <div class="stats-card<?php echo is_active_status('rejected', $status_filter); ?>"
             onclick="window.location='?status=rejected';">
            <div class="stats-icon">❌</div>
            <h3>Rejected</h3>
            <div class="stats-number"><?php echo $stats['rejected']; ?></div>
        </div>

        <div class="stats-card<?php echo is_active_status('confirmed', $status_filter); ?>"
             onclick="window.location='?status=confirmed';">
            <div class="stats-icon">✅</div>
            <h3>Confirmed</h3>
            <div class="stats-number"><?php echo $stats['confirmed']; ?></div>
        </div>

        <div class="stats-card" style="pointer-events: none; opacity: 0.6;">
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
                <th>GCASH ref</th>
                <th>GCASH name</th>
                <th>PAYMENT d&t</th>
                <th>STATUS</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($recent_tickets as $ticket): ?>
                <tr>
                    <td><?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($ticket['first_name'] . ' ' . $ticket['last_name']); ?>
                        <br>
                        <small style="color: var(--text-secondary);">
                            <?php echo htmlspecialchars($ticket['email']); ?>
                        </small>
                    </td>
                    <td><?php echo htmlspecialchars($ticket['title']); ?></td>
                    <td>₱<?php echo number_format($ticket['price'], 2); ?></td>
                    <td><?php echo htmlspecialchars($ticket['payment_reference'] ?? ''); ?></td>
                    <td><?php echo htmlspecialchars($ticket['account_name'] ?? ''); ?></td>
                    <td>
                        <?php
                        echo $ticket['payment_date']
                            ? htmlspecialchars(date('Y-m-d H:i', strtotime($ticket['payment_date'])))
                            : '';
                        ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?php echo strtolower($ticket['status']); ?>">
                            <?php echo htmlspecialchars($ticket['status']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <div class="action-btn-row">
                                <!-- Accept -->
                                <form method="POST" action="update_status.php" style="display: inline;">
                                    <input type="hidden" name="ticket_id"
                                           value="<?php echo $ticket['id']; ?>">
                                    <input type="hidden" name="ticket_number"
                                           value="<?php echo htmlspecialchars($ticket['ticket_number']); ?>">
                                    <input type="hidden" name="action" value="accept">
                                    <input type="hidden" name="redirect"
                                           value="<?php echo htmlspecialchars($current_url, ENT_QUOTES); ?>">
                                    <button type="submit"
                                            class="action-btn action-btn-accept"
                                            <?php if ($ticket['status'] === 'confirmed') echo 'disabled'; ?>>
                                        Accept
                                    </button>
                                </form>

                                <!-- Reject -->
                                <form method="POST" action="update_status.php" style="display: inline;">
                                    <input type="hidden" name="ticket_id"
                                           value="<?php echo $ticket['id']; ?>">
                                    <input type="hidden" name="ticket_number"
                                           value="<?php echo htmlspecialchars($ticket['ticket_number']); ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <input type="hidden" name="redirect"
                                           value="<?php echo htmlspecialchars($current_url, ENT_QUOTES); ?>">
                                    <button type="submit"
                                            class="action-btn action-btn-reject"
                                            <?php if ($ticket['status'] === 'rejected'
                                                || $ticket['status'] === 'expired') echo 'disabled'; ?>>
                                        Reject
                                    </button>
                                </form>
                            </div>

                            <!-- Pending -->
                            <form method="POST" action="update_status.php" style="display: inline;">
                                <input type="hidden" name="ticket_id"
                                       value="<?php echo $ticket['id']; ?>">
                                <input type="hidden" name="ticket_number"
                                       value="<?php echo htmlspecialchars($ticket['ticket_number']); ?>">
                                <input type="hidden" name="action" value="pending">
                                <input type="hidden" name="redirect"
                                       value="<?php echo htmlspecialchars($current_url, ENT_QUOTES); ?>">
                                <button type="submit"
                                        class="action-btn action-btn-pending"
                                        <?php if ($ticket['status'] === 'pending') echo 'disabled'; ?>>
                                    Pending
                                </button>
                            </form>
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
