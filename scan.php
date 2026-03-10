<?php
// scan.php
require_once 'auth.php';
// Only require login if not CLI
if (php_sapi_name() !== 'cli') {
    requireLogin();
}

class Scanner {
    private $db;
    private $baseDir;

    public function __construct($db) {
        $this->db = $db;
        $this->baseDir = __DIR__;
    }

    public function scanDirectory($path, $moveFiles = false) {
        if (!is_dir($path)) {
            return "Directory not found: $path";
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $allowedExtensions = ['mp4', 'mkv', 'avi', 'mov', 'webm'];
        $count = 0;

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, $allowedExtensions)) {
                    $this->processFile($file, $moveFiles);
                    $count++;
                }
            }
        }
        return "Scanned directory. Processed $count videos.";
    }

    private function processFile($file, $moveFiles) {
        $filePath = $file->getRealPath();
        
        // If not moving, check if already exists by path
        if (!$moveFiles) {
            $stmt = $this->db->prepare("SELECT id FROM videos WHERE filepath = ?");
            $stmt->execute([$filePath]);
            if ($stmt->fetch()) {
                return; // Skip if already indexed
            }
        }

        $filename = $file->getBasename();
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $parentDir = basename(dirname($filePath));
        
        // Use parent folder as category
        $category = preg_replace('/[^a-zA-Z0-9 _-]/', '', $parentDir); 
        if (empty($category)) $category = 'Uncategorized';

        $finalFilePath = $filePath;

        // Move Logic
        if ($moveFiles) {
            $targetDir = $this->baseDir . '/videos/' . $category;
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }

            $targetPath = $targetDir . '/' . $filename;
            
            // Handle duplicate filename
            if (file_exists($targetPath)) {
                $filename = time() . '_' . $filename;
                $targetPath = $targetDir . '/' . $filename;
            }

            if (rename($filePath, $targetPath)) {
                $finalFilePath = $targetPath;
            } else {
                // Failed to move, skip indexing or log error
                return;
            }
        }

        // Generate Thumbnail
        $thumbName = md5($finalFilePath) . '.jpg';
        $thumbPath = $this->baseDir . '/thumbnails/' . $thumbName;
        
        if (!is_dir(dirname($thumbPath))) {
            mkdir(dirname($thumbPath), 0755, true);
        }

        if (!file_exists($thumbPath)) {
            $cmd = "ffmpeg -i " . escapeshellarg($finalFilePath) . " -ss 00:00:01 -vframes 1 -q:v 2 " . escapeshellarg($thumbPath) . " 2>&1";
            exec($cmd);
        }
        
        $webThumbPath = 'thumbnails/' . $thumbName;

        // Insert into DB
        // If moved, we insert the new path. If not moved, we insert the original path.
        // We should check if the new path already exists in DB to avoid duplicates (though rename handled file collision)
        
        // For moved files, we might be re-importing something that was already there but deleted?
        // Just insert.
        
        $stmt = $this->db->prepare("INSERT INTO videos (title, filename, filepath, category, thumbnail, visibility, uploader_id) VALUES (?, ?, ?, ?, ?, 'public', ?)");
        // Assign to current user (admin/scanner)
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
        
        $stmt->execute([$title, $filename, $finalFilePath, $category, $webThumbPath, $userId]);
    }
}

// Handle request if POST
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['directory'])) {
    $scanner = new Scanner($db);
    $moveFiles = isset($_POST['move_files']) ? true : false;
    $result = $scanner->scanDirectory($_POST['directory'], $moveFiles);
    header('Content-Type: application/json');
    echo json_encode(['message' => $result]);
    exit;
}

// Handle CLI execution
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $scanner = new Scanner($db);
    // CLI doesn't support move flag yet, default to false
    echo $scanner->scanDirectory($argv[1], false) . PHP_EOL;
}
?>
