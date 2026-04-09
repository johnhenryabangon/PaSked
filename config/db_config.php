<?php
// Database configuration for PaSked
// MySQL connection details (port 3307 as requested)

$db_config = [
    'host' => 'localhost',
    'port' => '3306',
    'database' => 'pasked',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4'
];

date_default_timezone_set('Asia/Manila');

// Database connection function
function getDBConnection() {
    global $db_config;

    try {
        $dsn = "mysql:host={$db_config['host']};port={$db_config['port']};dbname={$db_config['database']};charset={$db_config['charset']}";
        $pdo = new PDO($dsn, $db_config['username'], $db_config['password']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Set MySQL session timezone to Asia/Manila (UTC+8)
        $pdo->exec("SET time_zone = '+08:00'");

        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage());
    }
}

// Function to execute queries safely
function executeQuery($query, $params = []) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        die("Query execution failed: " . $e->getMessage());
    }
}


function sanitizeInput($data) {
    $data = $data ?? '';  // Convert null to empty string
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>