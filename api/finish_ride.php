<?php
require_once '../config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);
    
    $ride_id = $data['ride_id'] ?? '';

    if ($ride_id) {
        // Only creator can finish the ride
        $stmt = $pdo->prepare("UPDATE rides SET status = 'finished' WHERE id = ? AND creator_id = ?");
        $stmt->execute([$ride_id, $user_id]);
        
        // Also delete locations for this ride to clean up
        $stmt = $pdo->prepare("DELETE FROM locations WHERE ride_id = ?");
        $stmt->execute([$ride_id]);

        echo json_encode(['status' => 'success']);
        exit;
    }
}

echo json_encode(['status' => 'error']);
?>
