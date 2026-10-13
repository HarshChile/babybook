<?php
/**
 * Memories API - Timeline & Photo Storage
 * BabyBook / TinyTales
 * Stores photos on disk and points to them in MongoDB
 */

require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// GET: Fetch timeline memories
if ($method === 'GET') {
    $sortDir = strtolower($_GET['sort'] ?? 'desc') === 'asc' ? 1 : -1;
    $category = trim($_GET['category'] ?? '');
    $onlyFavorites = isset($_GET['favorite']) && $_GET['favorite'] == '1';

    $filter = [];
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

    // Make sure photo paths are properly formatted for frontend consumption
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

// POST: Add new memory or perform actions
if ($method === 'POST') {

    // Action: Toggle Favorite
    if ($action === 'toggle_favorite') {
        $id = $_POST['id'] ?? '';
        if (empty($id)) {
            sendError('Memory ID required');
        }

        $memory = DB::findOne('memories', ['_id' => $id]);
        if (!$memory) {
            sendError('Memory not found', 404);
        }

        $newFav = empty($memory['is_favorite']);
        DB::update('memories', ['_id' => $id], ['is_favorite' => $newFav]);

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
            sendError('Memory ID required');
        }

        $memory = DB::findOne('memories', ['_id' => $id]);
        if ($memory) {
            // Remove photo from disk if present
            if (!empty($memory['photo_path'])) {
                deleteFileFromDisk($memory['photo_path']);
            }
            DB::delete('memories', ['_id' => $id]);
        }

        jsonResponse(['success' => true, 'message' => 'Memory deleted successfully']);
    }

    // Default POST: Upload photo and create new memory
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? ($_POST['desc'] ?? ''));
    $date = trim($_POST['date'] ?? '');
    $category = trim($_POST['category'] ?? 'Milestone');

    if (empty($title)) {
        sendError('Title is required');
    }

    if (empty($date)) {
        $date = date('Y-m-d');
    }

    // 1. Store Photo on Disk
    $photoPath = handleFileUpload('image', 'photos');

    // If no new photo uploaded, check if photoUrl or placeholder was provided
    if (!$photoPath && !empty($_POST['photo_url'])) {
        $photoPath = $_POST['photo_url'];
    }

    if (!$photoPath) {
        sendError('Please upload a photo for this memory');
    }

    // 2. Save document pointer & metadata in MongoDB
    $doc = [
        'title' => $title,
        'description' => $description,
        'date' => $date,
        'category' => $category,
        'photo_path' => $photoPath, // Disk pointer
        'is_favorite' => false,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $newId = DB::insert('memories', $doc);
    $doc['_id'] = $newId;
    $doc['photo_url'] = $photoPath;

    jsonResponse([
        'success' => true,
        'message' => 'Memory added to timeline successfully',
        'memory' => $doc
    ], 201);
}

// DELETE: HTTP DELETE method
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        sendError('Memory ID required');
    }

    $memory = DB::findOne('memories', ['_id' => $id]);
    if ($memory) {
        if (!empty($memory['photo_path'])) {
            deleteFileFromDisk($memory['photo_path']);
        }
        DB::delete('memories', ['_id' => $id]);
    }

    jsonResponse(['success' => true, 'message' => 'Memory deleted successfully']);
}

sendError('Method not allowed', 405);
