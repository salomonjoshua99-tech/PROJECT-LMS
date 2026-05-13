<?php

declare(strict_types=1);

function loadEnvFile(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $vars = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines === false) {
        return [];
    }

    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $trimmed, 2);

        if (count($parts) !== 2) {
            continue;
        }

        $key = trim($parts[0]);
        $value = trim($parts[1]);
        $value = trim($value, "\"'");

        $vars[$key] = $value;
    }

    return $vars;
}

function env(string $key, ?string $default = null): ?string
{
    static $envVars = null;

    if ($envVars === null) {
        $envVars = loadEnvFile(__DIR__ . DIRECTORY_SEPARATOR . '.env');
    }

    return $envVars[$key] ?? $default;
}

function appConfig(): array
{
    return [
        'db' => [
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'name' => env('DB_NAME', 'oop'),
            'user' => env('DB_USER', 'root'),
            'pass' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
        ],
        'session' => [
            'secret' => env('APP_SESSION_SECRET', 'replace-this-secret'),
            'name' => env('SESSION_NAME', 'EDUPORTALSESSID'),
        ],
    ];
}
