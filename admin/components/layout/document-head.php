<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Prompt Doom Admin</title>
  <meta name="description" content="Administration console for Prompt Doom">
  <link rel="icon" href="assets/images/prompt_doom_logo.png?v=<?= filemtime(__DIR__ . '/../../assets/images/prompt_doom_logo.png') ?>" type="image/png" sizes="any">
  <link rel="apple-touch-icon" href="assets/images/prompt_doom_logo.png?v=<?= filemtime(__DIR__ . '/../../assets/images/prompt_doom_logo.png') ?>">
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.2/cropper.min.js" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
  <?php foreach (["base", "auth", "layout", "pages", "overlays", "responsive"] as $stylesheet): ?>
    <link rel="stylesheet" href="assets/styles/<?= $stylesheet ?>.css?v=<?= filemtime(__DIR__ . "/../../assets/styles/{$stylesheet}.css") ?>">
  <?php endforeach; ?>
</head>
