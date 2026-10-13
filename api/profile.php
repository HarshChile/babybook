<?php
/**
 * Profile API
 * BabyBook / TinyTales
 * Stores baby and parent profile details
 */

require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $profile = DB::findOne('profile', []);
    if (!$profile) {
        $profile = [
            'baby_name' => 'Emma',
            'birth_date' => '2025-06-15',
            'parent_names' => 'Sarah & John',
            'bio' => 'Our sweet little miracle who brings endless joy every day.',
            'avatar_path' => null
        ];
    }

    jsonResponse([
        'success' => true,
        'profile' => $profile
    ]);
}

if ($method === 'POST') {
    $babyName = trim($_POST['babyName'] ?? ($_POST['baby_name'] ?? ''));
    $birthDate = trim($_POST['birthDate'] ?? ($_POST['birth_date'] ?? ''));
    $parentNames = trim($_POST['parentNames'] ?? ($_POST['parent_names'] ?? ''));
    $bio = trim($_POST['bio'] ?? '');

    $existing = DB::findOne('profile', []);

    $avatarPath = handleFileUpload('avatar', 'avatars');
    if (!$avatarPath && $existing && !empty($existing['avatar_path'])) {
        $avatarPath = $existing['avatar_path'];
    }

    $data = [
        'baby_name' => $babyName ?: ($existing['baby_name'] ?? 'Emma'),
        'birth_date' => $birthDate ?: ($existing['birth_date'] ?? date('Y-m-d')),
        'parent_names' => $parentNames ?: ($existing['parent_names'] ?? ''),
        'bio' => $bio,
        'avatar_path' => $avatarPath,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    if ($existing && isset($existing['_id'])) {
        DB::update('profile', ['_id' => $existing['_id']], $data);
        $data['_id'] = $existing['_id'];
    } else {
        $id = DB::insert('profile', $data);
        $data['_id'] = $id;
    }

    jsonResponse([
        'success' => true,
        'message' => 'Profile saved successfully',
        'profile' => $data
    ]);
}

sendError('Method not allowed', 405);
