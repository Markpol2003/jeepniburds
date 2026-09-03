<?php
declare(strict_types=1);

function jeepnigo_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function jeepnigo_csrf_token(): string
{
    jeepnigo_start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function jeepnigo_request_csrf_token(): string
{
    return (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '');
}

function jeepnigo_require_csrf(): void
{
    $provided = jeepnigo_request_csrf_token();
    if ($provided === '' || !hash_equals(jeepnigo_csrf_token(), $provided)) {
        jeepnigo_json_error('Invalid or expired request token.', 419);
    }
}

function jeepnigo_require_role(array $roles): int
{
    jeepnigo_start_session();
    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    $role = strtolower((string)($_SESSION['user_type'] ?? ''));
    $roles = array_map('strtolower', $roles);

    if (!$userId) {
        jeepnigo_json_error('Authentication required.', 401);
    }
    if ($roles && !in_array($role, $roles, true)) {
        jeepnigo_json_error('You are not authorized to perform this action.', 403);
    }
    return (int)$userId;
}

function jeepnigo_json_error(string $message, int $status): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'status' => 'error', 'message' => $message]);
    exit;
}

function jeepnigo_security_head(): string
{
    $token = htmlspecialchars(jeepnigo_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<meta name="csrf-token" content="' . $token . '">' . "\n" .
        '<script>(function(){const token="' . $token . '";const original=window.fetch;window.fetch=function(input,init){init=init||{};const method=(init.method||"GET").toUpperCase();if(!["GET","HEAD","OPTIONS"].includes(method)){init.headers=new Headers(init.headers||{});init.headers.set("X-CSRF-Token",token);}return original.call(this,input,init);};document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("form").forEach(function(form){if((form.method||"get").toLowerCase()!=="post"||form.querySelector("input[name=csrf_token]"))return;const input=document.createElement("input");input.type="hidden";input.name="csrf_token";input.value=token;form.appendChild(input);});});})();</script>';
}

function jeepnigo_safe_upload(array $file, string $directory, array $allowedMime, int $maxBytes): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The upload did not complete successfully.');
    }
    if (($file['size'] ?? 0) < 1 || $file['size'] > $maxBytes) {
        throw new RuntimeException('The uploaded file size is invalid.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowedMime[$mime])) {
        throw new RuntimeException('The uploaded file type is not allowed.');
    }
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('The upload directory is unavailable.');
    }
    $name = bin2hex(random_bytes(16)) . '.' . $allowedMime[$mime];
    $destination = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to save the uploaded file.');
    }
    return $name;
}
