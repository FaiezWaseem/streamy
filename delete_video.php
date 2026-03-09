<?php
// delete_video.php
require_once 'auth.php';
requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$videoId = $data['id'] ?? null;

if (!$videoId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing video ID']);
    exit;
}

// Check if video exists
$stmt = $db->prepare("SELECT filepath, thumbnail FROM videos WHERE id = ?");
$stmt->execute([$videoId]);
$video = $stmt->fetch();

if (!$video) {
    http_response_code(404);
    echo json_encode(['error' => 'Video not found']);
    exit;
}

// Delete from DB (Manual cascade)
try {
    $db->beginTransaction();
    
    // Delete related data first
    $db->prepare("DELETE FROM watch_history WHERE video_id = ?")->execute([$videoId]);
    $db->prepare("DELETE FROM comments WHERE video_id = ?")->execute([$videoId]);
    $db->prepare("DELETE FROM likes WHERE video_id = ?")->execute([$videoId]);
    
    // Delete the video itself
    $db->prepare("DELETE FROM videos WHERE id = ?")->execute([$videoId]);
    
    $db->commit();
    
    // Delete physical files
    if (file_exists($video['filepath'])) {
        unlink($video['filepath']);
    }
    
    // Delete thumbnail if it's not a generic placeholder
    // (Assuming all thumbnails are unique per video as per upload logic)
    $thumbPath = __DIR__ . '/' . $video['thumbnail'];
    if (!empty($video['thumbnail']) && file_exists($thumbPath) && is_file($thumbPath)) {
        unlink($thumbPath);
    }
    
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    $db->rollBack();
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
