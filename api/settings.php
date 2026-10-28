<?php
/**
 * Settings API
 * TinyTales BabyBook
 * Isolated user configuration and profile preferences
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
    $settings = DB::findOne('settings', ['user_id' => $userId]);
    if (!$settings) {
        $activeBaby = Auth::getActiveBaby($userId);
        $settings = [
            'user_id' => $userId,
            'baby_name' => $activeBaby['baby_name'] ?? 'Little One',
            'parent_email' => $user['email'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $id = DB::insert('settings', $settings);
        $settings['_id'] = $id;
    }

    jsonResponse(['success' => true, 'settings' => $settings]);
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true) ?? [];

    $babyName = trim($_POST['baby_name'] ?? ($json['baby_name'] ?? ''));
    $parentEmail = trim($_POST['parent_email'] ?? ($json['parent_email'] ?? ''));

    $existing = DB::findOne('settings', ['user_id' => $userId]);

    $data = [
        'baby_name' => $babyName,
        'parent_email' => $parentEmail ?: ($user['email'] ?? ''),
        'updated_at' => date('Y-m-d H:i:s')
    ];

    if ($existing && isset($existing['_id'])) {
        DB::update('settings', ['_id' => $existing['_id'], 'user_id' => $userId], $data);
        $data['_id'] = $existing['_id'];
        $data['user_id'] = $userId;
    } else {
        $data['user_id'] = $userId;
        $id = DB::insert('settings', $data);
        $data['_id'] = $id;
    }

    // Sync baby name to active baby profile if provided
    if (!empty($babyName)) {
        $activeBaby = Auth::getActiveBaby($userId);
        if ($activeBaby) {
            DB::update('profile', ['_id' => $activeBaby['_id'], 'user_id' => $userId], ['baby_name' => $babyName]);
        }
    }

    jsonResponse(['success' => true, 'message' => 'Settings saved successfully', 'settings' => $data]);
}

sendError('Method not allowed', 405);
