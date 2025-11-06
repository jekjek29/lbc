<?php
include 'connection.php';

// Get parameters
$ticket_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$action = $_GET['action'] ?? '';

if (!$ticket_id || !in_array($action, ['accept', 'reject', 'pending'])) {
    header("Location: admin_dashboard.php?update=error&message=Invalid+parameters");
    exit();
}

try {
    // Start transaction
    $conn->begin_transaction();
    
    // Get ticket details
    $get_sql = "SELECT event_id, status FROM tickets WHERE id = ?";
    $stmt = $conn->prepare($get_sql);
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();
    $stmt->close();
    
    if (!$ticket) {
        throw new Exception('Ticket not found');
    }
    
    $old_status = $ticket['status'];
    $event_id = $ticket['event_id'];
    
    // Map action to status
    $new_status = match($action) {
        'accept' => 'confirmed',
        'reject' => 'expired',
        'pending' => 'pending',
        default => 'pending'
    };
    
    // Update ticket status
    $update_sql = "UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($update_sql);
    $stmt->bind_param("si", $new_status, $ticket_id);
    $stmt->execute();
    $stmt->close();
    
    // **UPDATE AVAILABILITY**
    
    // If changing TO confirmed FROM non-confirmed: DECREMENT
    if ($new_status === 'confirmed' && $old_status !== 'confirmed') {
        $decrement_sql = "UPDATE events 
                         SET available_tickets = available_tickets - 1 
                         WHERE id = ? AND available_tickets > 0";
        $stmt = $conn->prepare($decrement_sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('No available tickets');
        }
        $stmt->close();
    }
    
    // If changing FROM confirmed TO anything else: INCREMENT
    if ($old_status === 'confirmed' && $new_status !== 'confirmed') {
        $increment_sql = "UPDATE events 
                         SET available_tickets = available_tickets + 1 
                         WHERE id = ?";
        $stmt = $conn->prepare($increment_sql);
        $stmt->bind_param("i", $event_id);
        $stmt->execute();
        $stmt->close();
    }
    
    $conn->commit();
    
    header("Location: admin_dashboard.php?update=success&ticket=" . $ticket_id . "&status=" . $new_status);
    exit();
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error: " . $e->getMessage());
    header("Location: admin_dashboard.php?update=error&message=" . urlencode($e->getMessage()));
    exit();
}

$conn->close();
?>
