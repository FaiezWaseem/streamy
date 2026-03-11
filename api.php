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
        $videoId = $data['video_id'] ?? 0;
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
        $videoId = $data['video_id'] ?? 0;
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
    
    if ($action === 'get_comments') {
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
    } elseif ($action === 'fetch_reels') {
        $offset = $_GET['offset'] ?? 0;
        $limit = 5;
        
        // Fetch random videos but try to avoid duplicates if possible (simple random for now)
        // In a real app, we'd exclude IDs already seen
        $excludeIds = $_GET['exclude'] ?? '';
        $excludeArray = array_filter(explode(',', $excludeIds), 'is_numeric');
        
        $sql = "SELECT * FROM videos WHERE visibility = 'public'";
        if (!empty($excludeArray)) {
            $placeholders = implode(',', array_fill(0, count($excludeArray), '?'));
            $sql .= " AND id NOT IN ($placeholders)";
        }
        $sql .= " ORDER BY RANDOM() LIMIT ?";
        
        $params = array_merge($excludeArray, [$limit]);
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $videos = $stmt->fetchAll();
        
        echo json_encode($videos);
    }
}
?>
