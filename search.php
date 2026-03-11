<?php
// search.php
require_once 'auth.php';
requireLogin();

$user = getCurrentUser($db);
$query = $_GET['q'] ?? '';
$category = $_GET['category'] ?? 'All';

// Fetch Categories for filter
$stmt = $db->query("SELECT DISTINCT category FROM videos ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Search Logic
$sql = "SELECT * FROM videos WHERE 1=1";
$params = [];

if (!empty($query)) {
    $sql .= " AND (title LIKE ? OR description LIKE ?)";
    $params[] = "%$query%";
    $params[] = "%$query%";
}

if ($category !== 'All') {
    $sql .= " AND category = ?";
    $params[] = $category;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Search - Streamy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background-color: #141414; color: #fff; }</style>
</head>
<body class="font-sans antialiased flex bg-black text-white">

    <?php include 'sidebar.php'; ?>

    <main class="md:ml-64 flex-1 p-4 md:p-10 min-h-screen">
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center space-x-4">
                <button onclick="toggleSidebar()" class="md:hidden text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 class="text-3xl font-bold">Search Videos</h1>
            </div>

            <!-- Layout Toggle -->
            <div class="flex bg-gray-800 rounded-lg p-1">
                <button onclick="setLayout('grid')" id="gridBtn" class="p-2 rounded hover:bg-gray-700 transition text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
                </button>
                <button onclick="setLayout('list')" id="listBtn" class="p-2 rounded hover:bg-gray-700 transition text-gray-400">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
            </div>
        </div>
        
        <form action="search.php" method="get" class="mb-10 bg-gray-900 p-6 rounded-lg flex flex-col md:flex-row gap-4 items-end">
            <div class="flex-1 w-full">
                <label class="block text-sm text-gray-400 mb-2">Keywords</label>
                <input type="text" name="q" value="<?= htmlspecialchars($query) ?>" placeholder="Search titles, descriptions..." class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
            </div>
            <div class="w-full md:w-64">
                <label class="block text-sm text-gray-400 mb-2">Category</label>
                <select name="category" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
                    <option value="All">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="px-6 py-2 bg-red-600 hover:bg-red-700 rounded text-white font-bold transition">Search</button>
        </form>

        <h2 class="text-xl font-bold mb-6">Results (<?= count($results) ?>)</h2>

        <?php if (empty($results)): ?>
            <div class="text-gray-500 text-center py-10">No videos found matching your criteria.</div>
        <?php else: ?>
            <div id="contentGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($results as $video): ?>
                    <a href="watch.php?id=<?= $video['id'] ?>" class="video-card block group relative overflow-hidden rounded-lg bg-gray-900 transition hover:scale-105 hover:z-10">
                        <div class="thumbnail-container aspect-video relative">
                            <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['title']) ?>" class="w-full h-full object-cover">
                            <div class="overlay absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                            </div>
                        </div>
                        <div class="list-info p-3">
                            <h3 class="font-bold text-white truncate"><?= htmlspecialchars($video['title']) ?></h3>
                            <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($video['category']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <script>
        const gridBtn = document.getElementById('gridBtn');
        const listBtn = document.getElementById('listBtn');
        const contentGrid = document.getElementById('contentGrid');
        const cards = document.querySelectorAll('.video-card');

        function setLayout(type) {
            localStorage.setItem('streamy_layout_preference', type);
            
            if (type === 'list') {
                // List Mode (Default 4-Column Grid)
                listBtn.classList.remove('text-gray-400');
                listBtn.classList.add('text-white', 'bg-gray-700');
                
                gridBtn.classList.add('text-gray-400');
                gridBtn.classList.remove('text-white', 'bg-gray-700');

                if(contentGrid) {
                    contentGrid.classList.remove('grid-cols-2');
                    contentGrid.classList.add('grid-cols-1', 'sm:grid-cols-2', 'md:grid-cols-3', 'lg:grid-cols-4');
                }
                
                // Ensure cards are vertical (reset any previous changes)
                cards.forEach(card => {
                    card.classList.add('block');
                    card.classList.remove('flex', 'flex-row', 'items-center', 'h-24', 'p-2');
                    
                    const thumb = card.querySelector('.thumbnail-container');
                    thumb.classList.add('aspect-video');
                    thumb.classList.remove('w-40', 'h-full', 'flex-shrink-0', 'mr-4');
                    
                    const info = card.querySelector('.list-info');
                    info.classList.remove('flex-1', 'flex', 'flex-col', 'justify-center');
                    
                    card.classList.add('hover:scale-105', 'hover:z-10');
                });

            } else {
                // Grid Mode (2-Column Grid)
                gridBtn.classList.remove('text-gray-400');
                gridBtn.classList.add('text-white', 'bg-gray-700');
                
                listBtn.classList.add('text-gray-400');
                listBtn.classList.remove('text-white', 'bg-gray-700');

                if(contentGrid) {
                    contentGrid.classList.remove('grid-cols-1', 'sm:grid-cols-2', 'md:grid-cols-3', 'lg:grid-cols-4');
                    contentGrid.classList.add('grid-cols-2');
                }

                // Ensure cards are vertical
                cards.forEach(card => {
                    card.classList.add('block');
                    card.classList.remove('flex', 'flex-row', 'items-center', 'h-24', 'p-2');
                    
                    const thumb = card.querySelector('.thumbnail-container');
                    thumb.classList.add('aspect-video');
                    thumb.classList.remove('w-40', 'h-full', 'flex-shrink-0', 'mr-4');
                    
                    const info = card.querySelector('.list-info');
                    info.classList.remove('flex-1', 'flex', 'flex-col', 'justify-center');
                    
                    card.classList.add('hover:scale-105', 'hover:z-10');
                });
            }
        }

        // Init
        const savedLayout = localStorage.getItem('streamy_layout_preference') || 'grid';
        setLayout(savedLayout);
    </script>
</body>
</html>
