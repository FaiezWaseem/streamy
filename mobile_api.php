<?php
require_once __DIR__ . '/mobile_auth.php';
require_once __DIR__ . '/recommendations.php';

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function mobileJson($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($action === 'login' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $stmt = $db->prepare('SELECT id, password FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        mobileJson(['error' => 'Invalid username or password'], 401);
    }

    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare("INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, datetime('now', '+90 days'))");
    $stmt->execute([(int)$user['id'], hash('sha256', $token)]);
    mobileJson(['token' => $token, 'expires_in' => 7776000, 'user_id' => (int)$user['id']]);
}

$userId = mobileRequireUser($db);

if ($action === 'logout' && $method === 'POST') {
    $stmt = $db->prepare('DELETE FROM api_tokens WHERE token_hash = ?');
    $stmt->execute([hash('sha256', mobileBearerToken())]);
    mobileJson(['success' => true]);
}

if ($action === 'videos' && $method === 'GET') {
    $stmt = $db->prepare("SELECT id, title, description, filename, category, thumbnail, preview_gif, tags, uploader_id, duration, views, created_at
        FROM videos WHERE visibility = 'public' OR uploader_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $videos = array_map(function ($video) use ($userId) {
        return [
            'id' => (int)$video['id'],
            'title' => $video['title'],
            'description' => $video['description'] ?? '',
            'filename' => $video['filename'],
            'category' => $video['category'] ?: 'Uncategorized',
            'thumbnail' => $video['thumbnail'] ?: null,
            'preview_gif' => $video['preview_gif'] ?: null,
            'tags' => videoTags($video['tags']),
            'can_edit' => (int)$video['uploader_id'] === $userId,
            'duration' => (int)$video['duration'],
            'views' => (int)$video['views'],
            'created_at' => $video['created_at'],
        ];
    }, $stmt->fetchAll());
    mobileJson(['videos' => $videos, 'history' => interestHistory($db, $userId), 'user_id' => $userId]);
}

if ($action === 'tags' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $stmt = $db->prepare('SELECT id FROM videos WHERE id=? AND uploader_id=?');
    $stmt->execute([(int)($input['video_id'] ?? 0),$userId]);
    if (!$stmt->fetchColumn()) mobileJson(['error'=>'You can only edit tags on your own videos.'],403);
    $tags = videoTags($input['tags'] ?? []);
    $db->prepare('UPDATE videos SET tags=? WHERE id=?')->execute([json_encode($tags),(int)$input['video_id']]);
    mobileJson(['success'=>true,'tags'=>$tags]);
}
if ($action === 'watch' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $duration = (float)($input['duration'] ?? 0);
    if (is_finite($duration) && $duration > 0 && $duration <= 86400) {
        $db->prepare("UPDATE videos SET duration=? WHERE id=? AND duration=0 AND (visibility='public' OR uploader_id=?)")
            ->execute([$duration,(int)($input['video_id'] ?? 0),$userId]);
    }
    $recorded = recordInterestWatch($db,$userId,(int)($input['video_id'] ?? 0),(string)($input['event_id'] ?? ''),(float)($input['watched_seconds'] ?? 0),$input['watched_at'] ?? null);
    mobileJson(['recorded'=>$recorded]);
}

mobileJson(['error' => 'Unknown action'], 404);
