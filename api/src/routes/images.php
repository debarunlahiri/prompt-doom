<?php

declare(strict_types=1);

$auth = current_auth($config, null, true);
$route = trim(substr($path, strlen("/api/v1/images")), "/");

if ($method === "GET" && $route === "") {
    [$page, $limit, $offset] = page_params();
    $where = ["i.status='published'", "i.deleted_at IS NULL"];
    $params = [];
    foreach (["q", "model"] as $key) {
        if (!empty($_GET[$key])) {
            if ($key === "q") {
                $where[] = "(i.title LIKE ? OR i.ai_model LIKE ?)";
                $params[] = "%" . $_GET[$key] . "%";
                $params[] = "%" . $_GET[$key] . "%";
            } else {
                $where[] = "i.ai_model=?";
                $params[] = $_GET[$key];
            }
        }
    }
    if (!empty($_GET["category"])) {
        $where[] = "c.slug=?";
        $params[] = $_GET["category"];
    }
    if (!empty($_GET["tag"])) {
        $where[] =
            "EXISTS(SELECT 1 FROM image_tags it JOIN tags t ON t.id=it.tag_id WHERE it.image_id=i.id AND t.slug=?)";
        $params[] = $_GET["tag"];
    }
    $sqlWhere = implode(" AND ", $where);
    $count = $db->prepare(
        "SELECT COUNT(*) FROM images i LEFT JOIN categories c ON c.id=i.category_id WHERE {$sqlWhere}",
    );
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $st = $db->prepare(
        "SELECT i.id,i.title,i.slug,i.image_url AS imageUrl,i.thumbnail_url AS thumbnailUrl,i.ai_model AS aiModel,i.published_at AS publishedAt,i.view_count AS viewCount,c.id AS categoryId,c.name AS categoryName FROM images i LEFT JOIN categories c ON c.id=i.category_id WHERE {$sqlWhere} ORDER BY i.published_at DESC LIMIT {$limit} OFFSET {$offset}",
    );
    $st->execute($params);
    $items = $st->fetchAll();
    foreach ($items as &$item) {
        $item["imageUrl"] = asset_url($item["imageUrl"], $config);
        $item["thumbnailUrl"] = asset_url($item["thumbnailUrl"], $config);
        $item["category"] = $item["categoryId"]
            ? [
                "id" => (int) $item["categoryId"],
                "name" => $item["categoryName"],
            ]
            : null;
        unset($item["categoryId"], $item["categoryName"]);
    }
    success([
        "items" => $items,
        "pagination" => pagination($page, $limit, $total),
    ]);
}

if (
    preg_match(
        '#^(\d+)(?:/(prompt|favorite|copy|share|reports))?$#',
        $route,
        $match,
    )
) {
    $id = (int) $match[1];
    $action = $match[2] ?? "";
    $check = $db->prepare(
        "SELECT id FROM images WHERE id=? AND status='published' AND deleted_at IS NULL",
    );
    $check->execute([$id]);
    if (!$check->fetch()) {
        throw new ApiException(404, "Image not found", "IMAGE_NOT_FOUND");
    }
    if ($method === "GET" && $action === "") {
        $st = $db->prepare(
            "SELECT i.id,i.title,i.slug,i.image_url AS imageUrl,i.thumbnail_url AS thumbnailUrl,i.ai_model AS aiModel,i.published_at AS publishedAt,i.view_count AS viewCount,i.copy_count AS copyCount,c.id AS categoryId,c.name AS categoryName FROM images i LEFT JOIN categories c ON c.id=i.category_id WHERE i.id=?",
        );
        $st->execute([$id]);
        $image = $st->fetch();
        $image["imageUrl"] = asset_url($image["imageUrl"], $config);
        $image["thumbnailUrl"] = asset_url($image["thumbnailUrl"], $config);
        $image["category"] = $image["categoryId"]
            ? [
                "id" => (int) $image["categoryId"],
                "name" => $image["categoryName"],
            ]
            : null;
        $tags = $db->prepare(
            "SELECT t.id,t.name FROM image_tags it JOIN tags t ON t.id=it.tag_id WHERE it.image_id=?",
        );
        $tags->execute([$id]);
        $image["tags"] = array_map(
            fn($tag) => ["tag" => $tag],
            $tags->fetchAll(),
        );
        unset($image["categoryId"], $image["categoryName"]);
        success(["image" => $image]);
    }
    if ($method === "GET" && $action === "prompt") {
        $st = $db->prepare(
            "SELECT main_prompt AS mainPrompt,negative_prompt AS negativePrompt FROM image_prompts WHERE image_id=?",
        );
        $st->execute([$id]);
        $prompt = $st->fetch();
        if (!$prompt) {
            throw new ApiException(404, "Prompt not found", "PROMPT_NOT_FOUND");
        }
        $db->prepare(
            "UPDATE images SET view_count=view_count+1 WHERE id=?",
        )->execute([$id]);
        $userId = $auth && $auth["role"] === "user" ? $auth["id"] : null;
        $db->prepare(
            "INSERT INTO analytics_events(event_id,user_id,image_id,event_type) VALUES (UUID(),?,?,?)",
        )->execute([$userId, $id, "prompt_view"]);
        if ($userId) {
            $db->prepare(
                "INSERT INTO prompt_view_history(user_id,image_id) VALUES (?,?) ON DUPLICATE KEY UPDATE viewed_at=NOW(),view_count=view_count+1",
            )->execute([$userId, $id]);
        }
        success(["prompt" => $prompt]);
    }
    if ($action === "favorite") {
        $user = current_auth($config, "user");
        if ($method === "POST") {
            $db->prepare(
                "INSERT IGNORE INTO user_favorites(user_id,image_id) VALUES (?,?)",
            )->execute([$user["id"], $id]);
            success(null, 201, "Added to favourites");
        }
        if ($method === "DELETE") {
            $db->prepare(
                "DELETE FROM user_favorites WHERE user_id=? AND image_id=?",
            )->execute([$user["id"], $id]);
            json_response(null, 204);
        }
    }
    if ($method === "POST" && in_array($action, ["copy", "share"], true)) {
        if ($action === "copy") {
            $user = current_auth($config, "user");
            $db->prepare(
                "UPDATE images SET copy_count=copy_count+1 WHERE id=?",
            )->execute([$id]);
            $db->prepare(
                "INSERT INTO prompt_view_history(user_id,image_id,copy_count,last_copied_at) VALUES (?,?,1,NOW()) ON DUPLICATE KEY UPDATE copy_count=copy_count+1,last_copied_at=NOW()",
            )->execute([$user["id"], $id]);
        }
        $db->prepare(
            "INSERT INTO analytics_events(event_id,user_id,image_id,event_type,metadata) VALUES (UUID(),?,?,?,?)",
        )->execute([
            $auth["id"] ?? null,
            $id,
            "prompt_" . $action,
            json_encode(input()),
        ]);
        success(null, 201);
    }
    if ($method === "POST" && $action === "reports") {
        $user = current_auth($config, "user");
        $body = input();
        require_fields($body, ["reason"]);
        if (
            !in_array(
                $body["reason"],
                [
                    "sexual",
                    "violent",
                    "hateful",
                    "copyright",
                    "misleading",
                    "other",
                ],
                true,
            )
        ) {
            throw new ApiException(
                422,
                "Validation failed",
                "VALIDATION_ERROR",
            );
        }
        $db->prepare(
            "INSERT INTO content_reports(user_id,image_id,reason,details) VALUES (?,?,?,?)",
        )->execute([
            $user["id"],
            $id,
            $body["reason"],
            $body["details"] ?? null,
        ]);
        success(null, 201, "Report submitted");
    }
}
