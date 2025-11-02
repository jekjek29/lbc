<?php
session_start();

// In a real application, you would verify the user's access to this ticket
// and fetch the ticket details from the database
$ticket = [
    'ticket_number' => 'LBC-' . strtoupper(uniqid()),
    'event' => [
        'title' => 'Sample Event Name',
        'date' => 'October 15, 2023',
        'time' => '8:00 PM',
        'venue' => 'Sample Venue, City',
        'address' => '123 Event St., Barangay, City',
        'organizer' => 'Lost Boys Club'
    ],
    'holder' => [
        'name' => $_SESSION['first_name'] . ' ' . $_SESSION['last_name'],
        'email' => $_SESSION['email'] ?? 'user@example.com'
    ],
    'details' => [
        'type' => 'General Admission',
        'price' => 500.00,
        'quantity' => 1,
        'order_date' => date('F j, Y'),
        'order_time' => date('g:i A')
    ]
];

$event = [
    'id' => $ticket['ticket_number'],
    'title' => 'Event Title',
    'date' => 'Date',
    'time' => '',
    'location' => 'Venue Name, City',
    'description' => 'This is a sample event description. In a real application, this would be pulled from your database.',
    'image' => 'images/logo.jpg',
    'price' => 500, // Single price for all tickets
    'available' => 200 // Total available tickets
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Ticket - Lost Boys Club</title>
    <link rel="stylesheet" href="user-style.css">
</head>
<body>
    <div class="ticket-container">
        <div class="ticket-header">
            <h1>LOST BOYS CLUB</h1>
            <p>OFFICIAL EVENT TICKET</p>
        </div>
        
        <div class="ticket-body">
            <div class="ticket-details">
                <h2 class="event-title"><?php echo htmlspecialchars($ticket['event']['title']); ?></h2>
                
                <div class="info-group">
                    <p class="info-label">Date & Time</p>
                    <p class="info-value">
                        <?php echo htmlspecialchars($ticket['event']['date']); ?><br>
                        <?php echo htmlspecialchars($ticket['event']['time']); ?>
                    </p>
                </div>
                
                <div class="info-group">
                    <p class="info-label">Venue</p>
                    <p class="info-value">
                        <?php echo htmlspecialchars($ticket['event']['venue']); ?><br>
                        <span style="font-weight: normal; font-size: 0.9rem;">
                            <?php echo htmlspecialchars($ticket['event']['address']); ?>
                        </span>
                    </p>
                </div>
                
                <div class="info-group">
                    <p class="info-label">Ticket Holder</p>
                    <p class="info-value"><?php echo htmlspecialchars($ticket['holder']['name']); ?></p>
                </div>
                
                <div class="info-group">
                    <p class="info-label">Ticket Type</p>
                    <p class="info-value"><?php echo htmlspecialchars($ticket['details']['type']); ?></p>
                </div>
                
                <div class="info-group">
                    <p class="info-label">Order Date</p>
                    <p class="info-value">
                        <?php echo $ticket['details']['order_date']; ?>
                        <span style="font-weight: normal;">at <?php echo $ticket['details']['order_time']; ?></span>
                    </p>
                </div>
            </div>
            
            <div class="ticket-qr">
                <div class="ticket-number">
                    <?php echo $ticket['ticket_number']; ?>
                </div>
                
                <div class="qr-code">
                    <!-- In a real application, generate a QR code here -->
                    QR Code<br>Will Be Here
                </div>
                
                <div style="text-align: center;">
                    <p style="margin: 0 0 10px; font-weight: bold;">Scan this QR code at the entrance</p>
                    <p style="margin: 0; font-size: 0.8rem; color: #666;">
                        This ticket admits 1 person
                    </p>
                </div>
            </div>
        </div>
        
        <div class="ticket-footer">
            <p>Present this ticket (digital or printed) at the event entrance. Lost tickets cannot be replaced.</p>
            <p>For assistance, contact: support@lostboysclub.com</p>
        </div>
    </div>
    
    <button onclick="window.print()" class="print-button">Print Ticket</button>
    
    <script>
        // In a real application, you would generate a QR code here
        // For example, using a library like QRCode.js or similar
        // Example:
        // new QRCode(document.querySelector(".qr-code"), {
        //     text: "<?php echo $ticket['ticket_number']; ?>",
        //     width: 180,
        //     height: 180
        // });
    </script>
</body>
</html>
