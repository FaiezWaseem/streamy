<?php
// reels.php
require_once 'auth.php';
requireLogin();

// Fetch random videos
// In a real app, this should be paginated or fetched via AJAX as you scroll
// For now, let's fetch 10 random videos
$stmt = $db->query("SELECT * FROM videos WHERE visibility = 'public' ORDER BY RANDOM() LIMIT 10");
$videos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Reels - Streamy</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #000; color: #fff; overflow: hidden; }
        .reel-container { 
            height: 100vh; 
            width: 100vw; 
            overflow-y: scroll; 
            scroll-snap-type: y mandatory; 
            scroll-behavior: smooth;
        }
        .reel-item { 
            height: 100vh; 
            width: 100vw; 
            scroll-snap-align: start; 
            position: relative; 
            display: flex;
            align-items: center;
            justify-content: center;
            background: #000;
        }
        video {
            max-height: 100%;
            max-width: 100%;
            object-fit: contain; /* Or cover if you want true full screen crop */
        }
        /* Hide scrollbar */
        .reel-container::-webkit-scrollbar { display: none; }
        .reel-container { -ms-overflow-style: none; scrollbar-width: none; }
        
        .action-shadow { text-shadow: 0 2px 4px rgba(0,0,0,0.5); }
    </style>
</head>
<body>

    <!-- Back Button (Mobile) -->
    <a href="index.php" class="fixed top-4 left-4 z-50 p-2 bg-black/20 rounded-full backdrop-blur-sm text-white">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </a>

    <div class="reel-container" id="reelContainer">
        <?php foreach ($videos as $index => $video): ?>
            <div class="reel-item" data-id="<?= $video['id'] ?>">
                <!-- Video Player -->
                <video 
                    src="stream.php?id=<?= $video['id'] ?>" 
                    loop 
                    playsinline
                    class="w-full h-full object-cover md:object-contain"
                    onclick="togglePlay(this)"
                    oncontextmenu="return false;"
                ></video>

                <!-- Overlay Info -->
                <div class="absolute bottom-20 left-4 right-16 z-20 pointer-events-none p-4 rounded-lg bg-black/30 backdrop-blur-sm">
                    <h3 class="font-bold text-lg drop-shadow-md text-white"><?= htmlspecialchars($video['title'] ?? '') ?></h3>
                    <p class="text-sm text-gray-200 drop-shadow-md line-clamp-2"><?= htmlspecialchars($video['description'] ?? '') ?></p>
                </div>

                <!-- Right Side Actions -->
                <div class="absolute bottom-10 right-4 z-30 flex flex-col items-center space-y-6">
                    <!-- Like -->
                    <button class="flex flex-col items-center space-y-1" onclick="toggleLike(<?= $video['id'] ?>, this)">
                        <div class="bg-gray-800/60 p-3 rounded-full backdrop-blur-sm">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </div>
                        <span class="text-xs font-medium drop-shadow-md">Like</span>
                    </button>

                    <!-- Comment -->
                    <button class="flex flex-col items-center space-y-1" onclick="location.href='watch.php?id=<?= $video['id'] ?>'">
                        <div class="bg-gray-800/60 p-3 rounded-full backdrop-blur-sm">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                        </div>
                        <span class="text-xs font-medium drop-shadow-md">Comment</span>
                    </button>

                    <!-- Share/More -->
                    <button class="flex flex-col items-center space-y-1">
                        <div class="bg-gray-800/60 p-3 rounded-full backdrop-blur-sm">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"/></svg>
                        </div>
                    </button>
                </div>

                <!-- Play/Pause Icon Overlay (Animated) -->
                <div class="play-icon absolute inset-0 flex items-center justify-center pointer-events-none opacity-0 transition-opacity duration-200">
                    <div class="bg-black/50 p-4 rounded-full backdrop-blur-sm">
                        <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <script>
        const container = document.getElementById('reelContainer');
        const videos = document.querySelectorAll('video');
        let pressTimer;

        // Intersection Observer to Auto-Play/Pause
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const video = entry.target.querySelector('video');
                if (entry.isIntersecting) {
                    video.currentTime = 0;
                    video.play().catch(() => {
                        // Auto-play blocked, show play icon?
                        video.muted = true; // Fallback
                        video.play();
                    });
                } else {
                    video.pause();
                }
            });
        }, { threshold: 0.6 });

        document.querySelectorAll('.reel-item').forEach(item => {
            observer.observe(item);
        });

        // Toggle Play/Pause on Tap
        function togglePlay(video) {
            const icon = video.parentElement.querySelector('.play-icon');
            if (video.paused) {
                video.play();
                icon.style.opacity = '0';
            } else {
                video.pause();
                icon.style.opacity = '1';
                setTimeout(() => icon.style.opacity = '0', 1000); // Fade out after 1s
            }
        }

        // Long Press for Fast Forward
        videos.forEach(video => {
            video.addEventListener('touchstart', (e) => {
                pressTimer = setTimeout(() => {
                    video.playbackRate = 2.0; // 2x Speed
                }, 500);
            });

            video.addEventListener('touchend', () => {
                clearTimeout(pressTimer);
                video.playbackRate = 1.0; // Normal Speed
            });

            video.addEventListener('mousedown', () => {
                pressTimer = setTimeout(() => {
                    video.playbackRate = 2.0;
                }, 500);
            });

            video.addEventListener('mouseup', () => {
                clearTimeout(pressTimer);
                video.playbackRate = 1.0;
            });
        });

        // Like Interaction
        async function toggleLike(videoId, btn) {
            const icon = btn.querySelector('svg');
            // Optimistic update
            const isLiked = icon.getAttribute('fill') === 'currentColor';
            
            if (isLiked) {
                icon.setAttribute('fill', 'none');
                icon.classList.remove('text-red-500');
                icon.classList.add('text-white');
            } else {
                icon.setAttribute('fill', 'currentColor');
                icon.classList.remove('text-white');
                icon.classList.add('text-red-500');
            }

            try {
                const res = await fetch('api.php?action=like', {
                    method: 'POST',
                    body: JSON.stringify({ video_id: videoId })
                });
                const data = await res.json();
                // Sync real state if needed
            } catch (e) {
                console.error('Like failed');
            }
        }
    </script>
</body>
</html>
