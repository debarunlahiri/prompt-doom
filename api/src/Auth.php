<?php

declare(strict_types=1);

function base64_url_encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), "+/", "-_"), "=");
}

function base64_url_decode(string $value): string
{
    return base64_decode(strtr($value, "-_", "+/")) ?: "";
}

function sign_token(array $claims, string $secret, int $ttl): string
{
    $header = base64_url_encode(
        json_encode(["alg" => "HS256", "typ" => "JWT"]),
    );
    $claims["iat"] = time();
    $claims["exp"] = time() + $ttl;
    $payload = base64_url_encode(json_encode($claims));
    $signature = base64_url_encode(
        hash_hmac("sha256", "{$header}.{$payload}", $secret, true),
    );
    return "{$header}.{$payload}.{$signature}";
}

function verify_token(string $token, string $secret): array
{
    $parts = explode(".", $token);
    if (count($parts) !== 3) {
        throw new ApiException(
            401,
            "Invalid or expired access token",
            "INVALID_TOKEN",
        );
    }
    [$header, $payload, $signature] = $parts;
    $expected = base64_url_encode(
        hash_hmac("sha256", "{$header}.{$payload}", $secret, true),
    );
    $claims = json_decode(base64_url_decode($payload), true);
    if (
        !hash_equals($expected, $signature) ||
        !is_array($claims) ||
        ($claims["exp"] ?? 0) < time()
    ) {
        throw new ApiException(
            401,
            "Invalid or expired access token",
            "INVALID_TOKEN",
        );
    }
    return $claims;
}

function bearer_token(): ?string
{
    $header =
        $_SERVER["HTTP_AUTHORIZATION"] ??
        ($_SERVER["REDIRECT_HTTP_AUTHORIZATION"] ?? "");
    return preg_match('/^Bearer\s+(.+)$/i', $header, $match) ? $match[1] : null;
}

function current_auth(
    array $config,
    ?string $requiredRole = null,
    bool $optional = false,
): ?array {
    $token = bearer_token();
    if (!$token) {
        if ($optional) {
            return null;
        }
        throw new ApiException(401, "Authentication required", "UNAUTHORIZED");
    }
    try {
        $claims = verify_token($token, $config["jwt_access_secret"]);
        if (
            ($claims["type"] ?? null) !== "access" ||
            ($requiredRole && ($claims["role"] ?? null) !== $requiredRole)
        ) {
            throw new RuntimeException();
        }
        return ["id" => (int) $claims["sub"], "role" => $claims["role"]];
    } catch (Throwable $error) {
        if ($optional) {
            return null;
        }
        if ($error instanceof ApiException) {
            throw $error;
        }
        throw new ApiException(
            401,
            "Invalid or expired access token",
            "INVALID_TOKEN",
        );
    }
}

function issue_tokens(PDO $db, array $config, int $id, string $role): array
{
    $jti = bin2hex(random_bytes(16));
    $access = sign_token(
        [
            "sub" => $id,
            "role" => $role,
            "type" => "access",
            "apiVersion" => "v1",
        ],
        $config["jwt_access_secret"],
        $config["access_token_minutes"] * 60,
    );
    $refresh = sign_token(
        [
            "sub" => $id,
            "role" => $role,
            "type" => "refresh",
            "jti" => $jti,
            "apiVersion" => "v1",
        ],
        $config["jwt_refresh_secret"],
        $config["refresh_token_days"] * 86400,
    );
    $column = $role === "admin" ? "admin_id" : "user_id";
    $statement = $db->prepare(
        "INSERT INTO user_refresh_tokens ({$column}, jti, family_id, session_id, token_hash, expires_at, user_agent, ip_hash) VALUES (?, ?, UUID(), UUID(), ?, FROM_UNIXTIME(?), ?, ?)",
    );
    $statement->execute([
        $id,
        $jti,
        hash("sha256", $refresh),
        time() + $config["refresh_token_days"] * 86400,
        $_SERVER["HTTP_USER_AGENT"] ?? null,
        hash("sha256", $_SERVER["REMOTE_ADDR"] ?? ""),
    ]);
    return [
        "accessToken" => $access,
        "refreshToken" => $refresh,
        "tokenType" => "Bearer",
    ];
}
