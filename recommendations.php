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

function rememberAvailableTags(PDO $db, int $userId, $tags): void {
    $stmt = $db->prepare('INSERT OR IGNORE INTO available_tags(user_id,tag) VALUES(?,?)');
    foreach (videoTags($tags) as $tag) $stmt->execute([$userId, $tag]);
}

function availableTags(PDO $db, int $userId): array {
    $stmt = $db->prepare('SELECT tag FROM available_tags WHERE user_id=? ORDER BY tag COLLATE NOCASE');
    $stmt->execute([$userId]);
    $tags = array_column($stmt->fetchAll(), 'tag');
    $stmt = $db->prepare('SELECT tags FROM videos WHERE uploader_id=?');
    $stmt->execute([$userId]);
    foreach ($stmt->fetchAll() as $row) $tags = array_merge($tags, videoTags($row['tags']));
    return videoTags($tags);
}

function availableActors(PDO $db, int $userId): array {
    $stmt = $db->prepare('SELECT id,name,profile_image FROM actors WHERE user_id=? ORDER BY name COLLATE NOCASE');
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function videoActors(PDO $db, int $videoId): array {
    $stmt = $db->prepare('SELECT a.id,a.name,a.profile_image FROM video_actors va JOIN actors a ON a.id=va.actor_id WHERE va.video_id=? ORDER BY a.name COLLATE NOCASE');
    $stmt->execute([$videoId]);
    return $stmt->fetchAll();
}

function videoActorLabel(PDO $db, int $videoId, int $limit = 2): string {
    $stmt=$db->prepare('SELECT a.name FROM video_actors va JOIN actors a ON a.id=va.actor_id WHERE va.video_id=? ORDER BY a.name COLLATE NOCASE');
    $stmt->execute([$videoId]); $names=array_column($stmt->fetchAll(),'name');
    if (!$names) return '';
    $shown=array_slice($names,0,max(1,$limit));
    return implode(' · ', $shown).(count($names)>count($shown)?' +'.(count($names)-count($shown)):'');
}

function saveVideoActors(PDO $db, int $userId, int $videoId, $actorIds): array {
    if (!is_array($actorIds)) $actorIds = [];
    $actorIds = array_values(array_unique(array_filter(array_map('intval', $actorIds), fn($id) => $id > 0)));
    $actorIds = array_slice($actorIds, 0, 50);
    $db->beginTransaction();
    try {
        $db->prepare('DELETE FROM video_actors WHERE video_id=?')->execute([$videoId]);
        $check = $db->prepare('SELECT id FROM actors WHERE id=? AND user_id=?');
        $insert = $db->prepare('INSERT OR IGNORE INTO video_actors(video_id,actor_id,user_id) VALUES(?,?,?)');
        foreach ($actorIds as $actorId) {
            $check->execute([$actorId,$userId]);
            if ($check->fetchColumn()) $insert->execute([$videoId,$actorId,$userId]);
        }
        $db->commit();
    } catch (Throwable $error) { $db->rollBack(); throw $error; }
    return videoActors($db,$videoId);
}
function interestHistory(PDO $db, int $userId): array {
    $stmt = $db->prepare("SELECT event_id, video_ref, created_at, tags FROM (
        SELECT c.event_id, c.video_ref, c.created_at, c.tags, c.id AS order_id
        FROM cloud_interest_events c WHERE c.user_id=?
        UNION ALL
        SELECT e.event_id, 'server-' || e.video_id AS video_ref, e.created_at, v.tags, e.id AS order_id
        FROM video_watch_events e JOIN videos v ON v.id=e.video_id
        WHERE e.user_id=? AND (v.visibility='public' OR v.uploader_id=?)
          AND NOT EXISTS (SELECT 1 FROM cloud_interest_events c WHERE c.user_id=e.user_id AND c.event_id=e.event_id)
    ) ORDER BY datetime(created_at) DESC, order_id DESC LIMIT 200");
    $stmt->execute([$userId, $userId, $userId]);
    return array_map(fn($row) => ['event_id'=>$row['event_id'], 'video_id'=>$row['video_ref'],
        'watched_at'=>str_replace(' ', 'T', $row['created_at']).'Z', 'tags'=>videoTags($row['tags'])], $stmt->fetchAll());
}

function recordCloudInterestEvent(PDO $db, int $userId, string $eventId, string $videoRef, $tags, float $watchedSeconds, float $duration, ?string $watchedAt = null): bool {
    if (!preg_match('/^[a-f0-9]{32}$/i', $eventId) || !is_finite($watchedSeconds) || !is_finite($duration) ||
        $watchedSeconds < 0 || $duration < 0 || $duration > 86400) return false;
    $threshold = $duration > 0 ? min(10, max(1, $duration * 0.2)) : 10;
    if ($watchedSeconds < $threshold) return false;
    $timestamp = $watchedAt ? strtotime($watchedAt) : time();
    if (!$timestamp || $timestamp > time() + 60 || $timestamp < time() - 31536000) $timestamp = time();
    $videoRef = preg_match('/^server-[1-9][0-9]*$/', $videoRef) ? $videoRef : 'local';
    $db->prepare('INSERT OR IGNORE INTO cloud_interest_events(user_id,event_id,video_ref,tags,watched_seconds,duration,created_at) VALUES(?,?,?,?,?,?,?)')
        ->execute([$userId,strtolower($eventId),$videoRef,json_encode(videoTags($tags)),min(86400,$watchedSeconds),$duration,gmdate('Y-m-d H:i:s',$timestamp)]);
    return true;
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
    $stmt = $db->prepare("SELECT duration,tags FROM videos WHERE id=? AND (visibility='public' OR uploader_id=?)");
    $stmt->execute([$videoId,$userId]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$video) return false;
    $duration = $video['duration'];
    $threshold = $duration > 0 ? min(10, max(1, $duration * 0.2)) : 10;
    if ($watchedSeconds < $threshold) return false;
    $timestamp = $watchedAt ? strtotime($watchedAt) : time();
    if (!$timestamp || $timestamp > time() + 60 || $timestamp < time() - 31536000) $timestamp = time();
    $db->prepare('INSERT OR IGNORE INTO video_watch_events(user_id,video_id,event_id,watched_seconds,created_at) VALUES(?,?,?,?,?)')
        ->execute([$userId,$videoId,strtolower($eventId),min(86400,$watchedSeconds),gmdate('Y-m-d H:i:s',$timestamp)]);
    return recordCloudInterestEvent($db,$userId,$eventId,'server-'.$videoId,$video['tags'],$watchedSeconds,(float)$duration,$watchedAt);
}
