function notify($conn, $user_id, $message, $type = 'general', $priority = 'low')
{
    $stmt = $conn->prepare("
        INSERT INTO notifications (user_id, message, type, priority)
        VALUES (?, ?, ?, ?)
    ");

    $stmt->bind_param("isss", $user_id, $message, $type, $priority);
    $stmt->execute();
}