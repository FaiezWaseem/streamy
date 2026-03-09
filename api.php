<?php
// api.php - Unified endpoint for likes and comments
require_once 'auth.php';
requireLogin();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $videoId = $data['video_id'] ?? 0;
    $userId = $_SESSION['user_id'];

    if (!$videoId) {
        echo json_encode(['error' => 'Missing video_id']);
        exit;
    }

    if ($action === 'like') {
        // Toggle Like
        $stmt = $db->prepare("SELECT id FROM likes WHERE user_id = ? AND video_id = ?");
        $stmt->execute([$userId, $videoId]);
        if ($stmt->fetch()) {
            // Unlike
            $db->prepare("DELETE FROM likes WHERE user_id = ? AND video_id = ?")->execute([$userId, $videoId]);
            $liked = false;
        } else {
            // Like
            $db->prepare("INSERT INTO likes (user_id, video_id) VALUES (?, ?)")->execute([$userId, $videoId]);
            $liked = true;
        }
        
        // Get updated count
        $stmt = $db->prepare("SELECT COUNT(*) FROM likes WHERE video_id = ?");
        $stmt->execute([$videoId]);
        $count = $stmt->fetchColumn();
        
        echo json_encode(['liked' => $liked, 'count' => $count]);

    } elseif ($action === 'comment') {
        // Add Comment
        $content = trim($data['content'] ?? '');
        if (empty($content)) {
            echo json_encode(['error' => 'Comment cannot be empty']);
            exit;
        }

        $stmt = $db->prepare("INSERT INTO comments (user_id, video_id, content) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $videoId, $content]);
        
        echo json_encode(['success' => true]);
    }
} elseif ($method === 'GET') {
    $videoId = $_GET['video_id'] ?? 0;
    
    if ($action === 'comments') {
        $stmt = $db->prepare("
            SELECT c.*, u.username, u.avatar 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.video_id = ? 
            ORDER BY c.created_at DESC
        ");
        $stmt->execute([$videoId]);
        echo json_encode($stmt->fetchAll());

    } elseif ($action === 'likes') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM likes WHERE video_id = ?");
        $stmt->execute([$videoId]);
        $count = $stmt->fetchColumn();
        
        $stmt = $db->prepare("SELECT 1 FROM likes WHERE video_id = ? AND user_id = ?");
        $stmt->execute([$videoId, $_SESSION['user_id']]);
        $liked = (bool)$stmt->fetch();
        
        echo json_encode(['count' => $count, 'liked' => $liked]);
    }
}
?>
