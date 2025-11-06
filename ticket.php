    <?php
    // ==========================================
    // DATABASE CONNECTION & SESSION CHECK
    // ==========================================
    include "connection.php";

    // Ensure the user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }

    $user_id = $_SESSION['user_id'];

    // ==========================================
    // MOCK EVENTS DATA
    // ==========================================
    $events = [
        1 => [
            'name' => 'The Big Rave Night',
            'datetime' => '2025-11-20 @ 8:00 PM',
            'venue' => 'Rave Arena',
            'address' => '123 Rave St.'
        ],
        2 => [
            'name' => 'Summer EDM Fest',
            'datetime' => '2025-12-05 @ 7:00 PM',
            'venue' => 'Festival Grounds',
            'address' => '456 EDM Blvd.'
        ],
        3 => [
            'name' => 'Hip-Hop Showcase',
            'datetime' => '2025-12-19 @ 9:00 PM',
            'venue' => 'Downtown Hall',
            'address' => '789 Hip-Hop Ave.'
        ],
        4 => [
            'name' => 'New Year\'s Eve Bash',
            'datetime' => '2025-12-31 @ 10:00 PM',
            'venue' => 'City Square',
            'address' => '101 Party Lane'
        ]
    ];

    // ==========================================
    // PAGINATION CONFIGURATION
    // ==========================================
    $records_per_page = 9;
    $current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

    // ==========================================
    // FILTER & SEARCH INPUTS
    // ==========================================
    $status_filter = $_GET['status'] ?? '';
    $search_query = $_GET['search'] ?? '';

    // ==========================================
    // BUILD COUNT QUERY
    // ==========================================
    $count_sql = "SELECT COUNT(*) as total FROM tickets WHERE user_id = ?";
    $count_params = "i";
    $count_values = [$user_id];

    if (!empty($status_filter) && in_array($status_filter, ['confirmed', 'pending', 'expired'])) {
        $count_sql .= " AND status = ?";
        $count_params .= "s";
        $count_values[] = $status_filter;
    }

    if (!empty($search_query)) {
        $count_sql .= " AND ticket_number LIKE ?";
        $count_params .= "s";
        $count_values[] = '%' . $search_query . '%';
    }

    // ==========================================
    // GET TOTAL RECORDS COUNT
    // ==========================================
    $count_stmt = $conn->prepare($count_sql);
    $count_stmt->bind_param($count_params, ...$count_values);
    $count_stmt->execute();
    $count_result = $count_stmt->get_result();
    $total_records = $count_result->fetch_assoc()['total'];
    $count_stmt->close();

    // ==========================================
    // CALCULATE PAGINATION VALUES
    // ==========================================
    $total_pages = ceil($total_records / $records_per_page);
    $offset = ($current_page - 1) * $records_per_page;

    // ==========================================
    // BUILD MAIN QUERY WITH FILTERS
    // ==========================================
    $sql = "SELECT * FROM tickets WHERE user_id = ?";
    $params = "i";
    $values = [$user_id];

    if (!empty($status_filter) && in_array($status_filter, ['confirmed', 'pending', 'expired'])) {
        $sql .= " AND status = ?";
        $params .= "s";
        $values[] = $status_filter;
    }

    if (!empty($search_query)) {
        $sql .= " AND ticket_number LIKE ?";
        $params .= "s";
        $values[] = '%' . $search_query . '%';
    }

    $sql .= " ORDER BY id DESC LIMIT ? OFFSET ?";
    $params .= "ii";
    $values[] = $records_per_page;
    $values[] = $offset;

    // ==========================================
    // EXECUTE MAIN QUERY
    // ==========================================
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($params, ...$values);
    $stmt->execute();
    $result = $stmt->get_result();
    $tickets = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    $conn->close();

    // ==========================================
    // HELPER FUNCTION FOR PAGINATION URLS
    // ==========================================
    function getPaginationUrl($page, $status_filter, $search_query) {
        $params = ['page' => $page];
        if (!empty($status_filter)) $params['status'] = $status_filter;
        if (!empty($search_query)) $params['search'] = $search_query;
        return 'ticket.php?' . http_build_query($params);
    }

    // ==========================================
    // HELPER FUNCTION TO GET EVENT DATA
    // ==========================================
    function getEventData($eventId, $events) {
        return isset($events[$eventId]) ? $events[$eventId] : [
            'name' => 'Unknown Event',
            'datetime' => 'Unknown Date/Time',
            'venue' => 'Unknown Venue',
            'address' => 'Unknown Address'
        ];
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Lost Boys Club - My Tickets</title>
        <link rel="stylesheet" href="user-style.css">
        <style>
            /* ==========================================
            MODAL MESSAGE STYLES
            ========================================== */
            .modal-message {
                text-align: center;
                padding: 40px 20px;
            }

            .modal-message h2 {
                margin-bottom: 20px;
                color: #333;
                font-size: 28px;
                font-weight: 600;
            }

            .modal-message p {
                font-size: 16px;
                color: #666;
                line-height: 1.6;
            }

            .modal-message .icon {
                font-size: 60px;
                margin-bottom: 20px;
            }

            .icon-pending {
                color: #ffc107;
            }

            .icon-expired {
                color: #dc3545;
            }

            .ticket-info-group {
                margin-bottom: 15px;
                padding: 10px;
                background: #f8f9fa;
                border-radius: 6px;
            }

            .ticket-info-group strong {
                display: block;
                color: #555;
                margin-bottom: 5px;
                font-weight: 600;
            }

            .ticket-info-group span {
                color: #333;
                font-size: 15px;
            }
        </style>
    </head>
    <body>
        <!-- ==========================================
            NAVIGATION BAR
            ========================================== -->
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

        <!-- ==========================================
            MAIN CONTENT AREA
            ========================================== -->
        <main class="events-container">
            
            <!-- ==========================================
                PAGE HEADER WITH CONTROLS
                ========================================== -->
            <div class="events-header">
                <h2>MY TICKETS</h2>
                
                <!-- Filter Controls -->
                <div class="ticket-controls">
                    <form action="ticket.php" method="GET" class="filter-form">
                        <input type="hidden" name="page" value="1">
                        <div class="filter-container">
                            <label for="status-filter">Filter by Status:</label>
                            <select id="status-filter" name="status" onchange="this.form.submit()">
                                <option value="">All</option>
                                <option value="confirmed" <?php if ($status_filter == 'confirmed') echo 'selected'; ?>>
                                    Confirmed
                                </option>
                                <option value="pending" <?php if ($status_filter == 'pending') echo 'selected'; ?>>
                                    Pending
                                </option>
                                <option value="expired" <?php if ($status_filter == 'expired') echo 'selected'; ?>>
                                    Expired
                                </option>
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

            <!-- ==========================================
                TICKETS GRID
                ========================================== -->
            <div class="events-grid">
                <?php
                if (empty($tickets)) {
                    echo '<p class="no-tickets-message">No tickets found for your search/filter criteria.</p>';
                } else {
                    foreach ($tickets as $ticket) {
                        // Get event data
                        $eventData = getEventData($ticket['event_id'], $events);
                        $ticketStatus = strtolower(trim($ticket['status'] ?? ''));
                        
                        // Prepare ticket data for modal
                        $ticketDataJson = htmlspecialchars(json_encode([
                            'title' => $eventData['name'],
                            'date' => $eventData['datetime'],
                            'venue' => $eventData['venue'],
                            'address' => $eventData['address'],
                            'ticket_number' => $ticket['ticket_number'],
                            'status' => $ticketStatus
                        ]));
                        ?>
                        <div class="event-card">
                            <div class="event-image">
                                <img src="images/logo.jpg" alt="<?php echo htmlspecialchars($eventData['name']); ?>" class="event-logo">
                            </div>
                            <div class="event-info">
                                <h3 class="event-name"><?php echo htmlspecialchars($eventData['name']); ?></h3>
                                <p class="event-datetime"><?php echo htmlspecialchars($eventData['datetime']); ?></p>
                                <p>Ticket No: <?php echo htmlspecialchars($ticket['ticket_number']); ?></p>
                                <p>Status: <?php echo htmlspecialchars($ticket['status']); ?></p>
                                <button class="view-ticket-btn" onclick="openTicketModal('<?php echo $ticketDataJson; ?>')">
                                    View Ticket
                                </button>
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>

            <!-- ==========================================
                PAGINATION CONTROLS
                ========================================== -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination">
                        <!-- First Page -->
                        <?php if ($current_page > 1): ?>
                            <a href="<?php echo getPaginationUrl(1, $status_filter, $search_query); ?>" class="page-btn first-btn">
                                &laquo;&laquo; First
                            </a>
                        <?php endif; ?>

                        <!-- Previous Page -->
                        <?php if ($current_page > 1): ?>
                            <a href="<?php echo getPaginationUrl($current_page - 1, $status_filter, $search_query); ?>" class="page-btn prev-btn">
                                &laquo; Previous
                            </a>
                        <?php endif; ?>

                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
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

                        if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <span class="ellipsis">...</span>
                            <?php endif; ?>
                            <a href="<?php echo getPaginationUrl($total_pages, $status_filter, $search_query); ?>" class="page-number">
                                <?php echo $total_pages; ?>
                            </a>
                        <?php endif; ?>

                        <!-- Next Page -->
                        <?php if ($current_page < $total_pages): ?>
                            <a href="<?php echo getPaginationUrl($current_page + 1, $status_filter, $search_query); ?>" class="page-btn next-btn">
                                Next &raquo;
                            </a>
                        <?php endif; ?>

                        <!-- Last Page -->
                        <?php if ($current_page < $total_pages): ?>
                            <a href="<?php echo getPaginationUrl($total_pages, $status_filter, $search_query); ?>" class="page-btn last-btn">
                                Last &raquo;&raquo;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
            
        </main>

        <!-- ==========================================
            TICKET MODAL
            ========================================== -->
        <div id="ticketModal" class="modal">
            <div class="modal-content">
                <span class="close" onclick="closeTicketModal()">&times;</span>
                <div class="modal-body" id="modalBody">
                    <!-- Content dynamically inserted here -->
                </div>
            </div>
        </div>

        <!-- ==========================================
            JAVASCRIPT
            ========================================== -->
        <script>
            const modal = document.getElementById("ticketModal");
            const modalBody = document.getElementById("modalBody");

            /**
             * Open ticket modal with conditional content based on status
             * @param {string} ticketDataJson - JSON string of ticket data
             */
            function openTicketModal(ticketDataJson) {
                try {
                    const ticketData = JSON.parse(ticketDataJson);
                    const status = ticketData.status.toLowerCase().trim();
                    
                    modalBody.innerHTML = '';

                    if (status === 'pending' || status === 'pending verification' || status === '') {
                        // Show pending message
                        modalBody.innerHTML = `
                            <div class="modal-message">
                                <div class="icon icon-pending">⏳</div>
                                <h2>Pending Confirmation</h2>
                                <p>Please wait for confirmation.</p>
                                <p>Your ticket is currently being processed and will be confirmed shortly.</p>
                            </div>
                        `;
                    } else if (status === 'expired') {
                        // Show expired message
                        modalBody.innerHTML = `
                            <div class="modal-message">
                                <div class="icon icon-expired">❌</div>
                                <h2>Ticket Expired</h2>
                                <p>Your ticket is already expired.</p>
                                <p>This event has passed and the ticket is no longer valid.</p>
                            </div>
                        `;
                    } else {
                        // Show confirmed ticket details
                        modalBody.innerHTML = `
                            <h2>Ticket Details</h2>
                            <div class="ticket-info-group">
                                <strong>Event:</strong>
                                <span>${ticketData.title}</span>
                            </div>
                            <div class="ticket-info-group">
                                <strong>Date & Time:</strong>
                                <span>${ticketData.date}</span>
                            </div>
                            <div class="ticket-info-group">
                                <strong>Venue:</strong>
                                <span>${ticketData.venue}</span>
                            </div>
                            <div class="ticket-info-group">
                                <strong>Address:</strong>
                                <span>${ticketData.address}</span>
                            </div>
                            <div class="ticket-info-group">
                                <strong>Ticket Number:</strong>
                                <span>${ticketData.ticket_number}</span>
                            </div>
                            <div class="ticket-info-group">
                                <strong>Status:</strong>
                                <span style="color: #28a745; font-weight: bold;">${ticketData.status.toUpperCase()}</span>
                            </div>
                        `;
                    }

                    modal.style.display = "block";
                } catch (error) {
                    console.error('Error parsing ticket data:', error);
                    modalBody.innerHTML = '<p class="modal-message">Error loading ticket information.</p>';
                    modal.style.display = "block";
                }
            }

            /**
             * Close the ticket modal
             */
            function closeTicketModal() {
                modal.style.display = "none";
            }

            /**
             * Close modal when clicking outside of it
             */
            window.onclick = function(event) {
                if (event.target == modal) {
                    closeTicketModal();
                }
            }
        </script>
    </body>
    </html>
