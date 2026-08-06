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

function slugify(string $value): string
{
    $slug = strtolower(trim($value));
    $slug = preg_replace("/[^a-z0-9]+/", "-", $slug) ?: "";
    return trim($slug, "-");
}
