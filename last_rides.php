<?php
session_start();
require_once 'config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch finished rides from self and friends
$stmt = $pdo->prepare("SELECT r.*, u.username as creator_name, 
                    (SELECT COUNT(*) FROM ride_participants WHERE ride_id = r.id) as participant_count
                    FROM rides r 
                    JOIN users u ON r.creator_id = u.id 
                    LEFT JOIN friends f ON (f.user_id = ? AND f.friend_id = r.creator_id AND f.status = 'accepted')
                    WHERE (r.creator_id = ? OR f.id IS NOT NULL) AND r.status = 'finished'
                    ORDER BY r.created_at DESC");
$stmt->execute([$user_id, $user_id]);
$finished_rides = $stmt->fetchAll();

require_once 'includes/header.php';
?>

<div class="max-w-6xl mx-auto">
    <div class="mb-12">
        <h1 class="text-3xl font-bold text-slate-900 mb-2">Last Rides</h1>
        <p class="text-slate-500">Relive the adventures you and your friends finished</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php foreach($finished_rides as $ride): ?>
            <div class="glass p-6 rounded-3xl shadow-lg border border-white hover:scale-105 transition-all">
                <div class="flex justify-between items-start mb-4">
                    <span class="px-3 py-1 bg-slate-100 text-slate-500 rounded-full text-[10px] font-bold uppercase tracking-widest">
                        Finished
                    </span>
                    <span class="text-[10px] text-slate-400 font-medium">
                        <?php echo date('M d, Y', strtotime($ride['created_at'])); ?>
                    </span>
                </div>
                
                <h3 class="text-xl font-bold text-slate-900 mb-2"><?php echo htmlspecialchars($ride['title']); ?></h3>
                <p class="text-sm text-slate-500 mb-6 font-medium">by <?php echo htmlspecialchars($ride['creator_name']); ?></p>
                
                <div class="flex items-center justify-between pt-6 border-t border-slate-100">
                    <div class="flex items-center gap-2 text-slate-400">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                        <span class="text-xs font-bold"><?php echo $ride['participant_count']; ?> Riders</span>
                    </div>
                    <div class="text-[10px] py-1 px-3 bg-indigo-50 text-indigo-500 rounded-full font-bold">COMPLETED</div>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if(empty($finished_rides)): ?>
            <div class="col-span-full py-24 text-center">
                <div class="w-20 h-20 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-6">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-slate-400">No finished rides yet</h3>
                <p class="text-slate-400 mt-2">Finish an active ride to see it here!</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
