<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Fetch rides from self and friends
$stmt = $pdo->prepare("SELECT r.*, u.username as creator_name, u.avatar as creator_avatar,
                    (SELECT COUNT(*) FROM ride_participants WHERE ride_id = r.id) as participant_count,
                    (SELECT COUNT(*) FROM ride_participants WHERE ride_id = r.id AND user_id = ?) as is_participating
                    FROM rides r 
                    JOIN users u ON r.creator_id = u.id 
                    LEFT JOIN friends f ON (f.user_id = ? AND f.friend_id = r.creator_id AND f.status = 'accepted')
                    WHERE (r.creator_id = ? OR f.id IS NOT NULL) AND r.status != 'finished'
                    ORDER BY r.created_at DESC");
$stmt->execute([$user_id, $user_id, $user_id]);
$rides = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="flex flex-col xl:flex-row gap-10">
    <!-- Main Center: Live Feed & Map -->
    <div class="flex-grow space-y-10">
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="px-2 md:px-0">
                <h1 class="text-3xl md:text-4xl font-black text-white tracking-tighter mb-1 uppercase">Dashboard</h1>
                <p class="text-xs md:text-sm text-slate-500 font-medium">Welcome back, Captain <span class="text-indigo-400"><?php echo htmlspecialchars($user['username']); ?></span></p>
            </div>
            <div class="flex gap-4 px-2 md:px-0">
                <div class="glass px-4 py-2.5 md:px-6 md:py-3 rounded-2xl flex items-center gap-3 border border-white/5 w-full md:w-auto">
                    <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse flex-shrink-0"></div>
                    <span class="text-[10px] md:text-xs font-bold text-slate-300">GPS Signal Optimized</span>
                </div>
            </div>
        </div>

        <!-- Live Feed Grid -->
        <section>
            <div class="flex items-center gap-3 mb-6">
                <span class="w-8 h-[2px] bg-indigo-500"></span>
                <h2 class="text-sm font-black text-slate-400 uppercase tracking-[0.2em]">Active Expeditions</h2>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                <?php foreach($rides as $ride): ?>
                    <div class="glass group p-5 md:p-8 rounded-[2rem] md:rounded-[2.5rem] border border-white/10 hover:border-indigo-500 transition-all relative overflow-hidden">
                        <div class="flex items-center justify-between mb-6 md:mb-8">
                            <div class="flex items-center gap-3 md:gap-4">
                                <img src="uploads/avatars/<?php echo $ride['creator_avatar'] ?: 'default.png'; ?>" class="w-10 h-10 md:w-12 md:h-12 rounded-xl md:rounded-2xl object-cover border border-white/10" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($ride['creator_name']); ?>&background=6366f1&color=fff'">
                                <div>
                                    <h3 class="text-base md:text-lg font-bold text-white leading-none mb-1"><?php echo htmlspecialchars($ride['title']); ?></h3>
                                    <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest truncate w-24 md:w-auto">Lead: <?php echo htmlspecialchars($ride['creator_name']); ?></p>
                                </div>
                            </div>
                            <div class="flex flex-col items-end gap-2">
                                <span class="px-2 py-0.5 md:px-3 md:py-1 bg-indigo-500/10 text-indigo-400 text-[8px] md:text-[9px] font-black rounded-full border border-indigo-500/20 uppercase tracking-widest">
                                    <?php echo $ride['status']; ?>
                                </span>
                                <?php if(!empty($ride['tags'])): ?>
                                    <span class="text-[7px] font-bold text-slate-500 uppercase tracking-tighter bg-white/5 px-2 py-0.5 rounded-lg border border-white/5">
                                        <?php echo htmlspecialchars($ride['tags']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-4 mb-8">
                            <div class="bg-black/20 p-4 rounded-3xl border border-white/5 text-center">
                                <p class="text-[8px] text-slate-500 font-black uppercase mb-1 tracking-widest">Personnel</p>
                                <p class="text-lg font-black text-white"><?php echo $ride['participant_count']; ?></p>
                            </div>
                            <div class="bg-black/20 p-4 rounded-3xl border border-white/5 text-center">
                                <p class="text-[8px] text-slate-500 font-black uppercase mb-1 tracking-widest">Time</p>
                                <p class="text-sm font-black text-white"><?php echo date('h:i A', strtotime($ride['created_at'])); ?></p>
                            </div>
                        </div>
                        
                        <?php if($ride['is_participating']): ?>
                            <a href="ride.php?id=<?php echo $ride['id']; ?>" class="block w-full py-4 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-2xl text-center font-black text-xs tracking-widest hover:bg-emerald-500 hover:text-white transition-all uppercase">
                                Resume Session
                            </a>
                        <?php else: ?>
                            <a href="api/join_ride.php?id=<?php echo $ride['id']; ?>" class="block w-full py-4 bg-indigo-600 text-white rounded-2xl text-center font-black text-xs tracking-widest shadow-xl shadow-indigo-500/20 hover:bg-indigo-700 transition-all uppercase">
                                Join Group
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>

                <?php if(empty($rides)): ?>
                    <div class="col-span-full py-24 glass rounded-[3rem] border border-dashed border-white/10 flex flex-col items-center justify-center text-center">
                        <h3 class="text-xl font-bold text-slate-500 uppercase tracking-widest">No Active Sessions</h3>
                        <p class="text-slate-600 text-xs mt-2">Broadcast a new ride to start tracking.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <!-- Right Column: Actions -->
    <div class="w-full xl:w-96 space-y-10">
        <!-- Broadcaster Card -->
        <div class="glass p-6 md:p-8 rounded-[2rem] md:rounded-[2.5rem] border border-white/10 shadow-2xl relative">
            <div class="absolute -top-10 -right-10 w-32 h-32 bg-indigo-600/20 rounded-full blur-3xl"></div>
            <h2 class="text-lg md:text-xl font-black text-white mb-6 uppercase tracking-tighter flex items-center gap-3">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 md:h-6 md:w-6 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                </svg>
                Broadcaster
            </h2>
            <form action="api/create_ride.php" method="POST" class="space-y-4">
                <input type="text" name="title" required class="w-full bg-white/5 border border-white/10 rounded-xl px-5 py-3.5 text-white focus:ring-1 focus:ring-indigo-500 outline-none text-xs font-medium" placeholder="Expedition Title...">
                <input type="text" name="tags" class="w-full bg-white/5 border border-white/10 rounded-xl px-5 py-3 text-white focus:ring-1 focus:ring-indigo-500 outline-none text-[10px] uppercase font-bold tracking-widest" placeholder="Tags: Night, Offroad, Chill...">
                
                <div class="space-y-6">
                <!-- Achievement Stat -->
                <div class="bg-indigo-600/10 p-4 rounded-2xl border border-indigo-500/20 flex items-center justify-between">
                    <div>
                        <p class="text-[8px] text-slate-500 font-black uppercase tracking-widest mb-1">Lifetime Distance</p>
                        <p class="text-lg font-black text-white"><?php echo number_format($user['total_km'] ?? 0, 1); ?> <span class="text-[10px] text-indigo-400">KM</span></p>
                    </div>
                    <div class="p-3 bg-indigo-500/20 rounded-xl text-indigo-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                        </svg>
                    </div>
                </div>

                <div class="flex items-center gap-4">
                        <input type="text" id="loc-search" placeholder="Target destination..." class="flex-grow bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white text-[10px] md:text-xs outline-none focus:ring-1 focus:ring-indigo-500">
                        <button type="button" onclick="searchLocation()" class="px-4 bg-white/10 text-white rounded-xl text-[10px] md:text-xs font-bold hover:bg-indigo-600 transition-all">Go</button>
                    </div>
                    <div class="relative group">
                        <div id="dest-map" class="h-40 md:h-48 rounded-2xl md:rounded-3xl border border-white/10 overflow-hidden layer-dark"></div>
                        <button type="button" onclick="toggleMapModal()" class="absolute top-3 right-3 p-2 bg-indigo-600 rounded-lg md:rounded-xl text-white shadow-xl opacity-100 lg:opacity-0 lg:group-hover:opacity-100 transition-opacity z-[400] border border-white/10">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                            </svg>
                        </button>
                    </div>
                    <input type="hidden" name="dest_lat" id="dest_lat">
                    <input type="hidden" name="dest_lng" id="dest_lng">
                </div>

                <button type="submit" class="btn-premium w-full py-4 rounded-2xl font-black text-white uppercase text-xs tracking-[0.2em]">
                    Launch Expedition
                </button>
            </form>
        </div>

        <!-- Settings Redirect / Anchor -->
        <div id="settings" class="glass p-8 rounded-[2.5rem] border border-white/10">
            <h2 class="text-sm font-black text-slate-300 uppercase tracking-widest mb-6 flex items-center gap-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37a1.724 1.724 0 002.572-1.065z" />
                </svg>
                Config
            </h2>
            <div class="space-y-6">
                <!-- Avatar -->
                <form action="api/upload_avatar.php" method="POST" enctype="multipart/form-data">
                    <p class="text-[9px] text-slate-500 font-black uppercase tracking-widest mb-3">Identity Image</p>
                    <div class="flex gap-2">
                        <input type="file" name="avatar" class="block w-full text-[9px] text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-[9px] file:font-black file:bg-white/5 file:text-slate-300 hover:file:bg-white/10" accept="image/*">
                        <button type="submit" class="px-4 py-2 bg-indigo-500/10 text-indigo-400 text-[10px] rounded-xl font-bold hover:bg-indigo-500 hover:text-white transition-all">Save</button>
                    </div>
                </form>
                
                <!-- Telegram -->
                <form action="api/update_profile.php" method="POST" class="space-y-4 pt-6 border-t border-white/5">
                    <div class="flex items-center justify-between uppercase tracking-widest leading-none">
                        <p class="text-[9px] text-slate-500 font-black">Telegram Alert ID</p>
                        <button type="button" onclick="toggleHelpModal()" class="text-indigo-400 hover:text-white transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </button>
                    </div>
                    <input type="text" name="telegram_chat_id" value="<?php echo htmlspecialchars($user['telegram_chat_id'] ?? ''); ?>" placeholder="Chat ID" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-white text-xs outline-none focus:ring-1 focus:ring-sky-500">
                    <button type="submit" class="w-full py-3 bg-sky-500/10 text-sky-400 text-[10px] rounded-xl font-black uppercase tracking-widest hover:bg-sky-500 hover:text-white transition-all border border-sky-500/20">Sync Channel</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Map Modal Interface -->
<div id="map-modal" class="fixed inset-0 z-[1000] hidden flex items-center justify-center p-6 md:p-12">
    <div class="absolute inset-0 bg-slate-950/95 backdrop-blur-xl" onclick="toggleMapModal()"></div>
    <div class="relative w-full h-full glass rounded-[3rem] border border-white/10 overflow-hidden shadow-2xl animate-in zoom-in duration-300">
        <div id="modal-map" class="w-full h-full"></div>
        <button onclick="toggleMapModal()" class="absolute top-8 right-8 p-4 bg-indigo-600 rounded-2xl text-white shadow-2xl z-[1100] hover:scale-110 transition-transform">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
        <div class="absolute bottom-8 left-1/2 -translate-x-1/2 glass px-8 py-4 rounded-2xl border border-white/10 text-white font-black text-xs uppercase tracking-widest z-[1100] text-center pointer-events-none">
            Drop pin to select destination
        </div>
    </div>
</div>

<!-- Telegram Help Modal -->
<div id="help-modal" class="fixed inset-0 z-[1200] hidden flex items-center justify-center p-6">
    <div class="absolute inset-0 bg-slate-950/90 backdrop-blur-md" onclick="toggleHelpModal()"></div>
    <div class="relative max-w-sm w-full glass p-10 rounded-[3rem] border border-white/10 shadow-2xl scale-in-center overflow-hidden">
        <div class="absolute -top-12 -right-12 w-32 h-32 bg-sky-500/10 rounded-full blur-3xl"></div>
        <h3 class="text-xl font-black text-white mb-6 uppercase tracking-tighter">Sync Telegram</h3>
        <ul class="space-y-6">
            <li class="flex gap-4">
                <span class="flex-shrink-0 w-6 h-6 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center text-[10px] font-black">01</span>
                <p class="text-slate-400 text-xs font-medium leading-relaxed">Search for <span class="text-white font-bold">@userinfobot</span> inside Telegram.</p>
            </li>
            <li class="flex gap-4">
                <span class="flex-shrink-0 w-6 h-6 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center text-[10px] font-black">02</span>
                <p class="text-slate-400 text-xs font-medium leading-relaxed">Press the <span class="text-white font-bold">/start</span> button to awaken the bot.</p>
            </li>
            <li class="flex gap-4">
                <span class="flex-shrink-0 w-6 h-6 rounded-lg bg-sky-500/20 text-sky-400 flex items-center justify-center text-[10px] font-black">03</span>
                <p class="text-slate-400 text-xs font-medium leading-relaxed">Copy the <span class="text-white font-bold">Id number</span> provided and paste it into the Config field.</p>
            </li>
        </ul>
        <button onclick="toggleHelpModal()" class="w-full mt-10 py-4 bg-white/5 hover:bg-white/10 text-white rounded-2xl font-black text-[10px] uppercase tracking-[0.2em] transition-all border border-white/10">
            Acknowledge
        </button>
    </div>
</div>

<script>
    const map = L.map('dest-map', { zoomControl: false, attributionControl: false }).setView([14.5995, 120.9842], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    let marker;
    map.on('click', function(e) { setMarker(e.latlng.lat, e.latlng.lng); });

    function setMarker(lat, lng) {
        if (marker) marker.setLatLng([lat, lng]);
        else marker = L.marker([lat, lng]).addTo(map);
        document.getElementById('dest_lat').value = lat;
        document.getElementById('dest_lng').value = lng;
    }

    async function searchLocation() {
        const query = document.getElementById('loc-search').value;
        if (!query) return;
        const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}`);
        const data = await response.json();
        if (data.length > 0) {
            const first = data[0]; setMarker(parseFloat(first.lat), parseFloat(first.lon));
            map.setView([parseFloat(first.lat), parseFloat(first.lon)], 15);
        }
    }

    let modalMap;
    let modalMarker;

    function toggleMapModal() {
        const modal = document.getElementById('map-modal');
        const isHidden = modal.classList.contains('hidden');
        
        if (isHidden) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
            
            if (!modalMap) {
                modalMap = L.map('modal-map', { zoomControl: false, attributionControl: false }).setView(map.getCenter(), map.getZoom());
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(modalMap);
                
                modalMap.on('click', function(e) {
                    setMarker(e.latlng.lat, e.latlng.lng);
                    if (modalMarker) modalMarker.setLatLng(e.latlng);
                    else modalMarker = L.marker(e.latlng).addTo(modalMap);
                });
            } else {
                modalMap.setView(map.getCenter(), map.getZoom());
                if (marker) {
                    if (modalMarker) modalMarker.setLatLng(marker.getLatLng());
                    else modalMarker = L.marker(marker.getLatLng()).addTo(modalMap);
                }
            }
            setTimeout(() => modalMap.invalidateSize(), 300);
        } else {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
            // Sync back to sidebar map
            if (modalMarker) {
                const pos = modalMarker.getLatLng();
                setMarker(pos.lat, pos.lng);
                map.setView(pos, 15);
            }
        }
    }

    function toggleHelpModal() {
        const modal = document.getElementById('help-modal');
        const isHidden = modal.classList.contains('hidden');
        if (isHidden) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        } else {
            modal.classList.add('hidden');
            if(!document.querySelector('#map-modal:not(.hidden)')) document.body.style.overflow = '';
        }
    }

    if ("geolocation" in navigator) {
        navigator.geolocation.getCurrentPosition(function(position) {
            map.setView([position.coords.latitude, position.coords.longitude], 13);
            fetch('api/update_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ride_id: 0, lat: position.coords.latitude, lng: position.coords.longitude })
            });
        });
    }
</script>

<?php require_once 'includes/footer.php'; ?>
