<?php

declare(strict_types=1);

$auth = current_auth($config, "admin");
$route = substr($path, strlen("/api/v1/admin/"));
$body = input();

function audit(
    PDO $db,
    int $adminId,
    string $action,
    string $type,
    ?int $id,
    array $metadata = [],
): void {
    $db->prepare(
        "INSERT INTO audit_logs (admin_id,action,entity_type,entity_id,metadata) VALUES (?,?,?,?,?)",
    )->execute([$adminId, $action, $type, $id, json_encode($metadata)]);
}

if ($method === "GET" && $route === "users") {
    [$page, $limit, $offset] = page_params();
    $q = trim((string) ($_GET["q"] ?? ""));
    $where = "deleted_at IS NULL";
    $params = [];
    if ($q !== "") {
        $where .= " AND (name LIKE ? OR email LIKE ?)";
        $params = ["%{$q}%", "%{$q}%"];
    }
    $count = $db->prepare("SELECT COUNT(*) FROM users WHERE {$where}");
    $count->execute($params);
    $statement = $db->prepare(
        "SELECT id,email,name,avatar_url AS avatarUrl,status,last_login_at AS lastLoginAt,created_at AS createdAt FROM users WHERE {$where} ORDER BY created_at DESC LIMIT {$limit} OFFSET {$offset}",
    );
    $statement->execute($params);
    $total = (int) $count->fetchColumn();
    success([
        "items" => $statement->fetchAll(),
        "pagination" => pagination($page, $limit, $total),
    ]);
}

if (
    preg_match('#^users/(\d+)/status$#', $route, $match) &&
    $method === "PATCH"
) {
    $status = $body["status"] ?? "";
    if (!in_array($status, ["active", "blocked"], true)) {
        throw new ApiException(422, "Validation failed", "VALIDATION_ERROR");
    }
    $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([
        $status,
        $match[1],
    ]);
    if ($status === "blocked") {
        $db->prepare(
            "UPDATE user_refresh_tokens SET revoked_at=NOW() WHERE user_id=? AND revoked_at IS NULL",
        )->execute([$match[1]]);
    }
    audit(
        $db,
        $auth["id"],
        $status === "blocked" ? "block" : "unblock",
        "user",
        (int) $match[1],
        ["status" => $status],
    );
    success(
        null,
        200,
        $status === "blocked" ? "User blocked" : "User unblocked",
    );
}

if ($method === "GET" && $route === "images") {
    [$page, $limit, $offset] = page_params();
    $where = ["i.deleted_at IS NULL"];
    $params = [];
    if (!empty($_GET["q"])) {
        $where[] = "(i.title LIKE ? OR i.ai_model LIKE ?)";
        $params[] = "%" . $_GET["q"] . "%";
        $params[] = "%" . $_GET["q"] . "%";
    }
    if (!empty($_GET["status"])) {
        $where[] = "i.status=?";
        $params[] = $_GET["status"];
    }
    if (!empty($_GET["categoryId"])) {
        $where[] = "i.category_id=?";
        $params[] = (int) $_GET["categoryId"];
    }
    $sqlWhere = implode(" AND ", $where);
    $count = $db->prepare("SELECT COUNT(*) FROM images i WHERE {$sqlWhere}");
    $count->execute($params);
    $total = (int) $count->fetchColumn();
    $statement = $db->prepare(
        "SELECT i.*,i.public_id AS publicId,i.image_url AS imageUrl,i.thumbnail_url AS thumbnailUrl,i.ai_model AS aiModel,i.moderation_status AS moderationStatus,i.view_count AS viewCount,i.copy_count AS copyCount,i.published_at AS publishedAt,i.created_at AS createdAt,i.updated_at AS updatedAt,c.name AS categoryName,c.slug AS categorySlug,p.main_prompt AS mainPrompt,p.negative_prompt AS negativePrompt FROM images i LEFT JOIN categories c ON c.id=i.category_id LEFT JOIN image_prompts p ON p.image_id=i.id WHERE {$sqlWhere} ORDER BY i.created_at DESC LIMIT {$limit} OFFSET {$offset}",
    );
    $statement->execute($params);
    $items = $statement->fetchAll();
    $tagQuery = $db->prepare(
        "SELECT t.id,t.name,t.slug,t.status FROM image_tags it JOIN tags t ON t.id=it.tag_id WHERE it.image_id=?",
    );
    foreach ($items as &$item) {
        $item["imageUrl"] = asset_url($item["imageUrl"], $config);
        $item["thumbnailUrl"] = asset_url($item["thumbnailUrl"], $config);
        $item["category"] = $item["category_id"]
            ? [
                "id" => (int) $item["category_id"],
                "name" => $item["categoryName"],
                "slug" => $item["categorySlug"],
            ]
            : null;
        $tagQuery->execute([$item["id"]]);
        $item["tags"] = $tagQuery->fetchAll();
        unset(
            $item["categoryName"],
            $item["categorySlug"],
            $item["image_url"],
            $item["thumbnail_url"],
            $item["ai_model"],
            $item["moderation_status"],
            $item["view_count"],
            $item["copy_count"],
            $item["published_at"],
            $item["created_at"],
            $item["updated_at"],
        );
    }
    success([
        "items" => $items,
        "pagination" => pagination($page, $limit, $total),
    ]);
}

if ($method === "POST" && $route === "images") {
    require_fields($body, ["title", "categoryId", "mainPrompt", "status"]);
    if (empty($_FILES["image"]) || empty($_FILES["thumbnail"])) {
        throw new ApiException(
            422,
            "Image and thumbnail are required",
            "FILES_REQUIRED",
        );
    }
    $allowed = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp",
        "image/gif" => "gif",
    ];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $saved = [];
    $maxUploadBytes = max(1, (int) $config["max_upload_mb"]) * 1024 * 1024;
    foreach (
        ["image" => "images", "thumbnail" => "thumbnails"]
        as $field => $folder
    ) {
        $upload = $_FILES[$field];
        if (($upload["error"] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new ApiException(
                422,
                "The {$field} upload did not complete successfully",
                "UPLOAD_INCOMPLETE",
                ["field" => $field, "uploadError" => $upload["error"] ?? null],
            );
        }
        if (
            empty($upload["tmp_name"]) ||
            !is_uploaded_file($upload["tmp_name"])
        ) {
            throw new ApiException(
                422,
                "The {$field} upload is invalid",
                "INVALID_UPLOAD",
                ["field" => $field],
            );
        }
        if ((int) ($upload["size"] ?? 0) < 1) {
            throw new ApiException(
                422,
                "The {$field} file is empty",
                "EMPTY_UPLOAD",
                ["field" => $field],
            );
        }
        if ((int) $upload["size"] > $maxUploadBytes) {
            throw new ApiException(
                413,
                "The {$field} exceeds the upload size limit",
                "UPLOAD_TOO_LARGE",
                ["field" => $field, "maxUploadMb" => $config["max_upload_mb"]],
            );
        }
        $mime = $finfo->file($upload["tmp_name"]);
        if (!isset($allowed[$mime])) {
            throw new ApiException(
                422,
                "Uploaded file content is not a supported image",
                "INVALID_IMAGE_CONTENT",
            );
        }
        $directory = __DIR__ . "/../../uploads/" . $folder;
        if (
            (!is_dir($directory) && !mkdir($directory, 0775, true)) ||
            !is_writable($directory)
        ) {
            foreach ($saved as $savedFile) {
                $savedPath = __DIR__ . "/../../" . $savedFile["key"];
                if (is_file($savedPath)) {
                    unlink($savedPath);
                }
            }
            throw new ApiException(
                500,
                "The upload directory is not writable",
                "UPLOAD_STORAGE_NOT_WRITABLE",
                ["field" => $field],
            );
        }
        $name = bin2hex(random_bytes(16)) . "." . $allowed[$mime];
        $destination = "{$directory}/{$name}";
        if (
            !move_uploaded_file(
                $upload["tmp_name"],
                $destination,
            )
        ) {
            foreach ($saved as $savedFile) {
                $savedPath = __DIR__ . "/../../" . $savedFile["key"];
                if (is_file($savedPath)) {
                    unlink($savedPath);
                }
            }
            throw new ApiException(
                500,
                "Unable to store uploaded image",
                "UPLOAD_ERROR",
            );
        }
        $saved[$field] = [
            "key" => "uploads/{$folder}/{$name}",
            "url" => $config["app_url"] . "/uploads/{$folder}/{$name}",
        ];
    }
    $slug = slugify((string) $body["title"]) . "-" . time();
    $db->beginTransaction();
    $statement = $db->prepare(
        'INSERT INTO images (public_id,title,slug,category_id,image_url,image_key,thumbnail_url,thumbnail_key,ai_model,status,published_at,created_by) VALUES (UUID(),?,?,?,?,?,?,?,?,?,IF(?="published",NOW(),NULL),?)',
    );
    $statement->execute([
        $body["title"],
        $slug,
        (int) $body["categoryId"],
        $saved["image"]["url"],
        $saved["image"]["key"],
        $saved["thumbnail"]["url"],
        $saved["thumbnail"]["key"],
        ($body["aiModel"] ?? null) ?: null,
        $body["status"],
        $body["status"],
        $auth["id"],
    ]);
    $id = (int) $db->lastInsertId();
    $db->prepare(
        "INSERT INTO image_prompts (image_id,main_prompt,negative_prompt) VALUES (?,?,?)",
    )->execute([$id, $body["mainPrompt"], $body["negativePrompt"] ?: null]);
    $db->prepare(
        "INSERT INTO prompt_revisions (image_id,revision,main_prompt,negative_prompt,change_note,created_by) VALUES (?,1,?,?,?,?)",
    )->execute([
        $id,
        $body["mainPrompt"],
        $body["negativePrompt"] ?: null,
        "Initial prompt",
        $auth["id"],
    ]);
    $assetInsert = $db->prepare(
        "INSERT INTO image_assets (public_id,image_id,kind,bucket,object_key,public_url,mime_type,byte_size,checksum_sha256,uploaded_by) VALUES (UUID(),?,?,?,?,?,?,?,?,?)",
    );
    foreach (
        ["image" => "original", "thumbnail" => "thumbnail"]
        as $field => $kind
    ) {
        $assetInsert->execute([
            $id,
            $kind,
            "local",
            $saved[$field]["key"],
            $saved[$field]["url"],
            $finfo->file(__DIR__ . "/../../" . $saved[$field]["key"]),
            filesize(__DIR__ . "/../../" . $saved[$field]["key"]),
            hash_file("sha256", __DIR__ . "/../../" . $saved[$field]["key"]),
            $auth["id"],
        ]);
    }
    $tagIds = json_decode((string) ($body["tagIds"] ?? "[]"), true) ?: [];
    $tagInsert = $db->prepare(
        "INSERT IGNORE INTO image_tags (image_id,tag_id) VALUES (?,?)",
    );
    foreach ($tagIds as $tagId) {
        $tagInsert->execute([$id, (int) $tagId]);
    }
    $db->commit();
    audit($db, $auth["id"], "create", "image", $id, [
        "title" => $body["title"],
        "status" => $body["status"],
    ]);
    success(["id" => $id], 201);
}

if (preg_match('#^images/(\d+)$#', $route, $match)) {
    $id = (int) $match[1];
    if ($method === "DELETE") {
        $db->prepare(
            "UPDATE images SET deleted_at=NOW(),status='unpublished' WHERE id=?",
        )->execute([$id]);
        audit($db, $auth["id"], "delete", "image", $id);
        json_response(null, 204);
    }
    if ($method === "PATCH") {
        $sets = [];
        $params = [];
        $map = [
            "title" => "title",
            "categoryId" => "category_id",
            "aiModel" => "ai_model",
            "status" => "status",
        ];
        foreach ($map as $key => $column) {
            if (array_key_exists($key, $body)) {
                $sets[] = "{$column}=?";
                $params[] = $body[$key];
            }
        }
        $sets[] = "version=version+1";
        if (($body["status"] ?? null) === "published") {
            $sets[] = "published_at=NOW()";
        }
        $params[] = $id;
        $db->prepare(
            "UPDATE images SET " . implode(",", $sets) . " WHERE id=?",
        )->execute($params);
        if (
            array_key_exists("mainPrompt", $body) ||
            array_key_exists("negativePrompt", $body)
        ) {
            $promptSets = [];
            $promptParams = [];
            foreach (
                [
                    "mainPrompt" => "main_prompt",
                    "negativePrompt" => "negative_prompt",
                ]
                as $key => $column
            ) {
                if (array_key_exists($key, $body)) {
                    $promptSets[] = "{$column}=?";
                    $promptParams[] = $body[$key];
                }
            }
            $promptParams[] = $id;
            $db->prepare(
                "UPDATE image_prompts SET " .
                    implode(",", $promptSets) .
                    " WHERE image_id=?",
            )->execute($promptParams);
            $prompt = $db->prepare(
                "SELECT main_prompt,negative_prompt FROM image_prompts WHERE image_id=?",
            );
            $prompt->execute([$id]);
            $revision = $prompt->fetch();
            $db->prepare(
                "INSERT INTO prompt_revisions(image_id,revision,main_prompt,negative_prompt,change_note,created_by) SELECT ?,version,?,?,?,? FROM images WHERE id=?",
            )->execute([
                $id,
                $revision["main_prompt"],
                $revision["negative_prompt"],
                "Prompt updated from admin API",
                $auth["id"],
                $id,
            ]);
        }
        if (isset($body["tagIds"])) {
            $db->prepare("DELETE FROM image_tags WHERE image_id=?")->execute([
                $id,
            ]);
            $insert = $db->prepare(
                "INSERT IGNORE INTO image_tags VALUES (?,?)",
            );
            foreach ($body["tagIds"] as $tagId) {
                $insert->execute([$id, $tagId]);
            }
        }
        audit($db, $auth["id"], "update", "image", $id, $body);
        success(null, 200, "Image updated");
    }
}

foreach (
    [
        "categories" => ["name", "description", "status"],
        "tags" => ["name", "status"],
    ]
    as $entity => $fields
) {
    if ($method === "GET" && $route === $entity) {
        $items = $db
            ->query(
                "SELECT id,name,slug," .
                    ($entity === "categories" ? "description," : "") .
                    "status,created_at AS createdAt,updated_at AS updatedAt FROM {$entity} ORDER BY name",
            )
            ->fetchAll();
        success(["items" => $items]);
    }
    if ($method === "POST" && $route === $entity) {
        require_fields($body, ["name"]);
        $columns = ["name", "slug"];
        $values = [$body["name"], slugify($body["name"])];
        foreach (array_slice($fields, 1) as $field) {
            if (isset($body[$field])) {
                $columns[] = $field;
                $values[] = $body[$field];
            }
        }
        $marks = implode(",", array_fill(0, count($values), "?"));
        $db->prepare(
            "INSERT INTO {$entity} (" .
                implode(",", $columns) .
                ") VALUES ({$marks})",
        )->execute($values);
        success(["id" => (int) $db->lastInsertId()], 201);
    }
    if (preg_match("#^{$entity}/(\\d+)$#", $route, $match)) {
        $id = (int) $match[1];
        if ($method === "DELETE") {
            $db->prepare("DELETE FROM {$entity} WHERE id=?")->execute([$id]);
            json_response(null, 204);
        }
        if ($method === "PATCH") {
            $sets = [];
            $params = [];
            foreach ($fields as $field) {
                if (array_key_exists($field, $body)) {
                    $sets[] = "{$field}=?";
                    $params[] = $body[$field];
                    if ($field === "name") {
                        $sets[] = "slug=?";
                        $params[] = slugify($body[$field]);
                    }
                }
            }
            if (!$sets) {
                throw new ApiException(
                    422,
                    "Validation failed",
                    "VALIDATION_ERROR",
                );
            }
            $params[] = $id;
            $db->prepare(
                "UPDATE {$entity} SET " . implode(",", $sets) . " WHERE id=?",
            )->execute($params);
            audit($db, $auth["id"], "update", rtrim($entity, "s"), $id, $body);
            $st = $db->prepare("SELECT * FROM {$entity} WHERE id=?");
            $st->execute([$id]);
            success(["item" => $st->fetch()]);
        }
    }
}

if ($method === "PUT" && $route === "ad-settings") {
    require_fields($body, [
        "enabled",
        "showAfterClicks",
        "minIntervalSeconds",
        "maxAdsPerSession",
    ]);
    $db->prepare(
        "UPDATE ad_settings SET enabled=?,show_after_clicks=?,min_interval_seconds=?,max_ads_per_session=?,version=version+1,updated_by=? WHERE id=1",
    )->execute([
        (int) $body["enabled"],
        (int) $body["showAfterClicks"],
        (int) $body["minIntervalSeconds"],
        (int) $body["maxAdsPerSession"],
        $auth["id"],
    ]);
    audit($db, $auth["id"], "update", "ad_setting", 1, $body);
    success();
}

if ($method === "GET" && $route === "analytics") {
    $days = min(365, max(1, (int) ($_GET["days"] ?? 30)));
    $events = $db
        ->query(
            "SELECT event_type AS eventType,COUNT(*) AS _count FROM analytics_events WHERE created_at>=DATE_SUB(NOW(),INTERVAL {$days} DAY) GROUP BY event_type ORDER BY event_type",
        )
        ->fetchAll();
    $summary = [
        "users" => (int) $db
            ->query("SELECT COUNT(*) FROM users WHERE deleted_at IS NULL")
            ->fetchColumn(),
        "publishedImages" => (int) $db
            ->query(
                "SELECT COUNT(*) FROM images WHERE status='published' AND deleted_at IS NULL",
            )
            ->fetchColumn(),
        "pendingReports" => (int) $db
            ->query(
                "SELECT COUNT(*) FROM content_reports WHERE status='pending'",
            )
            ->fetchColumn(),
        "favorites" => (int) $db
            ->query("SELECT COUNT(*) FROM user_favorites")
            ->fetchColumn(),
    ];
    success(["summary" => $summary, "events" => $events]);
}

if ($method === "GET" && $route === "reports") {
    [$page, $limit, $offset] = page_params();
    $items = $db
        ->query(
            "SELECT r.*,r.created_at AS createdAt,u.email AS userEmail,i.title AS imageTitle FROM content_reports r JOIN users u ON u.id=r.user_id JOIN images i ON i.id=r.image_id ORDER BY r.created_at DESC LIMIT {$limit} OFFSET {$offset}",
        )
        ->fetchAll();
    foreach ($items as &$item) {
        $item["user"] = ["email" => $item["userEmail"]];
        $item["image"] = ["title" => $item["imageTitle"]];
        unset($item["userEmail"], $item["imageTitle"]);
    }
    success(["items" => $items, "page" => $page, "limit" => $limit]);
}

if (preg_match('#^reports/(\d+)$#', $route, $match) && $method === "PATCH") {
    if (
        !in_array(
            $body["status"] ?? "",
            ["reviewed", "dismissed", "actioned"],
            true,
        )
    ) {
        throw new ApiException(422, "Validation failed", "VALIDATION_ERROR");
    }
    $db->prepare(
        "UPDATE content_reports SET status=?,reviewed_by=?,reviewed_at=NOW() WHERE id=?",
    )->execute([$body["status"], $auth["id"], $match[1]]);
    audit($db, $auth["id"], "review", "content_report", (int) $match[1], $body);
    success();
}
