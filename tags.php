<?php
require_once __DIR__ . '/auth.php';
requireLogin();
require_once __DIR__ . '/recommendations.php';
$user = getCurrentUser($db);
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_tag'])) {
        $tag = videoTags([$_POST['delete_tag']])[0] ?? '';
        if ($tag !== '') $db->prepare('DELETE FROM available_tags WHERE user_id=? AND tag=?')->execute([(int)$user['id'], $tag]);
        $message = 'Tag removed from your library.';
    } else {
        $tags = videoTags($_POST['tags'] ?? '');
        if (!$tags) $error = 'Enter at least one tag.';
        else { rememberAvailableTags($db, (int)$user['id'], $tags); $message = count($tags) . ' tag' . (count($tags) === 1 ? '' : 's') . ' saved.'; }
    }
}
$tags = availableTags($db, (int)$user['id']);
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Tags - Streamy</title><script src="https://cdn.tailwindcss.com"></script><style>body{background:#141414;color:#fff}</style></head>
<body class="font-sans antialiased flex bg-black text-white">
<?php include __DIR__ . '/sidebar.php'; ?>
<main class="md:ml-64 flex-1 min-h-screen p-6 md:p-10 max-w-5xl">
  <h1 class="text-3xl font-bold mb-2">Tag library</h1>
  <p class="text-gray-400 mb-8">Manage tags shared between your web library and connected mobile app. Saved tags appear as suggestions when uploading or editing videos.</p>
  <?php if ($message): ?><p class="mb-4 text-green-400"><?= htmlspecialchars($message) ?></p><?php endif; ?>
  <?php if ($error): ?><p class="mb-4 text-red-400"><?= htmlspecialchars($error) ?></p><?php endif; ?>
  <form method="post" class="flex flex-col sm:flex-row gap-3 mb-8"><input name="tags" required placeholder="Add tags separated by commas" class="flex-1 bg-gray-800 border border-gray-700 rounded px-4 py-3 text-white"><button class="bg-red-600 hover:bg-red-700 rounded px-6 py-3 font-semibold">Add tags</button></form>
  <section class="flex flex-wrap gap-3">
    <?php foreach ($tags as $tag): ?><form method="post" class="flex items-center gap-2 bg-gray-800 rounded-full px-4 py-2"><span><?= htmlspecialchars($tag) ?></span><button name="delete_tag" value="<?= htmlspecialchars($tag) ?>" aria-label="Remove <?= htmlspecialchars($tag) ?>" class="text-gray-400 hover:text-red-400">×</button></form><?php endforeach; ?>
    <?php if (!$tags): ?><p class="text-gray-500">No saved tags yet. Tags used on your existing videos will appear here as you edit them.</p><?php endif; ?>
  </section>
</main></body></html>
