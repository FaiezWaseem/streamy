<?php
require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/recommendations.php';
$user = getCurrentUser($db);
$error = $message = '';
$editing = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId) { $stmt=$db->prepare('SELECT * FROM actors WHERE id=? AND user_id=?'); $stmt->execute([$editId,(int)$user['id']]); $editing=$stmt->fetch(); }

function saveActorImage(?string $dataUri, ?array $upload): ?string {
    $bytes = null; $mime = '';
    if ($upload && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
        if (($upload['size'] ?? 0) > 8*1024*1024) throw new RuntimeException('Profile images must be under 8 MB.');
        $bytes=file_get_contents($upload['tmp_name']); $mime=(new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    } elseif ($dataUri && preg_match('#^data:image/(jpeg|png|webp);base64,([A-Za-z0-9+/=]+)$#',$dataUri,$match)) {
        $bytes=base64_decode($match[2],true); $mime='image/'.$match[1];
    }
    if ($bytes === null) return null;
    if (strlen($bytes)>8*1024*1024 || !in_array($mime,['image/jpeg','image/png','image/webp'],true) || !@getimagesizefromstring($bytes)) throw new RuntimeException('Choose a valid JPG, PNG, or WebP image under 8 MB.');
    $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]; $dir=__DIR__.'/actor_images';
    if (!is_dir($dir)) mkdir($dir,0755,true); $name=bin2hex(random_bytes(16)).'.'.$ext;
    if (file_put_contents($dir.'/'.$name,$bytes)===false) throw new RuntimeException('Could not save the actor image.');
    return 'actor_images/'.$name;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (isset($_POST['delete_actor'])) {
            $id=(int)$_POST['delete_actor'];
            $stmt=$db->prepare('SELECT profile_image FROM actors WHERE id=? AND user_id=?'); $stmt->execute([$id,(int)$user['id']]); $image=$stmt->fetchColumn();
            if ($image!==false) { $db->prepare('DELETE FROM video_actors WHERE actor_id=?')->execute([$id]); $db->prepare('DELETE FROM actors WHERE id=? AND user_id=?')->execute([$id,(int)$user['id']]); if ($image && str_starts_with($image,'actor_images/')) @unlink(__DIR__.'/'.$image); }
            $message='Actor removed.';
        } else {
            $name=trim(preg_replace('/\s+/u',' ',(string)($_POST['name']??''))??'');
            if ($name==='' || (function_exists('mb_strlen')?mb_strlen($name):strlen($name))>80) throw new RuntimeException('Enter an actor name up to 80 characters.');
            $id=(int)($_POST['actor_id']??0); $previous=null;
            if ($id) { $stmt=$db->prepare('SELECT profile_image FROM actors WHERE id=? AND user_id=?'); $stmt->execute([$id,(int)$user['id']]); $previous=$stmt->fetchColumn(); if ($previous===false) throw new RuntimeException('Actor not found.'); }
            $newImage=saveActorImage($_POST['captured_image']??'',$_FILES['profile_image']??null); $image=$newImage??$previous;
            if ($id) $db->prepare('UPDATE actors SET name=?,profile_image=? WHERE id=? AND user_id=?')->execute([$name,$image,$id,(int)$user['id']]);
            else $db->prepare('INSERT INTO actors(user_id,name,profile_image) VALUES(?,?,?)')->execute([(int)$user['id'],$name,$image]);
            if ($newImage && $previous && str_starts_with($previous,'actor_images/')) @unlink(__DIR__.'/'.$previous);
            $message='Actor saved.'; $editId=0; $editing=null;
        }
    } catch (Throwable $e) { $error=$e instanceof PDOException?'An actor with that name may already exist.':$e->getMessage(); }
}
$actors=availableActors($db,(int)$user['id']);
$ownedVideos=$db->prepare('SELECT id,title,filepath FROM videos WHERE uploader_id=? ORDER BY title COLLATE NOCASE'); $ownedVideos->execute([(int)$user['id']]); $ownedVideos=$ownedVideos->fetchAll();
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Actors - Streamy</title><script src="https://cdn.tailwindcss.com"></script><style>body{background:#141414;color:#fff}</style></head><body class="font-sans antialiased flex bg-black text-white">
<?php include __DIR__.'/sidebar.php'; ?><main class="md:ml-64 flex-1 min-h-screen p-6 md:p-10 max-w-6xl"><h1 class="text-3xl font-bold mb-2">Actors</h1><p class="text-gray-400 mb-8">Create actor profiles, then attach them to videos for actor based search.</p>
<?php if($message):?><p class="mb-4 text-green-400"><?=htmlspecialchars($message)?></p><?php endif;?><?php if($error):?><p class="mb-4 text-red-400"><?=htmlspecialchars($error)?></p><?php endif;?>
<form method="post" enctype="multipart/form-data" class="bg-gray-900 rounded-xl p-5 mb-8 grid md:grid-cols-2 gap-5"><input type="hidden" name="actor_id" value="<?=htmlspecialchars((string)($editing['id']??''))?>"><input type="hidden" name="captured_image" id="capturedImage">
<label class="block"> <span class="block text-sm text-gray-400 mb-2">Actor name</span><input name="name" required maxlength="80" value="<?=htmlspecialchars($editing['name']??'')?>" class="w-full bg-gray-800 border border-gray-700 rounded px-4 py-3"></label>
<label class="block"><span class="block text-sm text-gray-400 mb-2">Upload profile image</span><input type="file" name="profile_image" accept="image/jpeg,image/png,image/webp" class="w-full text-sm text-gray-300"></label>
<div class="md:col-span-2 border-t border-gray-800 pt-4"><h2 class="font-semibold mb-3">Or capture a frame from one of your videos</h2><div class="flex flex-col sm:flex-row gap-3"><select id="frameVideo" class="flex-1 bg-gray-800 border border-gray-700 rounded px-3 py-2"><option value="">Choose video</option><?php foreach($ownedVideos as $v):?><option value="stream.php?id=<?=(int)$v['id']?>"><?=htmlspecialchars($v['title'])?></option><?php endforeach;?></select><button type="button" id="captureActorFrame" class="bg-gray-700 rounded px-4 py-2">Capture frame</button></div><video id="framePlayer" class="hidden mt-4 max-h-72 rounded" controls></video><canvas id="frameCanvas" class="hidden"></canvas><div id="framePreview" class="mt-3"></div><p class="text-xs text-gray-500 mt-2">Scrub to the frame you want, then press Capture frame.</p></div>
<div class="md:col-span-2 flex gap-3"><button class="bg-red-600 hover:bg-red-700 rounded px-5 py-3 font-semibold"><?=$editing?'Save actor':'Create actor'?></button><?php if($editing):?><a href="actors.php" class="bg-gray-700 rounded px-5 py-3">Cancel</a><?php endif;?></div></form>
<div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4"><?php foreach($actors as $actor):?><article class="bg-gray-900 rounded-xl overflow-hidden"><img class="w-full aspect-square object-cover bg-gray-800" src="<?=htmlspecialchars($actor['profile_image']?:'assets/video-placeholder.svg')?>" alt="<?=htmlspecialchars($actor['name'])?>"><div class="p-4"><h2 class="font-semibold text-lg"><?=htmlspecialchars($actor['name'])?></h2><a class="text-sm text-red-400" href="search.php?actor=<?=(int)$actor['id']?>">View videos</a><div class="flex gap-4 mt-3"><a class="text-sm text-gray-300" href="actors.php?edit=<?=(int)$actor['id']?>">Edit</a><form method="post" onsubmit="return confirm('Delete this actor and remove it from their videos?')"><button name="delete_actor" value="<?=(int)$actor['id']?>" class="text-sm text-red-400">Delete</button></form></div></div></article><?php endforeach;?></div></main>
<script>
const select=document.getElementById('frameVideo'), player=document.getElementById('framePlayer'), canvas=document.getElementById('frameCanvas'), hidden=document.getElementById('capturedImage'), preview=document.getElementById('framePreview');
select.addEventListener('change',()=>{player.src=select.value;player.classList.toggle('hidden',!select.value);hidden.value='';preview.innerHTML='';});
document.getElementById('captureActorFrame').addEventListener('click',()=>{if(!player.videoWidth){alert('Choose a video and wait for it to load.');return;}const scale=Math.min(1,512/Math.max(player.videoWidth,player.videoHeight));canvas.width=Math.round(player.videoWidth*scale);canvas.height=Math.round(player.videoHeight*scale);canvas.getContext('2d').drawImage(player,0,0,canvas.width,canvas.height);hidden.value=canvas.toDataURL('image/jpeg',.84);preview.innerHTML='<img class="w-24 h-24 object-cover rounded-full" alt="Captured actor frame" src="'+hidden.value+'">';});
</script></body></html>
