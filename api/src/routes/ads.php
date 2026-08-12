<?php

declare(strict_types=1);

/**
 * Request context provided by api/index.php.
 *
 * @var array<string, mixed> $config
 * @var PDO $db
 * @var string $method
 * @var string $path
 */

$route = substr($path, strlen("/api/v1/ads/"));
if ($method === "GET" && $route === "config") {
    $row = $db
        ->query(
            "SELECT enabled,show_after_clicks AS showAfterClicks,min_interval_seconds AS minIntervalSeconds,max_ads_per_session AS maxAdsPerSession,updated_at AS updatedAt FROM ad_settings WHERE id=1",
        )
        ->fetch();
    if ($row) {
        $row["enabled"] = (bool) $row["enabled"];
    }
    success(["config" => $row ?: null]);
}
if ($method === "POST" && $route === "events") {
    $auth = current_auth($config, null, true);
    $body = input();
    require_fields($body, ["sessionId", "eventType"]);
    if (
        !in_array(
            $body["eventType"],
            ["displayed", "closed", "clicked", "failed", "skipped"],
            true,
        )
    ) {
        throw new ApiException(422, "Validation failed", "VALIDATION_ERROR");
    }
    $db->prepare(
        "INSERT INTO ad_events(event_id,user_id,session_id,event_type,provider,placement,metadata) VALUES (UUID(),?,?,?,?,?,?)",
    )->execute([
        $auth["id"] ?? null,
        $body["sessionId"],
        $body["eventType"],
        $body["provider"] ?? null,
        $body["placement"] ?? null,
        json_encode($body["metadata"] ?? null),
    ]);
    success(null, 201);
}
