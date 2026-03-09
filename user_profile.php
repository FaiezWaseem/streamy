<?php
// user_profile.php
require_once 'auth.php';
requireLogin();

$currentUserId = $_SESSION['user_id'];
$username = $_GET['user'] ?? '';

if (empty($username)) {
    header('Location: index.php');
    exit;
}

// Fetch User
$stmt = $db->prepare("SELECT id, username, avatar, created_at FROM users WHERE username = ?");
$stmt->execute([$username]);
$profileUser = $stmt->fetch();

if (!$profileUser) {
    die("User not found.");
}

// Fetch Public Videos
$stmt = $db->prepare("SELECT * FROM videos WHERE uploader_id = ? AND visibility = 'public' ORDER BY created_at DESC");
$stmt->execute([$profileUser['id']]);
$videos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($profileUser['username']) ?> - Streamy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background-color: #141414; color: #fff; }</style>
</head>
<body class="font-sans antialiased flex bg-black text-white">

    <?php include 'sidebar.php'; ?>

    <main class="md:ml-64 flex-1 p-4 md:p-10 min-h-screen">
        <div class="flex items-center space-x-4 mb-8">
            <button onclick="toggleSidebar()" class="md:hidden text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <h1 class="text-3xl font-bold"><?= htmlspecialchars($profileUser['username']) ?>'s Channel</h1>
        </div>

        <div class="bg-gray-900 p-8 rounded-lg mb-8 flex items-center space-x-6">
            <div class="w-24 h-24 rounded-full bg-red-600 flex items-center justify-center text-4xl font-bold">
                <?= strtoupper(substr($profileUser['username'], 0, 1)) ?>
            </div>
            <div>
                <h2 class="text-2xl font-bold"><?= htmlspecialchars($profileUser['username']) ?></h2>
                <p class="text-gray-400">Joined <?= date('F Y', strtotime($profileUser['created_at'])) ?></p>
                <p class="text-gray-400 mt-1"><?= count($videos) ?> Public Videos</p>
            </div>
        </div>
        
        <h3 class="text-xl font-bold mb-6">Videos</h3>

        <?php if (empty($videos)): ?>
            <div class="text-gray-500">No public videos yet.</div>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                <?php foreach ($videos as $video): ?>
                    <a href="watch.php?id=<?= $video['id'] ?>" class="block group relative overflow-hidden rounded-lg bg-gray-900 transition hover:scale-105 hover:z-10">
                        <div class="aspect-video relative">
                            <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['title']) ?>" class="w-full h-full object-cover">
                            <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition flex items-center justify-center">
                                <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                            </div>
                        </div>
                        <div class="p-3">
                            <h3 class="font-bold text-white truncate"><?= htmlspecialchars($video['title']) ?></h3>
                            <p class="text-xs text-gray-400 mt-1"><?= htmlspecialchars($video['category']) ?></p>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</body>
</html>
