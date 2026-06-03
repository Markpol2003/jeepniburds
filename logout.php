<?php
// Prevent caching so back button doesn't show protected pages
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

// Unset all session variables
$_SESSION = [];
if (function_exists('session_unset')) {
    session_unset();
}

// If session uses cookies, remove the cookie too (force path to root)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    $cookieParamsPath = '/';
    $cookieParamsDomain = $params['domain'] ?? '';
    $cookieParamsSecure = !empty($params['secure']);
    $cookieParamsHttpOnly = !empty($params['httponly']);
    setcookie(session_name(), '', time() - 42000, $cookieParamsPath, $cookieParamsDomain, $cookieParamsSecure, $cookieParamsHttpOnly);
}

// Best-effort clear of any remaining cookies (root path)
if (!headers_sent() && !empty($_COOKIE)) {
    foreach ($_COOKIE as $cookieName => $cookieValue) {
        setcookie($cookieName, '', time() - 42000, '/');
    }
}

// Finally, destroy the session
session_destroy();
session_write_close();

// Redirect to landing page explicitly (absolute URL in same directory)
$redirect = 'index.php';
if (!headers_sent()) {
	if (isset($_SERVER['HTTP_HOST'])) {
		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
		$redirect = $scheme . '://' . $_SERVER['HTTP_HOST'] . ($dir === '' ? '' : $dir) . '/index.php';
	}
	header('Location: ' . $redirect);
	exit;
} else {
	// Fallback if headers already sent
	echo '<script>location.href="index.php";</script>';
	echo '<noscript><meta http-equiv="refresh" content="0;url=index.php"></noscript>';
	exit;
}
?>
