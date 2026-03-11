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
        
        /* Bottom Sheet Modal */
        .bottom-sheet {
            position: fixed;
            bottom: -100%;
            left: 0;
            right: 0;
            height: 70vh;
            background: #1a1a1a;
            border-top-left-radius: 20px;
            border-top-right-radius: 20px;
            transition: bottom 0.3s ease-in-out;
            z-index: 50;
            display: flex;
            flex-direction: column;
        }
        .bottom-sheet.open {
            bottom: 0;
        }
        .sheet-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 40;
            opacity: 0;
            pointer-events: none;
            transition: opacity 0.3s;
        }
        .sheet-overlay.open {
            opacity: 1;
            pointer-events: auto;
        }
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

                <!-- Time Overlay -->
                <div class="absolute bottom-36 left-4 z-20 pointer-events-none text-xs text-gray-300 drop-shadow-md">
                    <span class="current-time">0:00</span> / <span class="duration">0:00</span>
                </div>

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
                    <button class="flex flex-col items-center space-y-1" onclick="openComments(<?= $video['id'] ?>)">
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

    <div class="sheet-overlay" onclick="closeComments()"></div>
    <div class="bottom-sheet" id="commentsSheet">
        <div class="p-4 border-b border-gray-800 flex justify-between items-center bg-gray-900 rounded-t-2xl">
            <h3 class="font-bold text-lg">Comments</h3>
            <button onclick="closeComments()" class="text-gray-400 hover:text-white">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="flex-1 overflow-y-auto p-4 space-y-4" id="commentsList">
            <!-- Comments will load here -->
            <div class="text-center text-gray-500 mt-10">Loading...</div>
        </div>
        <div class="p-4 border-t border-gray-800 bg-gray-900 pb-safe">
            <form id="reelCommentForm" class="flex gap-2">
                <input type="text" id="commentInput" placeholder="Add a comment..." class="flex-1 bg-gray-800 border border-gray-700 rounded-full px-4 py-2 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
                <button type="submit" class="bg-red-600 text-white rounded-full p-2 hover:bg-red-700 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                </button>
            </form>
        </div>
    </div>

    <script>
        const container = document.getElementById('reelContainer');
        // Initial videos loaded by PHP
        let loadedVideoIds = [<?= implode(',', array_column($videos, 'id')) ?>];
        let pressTimer;
        let currentVideoId = null;
        let isLoading = false;

        // Infinite Scroll
        container.addEventListener('scroll', () => {
            const scrollPosition = container.scrollTop + container.clientHeight;
            const scrollHeight = container.scrollHeight;
            
            // Load more when user is 2 videos away from end
            if (scrollHeight - scrollPosition < container.clientHeight * 2 && !isLoading) {
                loadMoreReels();
            }
        });

        async function loadMoreReels() {
            isLoading = true;
            try {
                const exclude = loadedVideoIds.join(',');
                const res = await fetch(`api.php?action=fetch_reels&exclude=${exclude}`);
                const newVideos = await res.json();
                
                if (newVideos.length === 0) {
                    isLoading = false;
                    return;
                }

                newVideos.forEach(video => {
                    loadedVideoIds.push(video.id);
                    const reelItem = createReelItem(video);
                    container.appendChild(reelItem);
                    observer.observe(reelItem);
                });
            } catch (e) {
                console.error('Failed to load more reels');
            } finally {
                isLoading = false;
            }
        }

        function createReelItem(video) {
            const div = document.createElement('div');
            div.className = 'reel-item';
            div.dataset.id = video.id;
            div.innerHTML = `
                <video 
                    src="stream.php?id=${video.id}" 
                    loop 
                    playsinline
                    class="w-full h-full object-cover md:object-contain"
                    onclick="togglePlay(this)"
                    oncontextmenu="return false;"
                ></video>

                <!-- Time Overlay -->
                <div class="absolute bottom-36 left-4 z-20 pointer-events-none text-xs text-gray-300 drop-shadow-md">
                    <span class="current-time">0:00</span> / <span class="duration">0:00</span>
                </div>

                <!-- Overlay Info -->
                <div class="absolute bottom-20 left-4 right-16 z-20 pointer-events-none p-4 rounded-lg bg-black/30 backdrop-blur-sm">
                    <h3 class="font-bold text-lg drop-shadow-md text-white">${escapeHtml(video.title)}</h3>
                    <p class="text-sm text-gray-200 drop-shadow-md line-clamp-2">${escapeHtml(video.description || '')}</p>
                </div>

                <!-- Right Side Actions -->
                <div class="absolute bottom-10 right-4 z-30 flex flex-col items-center space-y-6">
                    <!-- Like -->
                    <button class="flex flex-col items-center space-y-1" onclick="toggleLike(${video.id}, this)">
                        <div class="bg-gray-800/60 p-3 rounded-full backdrop-blur-sm">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>
                        </div>
                        <span class="text-xs font-medium drop-shadow-md">Like</span>
                    </button>

                    <!-- Comment -->
                    <button class="flex flex-col items-center space-y-1" onclick="openComments(${video.id})">
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
            `;
            
            // Attach interactions (Long press, etc)
            const video = div.querySelector('video');
            attachVideoEvents(video);
            
            return div;
        }

        function attachVideoEvents(video) {
            video.addEventListener('touchstart', (e) => {
                pressTimer = setTimeout(() => { video.playbackRate = 2.0; }, 500);
            });
            video.addEventListener('touchend', () => {
                clearTimeout(pressTimer);
                video.playbackRate = 1.0;
            });
            video.addEventListener('mousedown', () => {
                pressTimer = setTimeout(() => { video.playbackRate = 2.0; }, 500);
            });
            video.addEventListener('mouseup', () => {
                clearTimeout(pressTimer);
                video.playbackRate = 1.0;
            });
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Intersection Observer to Auto-Play/Pause
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                const video = entry.target.querySelector('video');
                if (entry.isIntersecting) {
                    video.currentTime = 0;
                    video.play().catch(() => {
                        video.muted = true;
                        video.play();
                    });
                    
                    // Start Time Tracking
                    const timeDisplay = entry.target.querySelector('.current-time');
                    const durationDisplay = entry.target.querySelector('.duration');
                    
                    video.ontimeupdate = () => {
                        if (timeDisplay) timeDisplay.textContent = formatTime(video.currentTime);
                        if (durationDisplay && video.duration) durationDisplay.textContent = formatTime(video.duration);
                    };
                } else {
                    video.pause();
                    video.ontimeupdate = null;
                }
            });
        }, { threshold: 0.6 });

        function formatTime(seconds) {
            const m = Math.floor(seconds / 60);
            const s = Math.floor(seconds % 60);
            return `${m}:${s.toString().padStart(2, '0')}`;
        }

        document.querySelectorAll('.reel-item').forEach(item => {
            observer.observe(item);
        });

        // Comment Sheet Logic
        const sheet = document.getElementById('commentsSheet');
        const overlay = document.querySelector('.sheet-overlay');
        const commentsList = document.getElementById('commentsList');
        const commentForm = document.getElementById('reelCommentForm');

        async function openComments(videoId) {
            currentVideoId = videoId;
            sheet.classList.add('open');
            overlay.classList.add('open');
            
            // Load Comments
            commentsList.innerHTML = '<div class="text-center text-gray-500 mt-10">Loading...</div>';
            try {
                const res = await fetch(`api.php?action=get_comments&video_id=${videoId}`);
                const comments = await res.json();
                
                if (comments.length === 0) {
                    commentsList.innerHTML = '<div class="text-center text-gray-500 mt-10">No comments yet. Be the first!</div>';
                } else {
                    commentsList.innerHTML = comments.map(c => `
                        <div class="flex gap-3 mb-4">
                            <div class="w-8 h-8 rounded-full bg-gray-700 flex-shrink-0 flex items-center justify-center text-xs font-bold">
                                ${c.username.charAt(0).toUpperCase()}
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-sm text-gray-300">${c.username}</span>
                                    <span class="text-xs text-gray-500">${new Date(c.created_at).toLocaleDateString()}</span>
                                </div>
                                <p class="text-sm text-white">${c.content}</p>
                            </div>
                        </div>
                    `).join('');
                }
            } catch (e) {
                commentsList.innerHTML = '<div class="text-center text-red-500 mt-10">Failed to load comments.</div>';
            }
        }

        function closeComments() {
            sheet.classList.remove('open');
            overlay.classList.remove('open');
            currentVideoId = null;
        }

        commentForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            if (!currentVideoId) return;
            
            const input = document.getElementById('commentInput');
            const content = input.value.trim();
            if (!content) return;

            try {
                const res = await fetch('api.php?action=comment', {
                    method: 'POST',
                    body: JSON.stringify({ video_id: currentVideoId, content: content })
                });
                
                if (res.ok) {
                    input.value = '';
                    openComments(currentVideoId); // Reload comments
                }
            } catch (e) {
                alert('Failed to post comment');
            }
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
