<?php
require_once 'auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    $stmt = $db->prepare("SELECT id, password FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Streamy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background-color: #141414; color: #fff; }</style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-8 space-y-6 bg-black/80 rounded-lg shadow-xl">
        <h1 class="text-3xl font-bold text-red-600 text-center">STREAMY</h1>
        <h2 class="text-xl font-semibold text-center">Sign In</h2>
        <?php if ($error): ?>
            <div class="p-3 text-sm text-red-500 bg-red-900/20 rounded"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm text-gray-400">Username</label>
                <input type="text" name="username" class="w-full p-3 mt-1 bg-gray-700 rounded focus:outline-none focus:ring-2 focus:ring-red-600" required>
            </div>
            <div>
                <label class="block text-sm text-gray-400">Password</label>
                <input type="password" name="password" class="w-full p-3 mt-1 bg-gray-700 rounded focus:outline-none focus:ring-2 focus:ring-red-600" required>
            </div>
            <button type="submit" class="w-full py-3 font-bold text-white bg-red-600 rounded hover:bg-red-700 transition">Sign In</button>
        </form>
        <p class="text-sm text-center text-gray-400">
            New to Streamy? <a href="register.php" class="text-white hover:underline">Sign up now</a>.
        </p>
    </div>
</body>
</html>
