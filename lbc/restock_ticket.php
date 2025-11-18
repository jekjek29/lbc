<?php
include('connection.php');

// Check admin access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$success_message = "";
$error_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = filter_var($_POST['event_id'], FILTER_VALIDATE_INT);
    $quantity = filter_var($_POST['quantity'], FILTER_VALIDATE_INT);
    $price = filter_var($_POST['price'], FILTER_VALIDATE_FLOAT);

    if ($quantity < 1 || $quantity > 1000) {
        $error_message = "Quantity must be between 1 and 1000";
    } elseif ($price < 0) {
        $error_message = "Price cannot be negative";
    } elseif ($event_id < 1) {
        $error_message = "Please select a valid event";
    } else {
        $check_event = $conn->prepare("SELECT id, event_name, available_tickets FROM events WHERE id = ?");
        $check_event->bind_param("i", $event_id);
        $check_event->execute();
        $event_result = $check_event->get_result();

        if ($event_result->num_rows === 0) {
            $error_message = "Event ID $event_id does not exist";
        } else {
            $event_data = $event_result->fetch_assoc();
            $current_tickets = $event_data['available_tickets'] ?? 0;
            $event_name = $event_data['event_name'];

            $update_stmt = $conn->prepare("UPDATE events SET available_tickets = available_tickets + ?, ticket_price = ? WHERE id = ?");
            $update_stmt->bind_param("idi", $quantity, $price, $event_id);

            if ($update_stmt->execute()) {
                $new_total = $current_tickets + $quantity;
                $success_message = "✅ Successfully added $quantity ticket(s) to '$event_name'! Total: $new_total";
            } else {
                $error_message = "❌ Error updating tickets: " . $conn->error;
            }
            $update_stmt->close();
        }
        $check_event->close();
    }
}

// Fetch events
$events_query = $conn->query("SELECT id, event_name, available_tickets, ticket_price FROM events ORDER BY event_name");
$events = $events_query->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restock Tickets - Admin</title>
    <link rel="stylesheet" href="admin-style.css">
</head>
<body>
    <?php include('admin_navbar.php'); ?>
    
    <div class="admin-container">
        <div class="admin-header">
            <h1>🎫 Restock Tickets</h1>
            <p>Add tickets to events and update pricing</p>
        </div>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo $success_message; ?></div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <div class="admin-form-container">
            <h2>📦 Add Tickets to Event</h2>
            <form method="POST" action="">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="event_id">Select Event *</label>
                        <select name="event_id" id="event_id" required>
                            <option value="">-- Select Event --</option>
                            <?php foreach ($events as $event): ?>
                                <option value="<?php echo $event['id']; ?>">
                                    <?php echo htmlspecialchars($event['event_name']); ?> 
                                    (Current: <?php echo $event['available_tickets']; ?> tickets)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="quantity">Quantity to Add *</label>
                        <input type="number" name="quantity" id="quantity" min="1" max="1000" required>
                        <span class="form-hint">Min: 1, Max: 1000</span>
                    </div>
                    
                    <div class="form-group">
                        <label for="price">Ticket Price (₱) *</label>
                        <input type="number" name="price" id="price" min="0" step="0.01" required>
                        <span class="form-hint">Set or update ticket price</span>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary">Add Tickets</button>
            </form>
        </div>
        
        <div class="events-list">
            <h2 style="color: var(--light-cyan); margin-bottom: 1.5rem;">📋 Current Events</h2>
            <?php foreach ($events as $event): ?>
                <div class="event-item">
                    <div class="event-info">
                        <h4><?php echo htmlspecialchars($event['event_name']); ?></h4>
                        <p>Event ID: <?php echo $event['id']; ?></p>
                    </div>
                    <div class="event-stats">
                        <div class="event-stat">
                            <span class="event-stat-number"><?php echo $event['available_tickets']; ?></span>
                            <span class="event-stat-label">Available</span>
                        </div>
                        <div class="event-stat">
                            <span class="event-stat-number">₱<?php echo number_format($event['ticket_price'], 2); ?></span>
                            <span class="event-stat-label">Price</span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
