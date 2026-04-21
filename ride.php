<?php
session_start();
require_once 'config/db.php';

if (!isset($_GET['id']) || !isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$ride_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Get ride details
$stmt = $pdo->prepare("SELECT r.*, u.username as creator_name FROM rides r 
                    JOIN users u ON r.creator_id = u.id 
                    WHERE r.id = ?");
$stmt->execute([$ride_id]);
$ride = $stmt->fetch();

if (!$ride) {
    header('Location: dashboard.php');
    exit;
}

// Mark ride as active if creator starts it (for demo, any access by creator sets it active)
if ($ride['creator_id'] == $user_id && $ride['status'] == 'planned') {
    $pdo->prepare("UPDATE rides SET status = 'active' WHERE id = ?")->execute([$ride_id]);
}

require_once 'includes/header.php';
?>
<script src="https://unpkg.com/peerjs@1.5.2/dist/peerjs.min.js"></script>


<div class="fixed inset-0 top-20 z-0">
    <div id="live-map" class="h-full w-full"></div>
    
    <!-- Floating Navigation Control -->
    <div id="info-card" class="absolute top-6 left-6 right-6 md:left-10 md:right-auto md:w-96 z-[1000] glass p-8 rounded-[2.5rem] text-white shadow-2xl border border-white/10 transition-all duration-500 overflow-hidden max-h-[85vh]">
        
        <div class="flex items-center justify-between mb-8">
            <div class="flex items-center gap-4">
                <a href="dashboard.php" class="p-3 bg-white/5 rounded-2xl hover:bg-white/10 transition-all border border-white/10">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                </a>
                <div>
                    <h1 class="font-extrabold text-xl leading-tight truncate w-32 md:w-auto"><?php echo htmlspecialchars($ride['title']); ?></h1>
                    <p class="text-xs text-indigo-400 font-bold tracking-widest uppercase">Lead: <?php echo htmlspecialchars($ride['creator_name']); ?></p>
                </div>
            </div>
            <button onclick="toggleInfo()" class="p-2 hover:bg-white/5 rounded-xl transition-all">
                <svg id="toggle-icon" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </div>
        
        <div id="collapsible-content">
            <!-- Stats -->
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div class="bg-white/5 p-4 rounded-3xl border border-white/5 relative overflow-hidden">
                    <p class="text-[9px] text-slate-500 font-bold uppercase mb-1 tracking-widest">To Goal</p>
                    <p id="dist-val" class="text-xl font-bold text-indigo-400">-- km</p>
                </div>
                <div class="bg-white/5 p-4 rounded-3xl border border-white/5">
                    <p class="text-[9px] text-slate-500 font-bold uppercase mb-1 tracking-widest">Velocity</p>
                    <p id="speed-val" class="text-xl font-bold text-white">0 <span class="text-[10px] text-slate-500">km/h</span></p>
                </div>
            </div>

            <!-- Environmental Intelligence -->
            <div id="weather-widget" class="bg-indigo-600/10 p-4 rounded-3xl border border-indigo-500/20 mb-8 flex items-center justify-between transition-all opacity-0">
                <div class="flex items-center gap-3">
                    <div id="weather-icon" class="text-2xl">🌦️</div>
                    <div>
                        <p id="weather-desc" class="text-[10px] text-white font-bold uppercase tracking-widest">Fetching Weather...</p>
                        <p id="weather-temp" class="text-[8px] text-indigo-400 font-black uppercase">Scanning Atmosphere</p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-[8px] text-slate-500 font-bold uppercase tracking-tighter">Conditions</p>
                    <p id="visibility-val" class="text-[10px] text-white font-black">--</p>
                </div>
            </div>

            <div class="space-y-4 mb-4">
                <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest pl-1">Choose Transport Mode</p>
                <div class="flex gap-2 p-1 bg-white/5 border border-white/10 rounded-3xl">
                    <button onclick="setVehicle('bicycle')" id="v-bicycle" class="flex-1 py-3 rounded-2xl flex flex-col items-center gap-1 transition-all">
                        <span class="text-xl">🚲</span>
                        <span class="text-[7px] font-black uppercase tracking-tighter text-slate-400">Cyclist</span>
                    </button>
                    <button onclick="setVehicle('motorcycle')" id="v-motorcycle" class="flex-1 py-3 rounded-2xl bg-indigo-600 flex flex-col items-center gap-1 transition-all shadow-xl shadow-indigo-500/20">
                        <span class="text-xl">🏍️</span>
                        <span class="text-[7px] font-black uppercase tracking-tighter text-white">Rider</span>
                    </button>
                    <button onclick="setVehicle('car')" id="v-car" class="flex-1 py-3 rounded-2xl flex flex-col items-center gap-1 transition-all">
                        <span class="text-xl">🚗</span>
                        <span class="text-[7px] font-black uppercase tracking-tighter text-slate-400">Driver</span>
                    </button>
                </div>
            </div>

            <div class="space-y-4 mb-8">
                <button id="on-the-way-btn" onclick="startRouting()" class="btn-premium w-full py-4 rounded-2xl font-extrabold text-sm uppercase tracking-widest">
                    Start Navigation
                </button>

                <?php if($ride['creator_id'] == $user_id): ?>
                    <button onclick="finishRide()" class="w-full py-4 bg-red-500/10 text-red-500 border border-red-500/20 rounded-2xl font-bold text-xs uppercase tracking-widest hover:bg-red-500 hover:text-white transition-all">
                        Terminate Session
                    </button>
                <?php endif; ?>
            </div>

            <h2 class="text-[10px] text-slate-500 font-bold uppercase tracking-widest mb-4 border-b border-white/5 pb-2">Riders in Range (<span id="participant-count">0</span>)</h2>
            <div id="riders-list" class="space-y-4 max-h-60 overflow-y-auto pr-2 custom-scroll">
                <!-- Dynamic List -->
            </div>

            <div class="mt-8 pt-6 border-t border-white/5 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 bg-emerald-500 rounded-full animate-pulse shadow-lg shadow-emerald-500/50"></div>
                    <span class="text-[10px] text-slate-400 font-bold uppercase tracking-widest">Live Signal Strong</span>
                </div>
                <button onclick="centerMap()" class="text-[10px] font-bold text-indigo-400 hover:text-indigo-300 uppercase tracking-widest">Recenter Map</button>
            </div>
        </div>
    </div>

    <!-- SOS Button -->
    <div class="absolute top-6 right-6 md:top-10 md:right-10 z-[1000]">
        <button onclick="triggerSOS()" class="w-14 h-14 md:w-16 md:h-16 bg-red-600 rounded-full flex items-center justify-center shadow-2xl shadow-red-500/50 border-4 border-white/20 hover:scale-110 active:scale-95 transition-all animate-pulse">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 md:h-8 md:w-8 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
        </button>
    </div>

    <!-- Map style controls -->
    <div class="absolute bottom-28 right-6 md:bottom-32 md:right-10 z-[1000] flex flex-col gap-4">
        <button id="rotation-toggle" onclick="toggleRotation()" class="w-12 h-12 md:w-14 md:h-14 glass flex items-center justify-center rounded-2xl hover:scale-110 transition-all border border-white/10 group">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
            </svg>
        </button>
        <button onclick="switchLayer('street')" class="w-12 h-12 md:w-14 md:h-14 glass flex items-center justify-center rounded-2xl hover:scale-110 transition-all border border-white/10 group">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
        </button>
        <button onclick="switchLayer('satellite')" class="w-12 h-12 md:w-14 md:h-14 bg-indigo-600 flex items-center justify-center rounded-2xl shadow-xl shadow-indigo-500/30 hover:scale-110 transition-all border border-white/20">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 002 2 2 2 0 012 2v.654M15 15.5l1.5-1.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </button>
    </div>

    <!-- Tactical Comms (Chat) Overlay -->
    <div id="chat-overlay" class="absolute bottom-6 right-6 md:bottom-10 md:right-10 z-[1100]">
        <div id="chat-window" class="hidden w-[85vw] md:w-80 glass rounded-[2.5rem] border border-white/10 mb-4 flex flex-col shadow-2xl overflow-hidden max-h-[60vh] animate-in slide-in-from-bottom duration-300">
            <div class="px-6 py-4 border-b border-white/10 flex items-center justify-between bg-white/5">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Tactical Comms</span>
                <div class="flex items-center gap-2">
                    <button id="broadcast-btn" onclick="toggleBroadcast()" class="px-2 py-1 bg-white/5 hover:bg-red-500 rounded-full text-[7px] font-black uppercase transition-all flex items-center gap-1 border border-white/10">
                        <span id="broadcast-dot" class="w-1.5 h-1.5 bg-slate-500 rounded-full"></span>
                        LIVE
                    </button>
                    <div class="w-1.5 h-1.5 bg-emerald-500 rounded-full animate-pulse"></div>
                </div>
            </div>
            <div id="video-grid" class="flex gap-2 p-2 empty:hidden bg-indigo-600/10 border-b border-indigo-500/20 overflow-x-auto min-h-0">
                <!-- Remote videos -->
            </div>

            <div id="messages-container" class="flex-grow overflow-y-auto p-6 space-y-4 custom-scroll h-64">
                <!-- Messages dynamic -->
                <p class="text-[9px] text-slate-500 text-center uppercase tracking-widest py-10">Starting Secure Channel...</p>
            </div>
            <div class="p-4 border-t border-white/10 bg-black/20">
                <form onsubmit="sendMessage(event)" class="relative">
                    <input type="text" id="chat-input" placeholder="Type command..." class="w-full bg-white/5 border border-white/10 rounded-2xl pl-4 pr-12 py-3 text-white text-xs outline-none focus:ring-1 focus:ring-indigo-500">
                    <button type="submit" class="absolute right-2 top-1.5 p-2 text-indigo-400 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                        </svg>
                    </button>
                </form>
            </div>
        </div>
        <button onclick="toggleChat()" class="float-right w-14 h-14 md:w-16 md:h-16 bg-slate-900/80 glass rounded-full flex items-center justify-center border border-white/10 shadow-2xl hover:bg-slate-800 transition-all group">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-slate-400 group-hover:text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
            </svg>
            <div id="chat-badge" class="hidden absolute top-0 right-0 w-4 h-4 bg-indigo-500 rounded-full border-2 border-slate-900 text-[8px] font-black text-white flex items-center justify-center">!</div>
        </button>
    </div>
</div>

<script src="https://unpkg.com/leaflet-rotate@0.2.8/dist/leaflet-rotate.js"></script>
<script>
    const rideId = <?php echo $ride_id; ?>;
    const userId = <?php echo $user_id; ?>;
    const destLat = <?php echo $ride['dest_lat']; ?>;
    const destLng = <?php echo $ride['dest_lng']; ?>;
    
    let map, userMarker, markers = {}, pathlines = {}, routingControl;
    let lastPosition = null;
    let currentVehicle = 'motorcycle';
    let currentHeading = 0;
    let isCourseUp = false;
    let lastSpokenInstructionIndex = -1;
    let currentInstructions = [];

    const layers = {
        street: L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }),
        satellite: L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}')
    };

    function initMap() {
        map = L.map('live-map', { 
            zoomControl: false,
            rotate: true,
            touchRotate: true
        }).setView([destLat, destLng], 14);
        layers.street.addTo(map);
        
        // Add dark mode class initially for street map
        document.getElementById('live-map').classList.add('layer-dark');

        map.on('zoomstart', () => document.body.classList.add('map-zooming'));
        map.on('zoomend', () => document.body.classList.remove('map-zooming'));

        // Destination Marker
        const destIcon = L.divIcon({
            html: `<div class="text-4xl filter drop-shadow-[0_10px_10px_rgba(0,0,0,0.5)] animate-bounce counter-rotate">
                    📍
                   </div>`,
            className: '', iconSize: [40, 40], iconAnchor: [20, 40]
        });
        L.marker([destLat, destLng], {icon: destIcon}).addTo(map);

        if (navigator.geolocation) {
            navigator.geolocation.watchPosition(updateUserLocation, handleError, {
                enableHighAccuracy: true,
                maximumAge: 3000
            });
        }
        
        setInterval(fetchRidersLocations, 3000);
    }

    function updateUserLocation(position) {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        
        // Calculate or get heading
        if (position.coords.heading !== null && position.coords.heading !== undefined) {
            currentHeading = position.coords.heading;
        } else if (lastPosition) {
            const newHeading = calculateHeading(lastPosition[0], lastPosition[1], lat, lng);
            if (newHeading !== null) currentHeading = newHeading;
        }

        lastPosition = [lat, lng];

        // Update Rotation IF Course Up enabled
        if (isCourseUp) {
            const bearing = 360 - currentHeading;
            map.setBearing(bearing);
            document.documentElement.style.setProperty('--map-rotation', bearing + 'deg');
        } else {
            document.documentElement.style.setProperty('--map-rotation', '0deg');
        }

        // Update Speedometer
        if (position.coords.speed !== null) {
            const speedKmH = Math.round(position.coords.speed * 3.6);
            document.getElementById('speed-val').innerHTML = `${speedKmH} <span class="text-[10px] text-slate-500">km/h</span>`;
        }

        // Fetch Weather if not already fetched this session
        if (!window.weatherLoaded) fetchWeather(lat, lng);

        // Auto Night Mode check
        checkNightMode();

        if (!userMarker) {
            const userIcon = L.divIcon({
                html: `<div id="user-icon-wrapper" class="relative group transition-transform duration-500 flex justify-center items-center">
                        <div class="absolute w-12 h-12 bg-indigo-500 rounded-full animate-ping opacity-25"></div>
                        <div class="text-[40px] filter drop-shadow-[0_5px_15px_rgba(0,0,0,0.6)] select-none">
                            <span id="user-emoji-icon" class="leading-none">🏍️</span>
                        </div>
                       </div>`,
                className: '', iconSize: [48, 48], iconAnchor: [24, 24]
            });
            userMarker = L.marker([lat, lng], {icon: userIcon, zIndexOffset: 1000}).addTo(map);
            map.panTo([lat, lng]);
        } else {
            userMarker.setLatLng([lat, lng]);
        }

        // Update user marker rotation based on heading (relative to screen)
        // If course up is on, marker should point up (0 deg)
        // If north up is on, marker should point to heading
        const markerWrapper = document.getElementById('user-icon-wrapper');
        if (markerWrapper) {
            const rotation = isCourseUp ? 0 : currentHeading;
            markerWrapper.style.transform = `rotate(${rotation}deg)`;
        }

        fetch('api/update_location.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ride_id: rideId, lat: lat, lng: lng, vehicle: currentVehicle })
        });

        updateDistance(lat, lng);
        checkNavigationSpeech(lat, lng);
    }

    function calculateHeading(lat1, lon1, lat2, lon2) {
        if (lat1 === lat2 && lon1 === lon2) return null;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const y = Math.sin(dLon) * Math.cos(lat2 * Math.PI / 180);
        const x = Math.cos(lat1 * Math.PI / 180) * Math.sin(lat2 * Math.PI / 180) -
                  Math.sin(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.cos(dLon);
        return (Math.atan2(y, x) * 180 / Math.PI + 360) % 360;
    }

    function toggleRotation() {
        isCourseUp = !isCourseUp;
        const btn = document.getElementById('rotation-toggle');
        const icon = btn.querySelector('svg');
        if (isCourseUp) {
            btn.style.backgroundColor = '#6366f1';
            icon.style.color = 'white';
            if (currentHeading) {
                const bearing = 360 - currentHeading;
                map.setBearing(bearing);
                document.documentElement.style.setProperty('--map-rotation', bearing + 'deg');
            }
        } else {
            btn.style.backgroundColor = '';
            icon.style.color = '';
            map.setBearing(0);
            document.documentElement.style.setProperty('--map-rotation', '0deg');
        }
    }

    function setVehicle(type) {
        currentVehicle = type;
        const vehicles = ['bicycle', 'motorcycle', 'car'];
        vehicles.forEach(v => {
            const btn = document.getElementById(`v-${v}`);
            const text = btn.querySelector('span:last-child');
            if (v === type) {
                btn.style.backgroundColor = '#6366f1';
                btn.classList.add('shadow-xl', 'shadow-indigo-500/20');
                text.style.color = 'white';
            } else {
                btn.style.backgroundColor = '';
                btn.classList.remove('shadow-xl', 'shadow-indigo-500/20');
                text.style.color = '';
            }
        });
        
        // Update user marker icon immediately
        const emojiMap = { bicycle: '🚲', motorcycle: '🏍️', car: '🚗' };
        const iconHtml = document.getElementById('user-emoji-icon');
        if (iconHtml) iconHtml.innerText = emojiMap[type];
        
        if (lastPosition) {
            fetch('api/update_location.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ride_id: rideId, lat: lastPosition[0], lng: lastPosition[1], vehicle: currentVehicle })
            });
        }
    }

    async function fetchWeather(lat, lng) {
        try {
            const apiKey = 'bd5e378503939ddaee76f12ad7a97608'; // Shared demo key
            const resp = await fetch(`https://api.openweathermap.org/data/2.5/weather?lat=${lat}&lon=${lng}&units=metric&appid=${apiKey}`);
            const data = await resp.json();
            
            if (data.main) {
                document.getElementById('weather-widget').style.opacity = '1';
                document.getElementById('weather-temp').innerText = `${Math.round(data.main.temp)}°C | Humidity ${data.main.humidity}%`;
                document.getElementById('weather-desc').innerText = data.weather[0].description;
                document.getElementById('visibility-val').innerText = `${(data.visibility / 1000).toFixed(1)} km`;
                
                const code = data.weather[0].icon;
                const emojiMap = { '01': '☀️', '02': '⛅', '03': '☁️', '04': '☁️', '09': '🌧️', '10': '🌦️', '11': '⛈️', '13': '❄️', '50': '🌫️' };
                document.getElementById('weather-icon').innerText = emojiMap[code.substring(0,2)] || '🌡️';
                window.weatherLoaded = true;
            }
        } catch(e) {}
    }

    function checkNightMode() {
        const hour = new Date().getHours();
        const isNight = hour < 6 || hour > 18;
        if (isNight && map.hasLayer(layers.street)) {
            document.getElementById('live-map').classList.add('layer-dark');
        }
    }

    function fetchRidersLocations() {
        fetch(`api/get_riders_locations.php?ride_id=${rideId}`)
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    updateRidersOnMap(data.locations || [], data.pathways || {});
                }
            });
    }

    function updateRidersOnMap(riders, pathways) {
        if (!Array.isArray(riders)) return;
        document.getElementById('participant-count').innerText = riders.length;
        const listEl = document.getElementById('riders-list');
        listEl.innerHTML = '';

        riders.forEach(loc => {
            const emojiMap = { bicycle: '🚲', motorcycle: '🏍️', car: '🚗' };
            const currentEmoji = emojiMap[loc.vehicle_type] || '🏍️';
            const isOnline = loc.is_online == 1;
            
            const riderRow = `<div onclick="map.flyTo([${loc.lat || 0}, ${loc.lng || 0}], 16)" class="flex items-center gap-3 p-3 bg-white/5 rounded-2xl border border-white/5 cursor-pointer hover:bg-white/10 transition-colors group ${!isOnline ? 'opacity-60' : ''}">
                <div class="relative">
                    <div class="w-10 h-10 rounded-full bg-indigo-500/20 flex items-center justify-center text-lg border border-indigo-500/20 group-hover:scale-110 transition-transform">
                        ${currentEmoji}
                    </div>
                    ${isOnline ? '<div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-emerald-500 rounded-full border-2 border-slate-900 animate-pulse"></div>' : '<div class="absolute -bottom-0.5 -right-0.5 w-3 h-3 bg-slate-500 rounded-full border-2 border-slate-900"></div>'}
                </div>
                <div class="flex-grow">
                   <p class="text-sm font-bold text-white">${loc.username}</p>
                   <p class="text-[9px] text-slate-500 uppercase tracking-widest">${isOnline ? (loc.vehicle_type || 'Active') : 'Offline'}</p>
                </div>
                <div class="text-indigo-500 opacity-0 group-hover:opacity-100 transition-opacity">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
            </div>`;
            listEl.innerHTML += riderRow;

            if (loc.user_id != userId && loc.lat && loc.lng) {
                if (!markers[loc.user_id]) {
                    const riderIcon = L.divIcon({
                        html: `<div id="rider-wrap-${loc.user_id}" class="relative transition-all duration-[3000ms] linear flex justify-center items-center counter-rotate ${!isOnline ? 'grayscale-[0.8]' : ''}">
                                <div class="text-[32px] filter drop-shadow-[0_5px_10px_rgba(0,0,0,0.5)] select-none">
                                    ${currentEmoji}
                                </div>
                                ${!isOnline ? '<div class="absolute top-0 right-0 text-[10px] bg-slate-800/80 px-1 rounded text-white font-bold opacity-50">OFF</div>' : ''}
                               </div>`,
                        className: '', iconSize: [40, 40], iconAnchor: [20, 20]
                    });
                    markers[loc.user_id] = L.marker([loc.lat, loc.lng], {icon: riderIcon})
                        .bindPopup(`<div class="text-center w-32 pb-1">
                                      <p class="font-extrabold text-slate-800 text-sm break-words">${loc.username}</p>
                                      <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest mt-0.5">${isOnline ? (loc.vehicle_type || 'Cruising') : 'Last seen'}</p>
                                    </div>`, {className: 'glass-popup', closeButton: false})
                        .addTo(map);
                } else {
                    const iconWrapper = document.getElementById(`rider-wrap-${loc.user_id}`);
                    if (iconWrapper) {
                        const iconInner = iconWrapper.querySelector('div.text-\\[32px\\]');
                        if (iconInner) iconInner.innerHTML = currentEmoji;
                        if (!isOnline) iconWrapper.classList.add('grayscale-[0.8]');
                        else iconWrapper.classList.remove('grayscale-[0.8]');
                    }
                    markers[loc.user_id].setLatLng([loc.lat, loc.lng]);
                }
            }

            // Update Pathway (Polyline)
            if (pathways[loc.user_id]) {
                const points = pathways[loc.user_id];
                if (!pathlines[loc.user_id]) {
                    pathlines[loc.user_id] = L.polyline(points, {
                        color: loc.user_id == userId ? '#6366f1' : '#10b981', // Indigo for self, Emerald for others
                        weight: 4,
                        opacity: 0.4,
                        dashArray: '10, 10',
                        lineCap: 'round'
                    }).addTo(map);
                } else {
                    pathlines[loc.user_id].setLatLngs(points);
                }
            }
        });
    }

    function updateDistance(lat, lng) {
        const dist = map.distance([lat, lng], [destLat, destLng]) / 1000;
        document.getElementById('dist-val').innerText = dist.toFixed(2) + " km";
    }

    function checkNavigationSpeech(lat, lng) {
        if (!routingControl || currentInstructions.length === 0) return;
        for (let i = lastSpokenInstructionIndex + 1; i < currentInstructions.length; i++) {
            const instr = currentInstructions[i];
            const dist = map.distance([lat, lng], [instr.latLng.lat, instr.latLng.lng]);
            if (dist < 50) {
                speak(instr.text);
                lastSpokenInstructionIndex = i;
                break;
            }
        }
    }

    function speak(text) {
        if (!window.speechSynthesis) return;
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.rate = 0.9;
        window.speechSynthesis.speak(utterance);
    }

    function switchLayer(style) {
        const mapContainer = document.getElementById('live-map');
        Object.values(layers).forEach(layer => map.removeLayer(layer));
        layers[style].addTo(map);
        
        if (style === 'street') {
            mapContainer.classList.add('layer-dark');
        } else {
            mapContainer.classList.remove('layer-dark');
        }
    }

    function centerMap() {
        if (lastPosition) map.setView(lastPosition, 16);
    }

    function startRouting() {
        if (!lastPosition) return alert("Waiting for GPS...");
        if (routingControl) {
            map.removeControl(routingControl);
            routingControl = null;
            document.getElementById('on-the-way-btn').innerText = "Start Navigation";
            switchLayer('street');
            return;
        }
        switchLayer('satellite');
        routingControl = L.Routing.control({
            waypoints: [L.latLng(lastPosition[0], lastPosition[1]), L.latLng(destLat, destLng)],
            routeWhileDragging: false, showAlternatives: false,
            createMarker: () => null,
            lineOptions: { styles: [{ color: '#6366f1', weight: 8, opacity: 0.8 }] }
        }).on('routesfound', e => {
            currentInstructions = e.routes[0].instructions;
            speak("Initiating guidance. Destination locked.");
        }).addTo(map);
        document.getElementById('on-the-way-btn').innerText = "Stop Navigation";
    }

    function finishRide() {
        if (confirm("Terminate ride for everyone?")) {
            fetch('api/finish_ride.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ride_id: rideId })
            }).then(() => window.location.href = 'dashboard.php');
        }
    }

    function toggleInfo() {
        const content = document.getElementById('collapsible-content');
        const icon = document.getElementById('toggle-icon');
        const isCollapsed = content.style.display === 'none';
        content.style.display = isCollapsed ? 'block' : 'none';
        icon.style.transform = isCollapsed ? 'rotate(0deg)' : 'rotate(180deg)';
    }

    // Tactical Comms Logic
    let lastMsgId = 0;
    function toggleChat() {
        const win = document.getElementById('chat-window');
        const badge = document.getElementById('chat-badge');
        win.classList.toggle('hidden');
        badge.classList.add('hidden');
        if (!win.classList.contains('hidden')) {
            scrollToBottom();
        }
    }

    async function sendMessage(e) {
        e.preventDefault();
        const input = document.getElementById('chat-input');
        const msg = input.value.trim();
        if (!msg) return;

        input.value = '';
        await fetch('api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ride_id: rideId, message: msg, type: 'chat' })
        });
        pollMessages();
    }

    async function triggerSOS() {
        if (!confirm("🚨 ACTIVATE EMERGENCY SOS?\nThis will alert all crew members and friends!")) return;
        
        await fetch('api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                ride_id: rideId, 
                message: `🆘 EMERGENCY: Rider needs help at [${lastPosition[0].toFixed(5)}, ${lastPosition[1].toFixed(5)}]`, 
                type: 'sos' 
            })
        });
        speak("SOS Activated. Emergency pings sent to crew.");
        pollMessages();
    }

    async function pollMessages() {
        if (window.pollingActive) return;
        window.pollingActive = true;
        try {
            const resp = await fetch(`api/chat.php?ride_id=${rideId}&last_id=${lastMsgId}`);
            const text = await resp.text();
            
            let messages;
            try {
                messages = JSON.parse(text);
            } catch (e) {
                console.warn("Server returned invalid JSON:", text);
                return;
            }
            
            const container = document.getElementById('messages-container');
            
            if (lastMsgId === 0 && (!messages || messages.length === 0)) {
               container.innerHTML = '<p id="chat-stub" class="text-[9px] text-slate-500 text-center uppercase tracking-widest py-10 italic">Channel active. Type to broadcast.</p>';
               lastMsgId = -1; 
               return;
            }

            if (Array.isArray(messages) && messages.length > 0) {
                const wasAtBottom = container.scrollHeight - container.scrollTop <= container.clientHeight + 100;
                
                if (lastMsgId <= 0) {
                    container.innerHTML = '';
                    lastMsgId = messages[0].id - 1; 
                }
                
                messages.forEach(m => {
                    const id = parseInt(m.id);
                    if (id <= lastMsgId) return; // Skip duplicates
                    
                    const isMe = m.user_id == userId;
                    const div = document.createElement('div');
                    div.className = `flex flex-col ${isMe ? 'items-end' : 'items-start'}`;
                    
                    const innerClass = m.type === 'sos' ? 'bg-red-500 text-white animate-pulse' : (isMe ? 'bg-indigo-600 text-white' : 'bg-white/10 text-slate-200');
                    
                    div.innerHTML = `
                        <span class="text-[8px] font-black uppercase tracking-widest text-slate-500 mb-1 px-1">${m.username || 'Rider'}</span>
                        <div class="px-4 py-2.5 rounded-2xl text-[11px] font-medium shadow-xl border border-white/5 max-w-[80%] ${innerClass}">
                            ${m.message}
                        </div>
                    `;
                    container.appendChild(div);
                    lastMsgId = Math.max(lastMsgId, id);
                });

                if (!document.getElementById('chat-window').classList.contains('hidden')) {
                    if (wasAtBottom) scrollToBottom();
                } else {
                    document.getElementById('chat-badge').classList.remove('hidden');
                }
            }
        } catch(e) { 
            console.warn("Chat sync error:", e);
        } finally {
            window.pollingActive = false;
        }
    }

    function toggleInfo() {
        const content = document.getElementById('collapsible-content');
        const icon = document.getElementById('toggle-icon');
        const isCollapsed = content.style.display === 'none';
        content.style.display = isCollapsed ? 'block' : 'none';
        icon.style.transform = isCollapsed ? 'rotate(180deg)' : 'rotate(0deg)';
    }

    function toggleChat() {
        const win = document.getElementById('chat-window');
        const badge = document.getElementById('chat-badge');
        win.classList.toggle('hidden');
        badge.classList.add('hidden');
        if (!win.classList.contains('hidden')) {
            scrollToBottom();
        }
    }

    async function sendMessage(e, directMsg = null) {
        if (e) e.preventDefault();
        const input = document.getElementById('chat-input');
        const msg = directMsg || input.value.trim();
        if (!msg) return;

        if (!directMsg) input.value = '';

        try {
            await fetch('api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ride_id: rideId, message: msg, type: 'chat' })
            });
            pollMessages();
        } catch(e) {}
    }

    async function triggerSOS() {
        if (!confirm("🚨 ACTIVATE EMERGENCY SOS?\nThis will alert all crew members!")) return;
        
        try {
            await fetch('api/chat.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    ride_id: rideId, 
                    message: `🆘 EMERGENCY: Rider needs help at [${lastPosition ? lastPosition[0].toFixed(5) : '?'}, ${lastPosition ? lastPosition[1].toFixed(5) : '?'}]`, 
                    type: 'sos' 
                })
            });
            if (window.speechSynthesis) {
                const utterance = new SpeechSynthesisUtterance("SOS Activated. Emergency pings sent to crew.");
                window.speechSynthesis.speak(utterance);
            }
            pollMessages();
        } catch(e) {}
    }

    // Call poll once on init
    pollMessages();

    function scrollToBottom() {
        const container = document.getElementById('messages-container');
        container.scrollTop = container.scrollHeight;
    }

    setInterval(pollMessages, 3000);

    function handleError(error) { console.error(error); }

    // --- TACTICAL LIVE VIDEO (PEERJS) ---
    let peer = null;
    let localStream = null;
    let broadcasting = false;
    let peerConnections = {};

    function initVideo() {
        const peerId = `rt_${rideId}_u${userId}`;
        peer = new Peer(peerId);
        
        peer.on('call', (call) => {
            console.log("Incoming tactical feed...");
            call.answer(null); 
            call.on('stream', (remoteStream) => {
                showRemoteVideo(call.peer, remoteStream);
            });
        });
    }

    async function toggleBroadcast() {
        const btn = document.getElementById('broadcast-btn');
        const dot = document.getElementById('broadcast-dot');
        
        if (!broadcasting) {
            try {
                localStream = await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                broadcasting = true;
                btn.classList.add('bg-red-500', 'text-white');
                btn.innerHTML = `HIDE LIVE`;
                dot.classList.remove('bg-slate-500');
                dot.classList.add('bg-white', 'animate-ping');
                
                showRemoteVideo('me', localStream, true);
                
                sendMessage({ preventDefault: () => {} }, "📡 **TACTICAL FEED ACTIVE**");

            } catch (err) {
                alert("Camera access denied.");
            }
        } else {
            stopBroadcast();
        }
    }

    function stopBroadcast() {
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
            localStream = null;
        }
        broadcasting = false;
        const btn = document.getElementById('broadcast-btn');
        btn.classList.remove('bg-red-500', 'text-white');
        btn.innerHTML = `<span id="broadcast-dot" class="w-1.5 h-1.5 bg-slate-500 rounded-full"></span> LIVE`;
        const myVid = document.getElementById('vid-me');
        if (myVid) myVid.remove();
    }

    function showRemoteVideo(id, stream, isMe = false) {
        let videoId = `vid-${id}`;
        if (document.getElementById(videoId)) return;
        
        const grid = document.getElementById('video-grid');
        const container = document.createElement('div');
        container.id = videoId;
        container.className = "flex-shrink-0 relative w-24 h-32 bg-slate-800 rounded-xl overflow-hidden shadow-xl border border-white/10";
        
        const video = document.createElement('video');
        video.srcObject = stream;
        video.autoplay = true;
        video.playsInline = true;
        video.muted = isMe;
        video.className = "w-full h-full object-cover";
        
        const label = document.createElement('span');
        label.className = "absolute bottom-1 left-1 px-1 bg-black/50 text-[6px] text-white font-bold rounded uppercase";
        label.innerText = isMe ? "ME" : "CREW";
        
        container.appendChild(video);
        container.appendChild(label);
        grid.prepend(container);
    }

    initVideo();

    window.onload = initMap;

</script>

<style>
    .leaflet-routing-container { display: none !important; }
    #riders-list::-webkit-scrollbar { width: 4px; }
    #riders-list::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, 0.1); border-radius: 10px; }
    .leaflet-marker-icon { transition: transform 3s linear !important; }
    .map-zooming .leaflet-marker-icon { transition: none !important; }
    .counter-rotate { transform: rotate(calc(-1 * var(--map-rotation, 0deg))); transition: transform 0.1s linear; }
    .map-zooming .counter-rotate { transition: none; }
    
    .glass-popup .leaflet-popup-content-wrapper {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border: 2px solid rgba(99, 102, 241, 0.2);
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        border-radius: 20px;
        color: #0f172a;
    }
    .glass-popup .leaflet-popup-tip {
        background: rgba(255, 255, 255, 0.95);
        border-right: 2px solid rgba(99, 102, 241, 0.2);
        border-bottom: 2px solid rgba(99, 102, 241, 0.2);
    }
</style>

<?php require_once 'includes/footer.php'; ?>
