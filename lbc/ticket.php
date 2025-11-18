<?php
include('connection.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$eventImages = [
    3 => 'images/landing1.jpg',
    4 => 'images/landing2.jpg',
    5 => 'images/landing3.jpg',
    6 => 'images/landing4.jpg'
];

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
$records_per_page = 9;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$status_filter = $_GET['status'] ?? '';
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
    <!-- Modal Styles -->
    <style>
    /* Modal overlay */
    .modal-overlay {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.72);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 999;
    }
    .modal-overlay.active { display: flex; }

    /* Modal box */
    .ticket-modal {
        background: #181b1b;
        border-radius: 1.33rem;
        box-shadow: 0 12px 44px rgba(0,0,0,0.45);
        width: 98vw;
        max-width: 420px;
        padding: 0;
        overflow: hidden;
        position: relative;
    }
    .ticket-modal-header {
        background: #121616;
        color: #8DDEF1;
        display: flex;
        align-items: center;
        padding: 1rem 1.7rem 0.6rem 1.2rem;
        border-bottom: 1.5px solid #8DDEF1;
    }
    .ticket-modal-header h3 {
        flex: 1;
        font-size: 1.35rem;
        font-weight: bold;
        margin: 0;
    }
    .ticket-modal-close {
        background: transparent;
        border: none;
        color: #bbb;
        font-size: 2rem;
        cursor: pointer;
        margin-right: 2px;
    }
    .ticket-modal-img {
        width: 100%;
        height: 160px;
        object-fit: cover;
        border-bottom: 2px solid #8DDEF1;
        background: #212324;
    }
    .ticket-modal-info {
        padding: 1.3rem 1.7rem;
        color: #fff;
    }
    .ticket-modal-info p {
        font-size: 1.08rem;
        margin: 0.7rem 0;
        color: #d1e4e5;
    }
    .ticket-modal-actions {
        display: flex;
        justify-content: right;
        padding: 1.15rem 1.6rem 1.15rem 1.6rem;
        background: #232728;
    }
    .btn-modal-print {
    padding: 0.6rem 1.35rem;
    background: linear-gradient(135deg, var(--medium-blue), var(--light-cyan));
    color: var(--white);
    font-weight: 800;
    border: none;
    border-radius: 2rem;
    font-size: 1.15rem;
    margin-right: 0.85rem;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(55,104,173,0.13);
    transition: background 0.18s, transform 0.14s;
    text-decoration: none;
    text-align: center;
    letter-spacing: 0.6px;
    display: inline-block;
}
.btn-modal-print:hover {
    background: linear-gradient(135deg, var(--deep-blue), var(--medium-blue));
    color: var(--white);
    transform: translateY(-2px) scale(1.03);
    box-shadow: 0 8px 28px rgba(55,104,173,0.24);
    outline: none;
}

@media print {
    html, body {
        background: #fff !important;
        margin: 0 !important;
        padding: 0 !important;
        height: auto !important;
        min-height: 0 !important;
        box-shadow: none !important;
    }
    body * {
        visibility: hidden !important;
        box-shadow: none !important;
        background: none !important;
        margin: 0 !important;
        padding: 0 !important;
        height: 0 !important;
        min-height: 0 !important;
    }
    /* Only display modal content */
    .modal-overlay.active,
    .modal-overlay.active .ticket-modal,
    .modal-overlay.active .ticket-modal * {
        visibility: visible !important;
        position: static !important;
        top: 0 !important;
        left: 0 !important;
        right: 0 !important;
        bottom: 0 !important;
        margin: 0 auto !important;
        width: 100% !important;
        height: auto !important;
        min-height: 0 !important;
        max-width: 440px !important;
        min-width: 260px !important;
        z-index: 99999 !important;
        box-shadow: none !important;
        background: #fff !important;
        page-break-before: avoid !important;
        page-break-after: avoid !important;
    }
    .modal-overlay.active {
        padding: 0 !important;
        margin: 0 !important;
        background: none !important;
    }
    .ticket-modal {
        margin: 0 auto !important;
        border: 2.5px solid #8DDEF1 !important;
        border-radius: 12px !important;
        box-shadow: 0 0 8px rgba(141,222,241,0.17) !important;
        color: #222 !important;
        background: #fff !important;
        position: static !important;
    }
    .ticket-modal-header, .ticket-modal-info, .ticket-modal-actions {
        page-break-inside: avoid !important;
        background: #f7fcfd !important;
    }
    .ticket-modal-close, .ticket-modal-actions button:not(.btn-modal-print) {
        display: none !important;
    }
    /* Optional: visually pad for print */
    .ticket-modal-header {
        padding: 1.25rem 1.5rem 0.8rem 1.5rem !important;
        color: #069672 !important;
        font-size: 1.32rem !important;
        border-bottom: 2px solid #8DDEF1 !important;
        border-radius: 8px 8px 0 0 !important;
        background: #eafffb !important;
        font-family: 'Segoe UI', Arial, sans-serif !important;
        font-weight: bold;
        letter-spacing: 1px;
    }
    .ticket-modal-img {
        display: block !important;
        width: 100% !important;
        height: 115px !important;
        object-fit: cover !important;
        margin-bottom: 1rem !important;
        border-bottom: 2px solid #8DDEF1 !important;
        background: #f3f8fe !important;
    }
    .ticket-modal-info {
        font-family: 'Segoe UI', Arial, sans-serif !important;
        font-size: 1.07rem !important;
        line-height: 1.65 !important;
        padding: 1.15rem 1.45rem !important;
        color: #1a2326 !important;
    }
    .ticket-modal-info p {
        margin: 0.48rem 0 !important;
        padding: 0 0 0.12rem 0 !important;
        border-bottom: 1px solid #e7f2f6 !important;
        font-size: 1.03rem !important;
    }
    .ticket-modal-info p:last-child { border-bottom: none !important; }
    .ticket-modal-actions {
        padding: 0.8rem 1.3rem 1.2rem 1.3rem !important;
        background: #f7fcfd !important;
        border-top: 2px solid #8DDEF1 !important;
        border-radius: 0 0 8px 8px !important;
    }
    .btn-modal-print {
        background: linear-gradient(90deg,#8DDEF1,#069672 98%) !important;
        color: #181b1b !important;
        font-weight: bold !important;
        border-radius: 2rem !important;
        text-align: right !important;
        font-size: 1.14rem !important;
        padding: 0.40rem 1.4rem !important;
        border: none !important;
        outline: none !important;
        display: inline-block !important;
        margin: 0 !important;
        box-shadow: none !important;
    }
     .ticket-modal-actions .btn-modal-print,
    .ticket-modal-actions .ticket-modal-close {
        display: none !important;
    }
}





    </style>
</head>
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
                    // Compose relevant data for JS modal trigger
                    $ticketData = htmlspecialchars(json_encode([
                        'id' => $ticket['id'],
                        'number' => $ticket['ticket_number'],
                        'event' => $event['name'],
                        'date' => $event['datetime'],
                        'venue' => $event['venue'],
                        'address' => $event['address'],
                        'type' => $ticket['ticket_type'],
                        'price' => number_format($ticket['price'], 2),
                        'purchase_date' => date('M d, Y', strtotime($ticket['purchase_date'])),
                        'img' => $imagePath,
                        'status' => ucfirst($ticket['status'])
                    ]));
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
                            <a href="javascript:void(0);" 
                                class="btn-primary view-ticket-btn"
                                data-ticket='<?php echo $ticketData; ?>'>View Ticket</a>
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
    <div class="modal-overlay" id="ticketModalOverlay">
        <div class="ticket-modal" id="ticketModal">
            <div class="ticket-modal-header">
                <h3 id="modalEventName">Ticket Details</h3>
                <button class="ticket-modal-close" onclick="closeModal()">&times;</button>
            </div>
            <img id="modalEventImage" class="ticket-modal-img" src="images/logo.jpg" alt="Event Image">
            <div class="ticket-modal-info">
                <p><b>Ticket #</b> <span id="modalTicketNumber"></span></p>
                <p><b>Status:</b> <span id="modalTicketStatus"></span></p>
                <p><b>Event:</b> <span id="modalTicketEvent"></span></p>
                <p><b>Date & Time:</b> <span id="modalTicketDate"></span></p>
                <p><b>Venue:</b> <span id="modalTicketVenue"></span></p>
                <p><b>Address:</b> <span id="modalTicketAddress"></span></p>
                <p><b>Type:</b> <span id="modalTicketType"></span></p>
                <p><b>Price:</b> ₱<span id="modalTicketPrice"></span></p>
                <p><b>Purchase Date:</b> <span id="modalTicketPurchase"></span></p>
            </div>
            <div class="ticket-modal-actions">
                <button class="btn-modal-print" onclick="printModal()">Print Ticket</button>
                <button class="btn-modal-print" onclick="closeModal()">Close</button>
            </div>
        </div>
    </div>

    <script>
    // Modal element references
    const modalOverlay = document.getElementById('ticketModalOverlay');
    const modalEventName = document.getElementById('modalEventName');
    const modalEventImage = document.getElementById('modalEventImage');
    const modalTicketNumber = document.getElementById('modalTicketNumber');
    const modalTicketStatus = document.getElementById('modalTicketStatus');
    const modalTicketEvent = document.getElementById('modalTicketEvent');
    const modalTicketDate = document.getElementById('modalTicketDate');
    const modalTicketVenue = document.getElementById('modalTicketVenue');
    const modalTicketAddress = document.getElementById('modalTicketAddress');
    const modalTicketType = document.getElementById('modalTicketType');
    const modalTicketPrice = document.getElementById('modalTicketPrice');
    const modalTicketPurchase = document.getElementById('modalTicketPurchase');

    function openModal(data) {
        // Fill modal fields
        modalEventName.textContent = 'Ticket for ' + (data.event || 'Unknown');
        modalEventImage.src = data.img || 'images/logo.jpg';
        modalTicketNumber.textContent = data.number || '';
        modalTicketStatus.textContent = data.status || '';
        modalTicketEvent.textContent = data.event || '';
        modalTicketDate.textContent = data.date || '';
        modalTicketVenue.textContent = data.venue || '';
        modalTicketAddress.textContent = data.address || '';
        modalTicketType.textContent = data.type || '';
        modalTicketPrice.textContent = data.price || '';
        modalTicketPurchase.textContent = data.purchase_date || '';
        modalOverlay.classList.add('active');
    }
    function closeModal() {
        modalOverlay.classList.remove('active');
    }
    function printModal() {
        window.print();
    }

    // Attach click events automatically to each "View Ticket" button
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.view-ticket-btn').forEach(function(btn){
            btn.addEventListener('click', function(){
                const ticketData = JSON.parse(btn.getAttribute('data-ticket'));
                openModal(ticketData);
            });
        });
        // Close modal when clicking outside modal box
        modalOverlay.addEventListener('click', function(event){
            if (event.target === modalOverlay) { closeModal(); }
        });
        // Optional: Escape key closes modal
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape') closeModal();
        });
    });
    </script>
</body>
</html>
