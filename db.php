<?php
// db.php
// Database connection and initialization
require_once __DIR__ . '/env_loader.php';

$dbPath = __DIR__ . '/db/streamy.sqlite';

try {
    $db = new PDO("sqlite:$dbPath");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create videos table
    $db->exec("CREATE TABLE IF NOT EXISTS videos (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        filename TEXT NOT NULL,
        filepath TEXT NOT NULL UNIQUE,
        category TEXT DEFAULT 'Uncategorized',
        thumbnail TEXT,
        duration INTEGER DEFAULT 0,
        views INTEGER DEFAULT 0,
        visibility TEXT DEFAULT 'public',
        uploader_id INTEGER DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Create users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        email TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL,
        avatar TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Create watch_history table
    $db->exec("CREATE TABLE IF NOT EXISTS watch_history (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        video_id INTEGER NOT NULL,
        progress INTEGER DEFAULT 0,
        completed INTEGER DEFAULT 0,
        last_watched_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (video_id) REFERENCES videos(id),
        UNIQUE(user_id, video_id)
    )");

    // Create comments table
    $db->exec("CREATE TABLE IF NOT EXISTS comments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        video_id INTEGER NOT NULL,
        content TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (video_id) REFERENCES videos(id)
    )");

    // Create likes table
    $db->exec("CREATE TABLE IF NOT EXISTS likes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id INTEGER NOT NULL,
        video_id INTEGER NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (video_id) REFERENCES videos(id),
        UNIQUE(user_id, video_id)
    )");

    // Add columns if they don't exist (migrations)
    $columns = $db->query("PRAGMA table_info(videos)")->fetchAll(PDO::FETCH_COLUMN, 1);
    if (!in_array('duration', $columns)) {
        $db->exec("ALTER TABLE videos ADD COLUMN duration INTEGER DEFAULT 0");
    }
    if (!in_array('description', $columns)) {
        $db->exec("ALTER TABLE videos ADD COLUMN description TEXT");
    }
    if (!in_array('views', $columns)) {
        $db->exec("ALTER TABLE videos ADD COLUMN views INTEGER DEFAULT 0");
    }
    if (!in_array('visibility', $columns)) {
        $db->exec("ALTER TABLE videos ADD COLUMN visibility TEXT DEFAULT 'public'");
    }
    if (!in_array('uploader_id', $columns)) {
        $db->exec("ALTER TABLE videos ADD COLUMN uploader_id INTEGER DEFAULT 0");
    }

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
