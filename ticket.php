<?php
include "connection.php";

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get the user's ID from the session
$user_id = $_SESSION['user_id'];

// Mock Events data - This simulates fetching from an 'events' table
$events = [
    1 => ['name' => 'The Big Rave Night', 'datetime' => '2025-11-20 @ 8:00 PM', 'venue' => 'Rave Arena', 'address' => '123 Rave St.'],
    2 => ['name' => 'Summer EDM Fest', 'datetime' => '2025-12-05 @ 7:00 PM', 'venue' => 'Festival Grounds', 'address' => '456 EDM Blvd.'],
    3 => ['name' => 'Hip-Hop Showcase', 'datetime' => '2025-12-19 @ 9:00 PM', 'venue' => 'Downtown Hall', 'address' => '789 Hip-Hop Ave.'],
    4 => ['name' => 'New Year\'s Eve Bash', 'datetime' => '2025-12-31 @ 10:00 PM', 'venue' => 'City Square', 'address' => '101 Party Lane']
];

// Pagination settings
$records_per_page = 9; // Number of tickets per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page); // Ensure page is at least 1

// Handle filter and search inputs
$status_filter = $_GET['status'] ?? '';
$search_query = $_GET['search'] ?? '';

// Build the base SQL query for counting total records
$count_sql = "SELECT COUNT(*) as total FROM tickets WHERE user_id = ?";
$count_params = "i";
$count_bind_values = [$user_id];

// Build the main SQL query for fetching records
$sql = "SELECT * FROM tickets WHERE user_id = ?";
$params = "i";
$bind_values = [$user_id];

// Add filters to both queries
if (!empty($status_filter) && in_array($status_filter, ['confirmed', 'pending', 'expired'])) {
    $count_sql .= " AND status = ?";
    $sql .= " AND status = ?";
    $count_params .= "s";
    $params .= "s";
    $count_bind_values[] = $status_filter;
    $bind_values[] = $status_filter;
}

if (!empty($search_query)) {
    $count_sql .= " AND ticket_number LIKE ?";
    $sql .= " AND ticket_number LIKE ?";
    $count_params .= "s";
    $params .= "s";
    $search_param = '%' . $search_query . '%';
    $count_bind_values[] = $search_param;
    $bind_values[] = $search_param;
}

// Get total count of records
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param($count_params, ...$count_bind_values);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Calculate pagination values
$total_pages = ceil($total_records / $records_per_page);
$offset = ($current_page - 1) * $records_per_page;

// Add LIMIT and OFFSET to main query
$sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
$params .= "ii";
$bind_values[] = $records_per_page;
$bind_values[] = $offset;

// Execute main query
$stmt = $conn->prepare($sql);
$stmt->bind_param($params, ...$bind_values);
$stmt->execute();
$result = $stmt->get_result();
$tickets = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$conn->close();

// Function to generate pagination URL
function getPaginationUrl($page, $status_filter, $search_query) {
    $params = ['page' => $page];
    if (!empty($status_filter)) $params['status'] = $status_filter;
    if (!empty($search_query)) $params['search'] = $search_query;
    return 'ticket.php?' . http_build_query($params);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost Boys Club - My Tickets</title>
    <link rel="stylesheet" href="user-style.css">
</head>
<body>
    <header class="navbar">
        <div class="logo">
            <img src="images/logo.jpg" alt="LBC Logo" class="navbar-logo">
            <h1>Lost Boys Club</h1>
        </div>
        <nav class="nav-links">
            <a href="dashboard.php">Home</a>
            <a href="ticket.php">Tickets</a>
            <a href="account.php">Account</a>
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span> 
            <a href="logout.php" class="logout-btn">Logout</a>
        </nav>
    </header>

    <main class="events-container">
        <div class="events-header">
            <h2>MY TICKETS</h2>
            <div class="ticket-controls">
                <form action="ticket.php" method="GET" class="filter-form">
                    <!-- Preserve current page when filtering -->
                    <input type="hidden" name="page" value="1">
            
                    <div class="filter-container">
                        <label for="status-filter">Filter by Status:</label>
                        <select id="status-filter" name="status" onchange="this.form.submit()">
                            <option value="">All</option>
                            <option value="confirmed" <?php if ($status_filter == 'confirmed') echo 'selected'; ?>>Confirmed</option>
                            <option value="pending" <?php if ($status_filter == 'pending') echo 'selected'; ?>>Pending</option>
                            <option value="expired" <?php if ($status_filter == 'expired') echo 'selected'; ?>>Expired</option>
                        </select>
                    </div>
                </form>
            </div>
            
            <!-- Pagination Info -->
            <?php if ($total_records > 0): ?>
                <div class="pagination-info">
                    Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $records_per_page, $total_records); ?> 
                    of <?php echo $total_records; ?> tickets
                </div>
            <?php endif; ?>
        </div>
        
        <div class="events-grid">
            <?php
            if (empty($tickets)) {
                echo '<p class="no-tickets-message">No tickets found for your search/filter criteria.</p>';
            } else {
                foreach ($tickets as $ticket) {
                    $eventId = $ticket['event_id'];
                    $eventName = isset($events[$eventId]) ? $events[$eventId]['name'] : 'Unknown Event';
                    $eventDateTime = isset($events[$eventId]) ? $events[$eventId]['datetime'] : 'Unknown Date/Time';
                    $eventVenue = isset($events[$eventId]) ? $events[$eventId]['venue'] : 'Unknown Venue';
                    $eventAddress = isset($events[$eventId]) ? $events[$eventId]['address'] : 'Unknown Address';

                    echo '<div class="event-card">';
                    echo '    <div class="event-image">';
                    echo '        <img src="images/logo.jpg" alt="' . htmlspecialchars($eventName) . ' Event" class="event-logo">';
                    echo '    </div>';
                    echo '    <div class="event-info">';
                    echo '        <h3 class="event-name">' . htmlspecialchars($eventName) . '</h3>';
                    echo '        <p class="event-datetime">' . htmlspecialchars($eventDateTime) . '</p>';
                    echo '        <p>Ticket No: ' . htmlspecialchars($ticket['ticket_number']) . '</p>';
                    echo '        <p>Status: ' . htmlspecialchars($ticket['status']) . '</p>';
                    echo '        <button class="view-ticket-btn" onclick="openTicketModal(\'' . htmlspecialchars(json_encode([
                        'title' => $eventName,
                        'date' => $eventDateTime,
                        'venue' => $eventVenue,
                        'address' => $eventAddress,
                        'ticket_number' => $ticket['ticket_number'],
                        'status' => $ticket['status']
                    ])) . '\')">View Ticket</button>';
                    echo '    </div>';
                    echo '</div>';
                }
            }
            ?>
        </div>

        <!-- Pagination Controls -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination">
                    <!-- First Page Button -->
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo getPaginationUrl(1, $status_filter, $search_query); ?>" class="page-btn first-btn">
                            &laquo;&laquo; First
                        </a>
                    <?php endif; ?>

                    <!-- Previous Button -->
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo getPaginationUrl($current_page - 1, $status_filter, $search_query); ?>" class="page-btn prev-btn">
                            &laquo; Previous
                        </a>
                    <?php endif; ?>

                    <!-- Page Numbers -->
                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    // Show ellipsis at the beginning if needed
                    if ($start_page > 1): ?>
                        <a href="<?php echo getPaginationUrl(1, $status_filter, $search_query); ?>" class="page-number">1</a>
                        <?php if ($start_page > 2): ?>
                            <span class="ellipsis">...</span>
                        <?php endif;
                    endif;

                    for ($page = $start_page; $page <= $end_page; $page++): ?>
                        <?php if ($page == $current_page): ?>
                            <span class="page-number active"><?php echo $page; ?></span>
                        <?php else: ?>
                            <a href="<?php echo getPaginationUrl($page, $status_filter, $search_query); ?>" class="page-number"><?php echo $page; ?></a>
                        <?php endif;
                    endfor;

                    // Show ellipsis at the end if needed
                    if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="ellipsis">...</span>
                        <?php endif; ?>
                        <a href="<?php echo getPaginationUrl($total_pages, $status_filter, $search_query); ?>" class="page-number"><?php echo $total_pages; ?></a>
                    <?php endif; ?>

                    <!-- Next Button -->
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo getPaginationUrl($current_page + 1, $status_filter, $search_query); ?>" class="page-btn next-btn">
                            Next &raquo;
                        </a>
                    <?php endif; ?>

                    <!-- Last Page Button -->
                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo getPaginationUrl($total_pages, $status_filter, $search_query); ?>" class="page-btn last-btn">
                            Last &raquo;&raquo;
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Modal remains the same -->
    <div id="ticketModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeTicketModal()">&times;</span>
            <div class="modal-body">
                <h2>Ticket Details</h2>
                <div class="ticket-info-group">
                    <strong>Event:</strong> <span id="modalEventName"></span>
                </div>
                <div class="ticket-info-group">
                    <strong>Date & Time:</strong> <span id="modalEventDateTime"></span>
                </div>
                <div class="ticket-info-group">
                    <strong>Venue:</strong> <span id="modalEventVenue"></span>
                </div>
                <div class="ticket-info-group">
                    <strong>Address:</strong> <span id="modalEventAddress"></span>
                </div>
                <div class="ticket-info-group">
                    <strong>Ticket Number:</strong> <span id="modalTicketNumber"></span>
                </div>
                <div class="ticket-info-group">
                    <strong>Status:</strong> <span id="modalTicketStatus"></span>
                </div>
                
            </div>
        </div>
    </div>
    
    <script>
        const modal = document.getElementById("ticketModal");

        function openTicketModal(ticketDataJson) {
            const ticketData = JSON.parse(ticketDataJson);
            
            document.getElementById('modalEventName').textContent = ticketData.title;
            document.getElementById('modalEventDateTime').textContent = ticketData.date;
            document.getElementById('modalEventVenue').textContent = ticketData.venue;
            document.getElementById('modalEventAddress').textContent = ticketData.address;
            document.getElementById('modalTicketNumber').textContent = ticketData.ticket_number;
            document.getElementById('modalTicketStatus').textContent = ticketData.status;

            modal.style.display = "block";
        }

        function closeTicketModal() {
            modal.style.display = "none";
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeTicketModal();
            }
        }
    </script>
</body>
</html>
