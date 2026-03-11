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
            background: #000;
        }
        video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: contain; /* Center horizontal videos with letterboxing */
        }
        /* Progress Bar */
        .progress-container {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 6px; /* Increased hit area */
            background: rgba(255,255,255,0.3);
            z-index: 25;
            cursor: pointer;
        }
        .progress-bar {
            height: 100%;
            background: #dc2626;
            width: 0%;
            transition: width 0.1s linear;
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

                <!-- Progress Bar -->
                <div class="progress-container cursor-pointer" onclick="event.stopPropagation(); seekByBar(event, this)">
                    <div class="progress-bar pointer-events-none"></div>
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

                <!-- Play/Pause Overlay (Animated) -->
                <div class="play-overlay absolute inset-0 flex items-center justify-center opacity-0 transition-opacity duration-200 z-10 bg-black/40 backdrop-blur-[2px] hidden" onclick="togglePlay(this.parentElement.querySelector('video'))">
                    <div class="flex items-center space-x-8">
                        <!-- -5s -->
                        <button onclick="event.stopPropagation(); seek(this, -5)" class="p-3 rounded-full bg-white/20 hover:bg-white/30 backdrop-blur-md text-white transition">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/></svg>
                        </button>
                        
                        <!-- Play Icon -->
                        <div class="p-5 rounded-full bg-white/20 backdrop-blur-md cursor-pointer" onclick="event.stopPropagation(); togglePlay(this.closest('.reel-item').querySelector('video'))">
                            <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 20 20"><path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/></svg>
                        </div>

                        <!-- +5s -->
                        <button onclick="event.stopPropagation(); seek(this, 5)" class="p-3 rounded-full bg-white/20 hover:bg-white/30 backdrop-blur-md text-white transition">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 5l7 7-7 7M5 5l7 7-7 7"/></svg>
                        </button>
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

        // Attach interactions for initial videos
        const initialVideos = document.querySelectorAll('.reel-item video');
        initialVideos.forEach(v => {
            attachVideoEvents(v);
        });

        // Toggle Play/Pause on Tap
        function togglePlay(video) {
            const overlay = video.parentElement.querySelector('.play-overlay');
            
            if (video.paused) {
                video.play();
                overlay.classList.add('opacity-0', 'hidden');
                overlay.classList.remove('flex');
            } else {
                video.pause();
                overlay.classList.remove('opacity-0', 'hidden');
                overlay.classList.add('flex');
            }
        }

        // Seek function (+/- 5s)
        function seek(btn, seconds) {
            const reelItem = btn.closest('.reel-item');
            const video = reelItem.querySelector('video');
            if (video) {
                video.currentTime += seconds;
            }
        }
        
        // Seek by clicking progress bar
        function seekByBar(e, container) {
            const reelItem = container.closest('.reel-item');
            const video = reelItem.querySelector('video');
            if (video && video.duration) {
                const rect = container.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const percent = x / rect.width;
                video.currentTime = percent * video.duration;
            }
        }
        
        // Dragging Support
        let isDragging = false;
        document.addEventListener('mousedown', (e) => {
            if (e.target.classList.contains('progress-container')) {
                isDragging = true;
                seekByBar(e, e.target);
            }
        });
        document.addEventListener('mousemove', (e) => {
            if (isDragging) {
                // Find the reel item under cursor or currently active
                // Simplified: just check if we are over a progress container
                const target = document.elementFromPoint(e.clientX, e.clientY);
                if (target && target.classList.contains('progress-container')) {
                     seekByBar(e, target);
                }
            }
        });
        document.addEventListener('mouseup', () => {
            isDragging = false;
        });
        
        // Touch Dragging Support
        document.addEventListener('touchstart', (e) => {
            if (e.target.classList.contains('progress-container')) {
                isDragging = true;
                seekByBar(e.touches[0], e.target);
            }
        });
        document.addEventListener('touchmove', (e) => {
            if (isDragging) {
                const target = document.elementFromPoint(e.touches[0].clientX, e.touches[0].clientY);
                if (target && target.classList.contains('progress-container')) {
                     seekByBar(e.touches[0], target);
                }
            }
        });
        document.addEventListener('touchend', () => {
            isDragging = false;
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

                <!-- Progress Bar -->
                <div class="progress-container">
                    <div class="progress-bar"></div>
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
            const _video = div.querySelector('video');
            attachVideoEvents(_video);
            
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
                    const progressBar = entry.target.querySelector('.progress-bar');
                    
                    video.ontimeupdate = () => {
                        if (timeDisplay) timeDisplay.textContent = formatTime(video.currentTime);
                        if (durationDisplay && video.duration) durationDisplay.textContent = formatTime(video.duration);
                        if (progressBar && video.duration) {
                            const percent = (video.currentTime / video.duration) * 100;
                            progressBar.style.width = percent + '%';
                        }
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
    </script>
</body>
</html>
