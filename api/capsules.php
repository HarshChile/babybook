<?php
/**
 * Time Capsule API
 * BabyBook / TinyTales
 * Save memories now, open them in the future
 */

require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($method === 'GET') {
    $capsules = DB::find('capsules', [], ['sort' => ['unlock_date' => 1]]);
    $today = date('Y-m-d');

    foreach ($capsules as &$c) {
        $unlockDate = $c['unlock_date'] ?? $today;
        $c['is_locked'] = ($today < $unlockDate);
        if ($c['is_locked']) {
            // Hide message content and media if still locked to keep the surprise!
            $c['preview_message'] = 'Locked until ' . $unlockDate;
        }
    }

    jsonResponse([
        'success' => true,
        'count' => count($capsules),
        'capsules' => $capsules
    ]);
}

if ($method === 'POST') {
    if ($action === 'delete') {
        $id = $_POST['id'] ?? ($_GET['id'] ?? '');
        if (empty($id)) {
            sendError('Capsule ID required');
        }
        $cap = DB::findOne('capsules', ['_id' => $id]);
        if ($cap && !empty($cap['media_path'])) {
            deleteFileFromDisk($cap['media_path']);
        }
        DB::delete('capsules', ['_id' => $id]);
        jsonResponse(['success' => true, 'message' => 'Capsule deleted']);
    }

    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $unlockDate = trim($_POST['unlockDate'] ?? ($_POST['unlock_date'] ?? ''));

    if (empty($title) || empty($message) || empty($unlockDate)) {
        sendError('Title, message, and unlock date are required');
    }

    $mediaPath = handleFileUpload('media', 'capsules');
    $mediaType = null;
    if ($mediaPath && isset($_FILES['media']['type'])) {
        $mediaType = $_FILES['media']['type'];
    }

    $doc = [
        'title' => $title,
        'message' => $message,
        'unlock_date' => $unlockDate,
        'media_path' => $mediaPath,
        'media_type' => $mediaType,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $id = DB::insert('capsules', $doc);
    $doc['_id'] = $id;
    $doc['is_locked'] = (date('Y-m-d') < $unlockDate);

    jsonResponse([
        'success' => true,
        'message' => 'Time capsule locked away safely 💌',
        'capsule' => $doc
    ], 201);
}

sendError('Method not allowed', 405);
