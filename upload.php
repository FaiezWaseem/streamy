<?php
// upload.php
require_once 'auth.php';
requireLogin();

$user = getCurrentUser($db);
$error = '';
$success = '';

// Fetch existing categories for the dropdown
$stmt = $db->query("SELECT DISTINCT category FROM videos ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if we have a file upload (chunked logic handles the file transfer separately)
    // Here we expect metadata and potentially a temp path or a direct file upload (fallback)
    
    $videoFile = $_FILES['video'] ?? null;
    $tempVideoPath = $_POST['video_temp_path'] ?? ''; // From chunked uploader
    
    // Validate that we have a video source
    if (empty($tempVideoPath) && (!isset($videoFile) || $videoFile['error'] !== UPLOAD_ERR_OK)) {
        $error = "Please select a video file.";
    } else {
        $thumbFile = $_FILES['thumbnail'] ?? null;
        
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category_select']);
        if ($category === 'new') {
            $category = trim($_POST['new_category']);
        }
        
        $visibility = $_POST['visibility'] ?? 'public';
        if (!in_array($visibility, ['public', 'private'])) {
            $visibility = 'public';
        }
        
        // Sanitize category
        $category = preg_replace('/[^a-zA-Z0-9 _-]/', '', $category);
        if (empty($category)) $category = 'Uncategorized';
        
        $targetDir = __DIR__ . '/videos/' . $category;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $filename = '';
        $sourcePath = '';
        
        if (!empty($tempVideoPath)) {
            // Using chunked upload result
            // $tempVideoPath is something like "uniqid_filename.mp4" inside temp_uploads
            // We need to validate it strictly to prevent LFI/Path traversal
            $tempName = basename($tempVideoPath);
            $sourcePath = __DIR__ . '/temp_uploads/' . $tempName;
            
            // Extract original filename from temp name (format: id_filename)
            $parts = explode('_', $tempName, 2);
            $originalName = isset($parts[1]) ? $parts[1] : $tempName;
            
            if (!file_exists($sourcePath)) {
                $error = "Uploaded file not found. Please try again.";
            } else {
                $filename = $originalName;
            }
        } else {
            // Standard upload
            $filename = basename($videoFile['name']);
            $sourcePath = $videoFile['tmp_name'];
        }

        if (empty($error)) {
            // Default title
            if (empty($title)) {
                $title = pathinfo($filename, PATHINFO_FILENAME);
            }

            $targetFilePath = $targetDir . '/' . $filename;

            // Check if file already exists
            if (file_exists($targetFilePath)) {
                $filename = time() . '_' . $filename;
                $targetFilePath = $targetDir . '/' . $filename;
            }

            $moveSuccess = false;
            if (!empty($tempVideoPath)) {
                $moveSuccess = rename($sourcePath, $targetFilePath);
            } else {
                $moveSuccess = move_uploaded_file($sourcePath, $targetFilePath);
            }

            if ($moveSuccess) {
                $webThumbPath = '';

                // Handle Custom Thumbnail
                if ($thumbFile && $thumbFile['error'] === UPLOAD_ERR_OK) {
                    $thumbName = md5($targetFilePath) . '_' . time() . '.' . pathinfo($thumbFile['name'], PATHINFO_EXTENSION);
                    $thumbDir = __DIR__ . '/thumbnails';
                    if (!is_dir($thumbDir)) mkdir($thumbDir, 0755, true);
                    
                    $thumbPath = $thumbDir . '/' . $thumbName;
                    if (move_uploaded_file($thumbFile['tmp_name'], $thumbPath)) {
                        $webThumbPath = 'thumbnails/' . $thumbName;
                    }
                }

                // Generate Thumbnail if no custom one provided
                if (empty($webThumbPath)) {
                    $thumbName = md5($targetFilePath) . '.jpg';
                    $thumbPath = __DIR__ . '/thumbnails/' . $thumbName;
                    if (!is_dir(dirname($thumbPath))) mkdir(dirname($thumbPath), 0755, true);

                    $cmd = "ffmpeg -i " . escapeshellarg($targetFilePath) . " -ss 00:00:10 -vframes 1 -q:v 2 " . escapeshellarg($thumbPath) . " 2>&1";
                    exec($cmd);
                    $webThumbPath = 'thumbnails/' . $thumbName;
                }

                $stmt = $db->prepare("INSERT INTO videos (title, description, filename, filepath, category, thumbnail, visibility, uploader_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                if ($stmt->execute([$title, $description, $filename, $targetFilePath, $category, $webThumbPath, $visibility, $user['id']])) {
                    $success = "Video uploaded successfully!";
                } else {
                    $error = "Database error.";
                }
            } else {
                $error = "Error moving uploaded file.";
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
    <title>Upload - Streamy</title>
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
            <div class="flex items-center space-x-4 mb-8">
                <button onclick="toggleSidebar()" class="md:hidden text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <h1 class="text-3xl font-bold">Upload Video</h1>
            </div>

            <?php if ($error): ?>
                <div class="p-4 mb-6 bg-red-900/30 border border-red-800 text-red-400 rounded"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="p-4 mb-6 bg-green-900/30 border border-green-800 text-green-400 rounded">
                    <?= htmlspecialchars($success) ?>
                    <a href="index.php" class="underline ml-2">Go to Home</a>
                </div>
            <?php endif; ?>

            <form id="uploadForm" method="post" enctype="multipart/form-data" class="space-y-6 bg-gray-900 p-8 rounded-lg border border-gray-800">
                <input type="hidden" name="video_temp_path" id="video_temp_path">
                
                <!-- Video File -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Video File (Required)</label>
                    <div class="flex items-center justify-center w-full">
                        <label for="video-upload" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-700 border-dashed rounded-lg cursor-pointer hover:bg-gray-800 transition">
                            <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                <svg class="w-8 h-8 mb-4 text-gray-500" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 20 16">
                                    <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 13h3a3 3 0 0 0 0-6h-.025A5.56 5.56 0 0 0 16 6.5 5.5 5.5 0 0 0 5.207 5.021C5.137 5.017 5.071 5 5 5a4 4 0 0 0 0 8h2.167M10 15V6m0 0L8 8m2-2 2 2"/>
                                </svg>
                                <p class="text-sm text-gray-500" id="file-label"><span class="font-semibold">Click to upload video</span></p>
                                <p class="text-xs text-gray-500">MP4, MKV, AVI, MOV (Chunked Upload Enabled)</p>
                            </div>
                            <input id="video-upload" name="video" type="file" class="hidden" accept="video/*" required />
                        </label>
                    </div> 
                    <!-- Progress Bar -->
                    <div id="progress-container" class="mt-4 hidden">
                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                            <span id="progress-text">Uploading... 0%</span>
                        </div>
                        <div class="w-full bg-gray-700 rounded-full h-2.5">
                            <div id="progress-bar" class="bg-red-600 h-2.5 rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                </div>

                <!-- Title -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Title (Optional)</label>
                    <input type="text" name="title" placeholder="Video Title (defaults to filename)" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Description (Optional)</label>
                    <textarea name="description" rows="4" placeholder="Tell viewers about your video..." class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600"></textarea>
                </div>

                <!-- Visibility -->
                <div>
                    <label class="block text-sm font-medium text-gray-400 mb-2">Visibility</label>
                    <select name="visibility" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
                        <option value="public">Public (Everyone can see)</option>
                        <option value="private">Private (Only you can see)</option>
                    </select>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Category</label>
                        <select name="category_select" onchange="toggleCategoryInput(this.value)" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
                            <option value="Uncategorized">Uncategorized</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                            <option value="new">+ Create New Category</option>
                        </select>
                        <input type="text" id="new_category_input" name="new_category" placeholder="Enter new category name" class="mt-2 hidden w-full bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
                    </div>

                    <!-- Thumbnail -->
                    <div>
                        <label class="block text-sm font-medium text-gray-400 mb-2">Custom Thumbnail (Optional)</label>
                        <input type="file" name="thumbnail" accept="image/*" class="w-full text-sm text-gray-400
                            file:mr-4 file:py-2 file:px-4
                            file:rounded-full file:border-0
                            file:text-sm file:font-semibold
                            file:bg-gray-800 file:text-white
                            hover:file:bg-gray-700
                        "/>
                        <p class="text-xs text-gray-500 mt-1">If empty, one will be generated automatically.</p>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4">
                    <button type="submit" id="submit-btn" class="w-full py-3 bg-red-600 hover:bg-red-700 text-white font-bold rounded transition text-lg">
                        Upload Video
                    </button>
                </div>

            </form>
        </div>
    </main>

    <script>
        const fileInput = document.getElementById('video-upload');
        const form = document.getElementById('uploadForm');
        const submitBtn = document.getElementById('submit-btn');
        const progressContainer = document.getElementById('progress-container');
        const progressBar = document.getElementById('progress-bar');
        const progressText = document.getElementById('progress-text');
        const tempPathInput = document.getElementById('video_temp_path');

        // Show filename when selected
        fileInput.addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            if (fileName) {
                document.getElementById('file-label').innerHTML = `<span class="font-semibold text-green-500">Selected: ${fileName}</span>`;
            }
        });

        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const file = fileInput.files[0];
            if (!file) {
                alert("Please select a video file.");
                return;
            }

            // If already uploaded (temp path set), just submit the form normally
            if (tempPathInput.value) {
                form.submit();
                return;
            }

            // Start Chunked Upload
            submitBtn.disabled = true;
            submitBtn.textContent = 'Uploading... Please wait.';
            progressContainer.classList.remove('hidden');
            
            const chunkSize = 2 * 1024 * 1024; // 2MB chunks
            const totalChunks = Math.ceil(file.size / chunkSize);
            const uploadId = Date.now().toString(36) + Math.random().toString(36).substr(2);
            
            let chunkIndex = 0;
            let start = 0;

            async function uploadNextChunk() {
                if (chunkIndex >= totalChunks) {
                    // All chunks uploaded
                    progressText.textContent = 'Processing...';
                    progressBar.style.width = '100%';
                    
                    // The last response contained the temp path
                    // But we can construct it or just rely on the last response
                    // Actually, let's rely on the last successful chunk response logic or just set it manually if we trust the naming convention
                    // Better: modify the loop to capture the last response
                    return;
                }

                const end = Math.min(start + chunkSize, file.size);
                const chunk = file.slice(start, end);
                
                const formData = new FormData();
                formData.append('file', chunk);
                formData.append('upload_id', uploadId);
                formData.append('chunk_index', chunkIndex);
                formData.append('total_chunks', totalChunks);
                formData.append('file_name', file.name);

                try {
                    const response = await fetch('upload_chunk.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        const err = await response.json();
                        throw new Error(err.error || 'Upload failed');
                    }

                    const data = await response.json();
                    
                    // Update Progress
                    chunkIndex++;
                    start = end;
                    const percent = Math.round((chunkIndex / totalChunks) * 100);
                    progressBar.style.width = percent + '%';
                    progressText.textContent = `Uploading... ${percent}%`;

                    if (chunkIndex < totalChunks) {
                        uploadNextChunk();
                    } else {
                        // Finished
                        tempPathInput.value = data.temp_path;
                        // Remove the file input so it's not sent again
                        fileInput.removeAttribute('name');
                        // Now submit the main form
                        submitBtn.textContent = 'Finalizing...';
                        form.submit();
                    }

                } catch (error) {
                    console.error(error);
                    alert('Upload failed: ' + error.message);
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Upload Video';
                    progressContainer.classList.add('hidden');
                }
            }

            uploadNextChunk();
        });
    </script>
</body>
</html>
