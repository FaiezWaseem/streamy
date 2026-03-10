<?php
require_once 'auth.php';
requireLogin();

$user = getCurrentUser($db);

// 1. Fetch Continue Watching
// Optimize: Select only needed columns
$stmt = $db->prepare("
    SELECT v.id, v.title, v.thumbnail, v.category, v.duration, wh.progress, wh.completed 
    FROM watch_history wh 
    JOIN videos v ON wh.video_id = v.id 
    WHERE wh.user_id = ? AND wh.completed = 0 
    ORDER BY wh.last_watched_at DESC 
    LIMIT 10
");
$stmt->execute([$user['id']]);
$continueWatching = $stmt->fetchAll();

// 2. Fetch Videos grouped by Category efficiently
$categoryFilter = $_GET['category'] ?? null;
$videosByCategory = [];

if ($categoryFilter) {
    // Single category: Simple query
    $stmt = $db->prepare("SELECT id, title, thumbnail, category, duration FROM videos WHERE category = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$categoryFilter]);
    $videos = $stmt->fetchAll();
    if (!empty($videos)) {
        $videosByCategory[$categoryFilter] = $videos;
    }
} else {
    // All categories: Use Window Function to fetch top 10 per category in one query
    // SQLite 3.25+ supports window functions
    try {
        $sql = "
            WITH RankedVideos AS (
                SELECT 
                    id, title, thumbnail, category, duration,
                    ROW_NUMBER() OVER (PARTITION BY category ORDER BY created_at DESC) as rn
                FROM videos
            )
            SELECT id, title, thumbnail, category, duration FROM RankedVideos WHERE rn <= 10;
        ";
        $stmt = $db->query($sql);
        $allVideos = $stmt->fetchAll();

        // Group by category in PHP
        foreach ($allVideos as $video) {
            $videosByCategory[$video['category']][] = $video;
        }
        
        // Sort categories alphabetically or by some other logic if needed
        ksort($videosByCategory);

    } catch (PDOException $e) {
        // Fallback for older SQLite versions (though unlikely on modern MAMP)
        // Fetch distinct categories then loop (Original slow method, but optimized columns)
        $stmt = $db->query("SELECT DISTINCT category FROM videos ORDER BY category");
        $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($categories as $cat) {
            $stmt = $db->prepare("SELECT id, title, thumbnail, category, duration FROM videos WHERE category = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$cat]);
            $videos = $stmt->fetchAll();
            if (!empty($videos)) {
                $videosByCategory[$cat] = $videos;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Streamy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #141414; color: #fff; }
        .video-card { transition: transform 0.3s, z-index 0.3s; }
        .video-card:hover { transform: scale(1.05); z-index: 20; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        /* Progress Bar Style */
        .progress-container { background: rgba(255,255,255,0.3); height: 4px; width: 100%; position: absolute; bottom: 0; left: 0; }
        .progress-bar { background: #dc2626; height: 100%; }
    </style>
</head>
<body class="font-sans antialiased flex bg-black text-white overflow-x-hidden">

    <?php include 'sidebar.php'; ?>

    <main class="md:ml-64 flex-1 min-h-screen relative w-full min-w-0">
        <!-- Top Navbar -->
        <nav class="sticky top-0 z-30 bg-gradient-to-b from-black/90 to-transparent px-4 md:px-8 py-4 flex justify-between items-center backdrop-blur-sm">
            <div class="flex items-center space-x-4">
                <button onclick="toggleSidebar()" class="md:hidden text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <div class="text-xl font-bold text-gray-300">
                    <?= $categoryFilter ? 'Channel: ' . htmlspecialchars($categoryFilter) : 'Home' ?>
                </div>
            </div>
            <div class="flex space-x-4 items-center">
                <a href="search.php" class="text-gray-400 hover:text-white transition">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                </a>
                <a href="upload.php" class="bg-gray-800 text-sm px-4 py-2 rounded hover:bg-gray-700 transition inline-block">Upload</a>
                <button onclick="document.getElementById('scanModal').classList.remove('hidden')" class="bg-red-600 text-sm px-4 py-2 rounded hover:bg-red-700 transition font-bold">Scan Directory</button>
            </div>
        </nav>

        <div class="px-4 md:px-8 pb-10 space-y-10 mt-4 overflow-hidden">
            
            <!-- Continue Watching Section -->
            <?php if (!empty($continueWatching)): ?>
                <div>
                    <h2 class="text-xl font-bold mb-4 text-white">Continue Watching</h2>
                    <div class="flex space-x-4 overflow-x-auto hide-scrollbar pb-4">
                        <?php foreach ($continueWatching as $video): ?>
                            <a href="watch.php?id=<?= $video['id'] ?>" class="flex-none w-64 cursor-pointer video-card relative group rounded-md overflow-hidden bg-gray-900">
                                <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['title']) ?>" class="w-full h-36 object-cover opacity-80 group-hover:opacity-100 transition" loading="lazy">
                                <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                    <div class="bg-black/50 rounded-full p-2">
                                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                                    </div>
                                </div>
                                <div class="p-2">
                                    <div class="text-sm font-medium truncate text-gray-200 group-hover:text-white"><?= htmlspecialchars($video['title']) ?></div>
                                    <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($video['category']) ?></div>
                                </div>
                                <!-- Progress Bar -->
                                <?php if ($video['duration'] > 0): ?>
                                    <div class="progress-container">
                                        <div class="progress-bar" style="width: <?= ($video['progress'] / $video['duration']) * 100 ?>%"></div>
                                    </div>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Categories -->
            <?php if (empty($videosByCategory)): ?>
                <div class="text-center text-gray-500 mt-20">
                    <h2 class="text-2xl">No videos found.</h2>
                    <p>Use the "Scan Directory" button to add videos.</p>
                </div>
            <?php endif; ?>

            <?php foreach ($videosByCategory as $category => $videos): ?>
                <?php if (!empty($videos)): ?>
                <div>
                    <h2 class="text-xl font-bold mb-4 text-gray-200 hover:text-red-500 cursor-pointer transition inline-block">
                        <a href="search.php?category=<?= urlencode($category) ?>"><?= htmlspecialchars($category) ?></a>
                    </h2>
                    <div class="flex space-x-4 overflow-x-auto hide-scrollbar pb-4">
                        <?php foreach ($videos as $video): ?>
                            <a href="watch.php?id=<?= $video['id'] ?>" class="flex-none w-64 cursor-pointer video-card relative group rounded-md overflow-hidden bg-gray-900">
                                <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['title']) ?>" class="w-full h-36 object-cover opacity-80 group-hover:opacity-100 transition" loading="lazy">
                                <div class="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                                    <div class="bg-black/50 rounded-full p-2">
                                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                                    </div>
                                </div>
                                <div class="p-2">
                                    <div class="text-sm font-medium truncate text-gray-200 group-hover:text-white"><?= htmlspecialchars($video['title']) ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- Scan Modal -->
    <div id="scanModal" class="fixed inset-0 bg-black/80 z-50 hidden flex items-center justify-center backdrop-blur-sm">
        <div class="bg-gray-900 p-8 rounded-lg w-full max-w-md border border-gray-800 shadow-2xl">
            <h2 class="text-xl font-bold mb-4 text-white">Scan Directory</h2>
            <form id="scanForm" class="space-y-4">
                <div>
                    <label class="block text-sm mb-1 text-gray-400">Directory Path</label>
                    <input type="text" name="directory" placeholder="/Users/me/Movies" class="w-full bg-gray-800 border border-gray-700 rounded px-3 py-2 text-white focus:outline-none focus:ring-2 focus:ring-red-600" required>
                </div>
                <div class="flex items-center">
                    <input type="checkbox" id="move_files" name="move_files" class="w-4 h-4 text-red-600 bg-gray-800 border-gray-700 rounded focus:ring-red-600">
                    <label for="move_files" class="ml-2 text-sm font-medium text-gray-300">Move files (Copy & Delete)</label>
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="document.getElementById('scanModal').classList.add('hidden')" class="px-4 py-2 text-gray-300 hover:text-white transition">Cancel</button>
                    <button type="submit" class="px-4 py-2 bg-red-600 rounded hover:bg-red-700 text-white font-bold transition">Scan</button>
                </div>
            </form>
            <div id="scanResult" class="mt-4 text-sm text-gray-400"></div>
        </div>
    </div>

    <script>
        document.getElementById('scanForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const result = document.getElementById('scanResult');
            const formData = new FormData(e.target);
            
            btn.disabled = true;
            btn.textContent = 'Scanning...';
            result.textContent = 'Scanning directory... this may take a while.';

            try {
                const response = await fetch('scan.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();
                result.textContent = data.message;
                setTimeout(() => location.reload(), 2000);
            } catch (err) {
                result.textContent = 'Error scanning directory.';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Scan';
            }
        });
    </script>
</body>
</html>
