<?php
require_once 'auth.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            $error = 'Username or email already exists.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            if ($stmt->execute([$username, $email, $hashed_password])) {
                $_SESSION['user_id'] = $db->lastInsertId();
                header('Location: index.php');
                exit;
            } else {
                $error = 'Registration failed.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Streamy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body { background-color: #141414; color: #fff; }</style>
</head>
<body class="flex items-center justify-center min-h-screen">
    <div class="w-full max-w-md p-8 space-y-6 bg-black/80 rounded-lg shadow-xl">
        <h1 class="text-3xl font-bold text-red-600 text-center">STREAMY</h1>
        <h2 class="text-xl font-semibold text-center">Create Account</h2>
        <?php if ($error): ?>
            <div class="p-3 text-sm text-red-500 bg-red-900/20 rounded"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post" class="space-y-4">
            <div>
                <label class="block text-sm text-gray-400">Username</label>
                <input type="text" name="username" class="w-full p-3 mt-1 bg-gray-700 rounded focus:outline-none focus:ring-2 focus:ring-red-600" required>
            </div>
            <div>
                <label class="block text-sm text-gray-400">Email</label>
                <input type="email" name="email" class="w-full p-3 mt-1 bg-gray-700 rounded focus:outline-none focus:ring-2 focus:ring-red-600" required>
            </div>
            <div>
                <label class="block text-sm text-gray-400">Password</label>
                <input type="password" name="password" class="w-full p-3 mt-1 bg-gray-700 rounded focus:outline-none focus:ring-2 focus:ring-red-600" required>
            </div>
            <div>
                <label class="block text-sm text-gray-400">Confirm Password</label>
                <input type="password" name="confirm_password" class="w-full p-3 mt-1 bg-gray-700 rounded focus:outline-none focus:ring-2 focus:ring-red-600" required>
            </div>
            <button type="submit" class="w-full py-3 font-bold text-white bg-red-600 rounded hover:bg-red-700 transition">Sign Up</button>
        </form>
        <p class="text-sm text-center text-gray-400">
            Already have an account? <a href="login.php" class="text-white hover:underline">Sign in</a>.
        </p>
    </div>
</body>
</html>
