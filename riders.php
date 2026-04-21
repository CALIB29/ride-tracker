<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$search = $_GET['search'] ?? '';

// Handle friendship actions
if (isset($_POST['action'])) {
    $target_id = $_POST['friend_id'];
    $action = $_POST['action'];

    if ($action == 'add') {
        $stmt = $pdo->prepare("INSERT IGNORE INTO friends (user_id, friend_id, status) VALUES (?, ?, 'pending')");
        $stmt->execute([$user_id, $target_id]);
    } elseif ($action == 'accept') {
        // Update their request to me to 'accepted'
        $stmt = $pdo->prepare("UPDATE friends SET status = 'accepted' WHERE user_id = ? AND friend_id = ?");
        $stmt->execute([$target_id, $user_id]);
        // Create my reciprocal 'accepted' link
        $stmt = $pdo->prepare("INSERT IGNORE INTO friends (user_id, friend_id, status) VALUES (?, ?, 'accepted')");
        $stmt->execute([$user_id, $target_id]);
    } elseif ($action == 'unfriend') {
        $stmt = $pdo->prepare("DELETE FROM friends WHERE (user_id = ? AND friend_id = ?) OR (user_id = ? AND friend_id = ?)");
        $stmt->execute([$user_id, $target_id, $target_id, $user_id]);
    }
}

// Get current user location
$stmt = $pdo->prepare("SELECT last_lat, last_lng FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$current_user = $stmt->fetch();
$u_lat = $current_user['last_lat'] ?? 0;
$u_lng = $current_user['last_lng'] ?? 0;

$riders = [];
$u_lat = $current_user['last_lat'] ?? 0;
$u_lng = $current_user['last_lng'] ?? 0;

$query_base = "SELECT u.*, 
                (SELECT status FROM friends WHERE user_id = :uid AND friend_id = u.id) as my_status,
                (SELECT status FROM friends WHERE user_id = u.id AND friend_id = :uid) as their_status,
                (6371 * acos(cos(radians(:lat)) * cos(radians(u.last_lat)) * cos(radians(u.last_lng) - radians(:lng)) + sin(radians(:lat)) * sin(radians(u.last_lat)))) AS distance
              FROM users u 
              WHERE u.id != :uid";

if ($search) {
    $stmt = $pdo->prepare("$query_base AND u.username LIKE :search");
    $stmt->execute(['uid' => $user_id, 'lat' => $u_lat, 'lng' => $u_lng, 'search' => "%$search%"]);
} else {
    $stmt = $pdo->prepare("$query_base AND u.last_lat IS NOT NULL HAVING distance < 100 ORDER BY distance ASC");
    $stmt->execute(['uid' => $user_id, 'lat' => $u_lat, 'lng' => $u_lng]);
}
$riders = $stmt->fetchAll();

// Get actual mutual friends
$friends = array_filter($riders, function($r) { return $r['my_status'] == 'accepted'; });
// Get incoming requests
$incoming = array_filter($riders, function($r) { return $r['their_status'] == 'pending' && $r['my_status'] === null; });

require_once 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-16 text-center">
        <h1 class="text-5xl font-black text-white mb-4 tracking-tighter">DISCOVER <span class="text-indigo-500">RIDERS</span></h1>
        <p class="text-slate-500 font-medium">Build your crew and dominate the roads</p>
    </div>

    <!-- Search Section -->
    <div class="glass p-10 rounded-[3rem] border border-white/10 mb-16 max-w-2xl mx-auto">
        <form method="GET" class="flex flex-col md:flex-row gap-4">
            <div class="relative flex-grow">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Enter Rider Name..." class="w-full bg-white/5 border border-white/10 rounded-2xl px-6 py-4 text-white focus:ring-1 focus:ring-indigo-500 outline-none">
            </div>
            <button type="submit" class="btn-premium px-10 py-4 rounded-2xl font-black text-sm uppercase tracking-widest text-white shadow-xl">
                SEARCH
            </button>
        </form>
    </div>

    <!-- Incoming Requests Section -->
    <?php if(!empty($incoming) && !$search): ?>
    <div class="mb-16">
        <h2 class="text-xl font-black text-white mb-8 border-l-4 border-amber-500 pl-4 uppercase tracking-widest">Incoming Requests</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <?php foreach($incoming as $request): ?>
                <div class="glass p-6 rounded-[2.5rem] border border-white/10 flex items-center justify-between group">
                    <div class="flex items-center gap-4">
                        <img src="uploads/avatars/<?php echo $request['avatar'] ?: 'default.png'; ?>" class="w-12 h-12 rounded-2xl object-cover border border-white/10" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($request['username']); ?>&background=6366f1&color=fff'">
                        <span class="text-sm font-bold text-white"><?php echo htmlspecialchars($request['username']); ?></span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="friend_id" value="<?php echo $request['id']; ?>">
                        <input type="hidden" name="action" value="accept">
                        <button type="submit" class="p-3 bg-emerald-500/10 text-emerald-400 rounded-xl border border-emerald-500/20 hover:bg-emerald-500 hover:text-white transition-all">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if(!empty($friends) && !$search): ?>
    <div class="mb-20">
        <h2 class="text-xl font-black text-white mb-8 border-l-4 border-indigo-500 pl-4 uppercase tracking-widest">Linked Crew</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-6">
            <?php foreach($friends as $friend): ?>
                <div class="flex flex-col items-center group relative">
                    <div class="relative mb-3">
                        <img src="uploads/avatars/<?php echo $friend['avatar'] ?: 'default.png'; ?>" class="relative w-16 h-16 rounded-2xl object-cover border-2 border-white/10 group-hover:border-indigo-500 transition-all">
                        <!-- Unfriend Tiny Button -->
                        <form method="POST" class="absolute -top-1 -right-1 opacity-0 group-hover:opacity-100 transition-all">
                            <input type="hidden" name="friend_id" value="<?php echo $friend['id']; ?>">
                            <input type="hidden" name="action" value="unfriend">
                            <button type="submit" class="bg-red-500 text-white p-1 rounded-lg shadow-xl">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </form>
                    </div>
                    <span class="text-[10px] font-black text-slate-400 text-center truncate w-full"><?php echo htmlspecialchars($friend['username']); ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="mb-8 flex justify-between items-end">
        <h2 class="text-xl font-black text-white uppercase tracking-widest">
            <?php echo $search ? 'Results' : 'Nearby (100km)'; ?>
        </h2>
        <?php if($search): ?>
            <a href="riders.php" class="text-xs font-bold text-indigo-400 hover:text-indigo-300">Reset Search</a>
        <?php endif; ?>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
        <?php foreach($riders as $rider): ?>
            <div class="glass p-8 rounded-[2.5rem] border border-white/10 flex flex-col items-center text-center group hover:border-indigo-500/50 transition-all">
                <div class="relative mb-6">
                    <img src="uploads/avatars/<?php echo $rider['avatar'] ?: 'default.png'; ?>" class="w-24 h-24 rounded-[2rem] object-cover border-4 border-white/5 shadow-2xl" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($rider['username']); ?>&background=6366f1&color=fff'">
                    <?php if(!$search && isset($rider['distance'])): ?>
                        <div class="absolute -bottom-2 -right-2 bg-indigo-500 text-white text-[10px] px-3 py-1 rounded-full font-black shadow-xl">
                            <?php echo number_format($rider['distance'], 1); ?> KM
                        </div>
                    <?php endif; ?>
                </div>
                
                <h3 class="text-xl font-black text-white mb-6 tracking-tight"><?php echo htmlspecialchars($rider['username']); ?></h3>
                
                <form method="POST" class="w-full">
                    <input type="hidden" name="friend_id" value="<?php echo $rider['id']; ?>">
                    <?php if($rider['my_status'] == 'accepted'): ?>
                        <input type="hidden" name="action" value="unfriend">
                        <button type="submit" class="w-full py-3 bg-white/5 text-slate-500 rounded-2xl font-black text-[10px] border border-white/10 tracking-widest uppercase hover:bg-red-500/10 hover:text-red-400 transition-all">
                            UNFRIEND
                        </button>
                    <?php elseif($rider['my_status'] == 'pending'): ?>
                        <button type="button" disabled class="w-full py-3 bg-white/5 text-amber-500/50 rounded-2xl font-black text-[10px] border border-white/5 tracking-widest uppercase">
                            REQUESTED
                        </button>
                    <?php elseif($rider['their_status'] == 'pending'): ?>
                        <input type="hidden" name="action" value="accept">
                        <button type="submit" class="w-full py-3 bg-emerald-500/10 text-emerald-400 rounded-2xl font-black text-[10px] border border-emerald-500/20 hover:bg-emerald-500 hover:text-white transition-all tracking-widest uppercase">
                            ACCEPT
                        </button>
                    <?php else: ?>
                        <input type="hidden" name="action" value="add">
                        <button type="submit" class="w-full py-3 bg-indigo-600/10 text-indigo-400 rounded-2xl font-black text-[10px] border border-indigo-600/20 hover:bg-indigo-600 hover:text-white transition-all tracking-widest uppercase">
                            CONNECT
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        <?php endforeach; ?>

        <?php if(empty($riders)): ?>
            <div class="col-span-full py-32 glass rounded-[3rem] border border-dashed border-white/10 text-center">
                <p class="text-slate-500 font-bold uppercase tracking-widest text-sm">No riders detected in this sector</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
