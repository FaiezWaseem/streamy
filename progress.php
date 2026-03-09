<?php
// progress.php
require_once 'auth.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $videoId = $data['video_id'] ?? null;
    $currentTime = $data['current_time'] ?? 0;
    $duration = $data['duration'] ?? 0;
    
    if (!$videoId) {
        http_response_code(400);
        exit;
    }

    $userId = $_SESSION['user_id'];
    
    // Close session immediately to prevent locking other requests
    session_write_close();

    $completed = ($duration > 0 && ($currentTime / $duration) > 0.95) ? 1 : 0; // Mark completed if > 95%

    // Update or Insert progress
    $stmt = $db->prepare("INSERT INTO watch_history (user_id, video_id, progress, completed, last_watched_at) 
                          VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                          ON CONFLICT(user_id, video_id) 
                          DO UPDATE SET progress = excluded.progress, 
                                        completed = excluded.completed, 
                                        last_watched_at = CURRENT_TIMESTAMP");
    $stmt->execute([$userId, $videoId, $currentTime, $completed]);

    // Also update video duration if not set
    if ($duration > 0) {
        $stmt = $db->prepare("UPDATE videos SET duration = ? WHERE id = ? AND duration = 0");
        $stmt->execute([$duration, $videoId]);
    }
    
    echo json_encode(['status' => 'success']);
}
?>
