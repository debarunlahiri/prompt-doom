<?php

declare(strict_types=1);

$action = substr($path, strlen("/api/v1/auth/"));
$body = input();

if (
    $method === "POST" &&
    in_array($action, ["register", "login", "admin/login"], true)
) {
    require_fields(
        $body,
        $action === "register"
            ? ["name", "email", "password"]
            : ["email", "password"],
    );
    $email = strtolower(trim((string) $body["email"]));
    $password = (string) $body["password"];
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        throw new ApiException(422, "Validation failed", "VALIDATION_ERROR");
    }

    if ($action === "register") {
        $name = trim((string) $body["name"]);
        if (strlen($name) < 2) {
            throw new ApiException(
                422,
                "Validation failed",
                "VALIDATION_ERROR",
            );
        }
        $statement = $db->prepare(
            "INSERT INTO users (public_id,email,password_hash,name) VALUES (UUID(),?,?,?)",
        );
        $statement->execute([
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $name,
        ]);
        $id = (int) $db->lastInsertId();
        success(
            [
                "user" => ["id" => $id, "name" => $name, "email" => $email],
                "tokens" => issue_tokens($db, $config, $id, "user"),
            ],
            201,
        );
    }

    $admin = $action === "admin/login";
    $table = $admin ? "admin_users" : "users";
    $statement = $db->prepare(
        "SELECT * FROM {$table} WHERE email=?" .
            ($admin ? "" : " AND deleted_at IS NULL") .
            " LIMIT 1",
    );
    $statement->execute([$email]);
    $account = $statement->fetch();
    if (!$account || !password_verify($password, $account["password_hash"])) {
        if ($account) {
            $failed = (int) $account["failed_login_count"] + 1;
            $locked = $failed >= 5 ? date("Y-m-d H:i:s", time() + 900) : null;
            $db->prepare(
                "UPDATE {$table} SET failed_login_count=?, locked_until=? WHERE id=?",
            )->execute([$failed, $locked, $account["id"]]);
        }
        throw new ApiException(
            401,
            "Invalid email or password",
            "INVALID_CREDENTIALS",
        );
    }
    if (
        $account["locked_until"] &&
        strtotime($account["locked_until"]) > time()
    ) {
        throw new ApiException(
            423,
            "Account is temporarily locked",
            "ACCOUNT_LOCKED",
        );
    }
    if ($account["status"] !== "active") {
        throw new ApiException(
            403,
            "Account is not active",
            "ACCOUNT_INACTIVE",
        );
    }
    $db->prepare(
        "UPDATE {$table} SET last_login_at=NOW(),failed_login_count=0,locked_until=NULL WHERE id=?",
    )->execute([$account["id"]]);
    $role = $admin ? "admin" : "user";
    success([
        "account" => [
            "id" => (int) $account["id"],
            "email" => $account["email"],
            "name" => $account["name"],
        ],
        "tokens" => issue_tokens($db, $config, (int) $account["id"], $role),
    ]);
}

if ($method === "POST" && $action === "refresh") {
    require_fields($body, ["refreshToken"]);
    $token = (string) $body["refreshToken"];
    try {
        $claims = verify_token($token, $config["jwt_refresh_secret"]);
    } catch (Throwable) {
        throw new ApiException(
            401,
            "Invalid refresh token",
            "INVALID_REFRESH_TOKEN",
        );
    }
    if (($claims["type"] ?? "") !== "refresh") {
        throw new ApiException(
            401,
            "Invalid refresh token",
            "INVALID_REFRESH_TOKEN",
        );
    }
    $statement = $db->prepare(
        "SELECT id FROM user_refresh_tokens WHERE jti=? AND token_hash=? AND revoked_at IS NULL AND expires_at>NOW()",
    );
    $statement->execute([$claims["jti"] ?? "", hash("sha256", $token)]);
    $stored = $statement->fetch();
    if (!$stored) {
        throw new ApiException(
            401,
            "Refresh token is no longer valid",
            "INVALID_REFRESH_TOKEN",
        );
    }
    $db->prepare(
        "UPDATE user_refresh_tokens SET revoked_at=NOW() WHERE id=?",
    )->execute([$stored["id"]]);
    success([
        "tokens" => issue_tokens(
            $db,
            $config,
            (int) $claims["sub"],
            (string) $claims["role"],
        ),
    ]);
}

if ($method === "POST" && $action === "logout") {
    if (!empty($body["refreshToken"])) {
        $db->prepare(
            "UPDATE user_refresh_tokens SET revoked_at=NOW() WHERE token_hash=?",
        )->execute([hash("sha256", (string) $body["refreshToken"])]);
    }
    json_response(null, 204);
}

if ($method === "POST" && $action === "forgot-password") {
    require_fields($body, ["email"]);
    $statement = $db->prepare(
        "SELECT id FROM users WHERE email=? AND deleted_at IS NULL",
    );
    $statement->execute([strtolower(trim((string) $body["email"]))]);
    $user = $statement->fetch();
    $resetToken = null;
    if ($user) {
        $resetToken = bin2hex(random_bytes(24));
        $db->prepare(
            "INSERT INTO password_reset_tokens (user_id,token_hash,expires_at) VALUES (?,?,DATE_ADD(NOW(), INTERVAL 30 MINUTE))",
        )->execute([$user["id"], hash("sha256", $resetToken)]);
    }
    $data =
        $config["app_env"] !== "production" && $resetToken
            ? ["resetToken" => $resetToken]
            : null;
    success(
        $data,
        200,
        "If the account exists, reset instructions have been generated",
    );
}

if ($method === "POST" && $action === "reset-password") {
    require_fields($body, ["token", "password"]);
    $statement = $db->prepare(
        "SELECT * FROM password_reset_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW()",
    );
    $statement->execute([hash("sha256", (string) $body["token"])]);
    $reset = $statement->fetch();
    if (!$reset) {
        throw new ApiException(
            400,
            "Invalid or expired reset token",
            "INVALID_RESET_TOKEN",
        );
    }
    $db->beginTransaction();
    $db->prepare(
        "UPDATE users SET password_hash=?,failed_login_count=0,locked_until=NULL WHERE id=?",
    )->execute([
        password_hash((string) $body["password"], PASSWORD_DEFAULT),
        $reset["user_id"],
    ]);
    $db->prepare(
        "UPDATE password_reset_tokens SET used_at=NOW() WHERE id=?",
    )->execute([$reset["id"]]);
    $db->prepare(
        "UPDATE user_refresh_tokens SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL",
    )->execute([$reset["user_id"]]);
    $db->commit();
    success(null, 200, "Password reset successfully");
}

if ($method === "DELETE" && $action === "sessions") {
    $auth = current_auth($config, "user");
    $db->prepare(
        "UPDATE user_refresh_tokens SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL",
    )->execute([$auth["id"]]);
    json_response(null, 204);
}
