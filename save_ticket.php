<?php
include "connection.php";

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // 1. Validate and Sanitize the Data
    $user_id = $_SESSION['user_id'];
    $event_id = filter_var($data['event_id'] ?? null, FILTER_VALIDATE_INT);
    $quantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT); // This is the total number of tickets
    $price = filter_var($data['price_per_ticket'] ?? null, FILTER_VALIDATE_FLOAT); // NOTE: Changed from 'price' to 'price_per_ticket' in JS/PHP
    $amount_paid = filter_var($data['amount_paid'] ?? null, FILTER_VALIDATE_FLOAT);
    $payment_reference = htmlspecialchars($data['payment_reference'] ?? '');
    $payment_date = htmlspecialchars($data['payment_date'] ?? '');
    $account_name = htmlspecialchars($data['account_name'] ?? '');
    $account_number = htmlspecialchars($data['account_number'] ?? '');

    // Check if required fields are present and valid
    if (!$event_id || $quantity < 1 || !$price || !$amount_paid || empty($payment_reference) || empty($payment_date) || empty($account_name) || empty($account_number)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing or invalid data: Check all fields, especially quantity.']);
        exit();
    }
    
    // Fixed variables for insertion
    $ticket_type = 'General Admission'; 
    $purchase_date = date('Y-m-d H:i:s');
    $status = 'Pending Verification'; // Status should match the text used in dashboard.php
    
    // Variables to track success
    $success_count = 0;
    $failed_count = 0;

    // 2. Prepare the SQL statement outside the loop
    // This is the single ticket insertion query
    $sql = "INSERT INTO tickets (ticket_number, event_id, user_id, ticket_type, price, purchase_date, status, payment_reference, payment_date, amount_paid, account_name, account_number) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

    try {
        // Prepare statement (assuming $conn is a mysqli object)
        $stmt = $conn->prepare($sql);
        
        // 3. Loop to insert multiple tickets
        for ($i = 0; $i < $quantity; $i++) {
            // Generate a unique ticket number for EACH ticket
            $ticket_number = uniqid('TKT', true) . '-' . ($i + 1);

            // Bind parameters for the current ticket
            // Note: The amount_paid is for the *entire* order, so we bind the single ticket price here.
            // If your tickets table has a 'total_amount_paid' field, you could insert the total there.
            // For now, we will track the individual ticket price.
            $stmt->bind_param(
                "siisdssssdss",
                $ticket_number,
                $event_id,
                $user_id,
                $ticket_type,
                $price, // Single ticket price
                $purchase_date,
                $status,
                $payment_reference,
                $payment_date,
                $price, // Use single ticket price for amount paid on this specific ticket record
                $account_name,
                $account_number
            );

            if ($stmt->execute()) {
                $success_count++;
            } else {
                $failed_count++;
                error_log("Ticket insert failed: " . $stmt->error);
            }
        }

        $stmt->close(); 
        
        // 4. Return Final Response
        if ($success_count > 0 && $failed_count == 0) {
            echo json_encode(['success' => true, 'message' => "✅ {$success_count} ticket(s) saved with status PENDING. Verification required."]);
        } elseif ($success_count > 0 && $failed_count > 0) {
            http_response_code(200); // Partial success is still a success response
            echo json_encode(['success' => true, 'message' => "⚠️ Partial success: {$success_count} tickets saved, but {$failed_count} failed."]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => '❌ All ticket insertions failed.']);
        }

    } catch (Exception $e) {
        error_log("Exception during transaction: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'An unexpected server error occurred.']);
    }

    $conn->close();

} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
}
?>