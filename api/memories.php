<?php
/**
 * Memories API - Isolated Photo Storage & Dynamic Timeline
 * TinyTales BabyBook
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

// ============================================================
// GET: Fetch timeline memories for the authenticated user only
// ============================================================
if ($method === 'GET') {
    $sortDir = strtolower($_GET['sort'] ?? 'desc') === 'asc' ? 1 : -1;
    $category = trim($_GET['category'] ?? '');
    $onlyFavorites = isset($_GET['favorite']) && $_GET['favorite'] == '1';

    // Strict ownership enforcement
    $filter = ['user_id' => $userId];

    // Optional baby profile scoping if specified
    if (!empty($_GET['baby_id'])) {
        $babyCheck = Auth::verifyBabyOwnership($_GET['baby_id'], $userId);
        if ($babyCheck) {
            $filter['baby_id'] = $_GET['baby_id'];
        }
    }

    if (!empty($category) && $category !== 'all') {
        $filter['category'] = $category;
    }
    if ($onlyFavorites) {
        $filter['is_favorite'] = true;
    }

    $options = [
        'sort' => ['date' => $sortDir, 'created_at' => $sortDir]
    ];

    if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
        $options['limit'] = (int)$_GET['limit'];
    }

    $memories = DB::find('memories', $filter, $options);

    foreach ($memories as &$m) {
        if (!empty($m['photo_path'])) {
            $m['photo_url'] = $m['photo_path'];
        } else {
            $m['photo_url'] = 'https://via.placeholder.com/600x400?text=Baby+Memory';
        }
        $m['is_favorite'] = !empty($m['is_favorite']);
    }

    jsonResponse([
        'success' => true,
        'count' => count($memories),
        'memories' => $memories
    ]);
}

// ============================================================
// POST: Add new memory or perform mutations
// ============================================================
if ($method === 'POST') {
    // Action: Toggle Favorite
    if ($action === 'toggle_favorite') {
        $id = $_POST['id'] ?? '';
        if (empty($id)) {
            sendError('Memory ID required', 422);
        }

        $memory = Auth::verifyOwnership('memories', $id, $userId);
        if ($memory === null) {
            sendError('Memory not found', 404);
        }
        if ($memory === false) {
            sendError('Unauthorized access to memory', 403);
        }

        $newFav = empty($memory['is_favorite']);
        DB::update('memories', ['_id' => $id, 'user_id' => $userId], ['is_favorite' => $newFav]);

        jsonResponse([
            'success' => true,
            'is_favorite' => $newFav,
            'message' => $newFav ? 'Added to favorites' : 'Removed from favorites'
        ]);
    }

    // Action: Delete Memory
    if ($action === 'delete') {
        $id = $_POST['id'] ?? ($_GET['id'] ?? '');
        if (empty($id)) {
            sendError('Memory ID required', 422);
        }

        $memory = Auth::verifyOwnership('memories', $id, $userId);
        if ($memory === null) {
            sendError('Memory not found', 404);
        }
        if ($memory === false) {
            sendError('Unauthorized access to memory', 403);
        }

        // Clean up disk storage safely
        if (!empty($memory['photo_path'])) {
            deleteFileFromDisk($memory['photo_path']);
        }

        DB::delete('memories', ['_id' => $id, 'user_id' => $userId]);

        jsonResponse(['success' => true, 'message' => 'Memory deleted successfully']);
    }

    // Default POST: Upload photo and create new memory
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? ($_POST['desc'] ?? ''));
    $date = trim($_POST['date'] ?? '');
    $category = trim($_POST['category'] ?? 'Milestone');

    if (empty($title)) {
        sendError('Title is required', 422);
    }

    if (empty($date)) {
        $date = date('Y-m-d');
    }

    // 1. Store Photo on Disk
    $photoPath = handleFileUpload('image', 'photos');

    if (!$photoPath && !empty($_POST['photo_url'])) {
        $photoPath = $_POST['photo_url'];
    }

    if (!$photoPath) {
        sendError('Please upload a photo for this memory', 422);
    }

    // Resolve active baby for this user
    $activeBaby = Auth::getActiveBaby($userId);
    $babyId = (string)$activeBaby['_id'];

    // 2. Save document with server-derived user and baby ownership
    $doc = [
        'user_id' => $userId,
        'baby_id' => $babyId,
        'title' => $title,
        'description' => $description,
        'date' => $date,
        'category' => $category,
        'photo_path' => $photoPath,
        'is_favorite' => false,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $newId = DB::insert('memories', $doc);
    $doc['_id'] = $newId;
    $doc['photo_url'] = $photoPath;

    // Record user notification
    DB::insert('notifications', [
        'user_id' => $userId,
        'type' => 'memories',
        'title' => 'Memory Added 📷',
        'message' => 'Added new memory "' . $title . '" to your scrapbook.',
        'is_read' => false,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Memory added to timeline successfully',
        'memory' => $doc
    ], 201);
}

// ============================================================
// DELETE: HTTP DELETE method
// ============================================================
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        sendError('Memory ID required', 422);
    }

    $memory = Auth::verifyOwnership('memories', $id, $userId);
    if ($memory === null) {
        sendError('Memory not found', 404);
    }
    if ($memory === false) {
        sendError('Unauthorized access to memory', 403);
    }

    if (!empty($memory['photo_path'])) {
        deleteFileFromDisk($memory['photo_path']);
    }

    DB::delete('memories', ['_id' => $id, 'user_id' => $userId]);

    jsonResponse(['success' => true, 'message' => 'Memory deleted successfully']);
}

sendError('Method not allowed', 405);
