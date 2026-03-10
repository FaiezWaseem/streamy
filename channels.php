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
<body class="font-sans antialiased flex bg-black text-white">
    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

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
