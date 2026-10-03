<?php
// Thumbnail generation is optional: a preview failure must not discard an upload.
function mobileVideoThumbnail(string $videoPath): string {
    $fallback = 'assets/video-placeholder.svg';
    if (!is_callable('proc_open')) return $fallback;
    $directory = __DIR__ . '/thumbnails';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true)) return $fallback;
    $name = hash('sha256', $videoPath) . '.jpg';
    $path = $directory . '/' . $name;
    $process = @proc_open([
        'ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
        '-threads', '1', '-i', $videoPath, '-frames:v', '1',
        '-vf', 'scale=640:-2', '-q:v', '3', $path,
    ], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    if (!is_resource($process)) return $fallback;
    $deadline = microtime(true) + 10;
    do {
        $status = proc_get_status($process);
        if (!$status['running']) break;
        usleep(100000);
    } while (microtime(true) < $deadline);
    if ($status['running']) proc_terminate($process, 9);
    proc_close($process);
    if (!$status['running'] && is_file($path) && filesize($path) > 0) {
        return 'thumbnails/' . $name;
    }
    @unlink($path);
    return $fallback;
}

function mobileVideoPreview(string $videoPath, int $duration = 0): string {
    if (!is_callable('proc_open')) return '';
    $directory = __DIR__ . '/thumbnails';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true)) return '';
    $name = hash('sha256', $videoPath) . '-preview.gif';
    $path = $directory . '/' . $name;
    if (is_file($path) && filesize($path) > 0) return 'thumbnails/' . $name;
    $start = max(0, min((int)floor($duration * 0.1), max(0, $duration - 3)));
    $process = @proc_open([
        'ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
        '-threads', '1', '-filter_complex_threads', '1', '-ss', (string)$start,
        '-t', '3', '-i', $videoPath, '-an', '-vf',
        'fps=8,scale=320:-2:flags=lanczos,split[s0][s1];[s0]palettegen=max_colors=128[p];[s1][p]paletteuse',
        '-loop', '0', $path,
    ], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    if (!is_resource($process)) return '';
    $deadline = microtime(true) + 10;
    do {
        $status = proc_get_status($process);
        if (!$status['running']) break;
        usleep(100000);
    } while (microtime(true) < $deadline);
    if ($status['running']) proc_terminate($process, 9);
    proc_close($process);
    if (!$status['running'] && is_file($path) && filesize($path) > 0) return 'thumbnails/' . $name;
    @unlink($path);
    return '';
}
