<?php
include 'connection.php';
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: admin_login.php");
    exit();
}

$success_message = '';
$error_message = '';

// Handle ticket restock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restock_ticket'])) {
    $event_id = (int)$_POST['event_id'];
    $quantity = (int)$_POST['quantity'];
    $price = (float)$_POST['price'];
    
    // Validation
    if ($quantity < 1 || $quantity > 1000) {
        $error_message = "Quantity must be between 1 and 1000";
    } elseif ($price < 0) {
        $error_message = "Price cannot be negative";
    } elseif ($event_id < 1) {
        $error_message = "Please select a valid event";
    } else {
        // Check if event exists
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
            
            // Update available tickets and price
            $update_stmt = $conn->prepare("UPDATE events SET available_tickets = available_tickets + ?, ticket_price = ? WHERE id = ?");
            $update_stmt->bind_param("idi", $quantity, $price, $event_id);
            
            if ($update_stmt->execute()) {
                $new_total = $current_tickets + $quantity;
                $success_message = "Successfully added $quantity ticket(s) to event '$event_name'! Total available tickets: $new_total";
            } else {
                $error_message = "Error updating tickets: " . $conn->error;
            }
            $update_stmt->close();
        }
        $check_event->close();
    }
}

// Fetch events for dropdown
$events_query = $conn->query("SELECT id, event_name, available_tickets, ticket_price FROM events ORDER BY event_name");
$events = $events_query->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Restock Tickets - Admin</title>
    <link rel="stylesheet" href="admin-style.css">
    <style>
        .restock-container {
            max-width: 800px;
            margin: 40px auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .restock-header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid #007bff;
        }
        
        .restock-header h2 {
            color: #333;
            margin: 0 0 10px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .restock-header p {
            color: #666;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #555;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
            box-sizing: border-box;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            border-color: #007bff;
            outline: none;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn-restock {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 15px 40px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            width: 100%;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .btn-restock:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
            transition: background 0.3s;
        }
        
        .btn-back:hover {
            background: #5a6268;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #f5c6cb;
        }
        
        .helper-text {
            font-size: 12px;
            color: #888;
            margin-top: 5px;
        }
        
        .preview-box {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            border: 2px solid #007bff;
        }
        
        .preview-box h4 {
            margin: 0 0 10px 0;
            color: #007bff;
        }

        .event-info {
            background: #fff;
            padding: 15px;
            border-radius: 6px;
            margin-top: 10px;
            border-left: 4px solid #28a745;
        }

        .event-info p {
            margin: 5px 0;
            color: #555;
        }

        .event-info strong {
            color: #333;
        }

        @media (max-width: 768px) {
            .form-row {
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
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="restock-container">
        <a href="admin_dashboard.php" class="btn-back">← Back to Dashboard</a>
        
        <div class="restock-header">
            <h2>🎫 Restock Event Tickets</h2>
            <p>Add tickets directly to the events table</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success">
                ✓ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error">
                ✗ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" id="restockForm">
            <div class="form-group">
                <label for="event_id">Select Event *</label>
                <select id="event_id" name="event_id" required>
                    <option value="">-- Select an Event --</option>
                    <?php foreach ($events as $event): ?>
                        <option value="<?php echo $event['id']; ?>" 
                                data-current="<?php echo $event['available_tickets'] ?? 0; ?>"
                                data-price="<?php echo $event['ticket_price'] ?? 0; ?>"
                                data-name="<?php echo htmlspecialchars($event['event_name']); ?>">
                            <?php echo htmlspecialchars($event['event_name']); ?> 
                            (Current: <?php echo $event['available_tickets'] ?? 0; ?> tickets)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="helper-text">Select which event to restock</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="quantity">Quantity to Add *</label>
                    <input type="number" id="quantity" name="quantity" required min="1" max="1000" value="1">
                    <div class="helper-text">Number of tickets to add (1-1000)</div>
                </div>

                <div class="form-group">
                    <label for="price">Ticket Price (₱) *</label>
                    <input type="number" id="price" name="price" step="0.01" required min="0" value="0">
                    <div class="helper-text">Price per ticket in Pesos</div>
                </div>
            </div>

            <div class="preview-box" id="previewBox" style="display: none;">
                <h4>📋 Restock Preview</h4>
                <div class="event-info" id="eventInfo"></div>
                <p id="previewText" style="margin-top: 15px; color: #333;"></p>
            </div>

            <button type="submit" name="restock_ticket" class="btn-restock">
                ✓ Add Tickets to Event
            </button>
        </form>
    </div>

    <script>
        const form = document.getElementById('restockForm');
        const previewBox = document.getElementById('previewBox');
        const eventInfo = document.getElementById('eventInfo');
        const previewText = document.getElementById('previewText');
        const eventSelect = document.getElementById('event_id');
        const priceInput = document.getElementById('price');
        
        // Auto-fill price when event is selected
        eventSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const currentPrice = selectedOption.getAttribute('data-price');
            if (currentPrice && currentPrice > 0) {
                priceInput.value = parseFloat(currentPrice).toFixed(2);
            }
            updatePreview();
        });
        
        // Live preview
        form.addEventListener('input', updatePreview);
        
        function updatePreview() {
            const eventId = eventSelect.value;
            const selectedOption = eventSelect.options[eventSelect.selectedIndex];
            const eventName = selectedOption.getAttribute('data-name');
            const currentTickets = parseInt(selectedOption.getAttribute('data-current')) || 0;
            const quantity = parseInt(document.getElementById('quantity').value) || 0;
            const price = parseFloat(document.getElementById('price').value) || 0;
            
            if (eventId && quantity > 0 && price >= 0) {
                const newTotal = currentTickets + quantity;
                const totalValue = (price * quantity).toFixed(2);
                
                eventInfo.innerHTML = `
                    <p><strong>Event:</strong> ${eventName}</p>
                    <p><strong>Event ID:</strong> ${eventId}</p>
                    <p><strong>Current Available Tickets:</strong> ${currentTickets}</p>
                `;
                
                previewText.innerHTML = `
                    <strong>Action:</strong> Adding <span style="color: #28a745; font-weight: bold;">${quantity}</span> ticket(s) 
                    at <strong>₱${price.toFixed(2)}</strong> each<br>
                    <strong>New Total Available:</strong> <span style="color: #007bff; font-weight: bold;">${newTotal}</span> tickets<br>
                    <strong>Total Value:</strong> <span style="color: #dc3545; font-weight: bold;">₱${totalValue}</span>
                `;
                previewBox.style.display = 'block';
            } else {
                previewBox.style.display = 'none';
            }
        }
        
        // Form validation
        form.addEventListener('submit', function(e) {
            const quantity = parseInt(document.getElementById('quantity').value);
            const eventName = eventSelect.options[eventSelect.selectedIndex].getAttribute('data-name');
            
            if (quantity < 1 || quantity > 1000) {
                e.preventDefault();
                alert('Quantity must be between 1 and 1000');
                return false;
            }
            
            return confirm(`Are you sure you want to add ${quantity} ticket(s) to "${eventName}"?`);
        });
    </script>
</body>
</html>

<?php $conn->close(); ?>
