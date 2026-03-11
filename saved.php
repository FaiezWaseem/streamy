<?php
require_once 'auth.php';
requireLogin();

$user = getCurrentUser($db);

// Fetch Saved Videos
$stmt = $db->prepare("
    SELECT v.* 
    FROM videos v
    JOIN saved_videos sv ON v.id = sv.video_id
    WHERE sv.user_id = ?
    ORDER BY sv.created_at DESC
");
$stmt->execute([$user['id']]);
$savedVideos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Saved Videos - Streamy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background-color: #141414; color: #fff; }</style>
</head>
<body class="font-sans antialiased flex bg-black text-white">
    
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <main class="md:ml-64 flex-1 p-4 md:p-10 min-h-screen">
        <div class="flex items-center space-x-4 mb-8">
            <button onclick="toggleSidebar()" class="md:hidden text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="text-3xl font-bold flex items-center gap-3">
                <svg class="w-8 h-8 text-red-600" fill="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                Saved Videos
            </h1>
        </div>
        
        <?php if (empty($savedVideos)): ?>
            <div class="text-center py-20 text-gray-500">
                <svg class="w-16 h-16 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                <p class="text-xl">No saved videos yet.</p>
                <p class="text-sm mt-2">Save videos to watch them later!</p>
                <a href="index.php" class="inline-block mt-6 bg-red-600 hover:bg-red-700 text-white font-bold py-2 px-6 rounded-full transition">Browse Videos</a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php foreach ($savedVideos as $video): ?>
                    <div class="bg-gray-900 rounded-lg overflow-hidden group hover:ring-2 hover:ring-red-600 transition">
                        <a href="watch.php?id=<?= $video['id'] ?>" class="block aspect-video relative">
                            <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['title']) ?>" class="w-full h-full object-cover">
                            <?php if ($video['duration']): ?>
                                <span class="absolute bottom-2 right-2 bg-black/80 text-white text-xs px-2 py-1 rounded">
                                    <?= gmdate(($video['duration'] > 3600 ? "H:i:s" : "i:s"), $video['duration']) ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <div class="p-4">
                            <div class="flex justify-between items-start">
                                <h3 class="font-bold truncate mb-1 flex-1"><?= htmlspecialchars($video['title']) ?></h3>
                                <button onclick="toggleSave(<?= $video['id'] ?>, this)" class="text-red-500 hover:text-white ml-2" title="Remove from Saved">
                                    <svg class="w-5 h-5" fill="currentColor" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                                </button>
                            </div>
                            <p class="text-xs text-gray-400"><?= date('M d, Y', strtotime($video['created_at'])) ?> • <?= $video['views'] ?> views</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        async function toggleSave(videoId, btn) {
            // Optimistic UI removal
            const card = btn.closest('.bg-gray-900');
            
            try {
                const res = await fetch('api.php?action=save', {
                    method: 'POST',
                    body: JSON.stringify({ video_id: videoId })
                });
                const data = await res.json();
                
                if (!data.saved) {
                    // Removed successfully
                    card.style.opacity = '0';
                    setTimeout(() => card.remove(), 300);
                } else {
                    // Re-added? (Shouldn't happen in this UI usually)
                }
            } catch (e) {
                console.error('Save toggle failed');
                alert('Failed to update.');
            }
        }
    </script>
</body>
</html>
