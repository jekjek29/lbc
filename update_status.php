<?php
include 'connection.php';

// Get parameters from POST (not GET)
$ticket_id     = filter_var($_POST['ticket_id'] ?? null, FILTER_VALIDATE_INT);
$ticket_number = $_POST['ticket_number'] ?? '';
$action        = $_POST['action'] ?? '';
$redirect      = $_POST['redirect'] ?? ''; // from dashboard forms

// Accept only the allowed actions
$allowed_actions = ['accept', 'reject', 'pending'];
$status_map = [
    'accept'  => 'confirmed',
    'reject'  => 'rejected',
    'pending' => 'pending'
];

if (!$ticket_id || !in_array($action, $allowed_actions, true)) {
    $base = $redirect !== '' ? $redirect : 'admin_dashboard.php';
    $sep  = (strpos($base, '?') !== false) ? '&' : '?';

    header('Location: ' . $base . $sep .
        'update=error&message=' . urlencode('Invalid parameters'));
    exit();
}

$new_status = $status_map[$action];

try {
    // Start transaction
    $conn->begin_transaction();

    // Get ticket details
    $stmt = $conn->prepare("SELECT event_id, status FROM tickets WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $ticket = $result->fetch_assoc();
    $stmt->close();

    if (!$ticket) {
        throw new Exception('Ticket not found');
    }

    $old_status = $ticket['status'];
    $event_id   = $ticket['event_id'];

    // Only update if status is changing
    if ($old_status !== $new_status) {
        // Update ticket status
        $stmt = $conn->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("si", $new_status, $ticket_id);
        $stmt->execute();
        $stmt->close();

        // Update event ticket availability
        if ($new_status === 'confirmed' && $old_status !== 'confirmed') {
            $stmt = $conn->prepare(
                "UPDATE events SET available_tickets = available_tickets - 1 
                 WHERE id = ? AND available_tickets > 0"
            );
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                $stmt->close();
                throw new Exception('No available tickets to confirm');
            }
            $stmt->close();
        } elseif ($old_status === 'confirmed' && $new_status !== 'confirmed') {
            $stmt = $conn->prepare(
                "UPDATE events SET available_tickets = available_tickets + 1 
                 WHERE id = ?"
            );
            $stmt->bind_param("i", $event_id);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Commit all changes
    $conn->commit();

    // Build redirect url: stay on same page, just add notification params.
    $ticket_number_qs = urlencode($ticket_number);
    $updated_status_qs = urlencode($new_status);

    $base = $redirect !== '' ? $redirect : 'admin_dashboard.php';
    $sep  = (strpos($base, '?') !== false) ? '&' : '?';

    header('Location: ' . $base . $sep .
        'update=success&ticket_number=' . $ticket_number_qs .
        '&updated_status=' . $updated_status_qs);
    exit();

} catch (Exception $e) {
    $conn->rollback();
    error_log("Status update error: " . $e->getMessage());

    $base = $redirect !== '' ? $redirect : 'admin_dashboard.php';
    $sep  = (strpos($base, '?') !== false) ? '&' : '?';

    header('Location: ' . $base . $sep .
        'update=error&message=' . urlencode($e->getMessage()));
    exit();
}

$conn->close();
