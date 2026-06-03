<?php
// Database configuration (prefer environment variables, fallback to sensible defaults)
$getEnv = function ($names, $default = null) {
	foreach ((array)$names as $name) {
		$val = getenv($name);
		if ($val !== false && $val !== '') return $val;
		if (isset($_ENV[$name]) && $_ENV[$name] !== '') return $_ENV[$name];
		if (isset($_SERVER[$name]) && $_SERVER[$name] !== '') return $_SERVER[$name];
	}
	return $default;
};

$parseDatabaseUrl = function ($url) {
	$parts = parse_url($url);
	if ($parts === false) return null;
	$host = isset($parts['host']) ? $parts['host'] : 'localhost';
	$user = isset($parts['user']) ? $parts['user'] : 'root';
	$pass = isset($parts['pass']) ? $parts['pass'] : '';
	$db   = isset($parts['path']) ? ltrim($parts['path'], '/') : 'jeepnigo';
	$port = isset($parts['port']) ? (int)$parts['port'] : null;
	return ['host' => $host, 'user' => $user, 'pass' => $pass, 'db' => $db, 'port' => $port];
};

$url = $getEnv(['DATABASE_URL', 'CLEARDB_DATABASE_URL', 'JAWSDB_URL', 'MYSQL_URL'], null);
if ($url) {
	$cfg = $parseDatabaseUrl($url);
	$host = $cfg['host'];
	$username = $cfg['user'];
	$password = $cfg['pass'];
	$database = $cfg['db'];
	$dbPort = $cfg['port'];
} else {
	$host = $getEnv('DB_HOST', '100.73.212.121');
	$username = $getEnv(['DB_USER', 'DB_USERNAME'], 'root');
	$password = $getEnv('DB_PASSWORD', 'jeepnigo_ite2025');
	$database = $getEnv('DB_NAME', 'jeepnigo');
	$dbPort = $getEnv('DB_PORT', '33078');
	$dbPort = ($dbPort !== null && $dbPort !== '') ? (int)$dbPort : 33078;
}

// Set default timezone (adjust to your server's location)
// For Philippines, use Asia/Manila
date_default_timezone_set('Asia/Manila');

try {
    // Create connection
	$resolvedHost = ($host === 'localhost') ? '127.0.0.1' : $host; // prefer TCP over socket

	// Define constants for compatibility with files expecting DB_* constants
	if (!defined('DB_HOST')) define('DB_HOST', $resolvedHost);
	if (!defined('DB_USER')) define('DB_USER', $username);
	if (!defined('DB_PASS')) define('DB_PASS', $password);
	if (!defined('DB_NAME')) define('DB_NAME', $database);
	if (!defined('DB_PORT')) define('DB_PORT', $dbPort !== null ? $dbPort : 33078);

	if ($dbPort !== null) {
		$conn = new mysqli($resolvedHost, $username, $password, $database, $dbPort);
	} else {
		$conn = new mysqli($resolvedHost, $username, $password, $database);
	}
    
    // Check connection
    if ($conn->connect_error) {
		throw new Exception("Connection failed: " . $conn->connect_error);
    }

	// Charset
	$conn->set_charset('utf8mb4');
    
    // Set MySQL timezone to match PHP timezone
    try {
        $offset = date('P'); // Gets offset like +08:00
        $conn->query("SET time_zone = '$offset'");
    } catch (Exception $e) {
        // If timezone setting fails, continue (some MySQL configurations might not support it)
        // The times will still work, just may not match exactly if server timezone differs
    }
} catch (Exception $e) {
	// Log the detailed error server-side and show a generic message to users
	$debug = $getEnv('DB_DEBUG', '0') === '1';
	$contextMsg = " host={$resolvedHost} user={$username}" . (($password === '') ? " password=EMPTY" : "");
	error_log("Database connection error: " . $e->getMessage() . " |" . $contextMsg);
	http_response_code(500);
	if ($debug) {
		echo 'Database connection error. Host: ' . htmlspecialchars($resolvedHost) . ', User: ' . htmlspecialchars($username) . ($password === '' ? ' (password empty)' : '');
	} else {
		echo 'Database connection error.';
	}
	exit();
}

// Create user_receipts table if it doesn't exist
$createUserReceiptsTable = "CREATE TABLE IF NOT EXISTS user_receipts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    payment_id INT NOT NULL,
    receipt_number VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method VARCHAR(50) NOT NULL,
    payment_date DATETIME NOT NULL,
    status VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (payment_id) REFERENCES membership_payments(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payment (payment_id)
)";

try {
    $conn->query($createUserReceiptsTable);
} catch (Exception $e) {
    // Table might already exist or there might be an error, but we'll continue
    // The error will be handled when trying to access the table
}
?>
