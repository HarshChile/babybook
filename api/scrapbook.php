<?php
/**
 * Scrapbook API
 * TinyTales BabyBook
 * Isolated notebook polaroid entries & journaling
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';

$user = Auth::requireAuth();
$userId = (string)$user['_id'];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Handle CORS OPTIONS preflight
if ($method === 'OPTIONS') {
    http_response_code(200);
    safeExit();
}

if ($method === 'GET') {
    $filter = ['user_id' => $userId];

    if (!empty($_GET['baby_id'])) {
        $babyCheck = Auth::verifyBabyOwnership($_GET['baby_id'], $userId);
        if ($babyCheck) {
            $filter['baby_id'] = $_GET['baby_id'];
        }
    }

    $items = DB::find('scrapbook', $filter, ['sort' => ['created_at' => -1]]);
    jsonResponse([
        'success' => true,
        'count' => count($items),
        'items' => $items
    ]);
}

if ($method === 'POST') {
    if ($action === 'clear') {
        // Only clear entries belonging to authenticated user
        $userItems = DB::find('scrapbook', ['user_id' => $userId]);
        foreach ($userItems as $item) {
            if (!empty($item['photo_path'])) {
                deleteFileFromDisk($item['photo_path']);
            }
        }

        DB::delete('scrapbook', ['user_id' => $userId], 0);
        jsonResponse(['success' => true, 'message' => 'Your scrapbook was cleared successfully']);
    }

    $title = trim($_POST['title'] ?? 'Scrapbook Note');
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $mood = trim($_POST['mood'] ?? '😊 Happy');
    $note = trim($_POST['note'] ?? '');

    $photoPath = handleFileUpload('image', 'scrapbook');

    $activeBaby = Auth::getActiveBaby($userId);
    $babyId = (string)$activeBaby['_id'];

    $doc = [
        'user_id' => $userId,
        'baby_id' => $babyId,
        'title' => $title,
        'date' => $date,
        'mood' => $mood,
        'note' => $note,
        'photo_path' => $photoPath,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $id = DB::insert('scrapbook', $doc);
    $doc['_id'] = $id;

    jsonResponse([
        'success' => true,
        'message' => 'Scrapbook entry saved',
        'item' => $doc
    ], 201);
}

sendError('Method not allowed', 405);
