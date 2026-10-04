<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/mobile_auth.php';

// Do not keep the PHP session lock while the client is buffering the stream.
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

if (!isLoggedIn() && !mobileCurrentUserId($db)) {
    http_response_code(403);
    exit('Unauthorized');
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    http_response_code(400);
    exit('No video ID specified.');
}

$viewerId = mobileCurrentUserId($db);
if (isLoggedIn()) {
    $viewerId = (int) $_SESSION['user_id'];
}

$stmt = $db->prepare("SELECT filepath FROM videos WHERE id = ? AND (visibility = 'public' OR uploader_id = ?)");
$stmt->execute([$id, $viewerId]);
$file = $stmt->fetchColumn();
if (!$file || !is_file($file) || !is_readable($file)) {
    http_response_code(404);
    exit('Video not found.');
}

$size = filesize($file);
if ($size === false || $size < 1) {
    http_response_code(404);
    exit('Video is empty.');
}

$start = 0;
$end = $size - 1;
$status = 200;
$rangeHeader = $_SERVER['HTTP_RANGE'] ?? '';

// Support normal and suffix byte ranges so browsers can start playback and
// seek without downloading the entire video first.
if ($rangeHeader !== '') {
    if (!preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $matches)) {
        header("Content-Range: bytes */{$size}");
        http_response_code(416);
        exit;
    }

    if ($matches[1] === '' && $matches[2] === '') {
        header("Content-Range: bytes */{$size}");
        http_response_code(416);
        exit;
    }

    if ($matches[1] === '') {
        $suffixLength = (int) $matches[2];
        if ($suffixLength < 1) {
            header("Content-Range: bytes */{$size}");
            http_response_code(416);
            exit;
        }
        $start = max(0, $size - $suffixLength);
    } else {
        $start = (int) $matches[1];
        if ($matches[2] !== '') {
            $end = (int) $matches[2];
        }
    }

    if ($start >= $size || $end < $start) {
        header("Content-Range: bytes */{$size}");
        http_response_code(416);
        exit;
    }

    $end = min($end, $size - 1);
    $status = 206;
}

$length = $end - $start + 1;
$mimeType = function_exists('mime_content_type') ? mime_content_type($file) : false;
header('Content-Type: ' . ($mimeType ?: 'application/octet-stream'));
header('Accept-Ranges: bytes');
header('Content-Length: ' . $length);
header('Cache-Control: private, no-transform');
header('X-Accel-Buffering: no');
if ($status === 206) {
    http_response_code(206);
    header("Content-Range: bytes {$start}-{$end}/{$size}");
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    exit;
}

// Clear PHP output buffers and stream bounded chunks instead of allowing a
// hosting-level PHP buffer to accumulate a large file before sending it.
while (ob_get_level() > 0) {
    ob_end_clean();
}
@ini_set('zlib.output_compression', '0');
$handle = fopen($file, 'rb');
if ($handle === false || fseek($handle, $start) !== 0) {
    http_response_code(500);
    exit;
}

$remaining = $length;
while ($remaining > 0 && !connection_aborted()) {
    $chunk = fread($handle, min(1024 * 256, $remaining));
    if ($chunk === false || $chunk === '') {
        break;
    }
    echo $chunk;
    $remaining -= strlen($chunk);
    flush();
}

fclose($handle);
