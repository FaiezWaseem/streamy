<?php
require_once __DIR__ . '/mobile_auth.php';
require_once __DIR__ . '/mobile_thumbnail.php';

header('Content-Type: application/json; charset=utf-8');
$userId = mobileRequireUser($db);

function uploadJson($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

$uploadId = (string)($_POST['upload_id'] ?? '');
$filename = basename((string)($_POST['filename'] ?? ''));
$index = filter_var($_POST['chunk_index'] ?? null, FILTER_VALIDATE_INT);
$total = filter_var($_POST['total_chunks'] ?? null, FILTER_VALIDATE_INT);
$expectedBytes = filter_var($_POST['total_bytes'] ?? null, FILTER_VALIDATE_INT);
$offset = filter_var($_POST['offset'] ?? null, FILTER_VALIDATE_INT);
$allowed = ['mp4', 'mov', 'm4v', 'webm', 'mkv', 'avi'];
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

if (!preg_match('/^[a-f0-9-]{16,64}$/i', $uploadId) || !$filename || !in_array($extension, $allowed, true) ||
    $index === false || $total === false || $total < 1 || $index < 0 || $index >= $total ||
    $expectedBytes === false || $expectedBytes < 1 || $offset === false || $offset < 0 ||
    empty($_FILES['chunk']) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK) {
    uploadJson(['error' => 'Invalid upload chunk'], 400);
}

$tempDir = __DIR__ . '/temp_uploads/mobile';
if (!is_dir($tempDir) && !mkdir($tempDir, 0755, true)) {
    uploadJson(['error' => 'Could not create upload directory'], 500);
}
$tempPath = $tempDir . '/' . $userId . '_' . $uploadId . '.part';
if (($index === 0 && $offset !== 0) || ($index > 0 && (!is_file($tempPath) || filesize($tempPath) !== $offset))) {
    uploadJson(['error' => 'Upload chunks arrived out of order'], 409);
}
if ($index === 0) {
    $out = fopen($tempPath, 'wb');
} else {
    $out = fopen($tempPath, 'ab');
}
if (!$out || !($in = fopen($_FILES['chunk']['tmp_name'], 'rb'))) {
    if ($out) fclose($out);
    uploadJson(['error' => 'Could not write upload chunk'], 500);
}
stream_copy_to_stream($in, $out);
fclose($in);
fclose($out);

if ($index + 1 < $total) uploadJson(['success' => true, 'complete' => false]);
clearstatcache(true, $tempPath);
if (filesize($tempPath) !== $expectedBytes) {
    @unlink($tempPath);
    uploadJson(['error' => 'Upload size verification failed'], 400);
}

$targetDir = __DIR__ . '/videos';
if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
    @unlink($tempPath);
    uploadJson(['error' => 'Could not create media directory'], 500);
}
$safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($filename, PATHINFO_FILENAME));
$storedName = $safeBase . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
$targetPath = $targetDir . '/' . $storedName;
if (!rename($tempPath, $targetPath)) {
    uploadJson(['error' => 'Could not finalize upload'], 500);
}

$title = trim((string)($_POST['title'] ?? pathinfo($filename, PATHINFO_FILENAME)));
$description = trim((string)($_POST['description'] ?? ''));
$category = trim((string)($_POST['category'] ?? 'Mobile Upload')) ?: 'Mobile Upload';
$duration = max(0, (int)($_POST['duration'] ?? 0));
$thumbnail = mobileVideoThumbnail($targetPath);
$preview = mobileVideoPreview($targetPath, $duration);
try {
    $stmt = $db->prepare("INSERT INTO videos (title, description, filename, filepath, category, duration, thumbnail, preview_gif, visibility, uploader_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'public', ?)");
    $stmt->execute([$title ?: pathinfo($filename, PATHINFO_FILENAME), $description, $storedName, $targetPath, $category, $duration, $thumbnail, $preview, $userId]);
    uploadJson(['success' => true, 'complete' => true, 'bytes' => $expectedBytes, 'id' => (int)$db->lastInsertId()]);
} catch (Throwable $error) {
    @unlink($targetPath);
    uploadJson(['error' => 'Could not save uploaded video'], 500);
}
