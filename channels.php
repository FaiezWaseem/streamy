<?php
require_once 'auth.php';
requireLogin();

$user = getCurrentUser($db);

// Get all categories with a thumbnail from the first video
$stmt = $db->query("
    SELECT category, COUNT(*) as count, 
    (SELECT thumbnail FROM videos v2 WHERE v2.category = v1.category LIMIT 1) as thumbnail 
    FROM videos v1 
    GROUP BY category
");
$categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Channels - Streamy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background-color: #141414; color: #fff; }</style>
</head>
<body class="font-sans antialiased flex">
    <!-- Sidebar -->
    <aside class="w-64 bg-black h-screen fixed top-0 left-0 border-r border-gray-800 flex flex-col">
        <div class="p-6 text-3xl font-bold text-red-600 tracking-wider">STREAMY</div>
        <nav class="flex-1 px-4 space-y-2">
            <a href="index.php" class="block px-4 py-3 rounded text-gray-400 hover:text-white hover:bg-gray-800">Home</a>
            <a href="channels.php" class="block px-4 py-3 rounded bg-gray-800 text-white font-medium">Channels</a>
            <a href="profile.php" class="block px-4 py-3 rounded text-gray-400 hover:text-white hover:bg-gray-800">Profile</a>
        </nav>
        <div class="p-4 border-t border-gray-800">
            <div class="flex items-center space-x-3 px-4 py-2">
                <div class="w-8 h-8 rounded-full bg-red-600 flex items-center justify-center text-sm font-bold">
                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                </div>
                <span class="text-sm font-medium truncate"><?= htmlspecialchars($user['username']) ?></span>
            </div>
            <a href="logout.php" class="block mt-2 px-4 py-2 text-sm text-gray-500 hover:text-white">Sign out</a>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="md:ml-64 flex-1 p-4 md:p-10">
        <div class="flex items-center space-x-4 mb-8">
            <button onclick="toggleSidebar()" class="md:hidden text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="text-3xl font-bold">Channels</h1>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
            <?php foreach ($categories as $cat): ?>
                <a href="search.php?category=<?= urlencode($cat['category']) ?>" class="block group relative overflow-hidden rounded-lg aspect-video bg-gray-800">
                    <img src="<?= htmlspecialchars($cat['thumbnail']) ?>" alt="<?= htmlspecialchars($cat['category']) ?>" class="w-full h-full object-cover transition transform group-hover:scale-110">
                    <div class="absolute inset-0 bg-black bg-opacity-40 group-hover:bg-opacity-30 transition flex items-center justify-center">
                        <div class="text-center">
                            <h3 class="text-xl font-bold"><?= htmlspecialchars($cat['category']) ?></h3>
                            <p class="text-sm text-gray-300"><?= $cat['count'] ?> Videos</p>
                        </div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
