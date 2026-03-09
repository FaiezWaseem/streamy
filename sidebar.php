<!-- sidebar.php -->
<!-- Mobile Overlay -->
<div id="mobile-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-30 hidden md:hidden backdrop-blur-sm transition-opacity"></div>

<!-- Sidebar -->
<aside id="sidebar" class="w-64 bg-black h-screen fixed top-0 left-0 border-r border-gray-800 flex flex-col z-40 transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out">
    <div class="p-6 text-3xl font-bold text-red-600 tracking-wider flex justify-between items-center">
        <span>STREAMY</span>
        <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <nav class="flex-1 px-4 space-y-2 overflow-y-auto">
        <a href="index.php" class="block px-4 py-3 rounded <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-gray-800 text-white font-medium' : 'text-gray-400 hover:text-white hover:bg-gray-800' ?>">Home</a>
        <a href="channels.php" class="block px-4 py-3 rounded <?= basename($_SERVER['PHP_SELF']) == 'channels.php' ? 'bg-gray-800 text-white font-medium' : 'text-gray-400 hover:text-white hover:bg-gray-800' ?>">Channels</a>
        <a href="profile.php" class="block px-4 py-3 rounded <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'bg-gray-800 text-white font-medium' : 'text-gray-400 hover:text-white hover:bg-gray-800' ?>">Profile</a>
    </nav>
    <div class="p-4 border-t border-gray-800">
        <a href="upload.php" class="block w-full text-center bg-gray-800 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded mb-4 transition">
            + Upload
        </a>
        <div class="flex items-center space-x-3 px-4 py-2 mb-2">
            <div class="w-8 h-8 rounded-full bg-red-600 flex items-center justify-center text-sm font-bold text-white">
                <?= strtoupper(substr($user['username'] ?? 'U', 0, 1)) ?>
            </div>
            <span class="text-sm font-medium truncate text-gray-300"><?= htmlspecialchars($user['username'] ?? 'User') ?></span>
        </div>
        <a href="logout.php" class="block px-4 py-2 text-sm text-gray-500 hover:text-white transition">Sign out</a>
    </div>
</aside>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('mobile-overlay');
        
        if (sidebar.classList.contains('-translate-x-full')) {
            // Open
            sidebar.classList.remove('-translate-x-full');
            overlay.classList.remove('hidden');
        } else {
            // Close
            sidebar.classList.add('-translate-x-full');
            overlay.classList.add('hidden');
        }
    }
</script>
