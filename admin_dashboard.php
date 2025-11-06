<?php
include 'connection.php';

// ==========================================
// RESTOCK FORM HANDLER
// ==========================================
$restock_message = '';
$restock_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_tickets'])) {
    $event_id = (int)$_POST['event_id'];
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    
    if ($event_id <= 0) {
        $restock_error = "Please select a valid event.";
    } elseif ($quantity <= 0) {
        $restock_error = "Quantity must be greater than 0.";
    } elseif ($price < 0) {
        $restock_error = "Price cannot be negative.";
    } else {
        $restock_sql = "UPDATE events SET available_tickets = available_tickets + ?, price = ? WHERE id = ?";
        $restock_stmt = $conn->prepare($restock_sql);
        
        if ($restock_stmt) {
            $restock_stmt->bind_param("idi", $quantity, $price, $event_id);
            
            if ($restock_stmt->execute()) {
                $restock_message = "Successfully added $quantity tickets to Event ID $event_id!";
            } else {
                $restock_error = "Error restocking tickets: " . $conn->error;
            }
            $restock_stmt->close();
        } else {
            $restock_error = "Database error: " . $conn->error;
        }
    }
}

// ==========================================
// FETCH EVENTS FOR DROPDOWN
// ==========================================
$events_query = "SELECT id, event_name, available_tickets, price FROM events ORDER BY event_name";
$events_result = $conn->query($events_query);
$events = [];

if ($events_result) {
    while ($event = $events_result->fetch_assoc()) {
        $events[] = $event;
    }
}

// ==========================================
// GET FILTER PARAMETERS
// ==========================================
$filter_status = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : '';
$filter_date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : '';

// ==========================================
// PAGINATION SETUP
// ==========================================
$records_per_page = 15;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$current_page = max(1, $current_page);
$offset = ($current_page - 1) * $records_per_page;

// ==========================================
// BUILD WHERE CLAUSE FOR FILTERING
// ==========================================
$where_clause = "";
$where_params = [];
$param_types = "";

if ($filter_status && in_array($filter_status, ['pending', 'confirmed', 'expired'])) {
    $where_clause .= " WHERE status = ?";
    $where_params[] = $filter_status;
    $param_types .= "s";
}

if ($filter_date) {
    if ($where_clause) {
        $where_clause .= " AND DATE(purchase_date) = ?";
    } else {
        $where_clause .= " WHERE DATE(purchase_date) = ?";
    }
    $where_params[] = $filter_date;
    $param_types .= "s";
}

// ==========================================
// GET TOTAL COUNT OF RECORDS
// ==========================================
$count_sql = "SELECT COUNT(*) as total FROM tickets" . $where_clause;
$count_stmt = $conn->prepare($count_sql);

if ($where_params) {
    $count_stmt->bind_param($param_types, ...$where_params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_records = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_records / $records_per_page);

// ==========================================
// FETCH TICKETS WITH PAGINATION
// ==========================================
$sql = "SELECT 
            id, ticket_number, event_id, user_id, ticket_type, price, 
            purchase_date, status, payment_reference, amount_paid, account_name 
        FROM tickets 
        " . $where_clause . "
        ORDER BY FIELD(status, 'pending', 'expired', 'confirmed'), purchase_date DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$where_params[] = $records_per_page;
$where_params[] = $offset;
$param_types .= "ii";

if ($where_params) {
    $stmt->bind_param($param_types, ...$where_params);
}
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    die("SQL Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Admin Dashboard</title>
    <link rel="stylesheet" href="admin-style.css">
    <style>
        /* ==========================================
           NAVBAR STYLES
           ========================================== */
        .nav-links {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .nav-btn {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.3s;
            font-size: 15px;
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.3);
            cursor: pointer;
            display: inline-block;
        }

        .nav-btn:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.5);
            transform: translateY(-2px);
        }

        .logout-btn {
            background: rgba(220, 53, 69, 0.9);
            border-color: rgba(220, 53, 69, 1);
        }

        .logout-btn:hover {
            background: rgba(200, 35, 51, 1);
            border-color: rgba(200, 35, 51, 1);
        }

        /* ==========================================
           FILTER STYLES
           ========================================== */
        .filter-section {
            background: #2d3748;
            padding: 20px 30px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            display: flex;
            gap: 15px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: #e2e8f0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 10px 12px;
            border: 1px solid #4a5568;
            border-radius: 4px;
            font-size: 13px;
            background: #1a202c;
            color: white;
            transition: border-color 0.3s, background 0.3s;
        }

        .filter-group select:hover,
        .filter-group input:hover {
            border-color: #007bff;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 2px rgba(0, 123, 255, 0.2);
            background: #1a202c;
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
        }

        .btn-apply {
            padding: 10px 20px;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .btn-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
        }

        .btn-reset {
            padding: 10px 20px;
            background: #4a5568;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }

        .btn-reset:hover {
            background: #2d3748;
        }

        /* ==========================================
           MODAL STYLES
           ========================================== */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 0;
            border: none;
            border-radius: 10px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
            animation: slideIn 0.3s;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 20px 25px;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border-radius: 10px 10px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            margin: 0;
            border: none;
            padding: 0;
            font-size: 22px;
        }

        .modal-body {
            padding: 25px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .close-modal {
            color: white;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
            line-height: 1;
        }

        .close-modal:hover,
        .close-modal:focus {
            transform: scale(1.2);
        }

        /* ==========================================
           RESTOCK FORM STYLES
           ========================================== */
        .restock-form {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            align-items: end;
        }
        
        .form-field {
            display: flex;
            flex-direction: column;
        }
        
        .form-field label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #555;
            font-size: 14px;
        }
        
        .form-field select,
        .form-field input {
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-field select:focus,
        .form-field input:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .form-field small {
            margin-top: 5px;
            color: #888;
            font-size: 12px;
        }
        
        .restock-btn {
            padding: 12px 30px;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 10px rgba(0, 123, 255, 0.3);
            width: 100%;
        }
        
        .restock-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(0, 123, 255, 0.4);
        }
        
        .event-info {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            display: none;
        }

        /* ==========================================
           ALERT STYLES
           ========================================== */
        .alert {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* ==========================================
           DASHBOARD HEADER STYLES
           ========================================== */
        .dashboard-header {
            background: white;
            padding: 25px 30px;
            margin-bottom: 25px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border-left: 4px solid #007bff;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-title h2 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 24px;
            font-weight: 600;
        }

        .header-subtitle {
            margin: 0;
            color: #6c757d;
            font-size: 14px;
            font-weight: 400;
        }

        .header-stats {
            display: flex;
            gap: 25px;
        }

        .stat-item {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
        }

        .stat-label {
            font-size: 12px;
            color: #6c757d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 500;
        }

        .stat-value {
            font-size: 20px;
            color: #2c3e50;
            font-weight: 600;
            margin-top: 2px;
        }

        .status-legend {
            display: flex;
            gap: 20px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #495057;
            font-weight: 500;
        }

        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot.status-pending {
            background-color: #ffc107;
        }

        .dot.status-confirmed {
            background-color: #28a745;
        }

        .dot.status-expired {
            background-color: #dc3545;
        }

        /* ==========================================
           PAGINATION STYLES
           ========================================== */
        .pagination-container {
            margin: 30px 0;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 20px;
        }
        
        .pagination {
            display: flex;
            list-style: none;
            padding: 0;
            margin: 0;
            gap: 5px;
        }
        
        .pagination a,
        .pagination span {
            padding: 8px 12px;
            text-decoration: none;
            background: #f0f0f0;
            color: #333;
            border: 1px solid #ddd;
            border-radius: 4px;
            transition: all 0.3s ease;
        }
        
        .pagination a:hover {
            background: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .pagination .active {
            background: #007bff;
            color: white;
            border-color: #007bff;
            font-weight: bold;
        }
        
        .pagination .disabled {
            background: #e9ecef;
            color: #6c757d;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        .page-btn {
            padding: 8px 15px;
            font-weight: bold;
        }
        
        .ellipsis {
            padding: 8px 12px;
            border: none;
            background: none;
        }

        @media screen and (max-width: 768px) {
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }
            
            .nav-btn {
                font-size: 14px;
                padding: 8px 16px;
            }

            .filter-section {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .filter-group select,
            .filter-group input {
                width: 100%;
            }

            .filter-buttons {
                width: 100%;
            }

            .filter-buttons button,
            .filter-buttons a {
                flex: 1;
            }

            .dashboard-header {
                padding: 20px;
            }
            
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-stats {
                width: 100%;
                justify-content: space-between;
            }
            
            .stat-item {
                align-items: flex-start;
            }
            
            .header-title h2 {
                font-size: 20px;
            }
            
            .status-legend {
                gap: 15px;
            }

            .restock-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="navbar">
        <div class="logo">
            <h1>Admin Panel</h1>
        </div>
        <div class="nav-links">
            <button id="openRestockModal" class="nav-btn">
                Restock Tickets
            </button>
            <a href="admin_dashboard.php" class="nav-btn">Dashboard</a>
            <a href="admin_logout.php" class="nav-btn logout-btn">Logout</a>
        </div>
    </div>

    <div class="dashboard-container">
        <div class="dashboard-header">
            <div class="header-content">
                <div class="header-title">
                    <h2>Ticket Purchase Verification</h2>
                    <p class="header-subtitle">Review and manage ticket payments</p>
                </div>
                <div class="header-stats">
                    <?php if ($total_records > 0): ?>
                        <div class="stat-item">
                            <span class="stat-label">Total Records</span>
                            <span class="stat-value"><?php echo $total_records; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Current Page</span>
                            <span class="stat-value"><?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="status-legend">
                <span class="legend-item"><span class="dot status-pending"></span> Pending</span>
                <span class="legend-item"><span class="dot status-confirmed"></span> Confirmed</span>
                <span class="legend-item"><span class="dot status-expired"></span> Rejected</span>
            </div>
        </div>

        <!-- ==========================================
             FILTER SECTION
             ========================================== -->
        <div class="filter-section">
            <form method="GET" action="admin_dashboard.php" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap; width: 100%;">
                <div class="filter-group">
                    <label for="status">Status</label>
                    <select name="status" id="status">
                        <option value="">All</option>
                        <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $filter_status === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="expired" <?php echo $filter_status === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="date">Date</label>
                    <input type="date" name="date" id="date" value="<?php echo htmlspecialchars($filter_date); ?>">
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn-apply">Filter</button>
                    <a href="admin_dashboard.php" class="btn-reset">Reset</a>
                </div>
            </form>
        </div>

        <?php if (isset($_GET['update']) && $_GET['update'] == 'success'): ?>
            <div class='alert alert-success'>
                Ticket ID <?php echo htmlspecialchars($_GET['ticket']); ?> status updated to 
                <strong><?php echo ucfirst(htmlspecialchars($_GET['status'])); ?></strong> successfully.
            </div>
        <?php elseif (isset($_GET['update']) && $_GET['update'] == 'error'): ?>
            <div class='alert alert-error'>
                Error updating ticket status. Please check server logs.
            </div>
        <?php endif; ?>

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
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                            <?php
                                list($status_class, $display_status) = match ($row["status"]) {
                                    'confirmed' => ['status-confirmed', 'Confirmed'],
                                    'expired'   => ['status-expired', 'Expired'],
                                    default     => ['status-pending', 'Pending'],
                                };
                            ?>
                            <tr>
                                <td><?php echo $row["id"]; ?></td>
                                <td><?php echo $row["ticket_number"]; ?></td>
                                <td><?php echo $row["event_id"]; ?></td>
                                <td><?php echo $row["user_id"]; ?></td>
                                <td><?php echo htmlspecialchars($row["ticket_type"]); ?></td>
                                <td>₱<?php echo number_format($row["price"], 2); ?></td>
                                <td><?php echo date("Y-m-d", strtotime($row["purchase_date"])); ?></td>
                                <td><?php echo htmlspecialchars($row["payment_reference"]); ?></td>
                                <td>₱<?php echo number_format($row["amount_paid"], 2); ?></td>
                                <td><?php echo htmlspecialchars($row["account_name"]); ?></td>
                                <td><strong class='<?php echo $status_class; ?>'><?php echo $display_status; ?></strong></td>
                                <td>
                                    <div class='action-links'>
                                        <a href='update_status.php?id=<?php echo $row["id"]; ?>&action=accept&page=<?php echo $current_page; ?>&status=<?php echo $filter_status; ?>&date=<?php echo $filter_date; ?>' class='action-accept'>Confirm</a>
                                        <a href='update_status.php?id=<?php echo $row["id"]; ?>&action=reject&page=<?php echo $current_page; ?>&status=<?php echo $filter_status; ?>&date=<?php echo $filter_date; ?>' class='action-reject'>Reject</a>
                                        <a href='update_status.php?id=<?php echo $row["id"]; ?>&action=pending&page=<?php echo $current_page; ?>&status=<?php echo $filter_status; ?>&date=<?php echo $filter_date; ?>' class='action-pending'>Pending</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan='12' style='text-align: center; padding: 20px; color: #ccc;'>
                                No ticket requests found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1): ?>
            <div class="pagination-container">
                <div class="pagination">
                    <?php if ($current_page > 1): ?>
                        <a href="?page=1&status=<?php echo $filter_status; ?>&date=<?php echo $filter_date; ?>" class="page-btn">&laquo;&laquo; First</a>
                        <a href="?page=<?php echo $current_page - 1; ?>&status=<?php echo $filter_status; ?>&date=<?php echo $filter_date; ?>" class="page-btn">&laquo; Previous</a>
                    <?php else: ?>
                        <span class="page-btn disabled">&laquo;&laquo; First</span>
                        <span class="page-btn disabled">&laquo; Previous</span>
                    <?php endif; ?>

                    <?php
                    $start_page = max(1, $current_page - 2);
                    $end_page = min($total_pages, $current_page + 2);
                    
                    if ($start_page > 1): ?>
                        <a href="?page=1&status=<?php echo $filter_status; ?>&date=<?php echo $filter_date; ?>">1</a>
                        <?php if ($start_page > 2): ?>
                            <span class="ellipsis">...</span>
                        <?php endif;
                    endif;

                    for ($page = $start_page; $page <= $end_page; $page++): ?>
                        <?php if ($page == $current_page): ?>
                            <span class="active"><?php echo $page; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $page; ?>&status=<?php echo $filter_status; ?>&date=<?php echo $filter_date; ?>"><?php echo $page; ?></a>
                        <?php endif;
                    endfor;

                    if ($end_page < $total_pages): ?>
                        <?php if ($end_page < $total_pages - 1): ?>
                            <span class="ellipsis">...</span>
                        <?php endif; ?>
                        <a href="?page=<?php echo $total_pages; ?>&status=<?php echo $filter_status; ?>&date=<?php echo $filter_date; ?>"><?php echo $total_pages; ?></a>
                    <?php endif; ?>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="?page=<?php echo $current_page + 1; ?>&status=<?php echo $filter_status; ?>&date=<?php echo $filter_date; ?>" class="page-btn">Next &raquo;</a>
                        <a href="?page=<?php echo $total_pages; ?>&status=<?php echo $filter_status; ?>&date=<?php echo $filter_date; ?>" class="page-btn">Last &raquo;&raquo;</a>
                    <?php else: ?>
                        <span class="page-btn disabled">Next &raquo;</span>
                        <span class="page-btn disabled">Last &raquo;&raquo;</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div id="restockModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>🎫 Restock Event Tickets</h3>
                <span class="close-modal">&times;</span>
            </div>
            
            <div class="modal-body">
                <?php if ($restock_message): ?>
                    <div class="alert alert-success">
                        <?php echo htmlspecialchars($restock_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($restock_error): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($restock_error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="restock-form">
                    <div class="form-field">
                        <label for="event_id">Select Event *</label>
                        <select id="event_id" name="event_id" required onchange="updateEventInfo()">
                            <option value="">Select event</option>
                            <?php foreach ($events as $event): ?>
                                <option 
                                    value="<?php echo htmlspecialchars($event['id']); ?>" 
                                    data-tickets="<?php echo htmlspecialchars($event['available_tickets']); ?>"
                                    data-price="<?php echo htmlspecialchars($event['price']); ?>">
                                    <?php echo htmlspecialchars($event['event_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="event-info" class="event-info"></div>
                    </div>
                    
                    <div class="form-field">
                        <label for="quantity">Quantity to Add *</label>
                        <input type="number" id="quantity" name="quantity" min="1" required placeholder="Enter quantity">
                        <small>Number of tickets to add to inventory</small>
                    </div>
                    
                    <div class="form-field">
                        <label for="price">Ticket Price (₱) *</label>
                        <input type="number" id="price" name="price" step="0.01" min="0" required placeholder="0.00">
                        <small>Price per ticket</small>
                    </div>
                    
                    <div class="form-field">
                        <button type="submit" name="restock_tickets" class="restock-btn">
                            ➕ Add Tickets
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        const modal = document.getElementById('restockModal');
        const openBtn = document.getElementById('openRestockModal');
        const closeBtn = document.querySelector('.close-modal');
        
        openBtn.onclick = function(e) {
            e.preventDefault();
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        closeBtn.onclick = function() {
            modal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        window.onclick = function(event) {
            if (event.target == modal) {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        }
        
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.style.display === 'block') {
                modal.style.display = 'none';
                document.body.style.overflow = 'auto';
            }
        });
        
        <?php if ($restock_message || $restock_error): ?>
            modal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        <?php endif; ?>
        
        function updateEventInfo() {
            const select = document.getElementById('event_id');
            const infoDiv = document.getElementById('event-info');
            const priceInput = document.getElementById('price');
            
            if (select.selectedIndex > 0) {
                const option = select.options[select.selectedIndex];
                const currentTickets = option.getAttribute('data-tickets') || '0';
                const currentPrice = option.getAttribute('data-price') || '0';
                
                if (currentTickets && currentPrice) {
                    const ticketsNum = parseInt(currentTickets, 10);
                    const priceNum = parseFloat(currentPrice);
                    
                    infoDiv.innerHTML = `Current: ${ticketsNum} tickets available @ ₱${priceNum.toFixed(2)}`;
                    infoDiv.style.display = 'block';
                    priceInput.value = priceNum.toFixed(2);
                } else {
                    infoDiv.innerHTML = 'Unable to load event information';
                    infoDiv.style.display = 'block';
                }
            } else {
                infoDiv.innerHTML = '';
                infoDiv.style.display = 'none';
                priceInput.value = '';
            }
        }
    </script>
    
    <?php
    $stmt->close();
    $conn->close();
    ?>
</body>
</html>
