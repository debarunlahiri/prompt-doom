<?php

declare(strict_types=1);

require __DIR__ . "/api/src/Database.php";

$config = require __DIR__ . "/api/config.php";
$imageId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT, [
    "options" => ["min_range" => 1],
]);

if ($imageId === false || $imageId === null) {
    http_response_code(404);
    exit("Image not found");
}

try {
    $database = Database::connect($config);
    $statement = $database->prepare(
        "SELECT title FROM images WHERE id=? AND status='published' AND deleted_at IS NULL",
    );
    $statement->execute([$imageId]);
    $image = $statement->fetch();
} catch (Throwable) {
    http_response_code(503);
    exit("Unable to open this image right now");
}

if (!$image) {
    http_response_code(404);
    exit("Image not found");
}

$title = htmlspecialchars(
    (string) $image["title"],
    ENT_QUOTES | ENT_SUBSTITUTE,
    "UTF-8",
);
$appUrl = "promptdoom://image/{$imageId}";
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $title ?> · Prompt Doom</title>
  <link rel="icon" href="admin/assets/images/prompt_doom_logo.png" type="image/png">
  <style>
    :root { color-scheme: light dark; font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    body { min-height: 100vh; margin: 0; display: grid; place-items: center; background: #111018; color: #fff; }
    main { width: min(88vw, 420px); text-align: center; }
    img { width: 88px; height: 88px; border-radius: 22px; object-fit: contain; background: #fff; }
    h1 { margin: 22px 0 8px; font-size: 1.55rem; }
    p { margin: 0 0 24px; color: #b9b6c7; line-height: 1.5; }
    a { display: inline-flex; align-items: center; justify-content: center; min-height: 48px; padding: 0 24px; border-radius: 14px; background: #7457ff; color: #fff; font-weight: 700; text-decoration: none; }
  </style>
</head>
<body>
  <main>
    <img src="admin/assets/images/prompt_doom_logo.png" alt="Prompt Doom">
    <h1><?= $title ?></h1>
    <p>Opening this image in Prompt Doom…</p>
    <a href="<?= $appUrl ?>">Open in Prompt Doom</a>
  </main>
  <script>
    window.location.replace(<?= json_encode($appUrl, JSON_UNESCAPED_SLASHES) ?>);
  </script>
</body>
</html>
