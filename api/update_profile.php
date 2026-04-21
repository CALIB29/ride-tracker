<?php
session_start();
require_once '../config/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $telegram_chat_id = $_POST['telegram_chat_id'] ?? '';

    $stmt = $pdo->prepare("UPDATE users SET telegram_chat_id = ? WHERE id = ?");
    $stmt->execute([$telegram_chat_id, $user_id]);

    header('Location: ../dashboard.php?success=profile_updated');
}
?>
