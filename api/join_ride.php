<?php
require_once '../config/db.php';
session_start();

if (isset($_SESSION['user_id'])) {
    $data = json_decode(file_get_contents('php://input'), true);
    $ride_id = $data['ride_id'] ?? $_GET['id'] ?? $_POST['ride_id'] ?? '';
    $user_id = $_SESSION['user_id'];

    if ($ride_id) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO ride_participants (ride_id, user_id) VALUES (?, ?)");
        $stmt->execute([$ride_id, $user_id]);
        
        if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
            $pdo = null;
            echo json_encode(['status' => 'success']);
            exit;
        } else {
            $pdo = null;
            header("Location: ../ride.php?id=$ride_id");
            exit;
        }
    }
}

$pdo = null;
if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
    echo json_encode(['status' => 'error']);
} else {
    header("Location: ../dashboard.php?error=join_failed");
}
exit;
?>
