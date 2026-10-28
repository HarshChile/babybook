<?php
/**
 * Baby Profile Management API
 * TinyTales BabyBook
 * Isolated strictly per user with multi-baby profile support
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';

$user = Auth::requireAuth();
$userId = (string)$user['_id'];

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// GET: Retrieve user's baby profile(s)
if ($method === 'GET') {
    // If specific baby ID requested, verify ownership
    if (!empty($_GET['id'])) {
        $babyId = $_GET['id'];
        $baby = Auth::verifyBabyOwnership($babyId, $userId);
        if (!$baby) {
            sendError('Baby profile not found or access denied', 404);
        }
        jsonResponse([
            'success' => true,
            'profile' => $baby,
            'baby' => $baby
        ]);
    }

    $activeBaby = Auth::getActiveBaby($userId);
    $allBabies = DB::find('profile', ['user_id' => $userId], ['sort' => ['created_at' => 1]]);

    jsonResponse([
        'success' => true,
        'profile' => $activeBaby,
        'baby' => $activeBaby,
        'active_baby' => $activeBaby,
        'babies' => $allBabies
    ]);
}

// POST: Manage baby profiles
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true) ?? [];

    $action = $_GET['action'] ?? ($_POST['action'] ?? ($jsonData['action'] ?? ''));

    // Action: Select active baby
    if ($action === 'select') {
        $babyId = $_POST['id'] ?? ($_POST['baby_id'] ?? ($jsonData['id'] ?? ($jsonData['baby_id'] ?? '')));
        if (empty($babyId)) {
            sendError('Baby ID required', 422);
        }

        $baby = Auth::verifyBabyOwnership($babyId, $userId);
        if (!$baby) {
            sendError('Unauthorized. You do not own this baby profile.', 403);
        }

        $_SESSION['active_baby_id'] = (string)$baby['_id'];
        DB::update('users', ['_id' => $userId], ['active_baby_id' => (string)$baby['_id']]);

        jsonResponse([
            'success' => true,
            'message' => 'Active baby profile selected',
            'active_baby' => $baby
        ]);
    }

    // Action: Create new baby profile
    if ($action === 'create') {
        $babyName = trim($_POST['babyName'] ?? ($_POST['baby_name'] ?? ($jsonData['baby_name'] ?? '')));
        $birthDate = trim($_POST['birthDate'] ?? ($_POST['birth_date'] ?? ($jsonData['birth_date'] ?? '')));
        $parentNames = trim($_POST['parentNames'] ?? ($_POST['parent_names'] ?? ($jsonData['parent_names'] ?? '')));
        $bio = trim($_POST['bio'] ?? ($jsonData['bio'] ?? ''));

        if (empty($babyName)) {
            sendError('Baby name is required', 422);
        }

        $avatarPath = handleFileUpload('avatar', 'avatars');

        $newBaby = [
            'user_id' => $userId,
            'baby_name' => $babyName,
            'birth_date' => $birthDate ?: date('Y-m-d'),
            'parent_names' => $parentNames ?: ($user['name'] ?? ''),
            'bio' => $bio,
            'avatar_path' => $avatarPath,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $newId = DB::insert('profile', $newBaby);
        $newBaby['_id'] = $newId;
        $_SESSION['active_baby_id'] = $newId;
        DB::update('users', ['_id' => $userId], ['active_baby_id' => $newId]);

        jsonResponse([
            'success' => true,
            'message' => 'New baby profile created successfully',
            'profile' => $newBaby,
            'baby' => $newBaby
        ], 201);
    }

    // Action: Delete baby profile
    if ($action === 'delete') {
        $babyId = $_POST['id'] ?? ($_POST['baby_id'] ?? ($jsonData['id'] ?? ''));
        if (empty($babyId)) {
            sendError('Baby ID required', 422);
        }

        $baby = Auth::verifyBabyOwnership($babyId, $userId);
        if (!$baby) {
            sendError('Baby profile not found or access denied', 403);
        }

        if (!empty($baby['avatar_path'])) {
            deleteFileFromDisk($baby['avatar_path']);
        }

        DB::delete('profile', ['_id' => $babyId, 'user_id' => $userId]);

        // If active baby was deleted, switch to another owned baby
        if (($_SESSION['active_baby_id'] ?? '') === $babyId) {
            $other = DB::findOne('profile', ['user_id' => $userId]);
            if ($other) {
                $_SESSION['active_baby_id'] = (string)$other['_id'];
                DB::update('users', ['_id' => $userId], ['active_baby_id' => (string)$other['_id']]);
            } else {
                unset($_SESSION['active_baby_id']);
                DB::update('users', ['_id' => $userId], ['active_baby_id' => null]);
            }
        }

        jsonResponse(['success' => true, 'message' => 'Baby profile deleted successfully']);
    }

    // Default Action: Update current or active baby profile
    $targetBabyId = $_POST['id'] ?? ($_POST['baby_id'] ?? ($jsonData['id'] ?? null));

    if ($targetBabyId) {
        $existing = Auth::verifyBabyOwnership($targetBabyId, $userId);
        if (!$existing) {
            sendError('Baby profile not found or access denied', 403);
        }
    } else {
        $existing = Auth::getActiveBaby($userId);
    }

    $babyName = trim($_POST['babyName'] ?? ($_POST['baby_name'] ?? ($jsonData['baby_name'] ?? '')));
    $birthDate = trim($_POST['birthDate'] ?? ($_POST['birth_date'] ?? ($jsonData['birth_date'] ?? '')));
    $parentNames = trim($_POST['parentNames'] ?? ($_POST['parent_names'] ?? ($jsonData['parent_names'] ?? '')));
    $bio = trim($_POST['bio'] ?? ($jsonData['bio'] ?? ''));

    $avatarPath = handleFileUpload('avatar', 'avatars');
    if (!$avatarPath && $existing && !empty($existing['avatar_path'])) {
        $avatarPath = $existing['avatar_path'];
    }

    // Safe mutation - prevent mass assignment of protected fields (id, user_id)
    $data = [
        'baby_name' => $babyName ?: ($existing['baby_name'] ?? 'Emma'),
        'birth_date' => $birthDate ?: ($existing['birth_date'] ?? date('Y-m-d')),
        'parent_names' => $parentNames ?: ($existing['parent_names'] ?? ''),
        'bio' => $bio,
        'avatar_path' => $avatarPath,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $babyId = (string)$existing['_id'];
    DB::update('profile', ['_id' => $babyId, 'user_id' => $userId], $data);
    $data['_id'] = $babyId;
    $data['user_id'] = $userId;

    // Sync baby name to user settings if settings exist
    $userSetting = DB::findOne('settings', ['user_id' => $userId]);
    if ($userSetting && !empty($babyName)) {
        DB::update('settings', ['_id' => $userSetting['_id']], ['baby_name' => $babyName]);
    }

    jsonResponse([
        'success' => true,
        'message' => 'Baby profile saved successfully',
        'profile' => $data,
        'baby' => $data
    ]);
}

sendError('Method not allowed', 405);
