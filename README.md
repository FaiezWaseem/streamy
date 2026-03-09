# Streamy

A Netflix-style media streaming platform for your local video collection.

## Features
- **Modern UI**: Dark theme, horizontal scrolling categories, responsive design.
- **Authentication**: User registration, login, and profile management.
- **Watch History**: Tracks playback progress and allows you to resume where you left off.
- **Channels**: Browse videos by category.
- **Local Streaming**: Streams videos directly from your local filesystem with seeking support.
- **Auto-Scanning**: Recursively scans directories, categorizes videos by folder name, and generates thumbnails automatically using FFmpeg.
- **Manual Upload**: Upload videos directly through the web interface.

## Setup
1. Ensure you have **PHP** and **FFmpeg** installed.
2. Place this project in your web server's document root (e.g., `/Applications/MAMP/htdocs/streamy`).
3. Make sure the `videos`, `thumbnails`, and `db` directories are writable by the web server.

## Usage
1. Open the application in your browser (e.g., `http://localhost:8888/streamy`).
2. **Register**: Create a new account.
3. **Scan**: Click "Scan Directory" and enter the absolute path to your video collection (e.g., `/Users/username/Movies`).
   - Folders become categories/channels.
   - Files become video titles.
4. **Upload**: Use the "Upload" button to add individual files.
5. **Watch**: Click any thumbnail to start streaming. Your progress is saved automatically.

## Requirements
- PHP 7.4+
- FFmpeg (must be in system PATH)
- PDO SQLite extension
