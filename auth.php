<?php
// auth.php
session_start();
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
require_once 'db.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getCurrentUser($db) {
    if (!isLoggedIn()) return null;
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
?>
