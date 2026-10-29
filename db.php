<?php
// Database connection shared by every page (same settings as the Extra Lab).
$host = "127.0.0.1";
$port = 3306;
$dbname = "hostel_complaints";
$user = "root";
$password = "";

$dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";

date_default_timezone_set("Asia/Kolkata");

try {
    $pdo = new PDO($dsn, $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Make NOW() in SQL use the same time zone as date() in PHP.
    $pdo->exec("SET time_zone = '+05:30'");
} catch (PDOException $e) {
    echo "Could not connect to the database.";
    exit;
}
?>
