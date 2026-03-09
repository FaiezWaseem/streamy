<?php
// edit_video.php
require_once 'auth.php';
requireLogin();

$user = getCurrentUser($db);
$videoId = $_GET['id'] ?? null;
$error = '';
$success = '';

if (!$videoId) {
    header('Location: index.php');
    exit;
}

// Fetch Video
$stmt = $db->prepare("SELECT * FROM videos WHERE id = ?");
$stmt->execute([$videoId]);
$video = $stmt->fetch();

// Security Check: Only uploader can edit
if (!$video || $video['uploader_id'] != $user['id']) {
    die("Unauthorized access.");
}

// Fetch Categories
$stmt = $db->query("SELECT DISTINCT category FROM videos ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $visibility = $_POST['visibility'];
    
    // Category logic
    $category = trim($_POST['category_select']);
    if ($category === 'new') {
        $category = trim($_POST['new_category']);
    }
    $category = preg_replace('/[^a-zA-Z0-9 _-]/', '', $category);
    if (empty($category)) $category = 'Uncategorized';

    if (!in_array($visibility, ['public', 'private'])) {
        $visibility = 'public';
    }

    if (empty($title)) {
        $error = "Title cannot be empty.";
    } else {
        $stmt = $db->prepare("UPDATE videos SET title = ?, description = ?, category = ?, visibility = ? WHERE id = ?");
        if ($stmt->execute([$title, $description, $category, $visibility, $videoId])) {
            $success = "Video updated successfully.";
            // Refresh video data
            $video['title'] = $title;
            $video['description'] = $description;
            $video['category'] = $category;
            $video['visibility'] = $visibility;
        } else {
            $error = "Update failed.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Video - Streamy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background-color: #141414; color: #fff; }</style>
    <script>
        function toggleCategoryInput(val) {
            const input = document.getElementById('new_category_input');
            if (val === 'new') {
                input.classList.remove('hidden');
                input.required = true;
            } else {
                input.classList.add('hidden');
                input.required = false;
            }
        }
    </script>
</head>
<body class="font-sans antialiased flex bg-black text-white">

    <?php include 'sidebar.php'; ?>

    <main class="md:ml-64 flex-1 p-4 md:p-10 min-h-screen">
        <div class="max-w-3xl mx-auto">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center space-x-4">
                    <button onclick="toggleSidebar()" class="md:hidden text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                    </button>
                    <h1 class="text-3xl font-bold">Edit Video</h1>
                </div>
                <a href="watch.php?id=<?= $videoId ?>" class="text-gray-400 hover:text-white">Back to Video</a>
            </div>

            <?php if ($error): ?>
                <div class="p-4 mb-6 bg-red-900/30 border border-red-800 text-red-400 rounded"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="p-4 mb-6 bg-green-900/30 border border-green-800 text-green-400 rounded"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <form method="post" class="space-y-6 bg-gray-900 p-8 rounded-lg border border-gray-800">
                
                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($video['title']) ?>" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600" required>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Description</label>
                    <textarea name="description" rows="6" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600"><?= htmlspecialchars($video['description']) ?></textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Category</label>
                        <select name="category_select" onchange="toggleCategoryInput(this.value)" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>" <?= $video['category'] === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                            <option value="new">+ Create New Category</option>
                        </select>
                        <input type="text" id="new_category_input" name="new_category" placeholder="Enter new category name" class="mt-2 hidden w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
                    </div>

                    <!-- Visibility -->
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Visibility</label>
                        <select name="visibility" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
                            <option value="public" <?= $video['visibility'] === 'public' ? 'selected' : '' ?>>Public</option>
                            <option value="private" <?= $video['visibility'] === 'private' ? 'selected' : '' ?>>Private</option>
                        </select>
                    </div>
                </div>

                <!-- Buttons -->
                <div class="pt-4 flex justify-between">
                    <button type="button" onclick="deleteVideo(<?= $videoId ?>)" class="text-red-500 hover:text-red-400 font-bold">Delete Video</button>
                    <button type="submit" class="px-8 py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded transition text-lg">
                        Save Changes
                    </button>
                </div>

            </form>
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
                    alert('Video deleted successfully.');
                    window.location.href = 'profile.php';
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
