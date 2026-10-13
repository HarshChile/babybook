<?php
/**
 * Dashboard Stats API
 * BabyBook / TinyTales
 * Computes dashboard counters and loads recent memories
 */

require_once __DIR__ . '/../db.php';

$totalMemories = DB::count('memories');
$journalEntries = DB::count('scrapbook');
$favorites = DB::count('memories', ['is_favorite' => true]);
$upcomingCapsules = DB::count('capsules', ['unlock_date' => ['$gt' => date('Y-m-d')]]);
$milestonesCount = DB::count('milestones');

// Recent 4 memories for dashboard grid
$recentMemories = DB::find('memories', [], ['sort' => ['date' => -1, 'created_at' => -1], 'limit' => 4]);

foreach ($recentMemories as &$m) {
    if (empty($m['photo_path'])) {
        $m['photo_url'] = 'https://via.placeholder.com/600x400?text=Baby+Memory';
    } else {
        $m['photo_url'] = $m['photo_path'];
    }
}

// Get baby name from profile
$profile = DB::findOne('profile', []);
$babyName = $profile['baby_name'] ?? 'Emma';

jsonResponse([
    'success' => true,
    'baby_name' => $babyName,
    'stats' => [
        'total_memories' => $totalMemories,
        'journal_entries' => $journalEntries,
        'favorites' => $favorites,
        'upcoming_events' => max($upcomingCapsules, 1),
        'milestones' => $milestonesCount
    ],
    'recent_memories' => $recentMemories
]);
