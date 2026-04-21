<?php
// Force JSON headers and disable error display to prevent breaking the flow
header('Content-Type: application/json');
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Table exists check removed - InfinityFree security blocks CREATE TABLE in web requests
// Token removed - InfinityFree security blocks files containing bot tokens (403 Forbidden)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $ride_id = isset($data['ride_id']) ? (int)$data['ride_id'] : 0;
    $message = trim($data['message'] ?? '');
    $type = $data['type'] ?? 'chat';

    if ($ride_id > 0 && $message !== '') {
        try {
            $stmt = $pdo->prepare("INSERT INTO ride_messages (ride_id, user_id, message, type) VALUES (?, ?, ?, ?)");
            $stmt->execute([$ride_id, $user_id, $message, $type]);
            
            echo json_encode(['status' => 'success']);
        } catch (Exception $e) {
            echo json_encode(['status' => 'error', 'message' => 'DB Error']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid parameters']);
    }
    $pdo = null; // Free up slot
    exit;
} else {
    $ride_id = isset($_GET['ride_id']) ? (int)$_GET['ride_id'] : 0;
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    
    try {
        $stmt = $pdo->prepare("SELECT m.id, m.user_id, m.message, m.type, m.created_at, u.username 
                              FROM ride_messages m 
                              LEFT JOIN users u ON m.user_id = u.id 
                              WHERE m.ride_id = ? AND m.id > ? 
                              ORDER BY m.id ASC LIMIT 50");
        $stmt->execute([$ride_id, $last_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($messages ?: []);
    } catch (Exception $e) {
        echo json_encode([]);
    }
    $pdo = null;
    exit;
}


function notifySOSTelegram($pdo, $user_id, $msg) {
    // Disabled to prevent 403 Forbidden security blocks on InfinityFree
    return;
}

