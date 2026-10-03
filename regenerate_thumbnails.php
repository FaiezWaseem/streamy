<?php
// regenerate_thumbnails.php
require_once 'auth.php';
require_once __DIR__ . '/mobile_thumbnail.php';
requireLogin();

$user = getCurrentUser($db);

// Only admin can regenerate? For now, allow logged in users for their own videos or global admin?
// Let's assume this is an admin tool or utility for now.

$message = '';
$progress = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Increase time limit for processing
    set_time_limit(0);
    ini_set('memory_limit', '512M');

    $targetCategory = $_POST['category'] ?? 'all';
    $generateGifs = isset($_POST['generate_gifs']);
    
    $sql = "SELECT * FROM videos";
    $params = [];
    
    if ($targetCategory !== 'all') {
        $sql .= " WHERE category = ?";
        $params[] = $targetCategory;
    }
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $videos = $stmt->fetchAll();
    
    $count = 0;
    $errors = 0;
    
    foreach ($videos as $video) {
        $filePath = $video['filepath'];
        
        if (!file_exists($filePath)) {
            $errors++;
            continue;
        }
        
        $thumbnail = mobileVideoThumbnail($filePath);
        if ($thumbnail !== 'assets/video-placeholder.svg') {
            $db->prepare('UPDATE videos SET thumbnail = ? WHERE id = ?')->execute([$thumbnail, $video['id']]);
        } else {
            $errors++;
            continue;
        }

        // 2. Generate Preview GIF (if requested)
        if ($generateGifs) {
            $preview = mobileVideoPreview($filePath, (int)$video['duration']);
            if ($preview !== '') {
                $db->prepare('UPDATE videos SET preview_gif = ? WHERE id = ?')->execute([$preview, $video['id']]);
            }
        }
        
        $count++;
    }
    
    $message = "Processed $count videos. Errors: $errors.";
}

// Fetch categories for dropdown
$stmt = $db->query("SELECT DISTINCT category FROM videos ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Regenerate Thumbnails - Streamy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background-color: #141414; color: #fff; }</style>
</head>
<body class="font-sans antialiased flex bg-black text-white">

    <?php include 'sidebar.php'; ?>

    <main class="md:ml-64 flex-1 p-4 md:p-10 min-h-screen">
        <h1 class="text-3xl font-bold mb-8">Regenerate Media Assets</h1>
        
        <?php if ($message): ?>
            <div class="bg-green-900/50 text-green-200 p-4 rounded mb-6 border border-green-800">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="bg-gray-900 p-8 rounded-lg max-w-2xl border border-gray-800">
            <p class="mb-6 text-gray-400">
                Use this tool to bulk regenerate thumbnails and create preview GIFs for your videos. 
                This process may take a while depending on the number of videos.
            </p>
            
            <form method="post" class="space-y-6">
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Target Channel / Category</label>
                    <select name="category" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
                        <option value="all">All Channels</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="flex items-center space-x-3">
                    <input type="checkbox" name="generate_gifs" id="generate_gifs" class="w-5 h-5 text-red-600 bg-gray-800 border-gray-700 rounded focus:ring-red-600" checked>
                    <label for="generate_gifs" class="text-white">Generate Preview GIFs (Animated)</label>
                </div>

                <div class="pt-4">
                    <button type="submit" onclick="this.textContent='Processing...'; this.disabled=true; this.form.submit();" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded transition">
                        Start Regeneration
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
