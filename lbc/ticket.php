<?php
include('connection.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Event image mapping (update IDs and filenames as needed)
$eventImages = [
    3 => 'images/landing1.jpg',
    4 => 'images/landing2.jpg',
    5 => 'images/landing3.jpg',
    6 => 'images/landing4.jpg'
];

// Fetch all events from database
$event_sql = "SELECT id, title, event_date, event_time, venue, location FROM events";
$event_result = $conn->query($event_sql);
if (!$event_result) {
    die('Error fetching events: ' . $conn->error);
}
$events = [];
while ($row = $event_result->fetch_assoc()) {
    $events[$row['id']] = [
        'name'     => $row['title'],
        'datetime' => date('Y-m-d', strtotime($row['event_date'])) . ' @ ' . date('g:i A', strtotime($row['event_time'])),
        'venue'    => $row['venue'],
        'address'  => $row['location']
    ];
}

// Pagination
$records_per_page = 9;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

// Filters
$status_filter = $_GET['status'] ?? '';

// Build count query
$count_sql = "SELECT COUNT(*) as total FROM tickets WHERE user_id = ?";
$count_params = "i";
$count_values = [$user_id];

if (!empty($status_filter) && in_array($status_filter, ['confirmed', 'pending', 'rejected'])) {
    $count_sql .= " AND status = ?";
    $count_params .= "s";
    $count_values[] = $status_filter;
}
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($count_params, ...$count_values);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $records_per_page);
$offset = ($current_page - 1) * $records_per_page;

// Main query
$sql = "SELECT * FROM tickets WHERE user_id = ?";
$params = "i";
$values = [$user_id];
if (!empty($status_filter) && in_array($status_filter, ['confirmed', 'pending', 'rejected'])) {
    $sql .= " AND status = ?";
    $params .= "s";
    $values[] = $status_filter;
}
$sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params .= "ii";
$values[] = $records_per_page;
$values[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($params, ...$values);
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets - LBC</title>
    <link rel="stylesheet" href="user-style.css">
    <style>
    /* ----- Grid & Card Styling ----- */
    .ticket-card {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        height: 100%;
        background: var(--bg-card);
        border-radius: 1.25rem;
        box-shadow: 0 8px 36px rgba(0,0,0,0.48);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        border: 1px solid rgba(141,222,241,0.17);
        min-height: 480px;
        transition: box-shadow 0.2s, transform 0.2s;
        margin-bottom: 1.75rem;
        position: relative;
    }
    .ticket-card-img {
        width: 100%;
        height: 170px;
        object-fit: cover;
        border-bottom: 2.5px solid var(--medium-blue);
    }
    .ticket-header {
        padding: 0.85rem 1.5rem 0.4rem;
        border-bottom: 1.5px solid var(--medium-blue);
        background: transparent;
    }
    .ticket-number {
        font-size: 0.93rem;
        color: var(--text-secondary);
        margin-bottom: 0.15rem;
    }
    .ticket-event {
        font-size: 1.18rem;
        color: var(--light-cyan);
        font-weight: bold;
        margin-top: 0.2rem;
        margin-bottom: 0.1rem;
        letter-spacing: .7px;
    }
    .status-badge {
        display: block;
        min-width: 110px;
        max-width: 160px;
        margin: 1.1rem auto 1.2rem;
        padding: 0.48rem 0;
        border-radius: 2rem;
        font-size: 1.08rem;
        font-weight: 650;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 2px 6px rgba(16,185,129,0.13);
        text-align: center;
        transition: background 0.22s, color 0.22s;
    }
    .status-confirmed {
        background: linear-gradient(135deg, #0abb83 40%, #069672 100%);
        color: #fff;
    }
    .status-pending {
        background: linear-gradient(135deg, #f5b60d, #d97706);
        color: #fff;
    }
    .status-expired {
        background: linear-gradient(135deg, #b71c1c 60%, #757575 100%);
        color: #fff;
    }
    .status-rejected {
        background: linear-gradient(135deg, #d32f2f 60%, #908585 100%);
        color: #fff;
    }
    .ticket-info {
        padding: 1rem 1.5rem 1.5rem 1.5rem;
        flex: 1 1 auto;
        display: flex;
        flex-direction: column;
        gap: 0.6rem;
    }
    .ticket-info p {
        margin: 0.17rem 0;
        color: var(--text-primary);
        font-size: 1.04rem;
        font-weight: 500;
        letter-spacing: 0.05em;
    }
    .ticket-card .btn-primary {
        width: 100%;
        margin: 1.1rem 0 0 0;
        padding: 1rem 0;
        background: linear-gradient(135deg, var(--medium-blue), var(--light-cyan));
        color: var(--white);
        border: none;
        border-radius: 0 0 1.25rem 1.25rem;
        font-weight: 800;
        font-size: 1.15rem;
        cursor: pointer;
        letter-spacing: 0.6px;
        box-shadow: 0 4px 14px rgba(55,104,173,0.13);
        text-decoration: none;
        text-align: center;
        transition: background 0.3s, box-shadow 0.3s, transform 0.18s;
        display: block;
    }
    .ticket-card .btn-primary:hover,
    .ticket-card .btn-primary:focus {
        background: linear-gradient(135deg, var(--deep-blue), var(--medium-blue));
        color: var(--white);
        transform: translateY(-2px) scale(1.03);
        box-shadow: 0 8px 28px rgba(55,104,173,0.24);
        outline: none;
    }
    @media (max-width: 768px) {
        .ticket-card, .ticket-info, .ticket-header { padding-left: 1.1rem; padding-right: 1.1rem;}
        .ticket-card-img { height: 140px; }
        .ticket-card .btn-primary {
            font-size: 1rem;
            padding: 0.85rem 0;
        }
    }
  /* Responsive Container for Status Filter */
.status-filter-container {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
    background: transparent;
    padding: 0.1rem 0 0.3rem 0;
    margin-bottom: 1rem;
    min-width: 170px;
    max-width: 100%;
    box-sizing: border-box;
}

/* Responsive Form Group */
.status-filter-container .form-group {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    margin: 0;
    padding: 0;
}
.filter-form-container{
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.35rem;
    margin: 5px;
    padding: 10px;
    max-width: 200px;
}
/* Responsive Label */
.status-filter-container label {
    color: #fff;
    font-size: 1.08rem;
    font-weight: 600;
    line-height: 1.2;
    margin-right: 0.25rem;
    white-space: nowrap;
}

/* Responsive Dropdown */
#status {
    padding: 0.4rem 0.9rem;
    border-radius: 1rem;
    border: 1.5px solid #8DDEF1;
    background: #181b1b;
    color: #fff;
    font-size: 1.07rem;
    font-weight: 500;
    outline: none;
    min-width: 110px;
    max-width: 170px;
    box-shadow: none;
    transition: border-color 0.18s;
}

#status:focus {
    border-color: #37c9f6;
}

/* MOBILE FRIENDLY STYLES */
@media (max-width: 630px) {
    .status-filter-container {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
        padding-bottom: 0.7rem;
        margin-bottom: 1rem;
        width: 100%;
    }
    .status-filter-container label {
        font-size: 1rem;
        margin-bottom: 0.15rem;
    }
    #status {
        font-size: 1rem;
        min-width: 100px;
        max-width: 99vw;
        padding: 0.38rem 0.8rem;
    }
}

    </style>
</head>
<body>
    <!-- Navbar -->
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
                <p>Welcome, <strong><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest'); ?></strong></p>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h2>MY TICKETS</h2>
            <p>View and manage your event tickets</p>
        </div>
        <!-- Filter Form -->
        <div class="filter-form-container">
    <form method="GET" action="ticket.php" class="filter-form">
        <div class="form-group">
            <label for="status">Filter by Status:</label>
            <select name="status" id="status" onchange="this.form.submit()">
                <option value="">All Status</option>
                <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>
    </form>
</div>

        <!-- Tickets Display -->
        <?php if (count($tickets) > 0): ?>
            <div class="tickets-grid">
                <?php foreach ($tickets as $ticket): ?>
                    <?php
                    $event = $events[$ticket['event_id']] ?? ['name' => 'Unknown Event', 'datetime' => 'TBA', 'venue' => 'TBA', 'address' => 'TBA'];
                    $imagePath = isset($eventImages[$ticket['event_id']]) ? $eventImages[$ticket['event_id']] : 'images/logo.jpg';
                    ?>
                    <div class="ticket-card">
                        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Event Image" class="ticket-card-img" onerror="this.src='images/logo.jpg'">
                        <div class="ticket-header">
                            <p class="ticket-number">Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?></p>
                            <h3 class="ticket-event"><?php echo htmlspecialchars($event['name']); ?></h3>
                        </div>
                        <span class="status-badge status-<?php echo strtolower($ticket['status']); ?>">
                            <?php echo htmlspecialchars(ucfirst($ticket['status'])); ?>
                        </span>
                        <div class="ticket-info">
                            <p><strong>📅 Date:</strong> <?php echo htmlspecialchars($event['datetime']); ?></p>
                            <p><strong>📍 Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
                            <p><strong>🎫 Type:</strong> <?php echo htmlspecialchars($ticket['ticket_type']); ?></p>
                            <p><strong>💰 Price:</strong> ₱<?php echo number_format($ticket['price'], 2); ?></p>
                            <p><strong>📆 Purchase Date:</strong> <?php echo date('M d, Y', strtotime($ticket['purchase_date'])); ?></p>
                        </div>
                        <?php if ($ticket['status'] == 'confirmed'): ?>
                            <a href="view_tickets.php?id=<?php echo $ticket['id']; ?>" class="btn-primary">View Ticket</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-tickets" style="text-align: center; padding: 3rem; background: rgba(30, 30, 30, 0.95); border-radius: 1rem; border: 2px dashed rgba(141, 222, 241, 0.3);">
                <h3 style="color: #8DDEF1; margin-bottom: 1rem;">No tickets found</h3>
                <p style="color: #bdbdbd; margin-bottom: 1.5rem;">
                    <?php if (!empty($status_filter)): ?>
                        Try adjusting your filter.
                    <?php else: ?>
                        You haven't purchased any tickets yet.
                    <?php endif; ?>
                </p>
                <a href="dashboard.php" class="btn-primary" style="text-decoration: none; display: inline-block; width: auto; padding: 0.875rem 2rem;">Browse Events</a>
            </div>
        <?php endif; ?>
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=1&status=<?php echo urlencode($status_filter); ?>" class="page-btn">First</a>
                        <a href="?page=<?php echo $current_page - 1; ?>&status=<?php echo urlencode($status_filter); ?>" class="page-btn">Prev</a>
                    <?php endif; ?>
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    for ($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?php echo $i; ?>&status=<?php echo urlencode($status_filter); ?>" 
                           class="page-number <?php echo $i == $current_page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>&status=<?php echo urlencode($status_filter); ?>" class="page-btn">Next</a>
                        <a href="?page=<?php echo $total_pages; ?>&status=<?php echo urlencode($status_filter); ?>" class="page-btn">Last</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
