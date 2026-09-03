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
	$user = isset($parts['user']) ? rawurldecode($parts['user']) : 'root';
	$pass = isset($parts['pass']) ? rawurldecode($parts['pass']) : '';
	$db   = isset($parts['path']) ? rawurldecode(ltrim($parts['path'], '/')) : 'jeepnigo';
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
	$host = $getEnv('DB_HOST', 'localhost');
	$username = $getEnv(['DB_USER', 'DB_USERNAME'], 'root');
	$password = $getEnv('DB_PASSWORD', '');
	$database = $getEnv('DB_NAME', 'jeepnigo');
	$rawPort = $getEnv('DB_PORT', '3306');
	$dbPort = ($rawPort !== null && $rawPort !== '') ? (int)$rawPort : 3306;
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
	if (!defined('DB_PORT')) define('DB_PORT', $dbPort !== null ? $dbPort : 3306);

	// Check if SSL is required (Aiven MySQL and cloud deployments require SSL)
	$sslEnv = $getEnv('DB_SSL', null);
	$isLocalHost = in_array(strtolower($host), ['localhost', '127.0.0.1', '::1'], true);
	$useSsl = ($sslEnv !== null) ? ($sslEnv === '1' || strtolower($sslEnv) === 'true') : !$isLocalHost;

	$conn = mysqli_init();
	if (!$conn) {
		throw new Exception("Failed to initialize mysqli instance");
	}

	$conn->options(MYSQLI_OPT_CONNECT_TIMEOUT, 10);

	$sslCa = $getEnv(['DB_SSL_CA', 'MYSQL_ATTR_SSL_CA', 'MYSQL_SSL_CA'], null);
	$sslVerify = $getEnv('DB_SSL_VERIFY', '1');

	if ($useSsl) {
		if ($sslVerify === '0' || strtolower($sslVerify) === 'false') {
			if (defined('MYSQLI_OPT_SSL_VERIFY_SERVER_CERT')) {
				$conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
			}
		}
		$conn->ssl_set(NULL, NULL, $sslCa, NULL, NULL);
		$flags = MYSQLI_CLIENT_SSL;
	} else {
		$flags = 0;
	}

	$connected = @$conn->real_connect($resolvedHost, $username, $password, $database, $dbPort, NULL, $flags);

	if (!$connected || $conn->connect_error) {
		$err = $conn->connect_error ? $conn->connect_error : mysqli_connect_error();
		throw new Exception("Connection failed: " . $err);
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
	$contextMsg = " host={$resolvedHost} port={$dbPort} user={$username}" . (($password === '') ? " password=EMPTY" : "");
	error_log("Database connection error: " . $e->getMessage() . " |" . $contextMsg);
	http_response_code(500);
	if ($debug) {
		echo 'Database connection error. Host: ' . htmlspecialchars($resolvedHost) . ', User: ' . htmlspecialchars($username) . ($password === '' ? ' (password empty)' : '');
	} else {
		echo 'Database connection error.';
	}
	exit();
}

?>
