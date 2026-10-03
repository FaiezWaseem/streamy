<?php
// seed_admin.php
// CLI script to create (or update) the admin user.
// Usage: php seed_admin.php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/db.php';

$username = getenv('admin_username') ?: 'admin';
$email = getenv('admin_email');
$password = getenv('admin_pass');
if (!$email || !$password) {
    fwrite(STDERR, "Set admin_email and admin_pass in .env before seeding.\n");
    exit(1);
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

$stmt = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
$stmt->execute([$email, $username]);
$matches = $stmt->fetchAll();
if (count($matches) > 1) {
    fwrite(STDERR, "Username and email belong to different accounts; resolve the conflict before seeding.\n");
    exit(1);
}
$existing = $matches[0] ?? null;

if ($existing) {
    $stmt = $db->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?");
    $stmt->execute([$username, $email, $hashed_password, $existing['id']]);
    echo "Admin user updated (id: {$existing['id']}).\n";
} else {
    $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
    $stmt->execute([$username, $email, $hashed_password]);
    echo "Admin user created (id: {$db->lastInsertId()}).\n";
}
