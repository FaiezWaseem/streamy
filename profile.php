<?php
require_once 'auth.php';
requireLogin();

$user = getCurrentUser($db);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['change_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        
        if (password_verify($current_password, $user['password'])) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user['id']]);
            $success = 'Password updated successfully.';
        } else {
            $error = 'Incorrect current password.';
        }
    }
}

// Fetch User's Videos
$stmt = $db->prepare("SELECT * FROM videos WHERE uploader_id = ? ORDER BY created_at DESC");
$stmt->execute([$user['id']]);
$myVideos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Streamy</title>
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
            <h1 class="text-3xl font-bold">Profile</h1>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-12">
            <div class="bg-gray-900 p-6 rounded-lg">
                <h2 class="text-xl font-bold mb-4">Account Information</h2>
                <div class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-400">Username</label>
                        <div class="text-lg"><?= htmlspecialchars($user['username']) ?></div>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400">Email</label>
                        <div class="text-lg"><?= htmlspecialchars($user['email']) ?></div>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400">Joined</label>
                        <div class="text-lg"><?= date('F j, Y', strtotime($user['created_at'])) ?></div>
                    </div>
                    <div>
                        <a href="user_profile.php?user=<?= urlencode($user['username']) ?>" class="text-red-500 hover:text-red-400 text-sm font-bold">View Public Channel</a>
                    </div>
                </div>
            </div>

            <div class="bg-gray-900 p-6 rounded-lg">
                <h2 class="text-xl font-bold mb-4">Change Password</h2>
                <?php if ($error): ?>
                    <div class="p-3 mb-4 text-sm text-red-500 bg-red-900/20 rounded"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="p-3 mb-4 text-sm text-green-500 bg-green-900/20 rounded"><?= htmlspecialchars($success) ?></div>
                <?php endif; ?>
                <form method="post" class="space-y-4">
                    <input type="hidden" name="change_password" value="1">
                    <div>
                        <label class="block text-sm text-gray-400">Current Password</label>
                        <input type="password" name="current_password" class="w-full p-3 mt-1 bg-gray-800 border border-gray-700 rounded focus:outline-none focus:ring-2 focus:ring-red-600" required>
                    </div>
                    <div>
                        <label class="block text-sm text-gray-400">New Password</label>
                        <input type="password" name="new_password" class="w-full p-3 mt-1 bg-gray-800 border border-gray-700 rounded focus:outline-none focus:ring-2 focus:ring-red-600" required>
                    </div>
                    <button type="submit" class="px-6 py-2 font-bold text-white bg-red-600 rounded hover:bg-red-700 transition">Update Password</button>
                </form>
            </div>
        </div>

        <!-- My Videos Section -->
        <div>
            <h2 class="text-2xl font-bold mb-6">My Videos</h2>
            <?php if (empty($myVideos)): ?>
                <div class="text-gray-500">You haven't uploaded any videos yet.</div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php foreach ($myVideos as $video): ?>
                        <div class="bg-gray-900 rounded-lg overflow-hidden group">
                            <a href="watch.php?id=<?= $video['id'] ?>" class="block aspect-video relative">
                                <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="<?= htmlspecialchars($video['title']) ?>" class="w-full h-full object-cover">
                                <?php if ($video['visibility'] === 'private'): ?>
                                    <div class="absolute top-2 right-2 bg-black/80 text-white text-xs px-2 py-1 rounded flex items-center">
                                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        Private
                                    </div>
                                <?php endif; ?>
                            </a>
                            <div class="p-4">
                                <h3 class="font-bold truncate mb-1"><?= htmlspecialchars($video['title']) ?></h3>
                                <p class="text-xs text-gray-400 mb-4"><?= date('M d, Y', strtotime($video['created_at'])) ?> • <?= $video['views'] ?> views</p>
                                <div class="flex space-x-2">
                                    <a href="edit_video.php?id=<?= $video['id'] ?>" class="flex-1 bg-gray-800 hover:bg-gray-700 text-white text-sm font-bold py-2 rounded text-center transition">Edit</a>
                                    <button onclick="deleteVideo(<?= $video['id'] ?>)" class="bg-red-900/30 hover:bg-red-900/50 text-red-500 hover:text-red-400 p-2 rounded transition">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        async function deleteVideo(id) {
            if (!confirm('Are you sure you want to delete this video? This cannot be undone.')) return;
            
            try {
                const res = await fetch('delete_video.php', {
                    method: 'POST',
                    body: JSON.stringify({ id: id })
                });
                
                if (res.ok) {
                    location.reload();
                } else {
                    const data = await res.json();
                    alert('Error: ' + (data.error || 'Failed to delete video.'));
                }
            } catch (err) {
                alert('Error deleting video.');
            }
        }
    </script>
</body>
</html>
