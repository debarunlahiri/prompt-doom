<?php

declare(strict_types=1);

session_start([
    "cookie_httponly" => true,
    "cookie_samesite" => "Strict",
    "cookie_secure" => isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off",
]);

require dirname(__DIR__) . "/api/src/Database.php";
$config = require dirname(__DIR__) . "/api/config.php";
$db = Database::connect($config);

if (!isset($_SESSION["admin_setup_csrf"])) {
    $_SESSION["admin_setup_csrf"] = bin2hex(random_bytes(32));
}

$error = null;
$adminExists = (int) $db->query("SELECT COUNT(*) FROM admin_users")->fetchColumn() > 0;

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$adminExists) {
    $name = trim((string) ($_POST["name"] ?? ""));
    $email = strtolower(trim((string) ($_POST["email"] ?? "")));
    $password = (string) ($_POST["password"] ?? "");
    $passwordConfirmation = (string) ($_POST["password_confirmation"] ?? "");
    $csrf = (string) ($_POST["csrf_token"] ?? "");

    if (!hash_equals((string) $_SESSION["admin_setup_csrf"], $csrf)) {
        $error = "The setup form expired. Refresh the page and try again.";
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
        $error = "Enter a name between 2 and 120 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Enter a valid email address.";
    } elseif (strlen($password) < 12) {
        $error = "Use a password containing at least 12 characters.";
    } elseif ($password !== $passwordConfirmation) {
        $error = "The password confirmation does not match.";
    } else {
        $lock = $db->query("SELECT GET_LOCK('prompt_doom_initial_admin_setup', 5)");
        $lockAcquired = (int) $lock->fetchColumn() === 1;

        if (!$lockAcquired) {
            $error = "Setup is currently busy. Please try again.";
        } else {
            try {
                $adminExists =
                    (int) $db
                        ->query("SELECT COUNT(*) FROM admin_users")
                        ->fetchColumn() > 0;

                if ($adminExists) {
                    $error = "Administrator setup has already been completed.";
                } else {
                    $statement = $db->prepare(
                        "INSERT INTO admin_users (public_id, email, password_hash, name, role, status) VALUES (UUID(), ?, ?, ?, 'super_admin', 'active')",
                    );
                    $statement->execute([
                        $email,
                        password_hash($password, PASSWORD_DEFAULT),
                        $name,
                    ]);
                    unset($_SESSION["admin_setup_csrf"]);
                    header("Location: index.php?setup=complete");
                    exit();
                }
            } finally {
                $db->query("SELECT RELEASE_LOCK('prompt_doom_initial_admin_setup')");
            }
        }
    }
}

function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}
?>
<!doctype html>
<html lang="en">
<?php require __DIR__ . "/components/layout/document-head.php"; ?>
<body>
  <main class="login-page">
    <section class="login-visual">
      <div class="brand-icon"><img src="assets/images/prompt_doom_logo.png?v=<?= filemtime(__DIR__ . '/assets/images/prompt_doom_logo.png') ?>" alt="Prompt Doom"></div>
      <div>
        <span class="eyebrow">INITIAL SETUP</span>
        <h1>Create the first administrator.</h1>
        <p>This one-time setup creates the super-admin account used to manage Prompt Doom.</p>
      </div>
      <div class="secure-note"><i data-lucide="lock-keyhole"></i> Setup closes automatically after the first account is created</div>
    </section>
    <section class="login-panel">
      <div class="login-card">
        <span class="eyebrow">ADMIN CONSOLE</span>
        <?php if ($adminExists): ?>
          <h2>Setup complete</h2>
          <p>An administrator account already exists. Initial setup is disabled.</p>
          <a class="primary wide text-link" href="index.php"><i data-lucide="log-in"></i> Go to admin sign in</a>
        <?php else: ?>
          <h2>Create administrator</h2>
          <p>Enter the credentials for the first super-admin account.</p>
          <?php if ($error !== null): ?>
            <div class="alert"><?= escape($error) ?></div>
          <?php endif; ?>
          <form method="post" action="setup.php" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= escape((string) $_SESSION["admin_setup_csrf"]) ?>">
            <label>Full name<input name="name" type="text" value="<?= escape((string) ($_POST["name"] ?? "")) ?>" placeholder="Enter your full name" minlength="2" maxlength="120" required autocomplete="name"></label>
            <label>Email address<input name="email" type="email" value="<?= escape((string) ($_POST["email"] ?? "")) ?>" placeholder="Enter your email address" maxlength="190" required autocomplete="email"></label>
            <label>Password<input name="password" type="password" placeholder="Create a password" minlength="12" required autocomplete="new-password"></label>
            <label>Confirm password<input name="password_confirmation" type="password" placeholder="Enter the password again" minlength="12" required autocomplete="new-password"></label>
            <button class="primary wide" type="submit"><i data-lucide="user-plus"></i><span>Create super-admin</span></button>
          </form>
          <a class="secondary-link" href="index.php"><i data-lucide="arrow-left"></i> Back to sign in</a>
        <?php endif; ?>
      </div>
    </section>
  </main>
  <script>lucide.createIcons();</script>
</body>
</html>
