<?php
/**
 * Calendar API - Events & Memories by Date
 * TinyTales BabyBook
 * Isolated strictly per authenticated user
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';

$user = Auth::requireAuth();
$userId = (string)$user['_id'];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Handle CORS OPTIONS preflight
if ($method === 'OPTIONS') {
    http_response_code(200);
    safeExit();
}

if ($method === 'GET') {
    $year = $_GET['year'] ?? date('Y');
    $month = $_GET['month'] ?? date('m');

    $filter = ['user_id' => $userId];

    if (!empty($_GET['baby_id'])) {
        $babyCheck = Auth::verifyBabyOwnership($_GET['baby_id'], $userId);
        if ($babyCheck) {
            $filter['baby_id'] = $_GET['baby_id'];
        }
    }

    // Retrieve only authenticated user's records
    $userMemories = DB::find('memories', $filter, ['sort' => ['date' => 1]]);
    $userMilestones = DB::find('milestones', $filter, ['sort' => ['date' => 1]]);

    $eventsByDate = [];

    foreach ($userMemories as $m) {
        $d = $m['date'] ?? '';
        if ($d) {
            if (!isset($eventsByDate[$d])) {
                $eventsByDate[$d] = [];
            }
            $eventsByDate[$d][] = [
                'type' => 'memory',
                'id' => $m['_id'],
                'title' => $m['title'],
                'description' => $m['description'] ?? '',
                'photo_url' => $m['photo_path'] ?? '',
                'category' => $m['category'] ?? 'Memory'
            ];
        }
    }

    foreach ($userMilestones as $ms) {
        $d = $ms['date'] ?? '';
        if ($d) {
            if (!isset($eventsByDate[$d])) {
                $eventsByDate[$d] = [];
            }
            $eventsByDate[$d][] = [
                'type' => 'milestone',
                'id' => $ms['_id'],
                'title' => $ms['title'],
                'category' => $ms['category'] ?? 'Milestone'
            ];
        }
    }

    jsonResponse([
        'success' => true,
        'year' => (int)$year,
        'month' => (int)$month,
        'events' => $eventsByDate
    ]);
}

// POST: Add memory from calendar panel
if ($method === 'POST') {
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $title = trim($_POST['title'] ?? 'Calendar Memory');
    $desc = trim($_POST['desc'] ?? ($_POST['description'] ?? ''));

    $photoPath = handleFileUpload('image', 'photos');

    $activeBaby = Auth::getActiveBaby($userId);
    $babyId = (string)$activeBaby['_id'];

    $doc = [
        'user_id' => $userId,
        'baby_id' => $babyId,
        'title' => $title,
        'description' => $desc,
        'date' => $date,
        'category' => 'Calendar',
        'photo_path' => $photoPath,
        'is_favorite' => false,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $id = DB::insert('memories', $doc);
    $doc['_id'] = $id;

    jsonResponse([
        'success' => true,
        'message' => 'Memory saved to calendar successfully',
        'memory' => $doc
    ], 201);
}

sendError('Method not allowed', 405);
