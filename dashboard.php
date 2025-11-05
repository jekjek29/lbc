<?php
include "connection.php";

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Hardcoded image mapping (since your database has path issues)
$eventImages = [
    1 => 'images/logo.jpg',
    2 => 'images/logo.jpg',
    3 => 'images/logo.jpg',
    4 => 'images/logo.jpg'
];

// **FETCH EVENTS WITH LIVE AVAILABILITY FROM DATABASE**
$sql = "SELECT id, 
               title as name, 
               CONCAT(event_date, ' @ ', DATE_FORMAT(event_time, '%h:%i %p')) as datetime, 
               price, 
               available_tickets,
               capacity
        FROM events 
        WHERE event_date >= CURDATE() 
        ORDER BY event_date ASC";

$result = $conn->query($sql);
$events = [];

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lost Boys Club Dashboard</title>
    <link rel="stylesheet" href="user-style.css">
</head>
<body>
    <header class="navbar">
        <div class="logo">
            <img src="images/logo.jpg" alt="LBC Logo" class="navbar-logo">
            <h1>Lost Boys Club</h1>
        </div>
        <nav class="nav-links">
            <a href="#">Home</a>
            <a href="ticket.php">Tickets</a>
            <a href="account.php">Account</a>
        </nav>
        <div class="user-info">
            <span class="user-info">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span> 
            <a href="logout.php">Logout</a>
        </div>
    </header>

    <main class="dashboard-container">
        <h2>UPCOMING EVENTS</h2>
        <div class="event-list">
            <?php
            foreach ($events as $event) {
                $js_event_name = addslashes($event['name']);
                $js_event_datetime = addslashes($event['datetime']);
                $imagePath = isset($eventImages[$event['id']]) ? $eventImages[$event['id']] : 'images/logo.jpg';

                echo '<div class="event" data-event-id="' . $event['id'] . '">';
                echo '<img src="' . htmlspecialchars($imagePath) . '" 
                           alt="' . htmlspecialchars($event['name']) . '" 
                           class="event-image" 
                           onerror="this.src=\'images/logo.jpg\'">';
                echo '<div class="event-details">';
                echo '<h3>' . htmlspecialchars($event['name']) . '</h3>';
                echo '<p>' . htmlspecialchars($event['datetime']) . '</p>';
                
                // **DISPLAY AVAILABILITY**
                echo '<p class="availability">Available: ' . htmlspecialchars($event['available_tickets']) . ' / ' . htmlspecialchars($event['capacity']) . ' tickets</p>';
                
                // **SHOW BUTTON OR SOLD OUT**
                if ($event['available_tickets'] > 0) {
                    echo '<button 
                            class="buy-tickets-btn" 
                            onclick="openStep1(\'' . $js_event_name . '\', \'' . $js_event_datetime . '\', ' . htmlspecialchars($event['price']) . ', ' . $event['id'] . ')">
                            Buy Tickets
                          </button>';
                } else {
                    echo '<button class="buy-tickets-btn sold-out" disabled>Sold Out</button>';
                }
                
                echo '</div>';
                echo '</div>';
            }
            ?>
        </div>
    </main>
    
    
    <div id="modalStep1" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAllModals()">&times;</span>
            <div class="modal-header">
                <h3>Step 1: Select Ticket Quantity</h3>
                <h4 id="modalEventName"></h4>
                <p id="modalEventDateTime"></p>
                <input type="hidden" id="modalEventId">
            </div>
            <form id="ticketQuantityForm">
                <div class="ticket-details">
                    <p>Price per Ticket: <span id="modalTicketPrice"></span></p>
                    <div class="form-group">
                        <label for="quantity">Quantity:</label>
                        <input type="number" id="quantity" name="quantity" min="1" value="1" required>
                    </div>
                    <p>Total Amount: <strong id="totalAmountDisplay">₱0.00</strong></p>
                </div>
                <button type="button" class="checkout-btn" onclick="nextModal(1, 2)">Proceed to Payment</button>
            </form>
        </div>
    </div>

    <div id="modalStep2" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAllModals()">&times;</span>
            <h2>Step 2: Scan to Pay via G-Cash</h2>
            <div class="qr-code-container">
                <img src="images/gcash-qr.jpg" alt="G-Cash QR Code">
                <p>Please send <strong id="modal2Amount">₱0.00</strong> to the account associated with this QR code.</p>
                <p>Account Name: <strong>Lost Boys Club Payments</strong></p>
            </div>
            <p>After a successful transaction, click 'Next' to input your payment details.</p>
            <div class="button-group">
                <button type="button" class="btn-confirm no-btn" onclick="nextModal(2, 1)">Back</button>
                <button type="button" class="checkout-btn" onclick="nextModal(2, 3)">Next: Enter Payment Details</button>
            </div>
        </div>
    </div>

    <div id="modalStep3" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAllModals()">&times;</span>
            <h2>Step 3: Payment Verification Details</h2>
            <form id="paymentForm">
                <div class="form-group">
                    <label for="amount">Amount Paid:</label>
                    <input type="text" id="amount" name="amount" required placeholder="Total amount to be paid" readonly>
                </div>
                <div class="form-group">
                    <label for="ref_number">G-Cash Reference Number:</label>
                    <input type="text"
                        id="ref_number"
                        name="ref_number"
                        required
                        placeholder="Enter 14-digit reference number"
                        maxlength="14"
                        pattern="\d{14}"
                        inputmode="numeric"
                        title="Reference number must be exactly 14 digits">
                </div>
                <div class="form-group">
                    <label for="payment_date">Date and Time of Payment:</label>
                    <input type="datetime-local" id="payment_date" name="payment_date" required>
                </div>
                <div class="form-group">
                    <label for="account_name">G-Cash Account Name (Used for Payment):</label>
                    <input type="text" id="account_name" name="account_name" required placeholder="Your G-Cash account name">
                </div>
                <div class="form-group">
                    <label for="account_number">G-Cash Number (Used for Payment):</label>
                    <input type="text"
                        id="account_number"
                        name="account_number"
                        required
                        placeholder="Your G-Cash phone number"
                        maxlength="11"
                        pattern="\d{11}"
                        inputmode="numeric"
                        title="G-Cash number must be exactly 11 digits">
                </div>
                <button type="button" class="checkout-btn" onclick="nextModal(3, 4)">Confirm Details</button>
            </form>
        </div>
    </div>

    <div id="modalStep4" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAllModals()">&times;</span>
            <h2>Step 4: Final Confirmation</h2>
            <p class="confirmation-text">Please ensure your payment details are correct, as we rely on this information for verification.</p>
            <p>Proceed to finalize your order?</p>
            <div class="button-group">
                <button type="button" class="btn-confirm yes-btn" onclick="submitOrderAndNext(4, 5)">Yes, Submit Order</button>
                <button type="button" class="btn-confirm no-btn" onclick="nextModal(4, 3)">No, Go Back to Details</button>
            </div>
        </div>
    </div>

    <div id="modalStep5" class="modal">
        <div class="modal-content">
            <span class="close-btn" onclick="closeAllModals()">&times;</span>
            <h2>Thank you for your Order! 🎉</h2>
            <p class="confirmation-text">Your payment details have been recorded and are currently being verified.</p>
            <p>You will receive your ticket confirmation shortly.</p>
            <button type="button" class="checkout-btn" onclick="closeAllModals()">Close</button>
        </div>
    </div>
    
    <script>
        // Global variables to store ticket data temporarily
        let currentEventPrice = 0;
        let currentEventId = 0;

        // References to all modals (Adjusted to match 5 steps)
        const modal1 = document.getElementById("modalStep1");
        const modal2 = document.getElementById("modalStep2");
        const modal3 = document.getElementById("modalStep3");
        const modal4 = document.getElementById("modalStep4");
        const modal5 = document.getElementById("modalStep5");

        // Function to close all modals and clear overlay
        function closeAllModals() {
            modal1.style.display = "none";
            modal2.style.display = "none";
            modal3.style.display = "none";
            modal4.style.display = "none";
            modal5.style.display = "none";
        }

        // Function to open the first step and populate event details
        function openStep1(name, datetime, price, id) {
            currentEventPrice = price;
            currentEventId = id;
            document.getElementById('modalEventName').textContent = name;
            document.getElementById('modalEventDateTime').textContent = datetime;
            document.getElementById('modalTicketPrice').textContent = '₱' + price.toFixed(2);
            document.getElementById('modalEventId').value = id;

            document.getElementById('quantity').value = 1;
            const initialTotal = (currentEventPrice * 1).toFixed(2);
            document.getElementById('totalAmountDisplay').textContent = '₱' + initialTotal;

            document.getElementById('quantity').oninput = function() {
                const quantity = parseInt(this.value) || 1;
                const totalAmount = (currentEventPrice * quantity).toFixed(2);
                document.getElementById('totalAmountDisplay').textContent = '₱' + totalAmount;
            };

            modal1.style.display = "block";
        }

        // Function to navigate between modal steps (Updated for 5 steps)
        function nextModal(currentStep, nextStep) {
            if (currentStep === 1 && nextStep === 2) {
                const quantity = parseInt(document.getElementById('quantity').value);
                if (quantity < 1) {
                    alert('Please select at least 1 ticket.');
                    return;
                }
                const totalAmount = (currentEventPrice * quantity).toFixed(2);
                document.getElementById('modal2Amount').textContent = '₱' + totalAmount;
            }
            if (currentStep === 2 && nextStep === 3) {
                const quantity = parseInt(document.getElementById('quantity').value);
                const totalAmount = (currentEventPrice * quantity).toFixed(2);
                document.getElementById('amount').value = totalAmount; 
            }
            if (currentStep === 3 && nextStep === 4) {
                const form = document.getElementById('paymentForm');
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return; 
                }
            }
            closeAllModals();

            let nextModalElement;
            if (nextStep === 1) nextModalElement = modal1;
            else if (nextStep === 2) nextModalElement = modal2;
            else if (nextStep === 3) nextModalElement = modal3;
            else if (nextStep === 4) nextModalElement = modal4;
            else if (nextStep === 5) nextModalElement = modal5;
            if (nextModalElement) {
                nextModalElement.style.display = "block";
            }
        }

        // This function is called only from Step 4 (Confirmation)
        function submitOrderAndNext(currentStep, nextStep) {
            const paymentForm = document.getElementById('paymentForm');
            if (!paymentForm.checkValidity()) {
                paymentForm.reportValidity();
                alert('Please check the payment details form for errors first.');
                return;
            }
            const orderData = {
                user_id: <?php echo $_SESSION['user_id'] ?? 'null'; ?>,
                event_id: document.getElementById('modalEventId').value,
                quantity: parseInt(document.getElementById('quantity').value),
                price_per_ticket: currentEventPrice, 
                amount_paid: parseFloat(document.getElementById('amount').value),
                payment_reference: document.getElementById('ref_number').value,
                payment_date: document.getElementById('payment_date').value,
                account_name: document.getElementById('account_name').value,
                account_number: document.getElementById('account_number').value,
                payment_method: 'GCash',
                order_status: 'Pending Verification'
            };
            fetch('save_ticket.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(orderData),
            })
            .then(response => {
                if (!response.ok) {
                    return response.json().then(err => { throw new Error(err.message || 'An error occurred on the server.'); });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    nextModal(currentStep, nextStep);
                    console.log(data.message);
                } else {
                    alert('Error submitting order: ' + data.message);
                }
            })
            .catch((error) => {
                alert('Could not submit your order due to a network or server error. Please check the console for details.');
                console.error('Error:', error);
            });
        }

        // Enforce digit-only and maxlength for GCash Reference and Account Number in Step 3
        document.addEventListener('DOMContentLoaded', function () {
            var refField = document.getElementById('ref_number');
            if (refField) {
                refField.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').slice(0, 14);
                });
            }
            var accField = document.getElementById('account_number');
            if (accField) {
                accField.addEventListener('input', function () {
                    this.value = this.value.replace(/\D/g, '').slice(0, 11);
                });
            }
        });
    </script>
</body>
</html>
