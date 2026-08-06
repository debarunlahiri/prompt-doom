<?php

declare(strict_types=1);

require dirname(__DIR__) . "/src/Database.php";
$config = require dirname(__DIR__) . "/config.php";
$db = Database::connect($config);
$email = strtolower(trim((string) env("INITIAL_ADMIN_EMAIL", "")));
$password = (string) env("INITIAL_ADMIN_PASSWORD", "");
$name = trim((string) env("INITIAL_ADMIN_NAME", "Prompt Doom Admin"));

if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
    fwrite(
        STDERR,
        "Set INITIAL_ADMIN_EMAIL and INITIAL_ADMIN_PASSWORD (minimum 12 characters), or use /admin/setup.php.\n",
    );
    exit(1);
}
$statement = $db->prepare(
    'INSERT INTO admin_users (public_id,email,password_hash,name,role) VALUES (UUID(),?,?,?,?) ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash),name=VALUES(name),role=VALUES(role),status="active"',
);
$statement->execute([
    $email,
    password_hash($password, PASSWORD_DEFAULT),
    $name,
    "super_admin",
]);
echo "Administrator account is ready for {$email}.\n";
