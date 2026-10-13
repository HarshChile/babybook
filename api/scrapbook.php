<?php
/**
 * Scrapbook API
 * BabyBook / TinyTales
 * Notebook scrapbook photo and note entries
 */

require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($method === 'GET') {
    $items = DB::find('scrapbook', [], ['sort' => ['created_at' => -1]]);
    jsonResponse([
        'success' => true,
        'count' => count($items),
        'items' => $items
    ]);
}

if ($method === 'POST') {
    if ($action === 'clear') {
        $allItems = DB::find('scrapbook', []);
        foreach ($allItems as $item) {
            if (!empty($item['photo_path'])) {
                deleteFileFromDisk($item['photo_path']);
            }
        }
        // Delete all
        DB::delete('scrapbook', [], 0);
        jsonResponse(['success' => true, 'message' => 'Scrapbook cleared successfully']);
    }

    $title = trim($_POST['title'] ?? 'Scrapbook Note');
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $mood = trim($_POST['mood'] ?? '😊 Happy');
    $note = trim($_POST['note'] ?? '');

    $photoPath = handleFileUpload('image', 'scrapbook');

    $doc = [
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
