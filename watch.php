<?php
// watch.php
require_once 'auth.php';
requireLogin();

$user = getCurrentUser($db);
$videoId = $_GET['id'] ?? 0;

// Fetch Video Details
$stmt = $db->prepare("SELECT * FROM videos WHERE id = ?");
$stmt->execute([$videoId]);
$video = $stmt->fetch();

if (!$video) {
    die("Video not found");
}

// Check Privacy
if ($video['visibility'] === 'private' && $video['uploader_id'] != $user['id']) {
    die("This video is private.");
}

// Fetch Related Videos (same category, public only)
$stmt = $db->prepare("SELECT * FROM videos WHERE category = ? AND id != ? AND visibility = 'public' LIMIT 5");
$stmt->execute([$video['category'], $videoId]);
$related = $stmt->fetchAll();

// Fetch Comments
$stmt = $db->prepare("
    SELECT c.*, u.username, u.avatar 
    FROM comments c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.video_id = ? 
    ORDER BY c.created_at DESC
");
$stmt->execute([$videoId]);
$comments = $stmt->fetchAll();

// Fetch Likes
$stmt = $db->prepare("SELECT COUNT(*) FROM likes WHERE video_id = ?");
$stmt->execute([$videoId]);
$likesCount = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT 1 FROM likes WHERE video_id = ? AND user_id = ?");
$stmt->execute([$videoId, $user['id']]);
$isLiked = (bool)$stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($video['title']) ?> - Streamy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background-color: #141414; color: #fff; }</style>
</head>
<body class="font-sans antialiased flex bg-black text-white">

    <?php include 'sidebar.php'; ?>

    <main class="md:ml-64 flex-1 p-4 md:p-10 min-h-screen w-full min-w-0">
        <div class="mb-4 md:hidden">
            <button onclick="toggleSidebar()" class="text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
        </div>
        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Main Content -->
            <div class="flex-1">
                <!-- Player -->
                <div class="aspect-video bg-black rounded-lg overflow-hidden shadow-2xl w-full">
                    <video id="player" controls autoplay class="w-full h-full object-contain" src="stream.php?id=<?= $videoId ?>"></video>
                </div>

                <!-- Info -->
                <div class="mt-4">
                    <h1 class="text-2xl font-bold break-words"><?= htmlspecialchars($video['title']) ?></h1>
                    <div class="flex flex-col md:flex-row md:items-center justify-between mt-2 text-gray-400 text-sm gap-4">
                        <span><?= $video['views'] ?> views • <?= date('M d, Y', strtotime($video['created_at'])) ?></span>
                        <div class="flex items-center space-x-4">
                            <button id="likeBtn" class="flex items-center space-x-2 px-4 py-2 rounded-full <?= $isLiked ? 'bg-white text-black' : 'bg-gray-800 hover:bg-gray-700' ?> transition">
                                <svg class="w-5 h-5" fill="<?= $isLiked ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 10h4.764a2 2 0 011.789 2.894l-3.5 7A2 2 0 0115.263 21h-4.017c-.163 0-.326-.02-.485-.06L7 20m7-10V5a2 2 0 00-2-2h-.095c-.5 0-.905.405-.905.905 0 .714-.211 1.412-.608 2.006L7 11v9m7-10h-2M7 20H5a2 2 0 01-2-2v-6a2 2 0 012-2h2.5"/></svg>
                                <span id="likeCount"><?= $likesCount ?></span>
                            </button>
                            <?php if ($user['id'] == $video['uploader_id']): ?>
                                <button onclick="deleteVideo(<?= $videoId ?>)" class="bg-red-900/50 hover:bg-red-900 text-red-500 hover:text-white px-4 py-2 rounded-full transition flex items-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    <span class="hidden sm:inline">Delete</span>
                                </button>
                                <a href="edit_video.php?id=<?= $videoId ?>" class="bg-gray-700 hover:bg-gray-600 text-white px-4 py-2 rounded-full transition flex items-center space-x-2">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    <span class="hidden sm:inline">Edit</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mt-4 bg-gray-900 p-4 rounded-lg">
                        <p class="text-gray-300"><?= nl2br(htmlspecialchars($video['description'] ?? 'No description.')) ?></p>
                    </div>
                </div>

                <!-- Comments -->
                <div class="mt-8">
                    <h3 class="text-xl font-bold mb-4"><?= count($comments) ?> Comments</h3>
                    
                    <!-- Add Comment -->
                    <form id="commentForm" class="flex gap-4 mb-6">
                        <div class="w-10 h-10 rounded-full bg-red-600 flex-shrink-0 flex items-center justify-center text-sm font-bold">
                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                        </div>
                        <div class="flex-1">
                            <input type="text" name="content" placeholder="Add a comment..." class="w-full bg-transparent border-b border-gray-700 focus:border-white outline-none py-2 text-white" required>
                            <div class="flex justify-end mt-2">
                                <button type="submit" class="bg-gray-800 px-4 py-2 rounded-full text-sm font-bold hover:bg-gray-700 transition">Comment</button>
                            </div>
                        </div>
                    </form>

                    <!-- Comment List -->
                    <div class="space-y-6">
                        <?php foreach ($comments as $comment): ?>
                            <div class="flex gap-4">
                                <div class="w-10 h-10 rounded-full bg-gray-700 flex-shrink-0 flex items-center justify-center text-sm font-bold">
                                    <?= strtoupper(substr($comment['username'], 0, 1)) ?>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm"><?= htmlspecialchars($comment['username']) ?></span>
                                        <span class="text-xs text-gray-500"><?= date('M d', strtotime($comment['created_at'])) ?></span>
                                    </div>
                                    <p class="text-sm mt-1 text-gray-300"><?= htmlspecialchars($comment['content']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Sidebar (Related) -->
            <div class="w-full lg:w-80 space-y-4">
                <h3 class="font-bold text-lg">Related Videos</h3>
                <?php foreach ($related as $rv): ?>
                    <a href="watch.php?id=<?= $rv['id'] ?>" class="flex gap-2 group cursor-pointer">
                        <div class="w-40 aspect-video relative rounded overflow-hidden flex-shrink-0">
                            <img src="<?= htmlspecialchars($rv['thumbnail']) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-sm truncate group-hover:text-red-500 transition"><?= htmlspecialchars($rv['title']) ?></h4>
                            <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($rv['category']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <script>
        // Like Functionality
        const likeBtn = document.getElementById('likeBtn');
        const likeCount = document.getElementById('likeCount');
        const likeIcon = likeBtn.querySelector('svg');

        likeBtn.addEventListener('click', async () => {
            const res = await fetch('api.php?action=like', {
                method: 'POST',
                body: JSON.stringify({ video_id: <?= $videoId ?> })
            });
            const data = await res.json();
            
            likeCount.textContent = data.count;
            if (data.liked) {
                likeBtn.classList.remove('bg-gray-800', 'hover:bg-gray-700', 'text-white');
                likeBtn.classList.add('bg-white', 'text-black');
                likeIcon.setAttribute('fill', 'currentColor');
            } else {
                likeBtn.classList.add('bg-gray-800', 'hover:bg-gray-700', 'text-white');
                likeBtn.classList.remove('bg-white', 'text-black');
                likeIcon.setAttribute('fill', 'none');
            }
        });

        // Comment Functionality
        document.getElementById('commentForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const input = e.target.querySelector('input[name="content"]');
            const content = input.value;
            
            const res = await fetch('api.php?action=comment', {
                method: 'POST',
                body: JSON.stringify({ video_id: <?= $videoId ?>, content: content })
            });
            
            if (res.ok) {
                location.reload(); // Simple reload to show new comment
            }
        });

        // Delete Functionality
        async function deleteVideo(id) {
            if (!confirm('Are you sure you want to delete this video? This cannot be undone.')) return;
            
            try {
                const res = await fetch('delete_video.php', {
                    method: 'POST',
                    body: JSON.stringify({ id: id })
                });
                
                if (res.ok) {
                    alert('Video deleted successfully.');
                    window.location.href = 'index.php';
                } else {
                    const data = await res.json();
                    alert('Error: ' + (data.error || 'Failed to delete video.'));
                }
            } catch (err) {
                alert('Error deleting video.');
            }
        }

        // Progress Tracking (Reuse logic)
        const player = document.getElementById('player');
        setInterval(() => {
            if (!player.paused) {
                fetch('progress.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    keepalive: true,
                    body: JSON.stringify({
                        video_id: <?= $videoId ?>,
                        current_time: player.currentTime,
                        duration: player.duration
                    })
                });
            }
        }, 10000); // Update every 10 seconds
    </script>
</body>
</html>
