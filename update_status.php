<?php
// This logic handles all three actions: accept, reject, and pending

include 'connection.php';

if (isset($_GET['id']) && isset($_GET['action'])) {
    
    // 1. Sanitize and validate inputs
    $ticket_id = $conn->real_escape_string($_GET['id']);
    $action = strtolower($conn->real_escape_string($_GET['action']));

    // 2. Map the action to the database status value
    $valid_actions = ['accept' => 'confirmed', 'reject' => 'expired', 'pending' => 'pending'];
    
    if (isset($valid_actions[$action])) {
        $new_status = $valid_actions[$action];
        
        // 3. Prepare and execute the UPDATE statement
        $sql = "UPDATE tickets SET status = ? WHERE id = ?";
        
        // Use prepared statements for better security
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("si", $new_status, $ticket_id); // 's' for string, 'i' for integer

        if ($stmt->execute()) {
            // Success: Redirect back to the dashboard
            header("Location: admin_dashboard.php?update=success&ticket=" . $ticket_id . "&status=" . $new_status);
        } else {
            // Error
            header("Location: admin_dashboard.php?update=error&msg=" . urlencode($conn->error));
        }
        
        $stmt->close();
    } else {
        header("Location: admin_dashboard.php?error=invalid_action");
    }
    
    $conn->close();
} else {
    header("Location: admin_dashboard.php?error=missing_parameters");
}
exit();
?>