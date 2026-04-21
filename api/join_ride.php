<?php
require_once '../config/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    $ride_id = $_GET['id'] ?? $_POST['ride_id'] ?? '';
    $user_id = $_SESSION['user_id'];

    if ($ride_id) {
        // Check if ride creator is a friend (or self)
        $stmt = $pdo->prepare("SELECT creator_id FROM rides WHERE id = ?");
        $stmt->execute([$ride_id]);
        $creator_id = $stmt->fetchColumn();

        // Simplified: Everyone can join for now, or maintain friend logic
        $stmt = $pdo->prepare("INSERT IGNORE INTO ride_participants (ride_id, user_id) VALUES (?, ?)");
        if ($stmt->execute([$ride_id, $user_id])) {
            header("Location: ../ride.php?id=$ride_id");
            exit;
        }
    }
}

header("Location: ../dashboard.php?error=join_failed");
exit;
?>
