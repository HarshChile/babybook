<?php
/**
 * Dashboard Stats API
 * TinyTales BabyBook
 * Isolated dashboard analytics strictly per authenticated user
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';

$user = Auth::requireAuth();
$userId = (string)$user['_id'];

// Scoped counters for authenticated user only
$totalMemories = DB::count('memories', ['user_id' => $userId]);
$journalEntries = DB::count('scrapbook', ['user_id' => $userId]);
$favorites = DB::count('memories', ['user_id' => $userId, 'is_favorite' => true]);
$upcomingCapsules = DB::count('capsules', ['user_id' => $userId, 'unlock_date' => ['$gt' => date('Y-m-d')]]);
$milestonesCount = DB::count('milestones', ['user_id' => $userId]);

// Recent 4 memories belonging to authenticated user
$recentMemories = DB::find('memories', ['user_id' => $userId], ['sort' => ['date' => -1, 'created_at' => -1], 'limit' => 4]);

foreach ($recentMemories as &$m) {
    if (empty($m['photo_path'])) {
        $m['photo_url'] = 'https://via.placeholder.com/600x400?text=Baby+Memory';
    } else {
        $m['photo_url'] = $m['photo_path'];
    }
}

// Active baby details
$activeBaby = Auth::getActiveBaby($userId);
$babyName = $activeBaby['baby_name'] ?? 'Little One';

jsonResponse([
    'success' => true,
    'baby_name' => $babyName,
    'active_baby' => $activeBaby,
    'stats' => [
        'total_memories' => $totalMemories,
        'journal_entries' => $journalEntries,
        'favorites' => $favorites,
        'upcoming_events' => max($upcomingCapsules, 1),
        'milestones' => $milestonesCount
    ],
    'recent_memories' => $recentMemories
]);
