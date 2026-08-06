<?php

declare(strict_types=1);

require __DIR__ . "/src/Http.php";
require __DIR__ . "/src/Database.php";
require __DIR__ . "/src/Auth.php";

$config = require __DIR__ . "/config.php";

header("Access-Control-Allow-Origin: *");
header(
    "Access-Control-Allow-Headers: Authorization, Content-Type, X-API-Version",
);
header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
header("X-API-Version: v1");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(204);
    exit();
}

try {
    $method = $_SERVER["REQUEST_METHOD"];
    $requestPath = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) ?: "/";
    $basePath = rtrim(dirname($_SERVER["SCRIPT_NAME"]), "/");
    $path = "/" . trim(substr($requestPath, strlen($basePath)), "/");
    if (str_starts_with($path, "/index.php")) {
        $path = "/" . trim(substr($path, strlen("/index.php")), "/");
    }
    if ($path === "/v1" || str_starts_with($path, "/v1/")) {
        $path = "/api" . $path;
    }

    if (
        $method === "GET" &&
        in_array($path, ["/", "/health", "/api/v1", "/api/v1/health"], true)
    ) {
        success([
            "service" => "Prompt Doom PHP API",
            "version" => "v1",
            "status" => "ok",
            "apiVersions" => ["v1"],
            "timestamp" => gmdate("c"),
        ]);
    }

    if (
        preg_match("#^/api/([^/]+)#", $path, $version) &&
        $version[1] !== "v1"
    ) {
        throw new ApiException(
            404,
            "API version '{$version[1]}' is not supported",
            "UNSUPPORTED_API_VERSION",
            ["supportedVersions" => ["v1"]],
        );
    }

    $db = Database::connect($config);

    if (str_starts_with($path, "/api/v1/auth/")) {
        require __DIR__ . "/src/routes/auth.php";
    }
    if (str_starts_with($path, "/api/v1/admin/")) {
        require __DIR__ . "/src/routes/admin.php";
    }
    if (
        $path === "/api/v1/images" ||
        str_starts_with($path, "/api/v1/images/")
    ) {
        require __DIR__ . "/src/routes/images.php";
    }
    if (
        $path === "/api/v1/users/me" ||
        str_starts_with($path, "/api/v1/users/")
    ) {
        require __DIR__ . "/src/routes/users.php";
    }
    if (str_starts_with($path, "/api/v1/ads/")) {
        require __DIR__ . "/src/routes/ads.php";
    }

    throw new ApiException(
        404,
        "Route {$method} {$path} not found",
        "NOT_FOUND",
    );
} catch (ApiException $error) {
    $payload = [
        "success" => false,
        "error" => [
            "code" => $error->errorCode,
            "message" => $error->getMessage(),
        ],
    ];
    if ($error->details !== null) {
        $payload["error"]["details"] = $error->details;
    }
    json_response($payload, $error->status);
} catch (PDOException $error) {
    $code = $error->getCode() === "23000" ? "CONFLICT" : "DATABASE_ERROR";
    $status = $error->getCode() === "23000" ? 409 : 500;
    json_response(
        [
            "success" => false,
            "error" => [
                "code" => $code,
                "message" =>
                    $status === 409
                        ? "The record conflicts with existing data"
                        : "Database operation failed",
            ],
        ],
        $status,
    );
} catch (Throwable $error) {
    json_response(
        [
            "success" => false,
            "error" => [
                "code" => "INTERNAL_ERROR",
                "message" => "Internal server error",
            ],
        ],
        500,
    );
}
