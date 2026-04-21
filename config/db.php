<?php
if ($_SERVER['HTTP_HOST'] == 'localhost' || $_SERVER['HTTP_HOST'] == '127.0.0.1') {
    // Localhost Settings
    $host = 'localhost';
    $dbname = 'ride_tracker';
    $username = 'root';
    $password = '';
} else {
    // InfinityFree Settings
    $host = 'sql106.infinityfree.com';
    $dbname = 'if0_41675570_rides';
    $username = 'if0_41675570';
    $password = '71Mt4ZYIZ0CW';
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Could not connect to the database: " . $e->getMessage());
}
?>
