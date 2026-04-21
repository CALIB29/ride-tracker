<?php
require_once '../config/db.php';
session_start();

if (isset($_GET['ride_id']) && isset($_SESSION['user_id'])) {
    $ride_id = $_GET['ride_id'];
    
    // Table auto-creation removed to prevent 500 error on InfinityFree.
    // Ensure 'location_history' and 'ride_messages' tables exist in phpMyAdmin.

    // Get all participants and their last known location for this ride
    $stmt = $pdo->prepare("SELECT rp.user_id, u.username, l.lat, l.lng, l.last_updated, l.vehicle_type,
                            (CASE WHEN l.last_updated > (NOW() - INTERVAL 1 MINUTE) THEN 1 ELSE 0 END) as is_online
                            FROM ride_participants rp
                            JOIN users u ON rp.user_id = u.id 
                            LEFT JOIN locations l ON rp.user_id = l.user_id AND l.ride_id = rp.ride_id
                            WHERE rp.ride_id = ?");
    $stmt->execute([$ride_id]);
    $locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $pathways = [];
    $user_counts = [];
    try {
        // Fetch last 50 points of history for each participant for pathways
        // Using a more compatible query for MySQL 5.7 (InfinityFree)
        $stmt = $pdo->prepare("SELECT user_id, lat, lng FROM location_history 
                                WHERE ride_id = ? 
                                ORDER BY user_id, id ASC");
        $stmt->execute([$ride_id]);
        $all_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_history as $point) {
            $uid = $point['user_id'];
            if (!isset($user_counts[$uid])) $user_counts[$uid] = 0;
            
            // Only keep last 100 for performance
            if ($user_counts[$uid] < 100) { 
                $pathways[$uid][] = [(float)$point['lat'], (float)$point['lng']];
                $user_counts[$uid]++;
            }
        }
    } catch (Exception $e) {
        // Silently return empty pathways if table doesn't exist
    }
    
    echo json_encode(['status' => 'success', 'locations' => $locations, 'pathways' => $pathways]);
    exit;
}

echo json_encode(['status' => 'error']);
