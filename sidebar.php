<!-- sidebar.php -->
<!-- PWA & Mobile Optimization Meta Tags (Added here to ensure inclusion across pages) -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#141414">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('service-worker.js').then(function(registration) {
                console.log('ServiceWorker registration successful with scope: ', registration.scope);
            }, function(err) {
                console.log('ServiceWorker registration failed: ', err);
            });
        });
    }
</script>

<!-- Mobile Overlay (Only for sidebar on larger mobile screens if needed, but we rely on bottom nav) -->
<div id="mobile-overlay" onclick="toggleSidebar()" class="fixed inset-0 bg-black/50 z-30 hidden md:hidden backdrop-blur-sm transition-opacity"></div>

<!-- Sidebar (Desktop Only) -->
<aside id="sidebar" class="hidden md:flex w-64 bg-black h-screen fixed top-0 left-0 border-r border-gray-800 flex-col z-40">
    <div class="p-6 text-3xl font-bold text-red-600 tracking-wider flex justify-between items-center">
        <a href="index.php">STREAMY</a>
    </div>
    <nav class="flex-1 px-4 space-y-2 overflow-y-auto">
        <a href="index.php" class="block px-4 py-3 rounded <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'bg-gray-800 text-white font-medium' : 'text-gray-400 hover:text-white hover:bg-gray-800' ?>">Home</a>
        <a href="channels.php" class="block px-4 py-3 rounded <?= basename($_SERVER['PHP_SELF']) == 'channels.php' ? 'bg-gray-800 text-white font-medium' : 'text-gray-400 hover:text-white hover:bg-gray-800' ?>">Channels</a>
        <a href="reels.php" class="block px-4 py-3 rounded <?= basename($_SERVER['PHP_SELF']) == 'reels.php' ? 'bg-gray-800 text-white font-medium' : 'text-gray-400 hover:text-white hover:bg-gray-800' ?>">Reels</a>
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
        <button id="installAppBtn" class="hidden block w-full text-left px-4 py-2 text-sm text-red-500 hover:text-red-400 transition font-bold">Install App</button>
    </div>
</aside>

<!-- Install Prompt Modal (Mobile) -->
<div id="installModal" class="fixed bottom-20 left-4 right-4 bg-gray-900 border border-gray-800 p-4 rounded-lg z-50 hidden shadow-2xl flex items-center justify-between">
    <div>
        <h3 class="font-bold text-white">Install Streamy</h3>
        <p class="text-xs text-gray-400">Add to home screen for better experience</p>
    </div>
    <button id="installModalBtn" class="bg-red-600 text-white px-4 py-2 rounded-full text-sm font-bold hover:bg-red-700">Install</button>
    <button onclick="document.getElementById('installModal').classList.add('hidden')" class="ml-2 text-gray-500">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
    </button>
</div>

<!-- Bottom Navigation (Mobile Only) -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-black border-t border-gray-800 z-50 pb-safe">
    <div class="flex justify-around items-center h-16">
        <a href="index.php" class="flex flex-col items-center justify-center w-full h-full text-xs <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'text-white' : 'text-gray-500' ?>">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            Home
        </a>
        <a href="channels.php" class="flex flex-col items-center justify-center w-full h-full text-xs <?= basename($_SERVER['PHP_SELF']) == 'channels.php' ? 'text-white' : 'text-gray-500' ?>">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            Channels
        </a>
    
        <a href="upload.php" class="flex flex-col items-center justify-center w-full h-full text-xs <?= basename($_SERVER['PHP_SELF']) == 'upload.php' ? 'text-red-500' : 'text-gray-500' ?>">
            <div class="bg-gray-800 rounded-full p-2 -mt-6 border-4 border-black">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            </div>
            <span class="mt-1">Upload</span>
        </a>
            <a href="reels.php" class="flex flex-col items-center justify-center w-full h-full text-xs <?= basename($_SERVER['PHP_SELF']) == 'reels.php' ? 'text-white' : 'text-gray-500' ?>">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
            Reels
        </a>
        <a href="profile.php" class="flex flex-col items-center justify-center w-full h-full text-xs <?= basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'text-white' : 'text-gray-500' ?>">
            <svg class="w-6 h-6 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
            Profile
        </a>
    </div>
</nav>

<!-- Spacer for bottom nav on mobile -->
<div class="md:hidden h-16"></div>

<script>
    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        // Legacy toggle logic if we keep sidebar on tablet or specific breakpoints
        if (sidebar) {
            sidebar.classList.toggle('hidden');
        }
    }
</script>
<style>
    /* Safe area for iPhone X+ */
    .pb-safe { padding-bottom: env(safe-area-inset-bottom); }
</style>
<script>
    let deferredPrompt;
    const installBtn = document.getElementById('installAppBtn');
    const installModal = document.getElementById('installModal');
    const installModalBtn = document.getElementById('installModalBtn');

    window.addEventListener('beforeinstallprompt', (e) => {
        // Prevent Chrome 67 and earlier from automatically showing the prompt
        e.preventDefault();
        // Stash the event so it can be triggered later.
        deferredPrompt = e;
        
        // Show the install button in sidebar (Desktop)
        if (installBtn) {
            installBtn.classList.remove('hidden');
        }

        // Show the install modal (Mobile) - check if not already installed
        if (window.innerWidth < 768 && !window.matchMedia('(display-mode: standalone)').matches) {
            installModal.classList.remove('hidden');
            installModal.classList.add('flex');
        }
    });

    async function installApp() {
        if (!deferredPrompt) return;
        
        // Show the prompt
        deferredPrompt.prompt();
        
        // Wait for the user to respond to the prompt
        const { outcome } = await deferredPrompt.userChoice;
        console.log(`User response to the install prompt: ${outcome}`);
        
        // We've used the prompt, and can't use it again, throw it away
        deferredPrompt = null;
        
        // Hide UI
        if (installBtn) installBtn.classList.add('hidden');
        if (installModal) installModal.classList.add('hidden');
    }

    if (installBtn) installBtn.addEventListener('click', installApp);
    if (installModalBtn) installModalBtn.addEventListener('click', installApp);

    // Hide prompt if app is already installed
    window.addEventListener('appinstalled', () => {
        if (installBtn) installBtn.classList.add('hidden');
        if (installModal) installModal.classList.add('hidden');
        console.log('PWA was installed');
    });
</script>
