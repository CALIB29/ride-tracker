<?php
require_once '../config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $data = json_decode(file_get_contents('php://input'), true);
    
    $ride_id = isset($data['ride_id']) ? $data['ride_id'] : '';
    $lat = $data['lat'] ?? '';
    $lng = $data['lng'] ?? '';

    if ($lat && $lng) {
        $vehicle = $data['vehicle'] ?? 'motorcycle';
        // Update user's last known global position for "Nearby" feature
        $stmt = $pdo->prepare("UPDATE users SET last_lat = ?, last_lng = ? WHERE id = ?");
        $stmt->execute([$lat, $lng, $user_id]);

        // Only update specific ride location if ride_id is provided and > 0
        if ($ride_id > 0) {
            $stmt = $pdo->prepare("INSERT INTO locations (user_id, ride_id, lat, lng, vehicle_type, last_updated) 
                                    VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP) 
                                    ON DUPLICATE KEY UPDATE lat = ?, lng = ?, vehicle_type = ?, last_updated = CURRENT_TIMESTAMP");
            $stmt->execute([$user_id, $ride_id, $lat, $lng, $vehicle, $lat, $lng, $vehicle]);

            // Save to history for breadcrumb trail
            try {
                $stmt = $pdo->prepare("INSERT INTO location_history (user_id, ride_id, lat, lng) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $ride_id, $lat, $lng]);
            } catch (Exception $e) {
                // Silently skip if table doesn't exist yet
            }
        }

        echo json_encode(['status' => 'success']);
        exit;
    }
}

echo json_encode(['status' => 'error']);
?>
