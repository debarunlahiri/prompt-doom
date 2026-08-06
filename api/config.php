<?php

declare(strict_types=1);

function load_environment(string $file): void
{
    if (!is_file($file)) {
        return;
    }

    foreach (
        file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: []
        as $line
    ) {
        $line = trim($line);
        if (
            $line === "" ||
            str_starts_with($line, "#") ||
            !str_contains($line, "=")
        ) {
            continue;
        }
        [$key, $value] = array_map("trim", explode("=", $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false) {
            putenv("{$key}={$value}");
        }
    }
}

load_environment(__DIR__ . "/.env");

function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

return [
    "app_env" => env("APP_ENV", "local"),
    "app_url" => rtrim(
        (string) env("APP_URL", "http://localhost/prompt-doom/api"),
        "/",
    ),
    "db" => [
        "host" => env("DB_HOST", "127.0.0.1"),
        "port" => env("DB_PORT", "3306"),
        "database" => env("DB_DATABASE", "prompt_doom"),
        "username" => env("DB_USERNAME", "root"),
        "password" => env("DB_PASSWORD", ""),
    ],
    "jwt_access_secret" => env(
        "JWT_ACCESS_SECRET",
        "local-access-secret-change-me",
    ),
    "jwt_refresh_secret" => env(
        "JWT_REFRESH_SECRET",
        "local-refresh-secret-change-me",
    ),
    "access_token_minutes" => (int) env("ACCESS_TOKEN_MINUTES", 15),
    "refresh_token_days" => (int) env("REFRESH_TOKEN_DAYS", 30),
    "max_upload_mb" => (int) env("MAX_UPLOAD_MB", 10),
];
