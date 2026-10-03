<?php
// Thumbnail generation is optional: a preview failure must not discard an upload.
function mobileVideoThumbnail(string $videoPath): string {
    $fallback = 'assets/video-placeholder.svg';
    if (!is_callable('proc_open')) return $fallback;
    $directory = __DIR__ . '/thumbnails';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true)) return $fallback;
    $probe = streamyMediaCommand(['ffprobe', '-v', 'error', '-show_entries',
        'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $videoPath], 5);
    $duration = is_numeric(trim($probe ?? '')) ? (float)trim($probe) : 0;
    if ($duration > 0 && $duration <= 5) {
        $milliseconds = (int)floor($duration * 500);
    } else {
        $upper = $duration > 0 ? min(12, $duration - 0.25) : 12;
        $milliseconds = random_int(5000, max(5000, (int)floor($upper * 1000)));
    }
    // New filenames prevent clients from reusing the previous thumbnail.
    $name = hash('sha256', $videoPath) . '-thumb-' . $milliseconds . '-' . bin2hex(random_bytes(3)) . '.jpg';
    $path = $directory . '/' . $name;
    $result = streamyMediaCommand([
        'ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
        '-threads', '1', '-ss', (string)($milliseconds / 1000), '-i', $videoPath,
        '-frames:v', '1', '-vf', 'scale=640:-2', '-q:v', '3', $path,
    ], 10);
    if ($result !== null && is_file($path) && filesize($path) > 0) return 'thumbnails/' . $name;
    @unlink($path);
    return $fallback;
}

// Run bounded media commands; preview failures never invalidate the source video.
function streamyMediaCommand(array $command, int $seconds = 20): ?string {
    $process = @proc_open($command, [0 => ['file', '/dev/null', 'r'],
        1 => ['pipe', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    if (!is_resource($process)) return null;
    stream_set_blocking($pipes[1], false);
    $output = '';
    $deadline = microtime(true) + $seconds;
    do {
        $output .= stream_get_contents($pipes[1]);
        $status = proc_get_status($process);
        if (!$status['running']) break;
        usleep(100000);
    } while (microtime(true) < $deadline);
    if ($status['running']) proc_terminate($process, 9);
    $output .= stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    proc_close($process);
    return !$status['running'] && $status['exitcode'] === 0 ? $output : null;
}

function mobileVideoPreview(string $videoPath, int $duration = 0): string {
    if (!is_callable('proc_open')) return '';
    $directory = __DIR__ . '/thumbnails';
    if (!is_dir($directory) && !@mkdir($directory, 0755, true)) return '';
    $name = hash('sha256', $videoPath) . '-preview-v2.gif';
    $path = $directory . '/' . $name;
    if (is_file($path) && filesize($path) > 0) return 'thumbnails/' . $name;
    $probe = streamyMediaCommand(['ffprobe', '-v', 'error', '-show_entries',
        'format=duration', '-of', 'default=noprint_wrappers=1:nokey=1', $videoPath], 5);
    $seconds = is_numeric(trim($probe ?? '')) ? (float)trim($probe) : (float)$duration;
    if ($seconds <= 0) return '';
    // Center the middle and three-quarter samples; include the final two seconds.
    $starts = $seconds <= 8 ? [0] : [0, $seconds / 2 - 1, $seconds * 0.75 - 1, $seconds - 2];
    $command = ['ffmpeg', '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
        '-filter_complex_threads', '1'];
    $filters = [];
    foreach ($starts as $i => $start) {
        array_push($command, '-threads', '1', '-ss', (string)max(0, $start),
            '-t', (string)($seconds <= 8 ? $seconds : 2), '-i', $videoPath);
        $length = $seconds <= 8 ? $seconds : 2;
        $filters[] = "[$i:v]setpts=PTS-STARTPTS,fps=8,scale=320:240:force_original_aspect_ratio=decrease:force_divisible_by=2,setsar=1,tpad=stop_mode=clone:stop_duration=2,trim=duration={$length}[v$i]";
    }
    $inputs = implode('', array_map(fn($i) => "[v$i]", array_keys($starts)));
    $filters[] = $inputs . 'concat=n=' . count($starts) . ':v=1:a=0,split[s0][s1]';
    $filters[] = '[s0]palettegen=max_colors=128[p]';
    $filters[] = '[s1][p]paletteuse[gif]';
    array_push($command, '-filter_complex', implode(';', $filters), '-map', '[gif]', '-an', '-loop', '0', $path);
    $result = streamyMediaCommand($command);
    if ($result !== null && is_file($path) && filesize($path) > 0) return 'thumbnails/' . $name;
    @unlink($path);
    return '';
}
