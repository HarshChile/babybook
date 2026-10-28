<?php
/**
 * Time Capsule API
 * TinyTales BabyBook
 * Preserves private heartfelt messages and media until their future unlock date
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

// GET: Fetch time capsules belonging to the authenticated user
if ($method === 'GET') {
    $filter = ['user_id' => $userId];

    if (!empty($_GET['baby_id'])) {
        $babyCheck = Auth::verifyBabyOwnership($_GET['baby_id'], $userId);
        if ($babyCheck) {
            $filter['baby_id'] = $_GET['baby_id'];
        }
    }

    $capsules = DB::find('capsules', $filter, ['sort' => ['unlock_date' => 1]]);
    $today = date('Y-m-d');

    foreach ($capsules as &$c) {
        $unlockDate = $c['unlock_date'] ?? $today;
        $isLocked = ($today < $unlockDate);
        $c['is_locked'] = $isLocked;

        if ($isLocked) {
            // STRICT SECURITY: Never leak locked capsule contents or media in response payload
            $c['preview_message'] = 'Locked until ' . $unlockDate;
            unset($c['message']);
            unset($c['media_path']);
            unset($c['media_type']);
        }
    }

    jsonResponse([
        'success' => true,
        'count' => count($capsules),
        'capsules' => $capsules
    ]);
}

// POST: Add new capsule or delete
if ($method === 'POST') {
    if ($action === 'delete') {
        $id = $_POST['id'] ?? ($_GET['id'] ?? '');
        if (empty($id)) {
            sendError('Capsule ID required', 422);
        }

        $cap = Auth::verifyOwnership('capsules', $id, $userId);
        if ($cap === null) {
            sendError('Capsule not found', 404);
        }
        if ($cap === false) {
            sendError('Unauthorized access to capsule', 403);
        }

        if (!empty($cap['media_path'])) {
            deleteFileFromDisk($cap['media_path']);
        }

        DB::delete('capsules', ['_id' => $id, 'user_id' => $userId]);
        jsonResponse(['success' => true, 'message' => 'Capsule deleted successfully']);
    }

    $title = trim($_POST['title'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $unlockDate = trim($_POST['unlockDate'] ?? ($_POST['unlock_date'] ?? ''));

    if (empty($title) || empty($message) || empty($unlockDate)) {
        sendError('Title, message, and unlock date are required', 422);
    }

    $mediaPath = handleFileUpload('media', 'capsules');
    $mediaType = null;
    if ($mediaPath && isset($_FILES['media']['type'])) {
        $mediaType = $_FILES['media']['type'];
    }

    $activeBaby = Auth::getActiveBaby($userId);
    $babyId = (string)$activeBaby['_id'];

    $isLocked = (date('Y-m-d') < $unlockDate);

    $doc = [
        'user_id' => $userId,
        'baby_id' => $babyId,
        'title' => $title,
        'message' => $message,
        'unlock_date' => $unlockDate,
        'media_path' => $mediaPath,
        'media_type' => $mediaType,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $id = DB::insert('capsules', $doc);
    $doc['_id'] = $id;
    $doc['is_locked'] = $isLocked;

    // Notify user of locked capsule
    DB::insert('notifications', [
        'user_id' => $userId,
        'type' => 'capsule',
        'title' => 'Time Capsule Locked 💌',
        'message' => 'Your capsule "' . $title . '" is locked safely until ' . $unlockDate,
        'is_read' => false,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Time capsule locked away safely 💌',
        'capsule' => $doc
    ], 201);
}

// DELETE: HTTP DELETE method
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        sendError('Capsule ID required', 422);
    }

    $cap = Auth::verifyOwnership('capsules', $id, $userId);
    if ($cap === null) {
        sendError('Capsule not found', 404);
    }
    if ($cap === false) {
        sendError('Unauthorized access to capsule', 403);
    }

    if (!empty($cap['media_path'])) {
        deleteFileFromDisk($cap['media_path']);
    }

    DB::delete('capsules', ['_id' => $id, 'user_id' => $userId]);
    jsonResponse(['success' => true, 'message' => 'Capsule deleted successfully']);
}

sendError('Method not allowed', 405);
