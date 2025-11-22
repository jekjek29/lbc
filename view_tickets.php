<?php
include('connection.php');

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Only fetch the needed fields + event_id for image mapping
$sql = "SELECT 
            ticket_number, price, status, payment_reference, payment_date, amount_paid, account_name, event_id
        FROM tickets
        WHERE id = ? AND user_id = ?";

$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die('<h2 style="color:#8DDEF1;text-align:center;margin-top:8rem;">SQL Error: ' . htmlspecialchars($conn->error) . '</h2>');
}

$stmt->bind_param("ii", $ticket_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$ticket = $result->fetch_assoc();
$stmt->close();
$conn->close();

if (!$ticket) {
    echo "<h2 style='text-align:center;color:#8DDEF1;margin-top:8rem;'>Ticket not found.</h2>";
    exit();
}

// Event image mapping
$eventImages = [
    3 => 'images/landing1.jpg',
    4 => 'images/landing2.jpg',
    5 => 'images/landing3.jpg',
    6 => 'images/landing4.jpg'
];

// Choose image or fallback
$imagePath = isset($eventImages[$ticket['event_id']]) ? $eventImages[$ticket['event_id']] : 'images/logo.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>My Ticket - Lost Boys Club</title>
<link rel="stylesheet" href="user-style.css" />
<style>
.ticket-card {
    max-width: 400px;
    margin: 4rem auto;
    padding: 2rem;
    border-radius: 1rem;
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    border: 2px solid #8DDEF1;
}
.ticket-label {
    color: #8DDEF1;
    font-weight: bold;
}
.ticket-value {
    color: #222;
}
.ticket-event-image {
    max-width: 150px;
    display: block;
    margin: 0.8rem auto 0.4rem;
    border-radius: 0.8rem;
    border: 2px solid #8DDEF1;
}
.print-button {
    margin: 2rem auto 0;
    display: block;
    background: #8DDEF1;
    color: #222;
    border: none;
    border-radius: 0.4rem;
    padding: 0.8rem 2rem;
    font-size: 1rem;
    cursor: pointer;
    font-weight: 600;
}
</style>
</head>
<body>
<main>
    <div class="ticket-card">
        <h1 style="text-align:center;color:#8DDEF1;">LBC EVENT TICKET</h1>
        <img src="<?php echo htmlspecialchars($imagePath); ?>" alt="Event Image" class="ticket-event-image" />
        <div>
            <span class="ticket-label">Ticket Number:</span>
            <span class="ticket-value"><?php echo htmlspecialchars($ticket['ticket_number']); ?></span>
        </div>
        <div>
            <span class="ticket-label">Account Name:</span>
            <span class="ticket-value"><?php echo htmlspecialchars($ticket['account_name']); ?></span>
        </div>
        <div>
            <span class="ticket-label">Price:</span>
            <span class="ticket-value">₱<?php echo number_format($ticket['price'], 2); ?></span>
        </div>
        <div>
            <span class="ticket-label">Status:</span>
            <span class="ticket-value"><?php echo htmlspecialchars($ticket['status']); ?></span>
        </div>
        <div>
            <span class="ticket-label">Payment Reference:</span>
            <span class="ticket-value"><?php echo htmlspecialchars($ticket['payment_reference']); ?></span>
        </div>
        <div>
            <span class="ticket-label">Payment Date:</span>
            <span class="ticket-value">
              <?php echo !empty($ticket['payment_date']) ? date('F j, Y g:i A', strtotime($ticket['payment_date'])) : 'N/A'; ?>
            </span>
        </div>
        <div>
            <span class="ticket-label">Amount Paid:</span>
            <span class="ticket-value">₱<?php echo number_format((float)$ticket['amount_paid'], 2); ?></span>
        </div>
    </div>
    <button onclick="window.print()" class="print-button">Print Ticket</button>
</main>
</body>
</html>
