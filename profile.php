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
    <style>
        body { background-color: #0f0f0f; color: #fff; }
        .glass-panel { background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); }
        .custom-input { background: #1a1a1a; border: 1px solid #333; transition: border 0.2s; }
        .custom-input:focus { border-color: #ef4444; outline: none; }
        .hidden { display: none; }
    </style>
</head>
<body class="font-sans antialiased flex">
    
    <?php include 'sidebar.php'; ?>

    <main class="md:ml-64 flex-1 p-4 md:p-8 min-h-screen">
        <div class="flex items-center justify-between mb-8">
            <h1 class="text-2xl font-bold tracking-tight">Channel Dashboard</h1>
            <a href="upload.php" class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-full text-sm font-bold transition flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                Create
            </a>
        </div>
        
        <div class="glass-panel rounded-xl overflow-hidden mb-10">
            <div class="flex flex-col lg:flex-row">
                
                <div class="flex-1 p-6 lg:border-r border-white/10">
                    <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-500 mb-6">Account Details</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <span class="block text-[10px] uppercase text-gray-500 mb-1">Username</span>
                            <span class="text-sm font-bold text-white"><?= htmlspecialchars($user['username']) ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] uppercase text-gray-500 mb-1">Email</span>
                            <span class="text-sm font-bold text-white truncate block"><?= htmlspecialchars($user['email']) ?></span>
                        </div>
                        <div>
                            <span class="block text-[10px] uppercase text-gray-500 mb-1">Joined</span>
                            <span class="text-sm font-bold text-white"><?= date('M Y', strtotime($user['created_at'])) ?></span>
                        </div>
                    </div>
                    <div class="mt-6 pt-5 border-t border-white/5">
                        <a href="saved.php" class="text-[11px] font-bold text-red-500 hover:text-red-400 flex items-center gap-2 uppercase tracking-tight">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                            Your Library
                        </a>
                    </div>
                </div>

                <div class="w-full lg:w-[380px] p-6 bg-white/[0.01]" id="security-box">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-[10px] font-black uppercase tracking-widest text-gray-500">Security</h2>
                        <button onclick="toggleSecurity()" id="edit-pwd-btn" class="text-[10px] font-bold uppercase text-red-500 hover:underline">
                            Change Password
                        </button>
                    </div>

                    <?php if ($error || $success): ?>
                        <div class="mb-4 text-[11px] font-bold <?= $error ? 'text-red-500' : 'text-green-500' ?> bg-black/40 p-2 rounded border border-white/5">
                            <?= htmlspecialchars($error ?: $success) ?>
                        </div>
                    <?php endif; ?>

                    <form method="post" id="pwd-form" class="space-y-3 <?= ($error || $success) ? '' : 'hidden' ?>">
                        <input type="hidden" name="change_password" value="1">
                        <input type="password" name="current_password" placeholder="Current Password" class="custom-input w-full p-2.5 text-xs rounded-md" required>
                        <div class="flex gap-2">
                            <input type="password" name="new_password" placeholder="New Password" class="custom-input w-full p-2.5 text-xs rounded-md" required>
                            <button type="submit" class="bg-white text-black text-[10px] font-black px-4 rounded-md hover:bg-gray-200 uppercase">Save</button>
                        </div>
                        <button type="button" onclick="toggleSecurity()" class="text-[10px] text-gray-500 uppercase hover:text-white transition">Cancel</button>
                    </form>

                    <div id="pwd-status" class="<?= ($error || $success) ? 'hidden' : '' ?> text-[11px] text-gray-500 italic">
                        Account protected by standard encryption.
                    </div>
                </div>
            </div>
        </div>

        <div>
            <h2 class="text-xl font-bold mb-6">Your Uploads <span class="text-gray-600 text-sm font-normal">(<?= count($myVideos) ?>)</span></h2>

            <?php if (empty($myVideos)): ?>
                <div class="glass-panel rounded-xl p-12 text-center border-dashed border-2 border-white/5">
                    <p class="text-gray-500 text-sm">No videos found. Start sharing your content!</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
                    <?php foreach ($myVideos as $video): ?>
                        <div class="glass-panel rounded-xl overflow-hidden group hover:border-white/20 transition duration-300">
                            <a href="watch.php?id=<?= $video['id'] ?>" class="block aspect-video relative">
                                <img src="<?= htmlspecialchars($video['thumbnail']) ?>" alt="Thumbnail" class="w-full h-full object-cover">
                                <?php if ($video['visibility'] === 'private'): ?>
                                    <div class="absolute top-2 right-2 bg-black/80 text-[9px] font-black px-2 py-1 rounded-sm uppercase tracking-tighter">Private</div>
                                <?php endif; ?>
                            </a>
                            <div class="p-4">
                                <h3 class="font-bold text-sm truncate mb-1"><?= htmlspecialchars($video['title']) ?></h3>
                                <p class="text-[11px] text-gray-500 mb-4"><?= number_format($video['views']) ?> views • <?= date('M d', strtotime($video['created_at'])) ?></p>
                                <div class="flex gap-2">
                                    <a href="edit_video.php?id=<?= $video['id'] ?>" class="flex-1 bg-white/5 hover:bg-white/10 text-white text-[10px] font-bold py-2 rounded text-center uppercase">Edit</a>
                                    <button onclick="deleteVideo(<?= $video['id'] ?>)" class="bg-red-500/10 hover:bg-red-500/20 text-red-500 px-3 rounded transition">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
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
        function toggleSecurity() {
            const form = document.getElementById('pwd-form');
            const status = document.getElementById('pwd-status');
            const btn = document.getElementById('edit-pwd-btn');
            
            form.classList.toggle('hidden');
            status.classList.toggle('hidden');
            btn.classList.toggle('hidden');
        }

        async function deleteVideo(id) {
            if (!confirm('Permanently delete this video?')) return;
            
            try {
                const res = await fetch('delete_video.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ id: id })
                });
                
                if (res.ok) {
                    location.reload();
                } else {
                    alert('Deletion failed.');
                }
            } catch (err) {
                alert('Connection error.');
            }
        }
    </script>
</body>
</html>