<?php
/**
 * Authentication API
 * BabyBook / TinyTales
 */

require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// GET request
if ($method === 'GET') {
    if ($action === 'me') {
        if (!empty($_SESSION['user_id'])) {
            $user = DB::findOne('users', ['_id' => $_SESSION['user_id']]);
            if ($user) {
                unset($user['password_hash']);
                jsonResponse(['success' => true, 'loggedIn' => true, 'user' => $user]);
            }
        }
        jsonResponse(['success' => true, 'loggedIn' => false, 'user' => null]);
    }

    if ($action === 'status') {
        jsonResponse([
            'success' => true,
            'mongodb_connected' => !DB::isFallback(),
            'storage_mode' => DB::isFallback() ? 'Local file fallback (awaiting MongoDB link)' : 'MongoDB',
            'database' => MONGODB_DATABASE,
            'upload_dir' => UPLOAD_DIR
        ]);
    }

    sendError('Invalid action', 400);
}

// POST request
if ($method === 'POST') {
    // Read input from POST or JSON payload
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true);

    $fullname = trim($_POST['fullname'] ?? ($jsonData['fullname'] ?? ''));
    $email = strtolower(trim($_POST['email'] ?? ($jsonData['email'] ?? '')));
    $password = $_POST['password'] ?? ($jsonData['password'] ?? '');

    if ($action === 'register') {
        if (empty($fullname) || empty($email) || empty($password)) {
            sendError('Full name, email, and password are required');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendError('Please enter a valid email address');
        }

        if (strlen($password) < 6) {
            sendError('Password must be at least 6 characters');
        }

        // Check if user already exists
        $existing = DB::findOne('users', ['email' => $email]);
        if ($existing) {
            sendError('An account with this email already exists');
        }

        $userDoc = [
            'name' => $fullname,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => date('Y-m-d H:i:s')
        ];

        $userId = DB::insert('users', $userDoc);

        // Also seed initial profile for baby if not created
        $existingProfile = DB::findOne('profile', ['user_id' => $userId]);
        if (!$existingProfile) {
            DB::insert('profile', [
                'user_id' => $userId,
                'baby_name' => 'Emma',
                'birth_date' => date('Y-m-d'),
                'parent_names' => $fullname,
                'bio' => 'Our sweet little miracle!',
                'avatar_path' => null,
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }

        $_SESSION['user_id'] = $userId;
        $_SESSION['user_name'] = $fullname;
        $_SESSION['user_email'] = $email;

        jsonResponse([
            'success' => true,
            'message' => 'Account created successfully',
            'user' => [
                'id' => $userId,
                'name' => $fullname,
                'email' => $email
            ]
        ]);
    }

    if ($action === 'login') {
        if (empty($email) || empty($password)) {
            sendError('Email and password are required');
        }

        $user = DB::findOne('users', ['email' => $email]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            sendError('Invalid email or password');
        }

        $_SESSION['user_id'] = (string)$user['_id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email'] = $user['email'];

        unset($user['password_hash']);

        jsonResponse([
            'success' => true,
            'message' => 'Logged in successfully',
            'user' => $user
        ]);
    }

    if ($action === 'logout') {
        session_unset();
        session_destroy();
        jsonResponse(['success' => true, 'message' => 'Logged out successfully']);
    }

    sendError('Invalid authentication action');
}

sendError('Method not allowed', 405);
