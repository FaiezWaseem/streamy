<?php
// Shared tags and recency-weighted recommendations for web and mobile.
function videoTags($value): array {
    if (is_string($value)) {
        $decoded = json_decode($value, true);
        $value = is_array($decoded) ? $decoded : preg_split('/[,\n]+/u', $value);
    }
    if (!is_array($value)) return [];
    $tags = [];
    foreach ($value as $tag) {
        if (!is_string($tag)) continue;
        $tag = trim(preg_replace('/\s+/u', ' ', $tag) ?? '');
        $tag = function_exists('mb_strtolower') ? mb_strtolower($tag, 'UTF-8') : strtolower($tag);
        preg_match_all('/./us', $tag, $characters);
        $tag = implode('', array_slice($characters[0], 0, 40));
        if ($tag !== '' && !in_array($tag, $tags, true)) $tags[] = $tag;
        if (count($tags) >= 20) break;
    }
    return $tags;
}
function interestHistory(PDO $db, int $userId): array {
    $stmt = $db->prepare("SELECT e.event_id, e.video_id, e.created_at, v.tags
        FROM video_watch_events e JOIN videos v ON v.id=e.video_id
        WHERE e.user_id=? AND (v.visibility='public' OR v.uploader_id=?)
        ORDER BY e.created_at DESC,e.id DESC LIMIT 200");
    $stmt->execute([$userId, $userId]);
    return array_map(fn($row) => ['event_id'=>$row['event_id'], 'video_id'=>'server-'.$row['video_id'],
        'watched_at'=>str_replace(' ', 'T', $row['created_at']).'Z', 'tags'=>videoTags($row['tags'])], $stmt->fetchAll());
}
function tagInterestScores(array $history): array {
    $scores = [];
    foreach ($history as $rank => $event) {
        $tags = videoTags($event['tags']);
        $ageDays = max(0, (time() - (strtotime($event['watched_at']) ?: time())) / 86400);
        $weight = ($rank < 3 ? 1.5 : 1) * pow(0.5, $rank / 3) * pow(0.5, $ageDays / 14) / max(1, count($tags));
        foreach ($tags as $tag) $scores[$tag] = ($scores[$tag] ?? 0) + $weight;
    }
    arsort($scores);
    return $scores;
}
function recommendedVideos(PDO $db, int $userId, int $limit = 12, ?array $reference = null): array {
    $history = interestHistory($db, $userId);
    $scores = tagInterestScores($history);
    $recent = array_column(array_slice($history, 0, 3), 'video_id');
    $referenceTags = videoTags($reference['tags'] ?? []);
    $stmt = $db->prepare("SELECT * FROM videos WHERE visibility='public' OR uploader_id=? ORDER BY created_at DESC,id DESC");
    $stmt->execute([$userId]);
    $videos = $stmt->fetchAll();
    if ($referenceTags) {
        $matching = array_values(array_filter($videos, fn($v) => $v['id'] != $reference['id'] && count(array_intersect(videoTags($v['tags']),$referenceTags)) > 0));
        if ($matching) $videos = $matching;
    }
    foreach ($videos as &$video) {
        $tags = videoTags($video['tags']);
        $interest = array_sum(array_map(fn($tag) => $scores[$tag] ?? 0, $tags)) / max(1, sqrt(count($tags)));
        $similar = count(array_intersect($tags, $referenceTags)) / max(1, count(array_unique(array_merge($tags, $referenceTags))));
        $video['recommendation_score'] = ($interest + 3 * $similar) * (in_array('server-'.$video['id'], $recent, true) ? 0.85 : 1);
    }
    unset($video);
    if ($reference) $videos = array_values(array_filter($videos, fn($v) => $v['id'] != $reference['id']));
    usort($videos, fn($a,$b) => ($b['recommendation_score'] <=> $a['recommendation_score']) ?: ($b['id'] <=> $a['id']));
    return array_slice($videos, 0, $limit);
}
function recordInterestWatch(PDO $db, int $userId, int $videoId, string $eventId, float $watchedSeconds, ?string $watchedAt = null): bool {
    if (!preg_match('/^[a-f0-9]{32}$/i', $eventId) || !is_finite($watchedSeconds)) return false;
    $stmt = $db->prepare("SELECT duration FROM videos WHERE id=? AND (visibility='public' OR uploader_id=?)");
    $stmt->execute([$videoId,$userId]);
    $duration = $stmt->fetchColumn();
    if ($duration === false) return false;
    $threshold = $duration > 0 ? min(10, max(1, $duration * 0.2)) : 10;
    if ($watchedSeconds < $threshold) return false;
    $timestamp = $watchedAt ? strtotime($watchedAt) : time();
    if (!$timestamp || $timestamp > time() + 60 || $timestamp < time() - 31536000) $timestamp = time();
    $db->prepare('INSERT OR IGNORE INTO video_watch_events(user_id,video_id,event_id,watched_seconds,created_at) VALUES(?,?,?,?,?)')
        ->execute([$userId,$videoId,strtolower($eventId),min(86400,$watchedSeconds),gmdate('Y-m-d H:i:s',$timestamp)]);
    return true;
}
