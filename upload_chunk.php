<?php
// upload_chunk.php
require_once 'auth.php';

// We need a session, so login is required
if (!isLoggedIn()) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

// Check for errors
if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    die(json_encode(['error' => 'Upload error code: ' . $_FILES['file']['error']]));
}

$tempDir = __DIR__ . '/temp_uploads';
if (!is_dir($tempDir)) {
    if (!mkdir($tempDir, 0755, true)) {
        http_response_code(500);
        die(json_encode(['error' => 'Failed to create temp directory']));
    }
}

// Unique identifier for the upload session (sent from client)
$uploadId = $_POST['upload_id'] ?? '';
// Original filename (sent from client)
$fileName = $_POST['file_name'] ?? '';
// Current chunk index (0-based)
$chunkIndex = isset($_POST['chunk_index']) ? intval($_POST['chunk_index']) : 0;
// Total chunks
$totalChunks = isset($_POST['total_chunks']) ? intval($_POST['total_chunks']) : 1;

if (empty($uploadId) || empty($fileName)) {
    http_response_code(400);
    die(json_encode(['error' => 'Missing upload_id or file_name']));
}

// Sanitize filename for safety
$safeFileName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($fileName));
$tempFilePath = $tempDir . '/' . $uploadId . '_' . $safeFileName;

// Open temp file for appending
$mode = ($chunkIndex === 0) ? 'wb' : 'ab';
$out = @fopen($tempFilePath, $mode);

if ($out) {
    $in = @fopen($_FILES['file']['tmp_name'], 'rb');
    if ($in) {
        while ($buff = fread($in, 4096)) {
            fwrite($out, $buff);
        }
        fclose($in);
    } else {
        fclose($out);
        http_response_code(500);
        die(json_encode(['error' => 'Failed to read input chunk']));
    }
    fclose($out);
} else {
    http_response_code(500);
    die(json_encode(['error' => 'Failed to open output file']));
}

// If this is the last chunk, verify size or just return success with path
// For simplicity, we just return the temp file name so the main form can use it.
// Ideally, we could check file size here if we knew the total size.

echo json_encode([
    'status' => 'success',
    'chunk_index' => $chunkIndex,
    'temp_path' => $uploadId . '_' . $safeFileName
]);
?>
