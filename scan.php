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

    public function scanDirectory($path) {
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
                    $this->processFile($file);
                    $count++;
                }
            }
        }
        return "Scanned directory. Processed $count videos.";
    }

    private function processFile($file) {
        $filePath = $file->getRealPath();
        
        // Check if already exists
        $stmt = $this->db->prepare("SELECT id FROM videos WHERE filepath = ?");
        $stmt->execute([$filePath]);
        if ($stmt->fetch()) {
            return; // Skip if already indexed
        }

        $filename = $file->getBasename();
        $title = pathinfo($filename, PATHINFO_FILENAME);
        $parentDir = basename(dirname($filePath));
        
        // If the parent dir is the root scan dir, maybe use "Uncategorized" or just the folder name
        $category = $parentDir; 

        // Generate Thumbnail
        $thumbName = md5($filePath) . '.jpg';
        $thumbPath = $this->baseDir . '/thumbnails/' . $thumbName;
        
        // Ensure thumbnail directory exists
        if (!is_dir(dirname($thumbPath))) {
            mkdir(dirname($thumbPath), 0755, true);
        }

        if (!file_exists($thumbPath)) {
            $cmd = "ffmpeg -i " . escapeshellarg($filePath) . " -ss 00:00:01 -vframes 1 -q:v 2 " . escapeshellarg($thumbPath) . " 2>&1";
            exec($cmd);
        }
        
        // Use relative path for thumbnail storage in DB if accessible via web
        // For local file streaming, we might need absolute paths, but for web serving thumbnails, relative is better.
        // Let's store relative path for thumbnail.
        $webThumbPath = 'thumbnails/' . $thumbName;

        $stmt = $this->db->prepare("INSERT INTO videos (title, filename, filepath, category, thumbnail) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$title, $filename, $filePath, $category, $webThumbPath]);
    }
}

// Handle request if POST
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['directory'])) {
    $scanner = new Scanner($db);
    $result = $scanner->scanDirectory($_POST['directory']);
    header('Content-Type: application/json');
    echo json_encode(['message' => $result]);
    exit;
}

// Handle CLI execution
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    $scanner = new Scanner($db);
    echo $scanner->scanDirectory($argv[1]) . PHP_EOL;
}
?>
