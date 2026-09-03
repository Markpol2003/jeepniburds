<?php
declare(strict_types=1);

function jeepnigo_json_read(string $file, array $default = []): array
{
    if (!is_file($file)) {
        return $default;
    }
    $handle = fopen($file, 'rb');
    if (!$handle) {
        return $default;
    }
    try {
        flock($handle, LOCK_SH);
        $decoded = json_decode(stream_get_contents($handle) ?: '', true);
        return is_array($decoded) ? $decoded : $default;
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function jeepnigo_json_write(string $file, array $data): bool
{
    $directory = dirname($file);
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        return false;
    }
    $temporary = tempnam($directory, '.jeepnigo-');
    if ($temporary === false) {
        return false;
    }
    $written = file_put_contents($temporary, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    if ($written === false) {
        @unlink($temporary);
        return false;
    }
    if (DIRECTORY_SEPARATOR === '\\' && is_file($file)) {
        @unlink($file);
    }
    if (!rename($temporary, $file)) {
        @unlink($temporary);
        return false;
    }
    return true;
}

