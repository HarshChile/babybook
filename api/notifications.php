<?php
/**
 * Notifications API
 * TinyTales BabyBook
 * Isolated notification dispatch and status tracking
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

// GET: Fetch user's isolated notifications
if ($method === 'GET') {
    // Check for any newly unlocked capsules to generate notification
    $today = date('Y-m-d');
    $unlockedCapsules = DB::find('capsules', [
        'user_id' => $userId,
        'unlock_date' => ['$lte' => $today]
    ]);

    foreach ($unlockedCapsules as $uc) {
        $notifKey = "capsule_unlocked_" . $uc['_id'];
        $alreadyNotified = DB::findOne('notifications', [
            'user_id' => $userId,
            'key' => $notifKey
        ]);
        if (!$alreadyNotified) {
            DB::insert('notifications', [
                'user_id' => $userId,
                'key' => $notifKey,
                'type' => 'capsule',
                'title' => 'Time Capsule Unlocked! 🔓',
                'message' => 'Your capsule "' . $uc['title'] . '" is now unlocked and ready to view!',
                'is_read' => false,
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }

    $notifications = DB::find('notifications', ['user_id' => $userId], ['sort' => ['created_at' => -1]]);
    $unreadCount = DB::count('notifications', ['user_id' => $userId, 'is_read' => false]);

    jsonResponse([
        'success' => true,
        'count' => count($notifications),
        'unread_count' => $unreadCount,
        'notifications' => $notifications
    ]);
}

// POST: Mark read, delete, or clear
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true) ?? [];
    $action = $_GET['action'] ?? ($_POST['action'] ?? ($jsonData['action'] ?? ''));

    if ($action === 'mark_read') {
        $id = $_POST['id'] ?? ($_GET['id'] ?? ($jsonData['id'] ?? ''));
        if (empty($id)) {
            sendError('Notification ID required', 422);
        }

        $notif = Auth::verifyOwnership('notifications', $id, $userId);
        if ($notif === null) {
            sendError('Notification not found', 404);
        }
        if ($notif === false) {
            sendError('Unauthorized access to notification', 403);
        }

        DB::update('notifications', ['_id' => $id, 'user_id' => $userId], ['is_read' => true]);
        jsonResponse(['success' => true, 'message' => 'Notification marked as read']);
    }

    if ($action === 'mark_all_read') {
        DB::update('notifications', ['user_id' => $userId], ['is_read' => true], true);
        jsonResponse(['success' => true, 'message' => 'All notifications marked as read']);
    }

    if ($action === 'delete') {
        $id = $_POST['id'] ?? ($_GET['id'] ?? ($jsonData['id'] ?? ''));
        if (empty($id)) {
            sendError('Notification ID required', 422);
        }

        $notif = Auth::verifyOwnership('notifications', $id, $userId);
        if ($notif === null) {
            sendError('Notification not found', 404);
        }
        if ($notif === false) {
            sendError('Unauthorized access to notification', 403);
        }

        DB::delete('notifications', ['_id' => $id, 'user_id' => $userId]);
        jsonResponse(['success' => true, 'message' => 'Notification deleted successfully']);
    }

    if ($action === 'clear_all') {
        DB::delete('notifications', ['user_id' => $userId], 0);
        jsonResponse(['success' => true, 'message' => 'All notifications cleared']);
    }

    sendError('Invalid notification action', 400);
}

sendError('Method not allowed', 405);
