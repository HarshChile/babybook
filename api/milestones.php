<?php
/**
 * Milestones API
 * TinyTales BabyBook
 * Isolated developmental milestone tracker
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

// GET: Fetch all milestones owned by authenticated user
if ($method === 'GET') {
    $filter = ['user_id' => $userId];

    if (!empty($_GET['baby_id'])) {
        $babyCheck = Auth::verifyBabyOwnership($_GET['baby_id'], $userId);
        if ($babyCheck) {
            $filter['baby_id'] = $_GET['baby_id'];
        }
    }

    $milestones = DB::find('milestones', $filter, ['sort' => ['date' => -1]]);
    jsonResponse([
        'success' => true,
        'count' => count($milestones),
        'milestones' => $milestones
    ]);
}

// POST: Add new milestone or delete
if ($method === 'POST') {
    if ($action === 'delete') {
        $id = $_POST['id'] ?? ($_GET['id'] ?? '');
        if (empty($id)) {
            sendError('Milestone ID required', 422);
        }

        $ms = Auth::verifyOwnership('milestones', $id, $userId);
        if ($ms === null) {
            sendError('Milestone not found', 404);
        }
        if ($ms === false) {
            sendError('Unauthorized access to milestone', 403);
        }

        DB::delete('milestones', ['_id' => $id, 'user_id' => $userId]);
        jsonResponse(['success' => true, 'message' => 'Milestone deleted successfully']);
    }

    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true) ?? [];

    $title = trim($_POST['title'] ?? ($json['title'] ?? ''));
    $category = trim($_POST['category'] ?? ($json['category'] ?? 'social'));
    $date = trim($_POST['date'] ?? ($json['date'] ?? ''));

    if (empty($title) || empty($date)) {
        sendError('Milestone title and date are required', 422);
    }

    $activeBaby = Auth::getActiveBaby($userId);
    $babyId = (string)$activeBaby['_id'];

    $doc = [
        'user_id' => $userId,
        'baby_id' => $babyId,
        'title' => $title,
        'category' => strtolower($category),
        'date' => $date,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $id = DB::insert('milestones', $doc);
    $doc['_id'] = $id;

    // Record milestone notification
    DB::insert('notifications', [
        'user_id' => $userId,
        'type' => 'milestones',
        'title' => 'New Milestone Achieved! ⭐',
        'message' => 'Captured milestone: ' . $title,
        'is_read' => false,
        'created_at' => date('Y-m-d H:i:s')
    ]);

    jsonResponse([
        'success' => true,
        'message' => 'Milestone saved successfully',
        'milestone' => $doc
    ], 201);
}

// DELETE: HTTP DELETE method
if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        sendError('Milestone ID required', 422);
    }

    $ms = Auth::verifyOwnership('milestones', $id, $userId);
    if ($ms === null) {
        sendError('Milestone not found', 404);
    }
    if ($ms === false) {
        sendError('Unauthorized access to milestone', 403);
    }

    DB::delete('milestones', ['_id' => $id, 'user_id' => $userId]);
    jsonResponse(['success' => true, 'message' => 'Milestone deleted successfully']);
}

sendError('Method not allowed', 405);
