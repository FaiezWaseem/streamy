<?php
// Shared bearer-token authentication for the Streamy mobile API.
require_once __DIR__ . '/db.php';

$db->exec("CREATE TABLE IF NOT EXISTS api_tokens (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    token_hash TEXT NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id)
)");

function mobileBearerToken() {
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!$header && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    return preg_match('/^Bearer\s+([a-f0-9]{64})$/i', $header, $matches) ? $matches[1] : null;
}

function mobileCurrentUserId($db) {
    $token = mobileBearerToken();
    if (!$token) return null;
    $stmt = $db->prepare('SELECT user_id FROM api_tokens WHERE token_hash = ? AND expires_at > CURRENT_TIMESTAMP');
    $stmt->execute([hash('sha256', $token)]);
    $userId = $stmt->fetchColumn();
    return $userId === false ? null : (int)$userId;
}

function mobileRequireUser($db) {
    $userId = mobileCurrentUserId($db);
    if (!$userId) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    return $userId;
}
