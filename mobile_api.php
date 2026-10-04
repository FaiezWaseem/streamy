<?php
require_once __DIR__ . '/mobile_auth.php';
require_once __DIR__ . '/recommendations.php';

header('Content-Type: application/json; charset=utf-8');
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

function mobileJson($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if ($action === 'login' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $username = trim((string)($input['username'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $stmt = $db->prepare('SELECT id, password FROM users WHERE username = ?');
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user || !password_verify($password, $user['password'])) {
        mobileJson(['error' => 'Invalid username or password'], 401);
    }

    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare("INSERT INTO api_tokens (user_id, token_hash, expires_at) VALUES (?, ?, datetime('now', '+90 days'))");
    $stmt->execute([(int)$user['id'], hash('sha256', $token)]);
    mobileJson(['token' => $token, 'expires_in' => 7776000, 'user_id' => (int)$user['id']]);
}

$userId = mobileRequireUser($db);

if ($action === 'tag-library' && $method === 'GET') {
    mobileJson(['tags' => availableTags($db, $userId)]);
}
if ($action === 'tag-library' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $insert = $db->prepare('INSERT OR IGNORE INTO available_tags(user_id,tag) VALUES(?,?)');
    foreach (videoTags($input['tags'] ?? []) as $tag) $insert->execute([$userId, $tag]);
    mobileJson(['success' => true, 'tags' => availableTags($db, $userId)]);
}
if ($action === 'tag-library' && $method === 'DELETE') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $tag = videoTags([$input['tag'] ?? ''])[0] ?? '';
    if ($tag !== '') $db->prepare('DELETE FROM available_tags WHERE user_id=? AND tag=?')->execute([$userId, $tag]);
    mobileJson(['success' => true, 'tags' => availableTags($db, $userId)]);
}
if ($action === 'actor-library' && $method === 'GET') {
    mobileJson(['actors'=>availableActors($db,$userId)]);
}
if ($action === 'actor-library' && $method === 'POST') {
    $input=json_decode(file_get_contents('php://input'),true)?:[];
    $name=trim(preg_replace('/\\s+/u',' ',(string)($input['name']??''))??'');
    if($name==='' || (function_exists('mb_strlen')?mb_strlen($name):strlen($name))>80) mobileJson(['error'=>'Actor name is required (maximum 80 characters).'],400);
    $image=null; $encoded=(string)($input['image_base64']??''); $mime=(string)($input['image_mime']??'');
    if($encoded!=='') {
        if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)) mobileJson(['error'=>'Use a JPG, PNG, or WebP actor image.'],400);
        $bytes=base64_decode($encoded,true);
        if($bytes===false || strlen($bytes)>8*1024*1024 || !@getimagesizefromstring($bytes)) mobileJson(['error'=>'Invalid actor image.'],400);
        $dir=__DIR__.'/actor_images'; if(!is_dir($dir)) mkdir($dir,0755,true);
        $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]; $nameFile=bin2hex(random_bytes(16)).'.'.$ext;
        if(file_put_contents($dir.'/'.$nameFile,$bytes)===false) mobileJson(['error'=>'Could not save actor image.'],500);
        $image='actor_images/'.$nameFile;
    }
    $actorId=(int)($input['actor_id']??0);
    try {
        if($actorId) {
            $sql=$image?'UPDATE actors SET name=?,profile_image=? WHERE id=? AND user_id=?':'UPDATE actors SET name=? WHERE id=? AND user_id=?';
            $args=$image?[$name,$image,$actorId,$userId]:[$name,$actorId,$userId];
            $db->prepare($sql)->execute($args);
            if(!$db->query('SELECT changes()')->fetchColumn()) mobileJson(['error'=>'Actor not found.'],404);
        } else $db->prepare('INSERT INTO actors(user_id,name,profile_image) VALUES(?,?,?)')->execute([$userId,$name,$image]);
    } catch(PDOException $e) { mobileJson(['error'=>'An actor with that name already exists.'],409); }
    mobileJson(['success'=>true,'actors'=>availableActors($db,$userId)]);
}
if ($action === 'actor-library' && $method === 'DELETE') {
    $input=json_decode(file_get_contents('php://input'),true)?:[]; $actorId=(int)($input['actor_id']??0);
    $db->prepare('DELETE FROM video_actors WHERE actor_id=? AND user_id=?')->execute([$actorId,$userId]);
    $db->prepare('DELETE FROM actors WHERE id=? AND user_id=?')->execute([$actorId,$userId]);
    mobileJson(['success'=>true,'actors'=>availableActors($db,$userId)]);
}
if ($action === 'video-actors' && $method === 'POST') {
    $input=json_decode(file_get_contents('php://input'),true)?:[]; $videoId=(int)($input['video_id']??0);
    $check=$db->prepare('SELECT id FROM videos WHERE id=? AND uploader_id=?'); $check->execute([$videoId,$userId]);
    if(!$check->fetchColumn()) mobileJson(['error'=>'You can only edit actors on your own videos.'],403);
    $actors=saveVideoActors($db,$userId,$videoId,$input['actor_ids']??[]);
    mobileJson(['success'=>true,'actors'=>$actors]);
}

if ($action === 'logout' && $method === 'POST') {
    $stmt = $db->prepare('DELETE FROM api_tokens WHERE token_hash = ?');
    $stmt->execute([hash('sha256', mobileBearerToken())]);
    mobileJson(['success' => true]);
}

if ($action === 'videos' && $method === 'GET') {
    $stmt = $db->prepare("SELECT id, title, description, filename, category, thumbnail, preview_gif, tags, uploader_id, duration, views, created_at
        FROM videos WHERE visibility = 'public' OR uploader_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $videos = array_map(function ($video) use ($userId) {
        return [
            'id' => (int)$video['id'],
            'title' => $video['title'],
            'description' => $video['description'] ?? '',
            'filename' => $video['filename'],
            'category' => $video['category'] ?: 'Uncategorized',
            'thumbnail' => $video['thumbnail'] ?: null,
            'preview_gif' => $video['preview_gif'] ?: null,
            'tags' => videoTags($video['tags']),
            'actors' => videoActors($GLOBALS['db'], (int)$video['id']),
            'can_edit' => (int)$video['uploader_id'] === $userId,
            'duration' => (int)$video['duration'],
            'views' => (int)$video['views'],
            'created_at' => $video['created_at'],
        ];
    }, $stmt->fetchAll());
    mobileJson(['videos' => $videos, 'history' => interestHistory($db, $userId), 'user_id' => $userId]);
}

if ($action === 'tags' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $stmt = $db->prepare('SELECT id FROM videos WHERE id=? AND uploader_id=?');
    $stmt->execute([(int)($input['video_id'] ?? 0),$userId]);
    if (!$stmt->fetchColumn()) mobileJson(['error'=>'You can only edit tags on your own videos.'],403);
    $tags = videoTags($input['tags'] ?? []);
    $db->prepare('UPDATE videos SET tags=? WHERE id=?')->execute([json_encode($tags),(int)$input['video_id']]);
    rememberAvailableTags($db, $userId, $tags);
    mobileJson(['success'=>true,'tags'=>$tags]);
}
if ($action === 'watch' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $duration = (float)($input['duration'] ?? 0);
    if (is_finite($duration) && $duration > 0 && $duration <= 86400) {
        $db->prepare("UPDATE videos SET duration=? WHERE id=? AND duration=0 AND (visibility='public' OR uploader_id=?)")
            ->execute([$duration,(int)($input['video_id'] ?? 0),$userId]);
    }
    $recorded = recordInterestWatch($db,$userId,(int)($input['video_id'] ?? 0),(string)($input['event_id'] ?? ''),(float)($input['watched_seconds'] ?? 0),$input['watched_at'] ?? null);
    mobileJson(['recorded'=>$recorded]);
}
if ($action === 'interest' && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: [];
    $videoRef = (string)($input['video_ref'] ?? 'local');
    $eventId = (string)($input['event_id'] ?? '');
    if (preg_match('/^server-([1-9][0-9]*)$/', $videoRef, $match)) {
        $recorded = recordInterestWatch($db,$userId,(int)$match[1],$eventId,(float)($input['watched_seconds'] ?? 0),$input['watched_at'] ?? null);
    } else {
        $recorded = recordCloudInterestEvent($db,$userId,$eventId,'local',$input['tags'] ?? [],(float)($input['watched_seconds'] ?? 0),(float)($input['duration'] ?? 0),$input['watched_at'] ?? null);
    }
    mobileJson(['recorded'=>$recorded]);
}

mobileJson(['error' => 'Unknown action'], 404);
