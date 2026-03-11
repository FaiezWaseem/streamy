<?php
// regenerate_thumbnails.php
require_once 'auth.php';
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
        
        // 1. Regenerate Thumbnail
        $thumbName = md5($filePath) . '.jpg';
        $thumbPath = __DIR__ . '/thumbnails/' . $thumbName;
        
        // Force overwrite
        if (file_exists($thumbPath)) unlink($thumbPath);
        
        $cmd = "ffmpeg -i " . escapeshellarg($filePath) . " -ss 00:00:10 -vframes 1 -q:v 2 " . escapeshellarg($thumbPath) . " 2>&1";
        exec($cmd);
        
        // 2. Generate Preview GIF (if requested)
        if ($generateGifs) {
            $gifName = md5($filePath) . '.gif';
            $gifPath = __DIR__ . '/thumbnails/' . $gifName;
            
            if (file_exists($gifPath)) unlink($gifPath);
            
            // Generate GIF: 0-3s, 12-15s, 22-25s (or dynamic based on duration)
            // Complex FFmpeg filter to concat segments
            // Simplified approach: Take 1.5s from start, middle, and end
            
            $duration = $video['duration'] ?: 30; // Default if unknown
            $mid = floor($duration / 2);
            $end = max(0, $duration - 5);
            
            // Create a complex filter to select segments and concat them
            // select='between(t,0,1.5)+between(t,${mid},${mid}+1.5)+between(t,${end},${end}+1.5)'
            // setpts=N/FRAME_RATE/TB
            // scale=320:-1:flags=lanczos,split[s0][s1];[s0]palettegen[p];[s1][p]paletteuse
            
            // Note: Creating high quality GIFs is CPU intensive.
            // Let's do a simpler version: just 3 seconds from 10% mark
            
            $start = floor($duration * 0.1);
            
            $cmdGif = "ffmpeg -y -ss {$start} -t 3 -i " . escapeshellarg($filePath) . " -vf \"fps=10,scale=320:-1:flags=lanczos,split[s0][s1];[s0]palettegen[p];[s1][p]paletteuse\" " . escapeshellarg($gifPath) . " 2>&1";
            exec($cmdGif);
            
            // Update DB
            $stmt = $db->prepare("UPDATE videos SET preview_gif = ? WHERE id = ?");
            $stmt->execute(['thumbnails/' . $gifName, $video['id']]);
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
