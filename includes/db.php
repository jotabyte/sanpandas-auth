<?php
// Database configuration
// Update these variables for your production MySQL server!
$dbHost = 'mysql.sanpandas.com';
$dbName = 'sanpandas_com';
$dbUser = 'sanpandascom';
$dbPass = 'Dionne2503abcd';

try {
    $dsn = "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $dbUser, $dbPass, $options);
} catch (PDOException $e) {
    die("Authentication Database Connection failed: " . $e->getMessage());
}
