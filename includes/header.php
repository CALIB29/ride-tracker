<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RideTracker - Live Group Ride Tracking</title>
    <link rel="manifest" href="manifest.json">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#0f172a">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-deep: #0f172a;
            --accent: #6366f1;
            --glass: rgba(30, 41, 59, 0.7);
        }
        body { 
            font-family: 'Outfit', sans-serif; 
            background-color: var(--bg-deep);
            color: #f8fafc;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0, transparent 50%), 
                radial-gradient(at 100% 100%, rgba(168, 85, 247, 0.15) 0, transparent 50%);
            background-attachment: fixed;
        }
        .glass {
            background: var(--glass);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37);
        }
        .premium-card {
            background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0) 100%);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .text-gradient {
            background: linear-gradient(to right, #818cf8, #c084fc);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .btn-premium {
            background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.4);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .btn-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.6);
        }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        
        /* Custom Map Styles */
        .leaflet-container { background: #0f172a !important; }
        .layer-dark .leaflet-tile { filter: brightness(0.6) invert(1) contrast(3) hue-rotate(200deg) saturate(0.3) brightness(0.7); }
    </style>
</head>
<body class="min-h-screen">
    <?php if(isset($_SESSION['user_id']) && basename($_SERVER['PHP_SELF']) !== 'ride.php'): ?>
    <!-- Sidebar / Mobile Drawer -->
    <aside id="main-sidebar" class="fixed top-0 left-0 h-screen w-72 glass border-r border-white/5 z-[60] -translate-x-full lg:translate-x-0 transition-transform duration-300 flex flex-col p-8">
        <div class="flex items-center justify-between mb-12">
            <a href="dashboard.php" class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-500/30">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                    </svg>
                </div>
                <span class="text-xl font-extrabold text-white tracking-tight">RIDE<span class="text-indigo-500">TRACKER</span></span>
            </a>
            <button onclick="toggleSidebar()" class="lg:hidden p-2 text-slate-400 hover:text-white">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <!-- Profile Quick View -->
        <div class="mb-12 group cursor-pointer" onclick="window.location.href='dashboard.php#settings'">
            <div class="relative mb-4 inline-block">
                <div class="absolute inset-0 bg-indigo-500 rounded-full blur-xl opacity-20 group-hover:opacity-40 transition-opacity"></div>
                <?php 
                    $u_stmt = $pdo->prepare("SELECT avatar, username FROM users WHERE id = ?");
                    $u_stmt->execute([$_SESSION['user_id']]);
                    $u_info = $u_stmt->fetch();
                ?>
                <img src="uploads/avatars/<?php echo $u_info['avatar'] ?: 'default.png'; ?>" class="relative w-16 h-16 rounded-2xl object-cover border-2 border-white/10 group-hover:border-indigo-500 transition-all" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($u_info['username']??'R'); ?>&background=6366f1&color=fff'">
                <div class="absolute -bottom-2 -right-2 bg-indigo-600 p-1.5 rounded-lg border-2 border-slate-900 scale-0 group-hover:scale-100 transition-transform">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
            </div>
            <h3 class="text-white font-bold text-lg mb-1 uppercase tracking-tight"><?php echo htmlspecialchars($u_info['username']); ?></h3>
            <p class="text-[10px] text-slate-500 uppercase tracking-widest font-black">Authorized Rider</p>
        </div>

        <!-- Navigation Links -->
        <nav class="flex-grow space-y-2">
            <a href="dashboard.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-bold <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'text-slate-400 hover:text-white hover:bg-white/5'; ?> transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                </svg>
                Dashboard
            </a>
            <a href="riders.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-bold <?php echo basename($_SERVER['PHP_SELF']) == 'riders.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'text-slate-400 hover:text-white hover:bg-white/5'; ?> transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                Discovery
            </a>
            <a href="last_rides.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-bold <?php echo basename($_SERVER['PHP_SELF']) == 'last_rides.php' ? 'bg-indigo-600 text-white shadow-lg shadow-indigo-600/20' : 'text-slate-400 hover:text-white hover:bg-white/5'; ?> transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Archived
            </a>
        </nav>

        <a href="auth/logout.php" class="flex items-center gap-4 px-6 py-4 rounded-2xl text-sm font-bold text-red-500/50 hover:bg-red-500/10 transition-all mt-auto border border-red-500/10 hover:text-red-400">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
            </svg>
            Sign Out
        </a>
    </aside>

    <!-- Content Area Adjustment -->
    <div class="lg:ml-72 min-h-screen">
        <header class="h-20 flex items-center px-8 lg:hidden glass sticky top-0 z-50">
            <button onclick="toggleSidebar()" class="mr-6 p-2 bg-white/5 rounded-xl text-slate-300 hover:text-white transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16m-7 6h7" />
                </svg>
            </button>
            <span class="text-xl font-black text-white tracking-tighter">DASHBOARD</span>
            <div class="ml-auto">
                 <img src="uploads/avatars/<?php echo $u_info['avatar'] ?: 'default.png'; ?>" class="w-10 h-10 rounded-xl border border-white/10 shadow-lg" onclick="window.location.href='dashboard.php'">
            </div>
        </header>
        <div class="p-4 md:p-8">

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('main-sidebar');
            sidebar.classList.toggle('-translate-x-full');
        }

        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                const swPath = window.location.pathname.includes('ride-tracker') ? '/ride-tracker/sw.js' : './sw.js';
                navigator.serviceWorker.register(swPath)
                    .then(reg => console.log('SW Registered'))
                    .catch(err => {
                        console.log('SW Failed', err);
                        if (location.protocol !== 'https:') {
                            console.warn('PWA requires HTTPS to function in production.');
                        }
                    });
            });
        }
    </script>
    <?php else: ?>
    <!-- Non-sidebar pages (Ride Map, Login) -->
    <div class="min-h-screen">
    <?php endif; ?>
