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
    <link rel="stylesheet" href="styles.css">
    <style>
        @page {
            size: A4;
            margin: 0;
        }
        
        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        
        .ticket-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        
        .ticket-header {
            background: #1a1a2e;
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .ticket-header h1 {
            margin: 0;
            color: #e94560;
            font-size: 2rem;
        }
        
        .ticket-header p {
            margin: 5px 0 0;
            opacity: 0.8;
        }
        
        .ticket-body {
            display: flex;
            flex-wrap: wrap;
        }
        
        .ticket-details {
            flex: 1;
            padding: 30px;
            min-width: 300px;
        }
        
        .ticket-qr {
            flex: 1;
            padding: 30px;
            background: #f9f9f9;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 300px;
        }
        
        .event-title {
            color: #e94560;
            font-size: 1.8rem;
            margin: 0 0 20px;
            border-bottom: 2px solid #e94560;
            padding-bottom: 10px;
        }
        
        .info-group {
            margin-bottom: 20px;
        }
        
        .info-label {
            font-size: 0.9rem;
            color: #666;
            margin: 0 0 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .info-value {
            font-size: 1.1rem;
            font-weight: bold;
            margin: 0;
        }
        
        .qr-code {
            width: 200px;
            height: 200px;
            background: #fff;
            border: 1px solid #ddd;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            color: #999;
        }
        
        .ticket-number {
            background: #1a1a2e;
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .ticket-footer {
            background: #f5f5f5;
            padding: 15px 30px;
            text-align: center;
            font-size: 0.9rem;
            color: #666;
            border-top: 1px solid #eee;
        }
        
        .print-button {
            display: block;
            width: 200px;
            margin: 30px auto;
            padding: 12px 20px;
            background: #e94560;
            color: white;
            text-align: center;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            text-decoration: none;
        }
        
        .print-button:hover {
            background: #d13354;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            .ticket-container, .ticket-container * {
                visibility: visible;
            }
            .ticket-container {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                box-shadow: none;
            }
            .print-button {
                display: none;
            }
        }
    </style>
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
