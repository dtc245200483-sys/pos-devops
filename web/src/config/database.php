<?php
function get_db_connection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '3306';
    $dbname = getenv('DB_DATABASE') ?: 'pos';
    $user = getenv('DB_USERNAME') ?: 'pos_app';
    $pass = getenv('DB_PASSWORD') ?: '';

    $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database connection error: " . $e->getMessage());
        http_response_code(500);
        die("Lỗi kết nối cơ sở dữ liệu: " . htmlspecialchars($e->getMessage()));
    }
}
