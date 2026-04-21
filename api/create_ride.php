<?php
require_once '../config/db.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SESSION['user_id'])) {
    $title = $_POST['title'] ?? '';
    $lat = $_POST['dest_lat'] ?? '';
    $lng = $_POST['dest_lng'] ?? '';
    $user_id = $_SESSION['user_id'];

    if ($title && $lat && $lng) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO rides (creator_id, title, dest_lat, dest_lng) VALUES (?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $lat, $lng]);
            
            $ride_id = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO ride_participants (ride_id, user_id) VALUES (?, ?)")->execute([$ride_id, $user_id]);
            
            $pdo->commit();

            // Notify Friends via Telegram (Silently fail if hosting blocks it)
            try {
                notifyFriends($pdo, $user_id, $title);
            } catch (Exception $e) {
                // Silently skip if Telegram API is blocked by hosting
            }

            header('Location: ../ride.php?id=' . $ride_id);
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            // Log error or redirect with specific message
            header("Location: ../dashboard.php?error=db_error_" . urlencode($e->getMessage()));
            exit;
        }
    }
}

function notifyFriends($pdo, $creator_id, $ride_title) {
    if (!function_exists('curl_init')) return;

    // Get creator name
    $stmt = $pdo->prepare("SELECT username FROM users WHERE id = ?");
    $stmt->execute([$creator_id]);
    $creator_name = $stmt->fetchColumn();

    // Get all friends' telegram chat IDs
    $stmt = $pdo->prepare("SELECT u.telegram_chat_id 
                          FROM users u 
                          JOIN friends f ON f.user_id = u.id 
                          WHERE f.friend_id = ? AND u.telegram_chat_id IS NOT NULL");
    $stmt->execute([$creator_id]);
    $friends = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($friends)) return;

    $botToken = "8319254702:AAG-0NLk5xaI7iPnMNWa7UyP6ZHQuEH4V84";
    $message = "🏍️ *New Ride Planned!*\n\n*{$creator_name}* just planned a new ride: *{$ride_title}*.\n\nJoin now on the RideTracker dashboard!";

    foreach ($friends as $chat_id) {
        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $data = [
            'chat_id' => $chat_id,
            'text' => $message,
            'parse_mode' => 'Markdown'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5); // Don't hang the script
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_exec($ch);
        curl_close($ch);
    }
}

header("Location: ../dashboard.php?error=creation_failed");
exit;
?>
