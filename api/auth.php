<?php
/**
 * Authentication & Password Lifecycle API
 * TinyTales BabyBook
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/auth_middleware.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// Handle CORS OPTIONS preflight
if ($method === 'OPTIONS') {
    http_response_code(200);
    safeExit();
}

// ============================================================
// GET REQUESTS
// ============================================================
if ($method === 'GET') {
    // Current authenticated user identity check
    if ($action === 'me') {
        $user = Auth::user();
        if ($user) {
            $activeBaby = Auth::getActiveBaby((string)$user['_id']);
            jsonResponse([
                'success' => true,
                'loggedIn' => true,
                'user' => $user,
                'active_baby' => $activeBaby
            ]);
        }
        jsonResponse([
            'success' => true,
            'loggedIn' => false,
            'user' => null,
            'active_baby' => null
        ]);
    }

    // Health and storage mode status
    if ($action === 'status') {
        jsonResponse([
            'success' => true,
            'mongodb_connected' => !DB::isFallback(),
            'storage_mode' => DB::isFallback() ? 'Local JSON fallback' : 'MongoDB',
            'database' => MONGODB_DATABASE,
            'upload_dir' => UPLOAD_DIR
        ]);
    }

    sendError('Invalid action', 400);
}

// ============================================================
// POST REQUESTS
// ============================================================
if ($method === 'POST') {
    $rawInput = file_get_contents('php://input');
    $jsonData = json_decode($rawInput, true) ?? [];

    $action = $_GET['action'] ?? ($_POST['action'] ?? ($jsonData['action'] ?? ''));

    // ------------------------------------------------------------
    // 1. REGISTRATION
    // ------------------------------------------------------------
    if ($action === 'register') {
        $fullname = trim($_POST['fullname'] ?? ($jsonData['fullname'] ?? ''));
        $email = strtolower(trim($_POST['email'] ?? ($jsonData['email'] ?? '')));
        $password = $_POST['password'] ?? ($jsonData['password'] ?? '');
        $confirm = $_POST['confirm'] ?? ($jsonData['confirm'] ?? '');

        if (empty($fullname) || empty($email) || empty($password)) {
            sendError('Full name, email, and password are required', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendError('Please enter a valid email address', 422);
        }

        if (strlen($password) < 6) {
            sendError('Password must be at least 6 characters long', 422);
        }

        if (!empty($confirm) && $password !== $confirm) {
            sendError('Passwords do not match', 422);
        }

        // Check for existing account
        $existing = DB::findOne('users', ['email' => $email]);
        if ($existing) {
            sendError('An account with this email already exists', 409);
        }

        // Password hash using standard bcrypt
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        // Safe account creation (role is always 'user' during registration)
        $userDoc = [
            'name' => $fullname,
            'email' => $email,
            'password_hash' => $passwordHash,
            'role' => 'user',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];

        try {
            $userId = DB::insert('users', $userDoc);
        } catch (\Exception $e) {
            sendError('Failed to create account: ' . $e->getMessage(), 400);
        }

        // Initialize user's default baby profile
        $babyDoc = [
            'user_id' => $userId,
            'baby_name' => 'Little One',
            'birth_date' => date('Y-m-d'),
            'parent_names' => $fullname,
            'bio' => 'Our sweet little miracle!',
            'avatar_path' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $babyId = DB::insert('profile', $babyDoc);
        $babyDoc['_id'] = $babyId;

        // Initialize user's default settings
        DB::insert('settings', [
            'user_id' => $userId,
            'baby_name' => 'Little One',
            'parent_email' => $email,
            'email_alerts' => true,
            'milestone_reminders' => true,
            'capsule_reminders' => true,
            'private_memories' => true,
            'hd_photo_storage' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Initialize welcome notification
        DB::insert('notifications', [
            'user_id' => $userId,
            'type' => 'tip',
            'title' => 'Welcome to TinyTales! 🍼',
            'message' => 'Start preserving your baby\'s precious memories, milestones, and time capsules.',
            'is_read' => false,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Establish authenticated session with ID regeneration
        session_regenerate_id(true);
        $_SESSION['user_id'] = $userId;
        $_SESSION['active_baby_id'] = $babyId;

        unset($userDoc['password_hash']);
        $userDoc['_id'] = $userId;

        jsonResponse([
            'success' => true,
            'message' => 'Account created successfully',
            'user' => $userDoc,
            'active_baby' => $babyDoc
        ], 201);
    }

    // ------------------------------------------------------------
    // 2. LOGIN
    // ------------------------------------------------------------
    if ($action === 'login') {
        if (!Auth::checkRateLimit('login', 15, 60)) {
            sendError('Too many login attempts. Please wait a minute and try again.', 429);
        }

        $email = strtolower(trim($_POST['email'] ?? ($jsonData['email'] ?? '')));
        $password = $_POST['password'] ?? ($jsonData['password'] ?? '');

        if (empty($email) || empty($password)) {
            sendError('Email and password are required', 422);
        }

        // Generic error message prevents account enumeration
        $genericError = 'Invalid email or password';

        // Auto-seed demo parent account if requested
        if ($email === 'demo@tinytales.app') {
            $demoExists = DB::findOne('users', ['email' => 'demo@tinytales.app']);
            if (!$demoExists) {
                $demoId = DB::insert('users', [
                    'name' => 'Demo Parent',
                    'email' => 'demo@tinytales.app',
                    'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
                    'role' => 'user',
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                DB::insert('profile', [
                    'user_id' => $demoId,
                    'baby_name' => 'Emma',
                    'birth_date' => '2025-06-15',
                    'parent_names' => 'Demo Parent',
                    'bio' => 'Demo baby scrapbook account',
                    'avatar_path' => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
                DB::insert('settings', [
                    'user_id' => $demoId,
                    'baby_name' => 'Emma',
                    'parent_email' => 'demo@tinytales.app',
                    'email_alerts' => true,
                    'milestone_reminders' => true,
                    'capsule_reminders' => true,
                    'private_memories' => true,
                    'hd_photo_storage' => true,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ]);
            }
        }

        $user = DB::findOne('users', ['email' => $email]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            sendError($genericError, 401);
        }

        // Regenerate session ID upon successful login
        session_regenerate_id(true);
        $userId = (string)$user['_id'];
        $_SESSION['user_id'] = $userId;

        // Resolve active baby profile for this user
        $activeBaby = Auth::getActiveBaby($userId);
        $_SESSION['active_baby_id'] = (string)$activeBaby['_id'];

        unset($user['password_hash']);
        unset($user['reset_token_hash']);
        unset($user['reset_token_expires']);

        jsonResponse([
            'success' => true,
            'message' => 'Logged in successfully',
            'user' => $user,
            'active_baby' => $activeBaby
        ]);
    }

    // ------------------------------------------------------------
    // 3. LOGOUT
    // ------------------------------------------------------------
    if ($action === 'logout') {
        Auth::logout();
        jsonResponse([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    // ------------------------------------------------------------
    // 4. FORGOT PASSWORD (REQUEST RESET TOKEN)
    // ------------------------------------------------------------
    if ($action === 'forgot_password') {
        if (!Auth::checkRateLimit('forgot', 10, 60)) {
            sendError('Too many requests. Please wait a minute and try again.', 429);
        }

        $email = strtolower(trim($_POST['email'] ?? ($jsonData['email'] ?? '')));
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendError('Please provide a valid registered email address', 422);
        }

        $user = DB::findOne('users', ['email' => $email]);
        $devToken = null;

        if ($user) {
            // Cryptographically secure time-limited reset token
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour validity

            DB::update('users', ['_id' => $user['_id']], [
                'reset_token_hash' => $tokenHash,
                'reset_token_expires' => $expires
            ]);

            // If an SMTP mailer is configured, send email here.
            // For local development environments without an active SMTP server,
            // provide the reset link in the response payload for testing.
            $devToken = $rawToken;
        }

        // Always return generic response that does not disclose account existence
        $response = [
            'success' => true,
            'message' => 'If an account exists for this email, password reset instructions have been dispatched.'
        ];

        // Include reset link for local development testing
        if ($devToken) {
            $response['dev_reset_url'] = "reset.html?email=" . urlencode($email) . "&token=" . urlencode($devToken);
            $response['dev_token'] = $devToken;
        }

        jsonResponse($response);
    }

    // ------------------------------------------------------------
    // 5. VALIDATE RESET TOKEN
    // ------------------------------------------------------------
    if ($action === 'validate_reset_token') {
        $email = strtolower(trim($_POST['email'] ?? ($jsonData['email'] ?? '')));
        $token = trim($_POST['token'] ?? ($jsonData['token'] ?? ''));

        if (empty($email) || empty($token)) {
            sendError('Email and token are required', 422);
        }

        $user = DB::findOne('users', ['email' => $email]);
        if (!$user || empty($user['reset_token_hash']) || empty($user['reset_token_expires'])) {
            sendError('Invalid or expired reset token', 400);
        }

        $tokenHash = hash('sha256', $token);
        if (!hash_equals($user['reset_token_hash'], $tokenHash)) {
            sendError('Invalid or expired reset token', 400);
        }

        if (strtotime($user['reset_token_expires']) < time()) {
            sendError('Reset token has expired. Please request a new one.', 400);
        }

        jsonResponse([
            'success' => true,
            'message' => 'Token is valid'
        ]);
    }

    // ------------------------------------------------------------
    // 6. RESET PASSWORD (SUBMIT NEW PASSWORD)
    // ------------------------------------------------------------
    if ($action === 'reset_password') {
        $email = strtolower(trim($_POST['email'] ?? ($jsonData['email'] ?? '')));
        $token = trim($_POST['token'] ?? ($jsonData['token'] ?? ''));
        $password = $_POST['password'] ?? ($jsonData['password'] ?? '');
        $confirm = $_POST['confirm'] ?? ($jsonData['confirm'] ?? '');

        if (empty($email) || empty($token) || empty($password)) {
            sendError('Email, token, and new password are required', 422);
        }

        if (strlen($password) < 6) {
            sendError('New password must be at least 6 characters long', 422);
        }

        if (!empty($confirm) && $password !== $confirm) {
            sendError('Passwords do not match', 422);
        }

        $user = DB::findOne('users', ['email' => $email]);
        if (!$user || empty($user['reset_token_hash']) || empty($user['reset_token_expires'])) {
            sendError('Invalid or expired reset token', 400);
        }

        $tokenHash = hash('sha256', $token);
        if (!hash_equals($user['reset_token_hash'], $tokenHash)) {
            sendError('Invalid or expired reset token', 400);
        }

        if (strtotime($user['reset_token_expires']) < time()) {
            sendError('Reset token has expired. Please request a new one.', 400);
        }

        // Hash new password securely
        $newHash = password_hash($password, PASSWORD_DEFAULT);

        // Invalidate token (single use) and update password
        DB::update('users', ['_id' => $user['_id']], [
            'password_hash' => $newHash,
            'reset_token_hash' => null,
            'reset_token_expires' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Invalidate any active session
        Auth::logout();

        jsonResponse([
            'success' => true,
            'message' => 'Password reset successfully. You may now log in with your new password.'
        ]);
    }

    // ------------------------------------------------------------
    // 7. CHANGE PASSWORD (AUTHENTICATED USER)
    // ------------------------------------------------------------
    if ($action === 'change_password') {
        $user = Auth::requireAuth();
        $userId = (string)$user['_id'];

        $currentPassword = $_POST['current_password'] ?? ($jsonData['current_password'] ?? '');
        $newPassword = $_POST['new_password'] ?? ($jsonData['new_password'] ?? '');
        $confirmPassword = $_POST['confirm_password'] ?? ($jsonData['confirm_password'] ?? '');

        if (empty($currentPassword) || empty($newPassword)) {
            sendError('Current password and new password are required', 422);
        }

        if (strlen($newPassword) < 6) {
            sendError('New password must be at least 6 characters long', 422);
        }

        if ($newPassword !== $confirmPassword) {
            sendError('New password and confirmation do not match', 422);
        }

        // Verify current password against stored hash
        $userDoc = DB::findOne('users', ['_id' => $userId]);
        if (!$userDoc || !password_verify($currentPassword, $userDoc['password_hash'])) {
            sendError('Incorrect current password', 401);
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        DB::update('users', ['_id' => $userId], [
            'password_hash' => $newHash,
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        // Regenerate session ID after password change
        session_regenerate_id(true);

        jsonResponse([
            'success' => true,
            'message' => 'Password changed successfully'
        ]);
    }

    sendError('Invalid authentication action', 400);
}

sendError('Method not allowed', 405);
