<?php

declare(strict_types=1);

$auth = current_auth($config, "user");
$route = substr($path, strlen("/api/v1/users/"));
$body = input();
if ($route === "me" && $method === "GET") {
    $st = $db->prepare(
        "SELECT id,email,name,avatar_url AS avatarUrl,status,created_at AS createdAt FROM users WHERE id=? AND deleted_at IS NULL",
    );
    $st->execute([$auth["id"]]);
    $user = $st->fetch();
    if (!$user) {
        throw new ApiException(404, "User not found", "NOT_FOUND");
    }
    success(["user" => $user]);
}
if ($route === "me" && $method === "PATCH") {
    $sets = [];
    $params = [];
    foreach (
        ["name" => "name", "avatarUrl" => "avatar_url"]
        as $key => $column
    ) {
        if (array_key_exists($key, $body)) {
            $sets[] = "{$column}=?";
            $params[] = $body[$key];
        }
    }
    if (!$sets) {
        throw new ApiException(422, "Validation failed", "VALIDATION_ERROR");
    }
    $params[] = $auth["id"];
    $db->prepare(
        "UPDATE users SET " . implode(",", $sets) . " WHERE id=?",
    )->execute($params);
    success(null, 200, "Profile updated");
}
if ($route === "me" && $method === "DELETE") {
    require_fields($body, ["password"]);
    $st = $db->prepare("SELECT password_hash,email FROM users WHERE id=?");
    $st->execute([$auth["id"]]);
    $user = $st->fetch();
    if (!$user || !password_verify($body["password"], $user["password_hash"])) {
        throw new ApiException(
            401,
            "Password is incorrect",
            "INVALID_PASSWORD",
        );
    }
    $db->prepare(
        "UPDATE users SET deleted_at=NOW(),status='blocked',email=CONCAT('deleted-',id,'-',email),password_hash=? WHERE id=?",
    )->execute([
        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
        $auth["id"],
    ]);
    $db->prepare(
        "UPDATE user_refresh_tokens SET revoked_at=NOW() WHERE user_id=?",
    )->execute([$auth["id"]]);
    json_response(null, 204);
}
if (in_array($route, ["favorites", "history"], true) && $method === "GET") {
    [$page, $limit, $offset] = page_params();
    if ($route === "favorites") {
        $st = $db->prepare(
            "SELECT f.created_at AS createdAt,i.id,i.title,i.slug,i.image_url AS imageUrl,i.thumbnail_url AS thumbnailUrl,i.ai_model AS aiModel,c.name AS categoryName FROM user_favorites f JOIN images i ON i.id=f.image_id LEFT JOIN categories c ON c.id=i.category_id WHERE f.user_id=? AND i.status='published' AND i.deleted_at IS NULL ORDER BY f.created_at DESC LIMIT {$limit} OFFSET {$offset}",
        );
        $st->execute([$auth["id"]]);
        $items = [];
        foreach ($st->fetchAll() as $row) {
            $row["imageUrl"] = asset_url($row["imageUrl"], $config);
            $row["thumbnailUrl"] = asset_url(
                $row["thumbnailUrl"],
                $config,
            );
            $image = $row;
            unset($image["createdAt"], $image["categoryName"]);
            $image["category"] = ["name" => $row["categoryName"]];
            $items[] = ["createdAt" => $row["createdAt"], "image" => $image];
        }
        $count = $db->prepare(
            "SELECT COUNT(*) FROM user_favorites WHERE user_id=?",
        );
        $count->execute([$auth["id"]]);
        success([
            "items" => $items,
            "pagination" => pagination(
                $page,
                $limit,
                (int) $count->fetchColumn(),
            ),
        ]);
    } else {
        $st = $db->prepare(
            "SELECT h.viewed_at AS viewedAt,h.copy_count AS copyCount,h.last_copied_at AS lastCopiedAt,i.id,i.title,i.slug,i.thumbnail_url AS thumbnailUrl FROM prompt_view_history h JOIN images i ON i.id=h.image_id WHERE h.user_id=? AND i.status='published' ORDER BY h.viewed_at DESC LIMIT {$limit} OFFSET {$offset}",
        );
        $st->execute([$auth["id"]]);
        $items = [];
        foreach ($st->fetchAll() as $row) {
            $image = [
                "id" => $row["id"],
                "title" => $row["title"],
                "slug" => $row["slug"],
                "thumbnailUrl" => asset_url(
                    $row["thumbnailUrl"],
                    $config,
                ),
            ];
            unset(
                $row["id"],
                $row["title"],
                $row["slug"],
                $row["thumbnailUrl"],
            );
            $row["image"] = $image;
            $items[] = $row;
        }
        success(["items" => $items, "page" => $page, "limit" => $limit]);
    }
}
