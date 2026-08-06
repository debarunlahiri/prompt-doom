<?php declare(strict_types=1) ?>
<!doctype html>
<html lang="en">
<?php require __DIR__ . "/components/layout/document-head.php"; ?>
<body>
  <?php require __DIR__ . "/components/auth/login.php"; ?>
  <?php require __DIR__ . "/components/layout/admin-shell.php"; ?>
  <script>window.PROMPT_DOOM_API = '../api/api/v1';</script>
  <script type="module" src="assets/js/app.js"></script>
</body>
</html>
