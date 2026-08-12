<?php

declare(strict_types=1);

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        string $message,
        public readonly string $errorCode,
        public readonly mixed $details = null,
    ) {
        parent::__construct($message);
    }
}

function json_response(mixed $data = null, int $status = 200): never
{
    http_response_code($status);
    if ($status !== 204) {
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(
            $data,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }
    exit();
}

function success(
    mixed $data = null,
    int $status = 200,
    ?string $message = null,
): never {
    $payload = ["success" => true];
    if ($data !== null) {
        $payload["data"] = $data;
    }
    if ($message !== null) {
        $payload["message"] = $message;
    }
    json_response($payload, $status);
}

function input(): array
{
    $type = strtolower($_SERVER["CONTENT_TYPE"] ?? "");
    if (str_contains($type, "application/json")) {
        $raw = file_get_contents("php://input") ?: "";
        $decoded = json_decode($raw, true);
        if ($raw !== "" && !is_array($decoded)) {
            throw new ApiException(
                400,
                "Malformed JSON request body",
                "INVALID_JSON",
            );
        }
        return $decoded ?: [];
    }
    return $_POST;
}

function require_fields(array $data, array $fields): void
{
    $missing = array_values(
        array_filter(
            $fields,
            fn($field) => !isset($data[$field]) || $data[$field] === "",
        ),
    );
    if ($missing) {
        throw new ApiException(422, "Validation failed", "VALIDATION_ERROR", [
            "missing" => $missing,
        ]);
    }
}

function page_params(): array
{
    $page = max(1, (int) ($_GET["page"] ?? 1));
    $limit = min(100, max(1, (int) ($_GET["limit"] ?? 20)));
    return [$page, $limit, ($page - 1) * $limit];
}

function pagination(int $page, int $limit, int $total): array
{
    return [
        "page" => $page,
        "limit" => $limit,
        "total" => $total,
        "totalPages" => max(1, (int) ceil($total / $limit)),
    ];
}

function asset_url(?string $storedUrl, array $config): ?string
{
    if ($storedUrl === null || $storedUrl === "") {
        return $storedUrl;
    }

    $path = parse_url($storedUrl, PHP_URL_PATH);
    if (!is_string($path)) {
        return $storedUrl;
    }

    $uploadPosition = strpos($path, "/uploads/");
    if ($uploadPosition === false) {
        return $storedUrl;
    }

    return asset_base_url($config) . substr($path, $uploadPosition);
}

function asset_base_url(array $config): string
{
    $forwardedHost =
        $_SERVER["HTTP_X_FORWARDED_HOST"] ?? $_SERVER["HTTP_HOST"] ?? "";
    $requestHost = trim(explode(",", (string) $forwardedHost)[0]);
    if (
        $requestHost !== "" &&
        preg_match('/^(?:\[[0-9a-f:]+\]|[a-z0-9.-]+)(?::\d+)?$/i', $requestHost)
    ) {
        $forwardedScheme = strtolower(
            trim(
                explode(
                    ",",
                    (string) ($_SERVER["HTTP_X_FORWARDED_PROTO"] ?? ""),
                )[0],
            ),
        );
        $scheme = in_array($forwardedScheme, ["http", "https"], true)
            ? $forwardedScheme
            : (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off"
                ? "https"
                : "http");
        $scriptName = (string) ($_SERVER["SCRIPT_NAME"] ?? "");
        $sitePath = str_replace("\\", "/", dirname($scriptName));
        $sitePath = preg_replace('#/api$#', '', $sitePath) ?? $sitePath;
        $sitePath = in_array($sitePath, ["", "/", "."], true)
            ? ""
            : "/" . trim($sitePath, "/");

        return "{$scheme}://{$requestHost}{$sitePath}";
    }

    $baseUrl = rtrim((string) $config["app_url"], "/");

    // Non-web processes do not have request information, so derive the site
    // root from APP_URL by removing its API suffix.
    return preg_replace('#/api(?:/v\d+)?$#', '', $baseUrl) ?: $baseUrl;
}

function thumbnail_asset_url(
    ?string $thumbnailUrl,
    ?string $imageUrl,
    array $config,
): ?string {
    $thumbnailPath = parse_url((string) $thumbnailUrl, PHP_URL_PATH);
    $uploadPosition = is_string($thumbnailPath)
        ? strpos($thumbnailPath, "/uploads/")
        : false;

    if ($uploadPosition !== false) {
        $localPath =
            __DIR__ . "/../.." . substr($thumbnailPath, $uploadPosition);
        if (!is_file($localPath)) {
            return asset_url($imageUrl, $config);
        }
    }

    return asset_url($thumbnailUrl, $config) ?: asset_url($imageUrl, $config);
}

function image_share_payload(
    int $imageId,
    string $title,
    array $config,
): array
{
    $shareUrl = asset_base_url($config) . "/share/{$imageId}";

    return [
        "shareUrl" => $shareUrl,
        "shareMessage" => trim($title) . "\n" . $shareUrl,
    ];
}

function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace("/[^a-z0-9]+/", "-", $slug) ?: "";
    return trim($slug, "-");
}
