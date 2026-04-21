<?php
// Get environment variables from Render (or use local defaults)
$host = getenv('MYSQL_ADDON_HOST') ?: getenv('DB_HOST') ?: 'localhost';
$dbname = getenv('MYSQL_ADDON_DB') ?: getenv('DB_NAME') ?: 'ride_tracker';
$username = getenv('MYSQL_ADDON_USER') ?: getenv('DB_USER') ?: 'root';
$password = getenv('MYSQL_ADDON_PASSWORD') ?: getenv('DB_PASS') ?: '';

try {
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3, // 3 second timeout
        PDO::ATTR_PERSISTENT => false
    ];
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, $options);
} catch (PDOException $e) {
    header('HTTP/1.1 503 Service Unavailable');
    die("Database Busy");
}
