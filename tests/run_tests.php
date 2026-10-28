<?php
/**
 * TinyTales End-to-End & Two-User Isolation Automated Test Suite
 * Validates Authentication, Authorization, Data Ownership & Module Regressions
 */

define('TESTING_MODE', true);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../api/auth_middleware.php';

class TestRunner {
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function assert(bool $condition, string $testName, string $details = '') {
        if ($condition) {
            $this->passed++;
            echo "  [PASS] {$testName}\n";
        } else {
            $this->failed++;
            $msg = "  [FAIL] {$testName}" . ($details ? " - {$details}" : '');
            $this->failures[] = $msg;
            echo "{$msg}\n";
        }
    }

    public function printSummary() {
        echo "\n" . str_repeat('=', 65) . "\n";
        echo "TEST SUMMARY: {$this->passed} Passed, {$this->failed} Failed\n";
        echo str_repeat('=', 65) . "\n";
        if ($this->failed > 0) {
            echo "FAILURES:\n";
            foreach ($this->failures as $f) {
                echo "{$f}\n";
            }
        } else {
            echo "ALL TESTS PASSED SUCCESSFULLY! Security & Data Isolation Verified.\n";
        }
        return $this->failed === 0;
    }
}

class TestClient {
    public array $session = [];
    public ?string $activeUserId = null;

    public function request(string $endpoint, string $method = 'GET', array $params = [], array $body = [], array $files = []): array {
        // Save current session
        $oldSession = $_SESSION;
        $_SESSION = $this->session;

        // Parse query string from endpoint if present
        $parts = explode('?', $endpoint, 2);
        $script = $parts[0];
        $mergedParams = $params;
        if (isset($parts[1])) {
            parse_str($parts[1], $queryVars);
            $mergedParams = array_merge($mergedParams, $queryVars);
        }

        // Setup mock environment
        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_GET = $mergedParams;
        $_POST = $body;
        $_FILES = $files;

        $GLOBALS['LAST_RESPONSE_CODE'] = 200;
        http_response_code(200);

        // Capture output
        ob_start();
        $targetFile = __DIR__ . '/../api/' . $script;
        
        try {
            // Run endpoint in isolated closure
            (function($__file) {
                require $__file;
            })($targetFile);
        } catch (TestingExitException $e) {
            // Normal exit simulation
        } catch (\Throwable $e) {
            echo "Unexpected error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
        }

        $output = ob_get_clean();
        $statusCode = $GLOBALS['LAST_RESPONSE_CODE'] ?? http_response_code();

        // Update local session from modified $_SESSION
        $this->session = $_SESSION;
        if (!empty($_SESSION['user_id'])) {
            $this->activeUserId = $_SESSION['user_id'];
        } else {
            $this->activeUserId = null;
        }

        // Restore global session
        $_SESSION = $oldSession;

        $json = json_decode($output, true);
        return [
            'status' => $statusCode,
            'body' => $json,
            'raw' => $output
        ];
    }
}

// Instantiate Runner
$t = new TestRunner();
$timestamp = time();

// Create isolated data backup for test run
$dataDir = __DIR__ . '/../data';
$backupDir = __DIR__ . '/../data_test_backup';
if (is_dir($dataDir)) {
    @mkdir($backupDir, 0777, true);
    foreach (glob($dataDir . '/*.json') as $jsonFile) {
        copy($jsonFile, $backupDir . '/' . basename($jsonFile));
    }
}

// Ensure cleanup on shutdown
register_shutdown_function(function() use ($dataDir, $backupDir) {
    if (is_dir($backupDir)) {
        foreach (glob($backupDir . '/*.json') as $backupFile) {
            copy($backupFile, $dataDir . '/' . basename($backupFile));
            @unlink($backupFile);
        }
        @rmdir($backupDir);
    }
});

echo "\n" . str_repeat('=', 65) . "\n";
echo "1. AUTHENTICATION & ACCESS CONTROL TESTS\n";
echo str_repeat('=', 65) . "\n";

$client = new TestClient();

// Test 1: Valid registration succeeds
$regA = $client->request('auth.php?action=register', 'POST', ['action' => 'register'], [
    'fullname' => 'Test Parent Alpha',
    'email' => "user_a_{$timestamp}@tinytales.app",
    'password' => 'SecurePass123!',
    'confirm' => 'SecurePass123!'
]);
$t->assert($regA['status'] === 201 && !empty($regA['body']['success']), 'Valid registration succeeds (201 Created)');
$userAId = $regA['body']['user']['_id'] ?? null;
$t->assert(!empty($userAId), 'User A ID generated upon registration');
$sessionA = $client->session;

// Test 2: Invalid registration fails (missing fields / short password)
$regInvalid = (new TestClient())->request('auth.php?action=register', 'POST', ['action' => 'register'], [
    'fullname' => '',
    'email' => 'invalid-email',
    'password' => '123'
]);
$t->assert($regInvalid['status'] === 422 && empty($regInvalid['body']['success']), 'Invalid registration fails with validation error');

// Test 3: Duplicate email registration is rejected
$regDup = (new TestClient())->request('auth.php?action=register', 'POST', ['action' => 'register'], [
    'fullname' => 'Duplicate Parent',
    'email' => "user_a_{$timestamp}@tinytales.app",
    'password' => 'AnotherPass123!',
    'confirm' => 'AnotherPass123!'
]);
$t->assert($regDup['status'] === 409, 'Duplicate email registration is rejected (409 Conflict)');

// Test 4: Passwords are stored as hashes (never plaintext)
$userDoc = DB::findOne('users', ['_id' => $userAId]);
$t->assert(!empty($userDoc['password_hash']) && str_starts_with($userDoc['password_hash'], '$2y$'), 'Passwords are stored as bcrypt hashes');
$t->assert(!isset($userDoc['password']) && !in_array('SecurePass123!', $userDoc), 'Plaintext password is never stored');

// Test 5: Valid login succeeds
$loginClient = new TestClient();
$loginA = $loginClient->request('auth.php?action=login', 'POST', ['action' => 'login'], [
    'email' => "user_a_{$timestamp}@tinytales.app",
    'password' => 'SecurePass123!'
]);
$t->assert($loginA['status'] === 200 && !empty($loginA['body']['success']), 'Valid login succeeds');
$t->assert(!empty($loginClient->session['user_id']), 'Session is established on successful login');

// Test 6: Invalid credentials fail with generic error
$loginBad = (new TestClient())->request('auth.php?action=login', 'POST', ['action' => 'login'], [
    'email' => "user_a_{$timestamp}@tinytales.app",
    'password' => 'WrongPassword!'
]);
$t->assert($loginBad['status'] === 401, 'Invalid credentials fail (401 Unauthorized)');
$t->assert($loginBad['body']['error'] === 'Invalid email or password', 'Generic error message prevents account enumeration');

// Test 7: Unauthenticated API requests are rejected (401)
$unauthClient = new TestClient();
$unauthMemories = $unauthClient->request('memories.php', 'GET');
$t->assert($unauthMemories['status'] === 401, 'Unauthenticated GET /api/memories.php rejected (401)');
$unauthStats = $unauthClient->request('stats.php', 'GET');
$t->assert($unauthStats['status'] === 401, 'Unauthenticated GET /api/stats.php rejected (401)');

// Test 8: Logout invalidates the session
$logoutRes = $loginClient->request('auth.php?action=logout', 'POST', ['action' => 'logout']);
$t->assert($logoutRes['status'] === 200 && empty($loginClient->session['user_id']), 'Logout clears session identity');

// Test 9: Forgot-password responses do not reveal account existence
$forgotReal = (new TestClient())->request('auth.php?action=forgot_password', 'POST', ['action' => 'forgot_password'], [
    'email' => "user_a_{$timestamp}@tinytales.app"
]);
$forgotFake = (new TestClient())->request('auth.php?action=forgot_password', 'POST', ['action' => 'forgot_password'], [
    'email' => "nonexistent_{$timestamp}@tinytales.app"
]);
$t->assert($forgotReal['body']['message'] === $forgotFake['body']['message'], 'Forgot-password returns identical generic message for real and nonexistent accounts');

// Test 10: Valid reset tokens allow a password reset
$devToken = $forgotReal['body']['dev_token'] ?? null;
$t->assert(!empty($devToken), 'Cryptographically secure reset token generated');

$tokenHash = hash('sha256', $devToken);
$userAfterForgot = DB::findOne('users', ['_id' => $userAId]);
$t->assert($userAfterForgot['reset_token_hash'] === $tokenHash, 'Reset token stored only as secure SHA256 hash');

// Test 11: Validate token endpoint accepts valid token and rejects invalid
$valValid = (new TestClient())->request('auth.php?action=validate_reset_token', 'POST', ['action' => 'validate_reset_token'], [
    'email' => "user_a_{$timestamp}@tinytales.app",
    'token' => $devToken
]);
$t->assert($valValid['status'] === 200, 'Valid reset token passes verification');

$valInvalid = (new TestClient())->request('auth.php?action=validate_reset_token', 'POST', ['action' => 'validate_reset_token'], [
    'email' => "user_a_{$timestamp}@tinytales.app",
    'token' => 'invalid_forged_token_12345'
]);
$t->assert($valInvalid['status'] === 400, 'Forged reset token is rejected');

// Test 12: Password reset succeeds and invalidates token
$resetExec = (new TestClient())->request('auth.php?action=reset_password', 'POST', ['action' => 'reset_password'], [
    'email' => "user_a_{$timestamp}@tinytales.app",
    'token' => $devToken,
    'password' => 'NewPassword123!',
    'confirm' => 'NewPassword123!'
]);
$t->assert($resetExec['status'] === 200 && !empty($resetExec['body']['success']), 'Password reset succeeds with new password');

// Reused token is rejected
$resetReused = (new TestClient())->request('auth.php?action=reset_password', 'POST', ['action' => 'reset_password'], [
    'email' => "user_a_{$timestamp}@tinytales.app",
    'token' => $devToken,
    'password' => 'AnotherPass123!',
    'confirm' => 'AnotherPass123!'
]);
$t->assert($resetReused['status'] === 400, 'Reused reset token is rejected (single use enforced)');

// Login with new password succeeds; old password fails
$loginOld = (new TestClient())->request('auth.php?action=login', 'POST', ['action' => 'login'], [
    'email' => "user_a_{$timestamp}@tinytales.app",
    'password' => 'SecurePass123!'
]);
$t->assert($loginOld['status'] === 401, 'Old password no longer works after reset');

$loginNew = (new TestClient())->request('auth.php?action=login', 'POST', ['action' => 'login'], [
    'email' => "user_a_{$timestamp}@tinytales.app",
    'password' => 'NewPassword123!'
]);
$t->assert($loginNew['status'] === 200, 'Login with new password succeeds');

// Test 13: Password change for logged in user
$changeClient = new TestClient();
$changeClient->session = $loginNewClientSession = ['user_id' => $userAId];
$changePwd = $changeClient->request('auth.php?action=change_password', 'POST', ['action' => 'change_password'], [
    'current_password' => 'NewPassword123!',
    'new_password' => 'FinalPassword456!',
    'confirm_password' => 'FinalPassword456!'
]);
$t->assert($changePwd['status'] === 200 && !empty($changePwd['body']['success']), 'Password change succeeds with correct current password');

$changeBadCurrent = $changeClient->request('auth.php?action=change_password', 'POST', ['action' => 'change_password'], [
    'current_password' => 'IncorrectCurrentPwd!',
    'new_password' => 'SomePass999!',
    'confirm_password' => 'SomePass999!'
]);
$t->assert($changeBadCurrent['status'] === 401, 'Password change rejected with incorrect current password');

// Test 14: Mass-assignment protection - ordinary user cannot elevate role
$updateProfileRole = $changeClient->request('profile.php', 'POST', [], [
    'baby_name' => 'Emma Super',
    'role' => 'admin',
    'user_id' => '000000000000000000000000'
]);
$userDocCheck = DB::findOne('users', ['_id' => $userAId]);
$t->assert($userDocCheck['role'] === 'user', 'Ordinary user role cannot be elevated to admin via mutation');

echo "\n" . str_repeat('=', 65) . "\n";
echo "2. TWO-USER ISOLATION TESTS (USER A vs USER B)\n";
echo str_repeat('=', 65) . "\n";

// Register User A
$clientA = new TestClient();
$clientA->request('auth.php?action=register', 'POST', ['action' => 'register'], [
    'fullname' => 'Alice Parent',
    'email' => "alice_{$timestamp}@tinytales.app",
    'password' => 'AlicePass123!',
    'confirm' => 'AlicePass123!'
]);
$aliceId = $clientA->activeUserId;

// Register User B
$clientB = new TestClient();
$clientB->request('auth.php?action=register', 'POST', ['action' => 'register'], [
    'fullname' => 'Bob Parent',
    'email' => "bob_{$timestamp}@tinytales.app",
    'password' => 'BobPass123!',
    'confirm' => 'BobPass123!'
]);
$bobId = $clientB->activeUserId;

$t->assert(!empty($aliceId) && !empty($bobId) && $aliceId !== $bobId, 'User A (Alice) and User B (Bob) registered independently');

// 1. User A creates resources:
// 1.1 Alice updates her baby profile
$aliceProfileRes = $clientA->request('profile.php', 'POST', [], [
    'baby_name' => 'Alice Baby Lily',
    'birth_date' => '2025-05-10',
    'parent_names' => 'Alice & Partner',
    'bio' => 'Alice secret baby story'
]);
$aliceBabyId = $aliceProfileRes['body']['profile']['_id'] ?? null;
$t->assert(!empty($aliceBabyId), 'User A baby profile created with name Lily');

// 1.2 Alice adds a memory
$aliceMemRes = $clientA->request('memories.php', 'POST', [], [
    'title' => 'Alice Secret Memory #1',
    'desc' => 'Alice private note about first tooth',
    'date' => '2026-05-12',
    'category' => 'Milestone',
    'photo_url' => 'uploads/photos/alice_photo.jpg'
]);
$aliceMemId = $aliceMemRes['body']['memory']['_id'] ?? null;
$t->assert(!empty($aliceMemId), 'User A created memory #1');

// 1.3 Alice toggles favorite on her memory
$aliceFavRes = $clientA->request('memories.php?action=toggle_favorite', 'POST', ['action' => 'toggle_favorite'], [
    'id' => $aliceMemId
]);
$t->assert(!empty($aliceFavRes['body']['is_favorite']), 'User A toggled favorite on memory #1');

// 1.4 Alice adds a milestone
$aliceMsRes = $clientA->request('milestones.php', 'POST', [], [
    'title' => 'Alice First Steps',
    'category' => 'physical',
    'date' => '2026-06-01'
]);
$aliceMsId = $aliceMsRes['body']['milestone']['_id'] ?? null;
$t->assert(!empty($aliceMsId), 'User A created milestone');

// 1.5 Alice adds a future locked time capsule
$aliceCapRes = $clientA->request('capsules.php', 'POST', [], [
    'title' => 'Alice Capsule For 2040',
    'message' => 'Super top secret message for Lily in 2040',
    'unlock_date' => '2040-01-01'
]);
$aliceCapId = $aliceCapRes['body']['capsule']['_id'] ?? null;
$t->assert(!empty($aliceCapId), 'User A created time capsule');

// 1.6 Alice adds a scrapbook entry
$aliceSbRes = $clientA->request('scrapbook.php', 'POST', [], [
    'title' => 'Alice Polaroid Sunshine',
    'date' => '2026-06-15',
    'mood' => '😊 Joyful',
    'note' => 'Alice scrapbook diary entry'
]);
$aliceSbId = $aliceSbRes['body']['item']['_id'] ?? null;
$t->assert(!empty($aliceSbId), 'User A created scrapbook note');

// 1.7 Alice updates settings
$aliceSettingsSave = $clientA->request('settings.php', 'POST', [], [
    'baby_name' => 'Alice Baby Lily',
    'parent_email' => "alice_secret_{$timestamp}@tinytales.app"
]);
$t->assert($aliceSettingsSave['status'] === 200 && !empty($aliceSettingsSave['body']['success']), 'User A can save profile & family settings');

// 2. User A can retrieve her own records
$aliceMemories = $clientA->request('memories.php', 'GET');
$t->assert(count($aliceMemories['body']['memories']) >= 1, 'User A can retrieve her own memories');

$aliceCapsules = $clientA->request('capsules.php', 'GET');
$t->assert(count($aliceCapsules['body']['capsules']) >= 1, 'User A can retrieve her own capsules');

$aliceStats = $clientA->request('stats.php', 'GET');
$t->assert($aliceStats['body']['baby_name'] === 'Alice Baby Lily', 'User A stats shows her baby Lily');

// 3. USER B LOGS IN AND SEES NONE OF USER A'S RECORDS
$bobMemories = $clientB->request('memories.php', 'GET');
$bobMemoryTitles = array_column($bobMemories['body']['memories'] ?? [], 'title');
$t->assert(!in_array('Alice Secret Memory #1', $bobMemoryTitles), 'User B sees NONE of User A memories');

$bobMilestones = $clientB->request('milestones.php', 'GET');
$bobMilestoneTitles = array_column($bobMilestones['body']['milestones'] ?? [], 'title');
$t->assert(!in_array('Alice First Steps', $bobMilestoneTitles), 'User B sees NONE of User A milestones');

$bobCapsules = $clientB->request('capsules.php', 'GET');
$bobCapTitles = array_column($bobCapsules['body']['capsules'] ?? [], 'title');
$t->assert(!in_array('Alice Capsule For 2040', $bobCapTitles), 'User B sees NONE of User A capsules');

$bobScrapbook = $clientB->request('scrapbook.php', 'GET');
$bobSbTitles = array_column($bobScrapbook['body']['items'] ?? [], 'title');
$t->assert(!in_array('Alice Polaroid Sunshine', $bobSbTitles), 'User B sees NONE of User A scrapbook items');

$bobProfile = $clientB->request('profile.php', 'GET');
$t->assert($bobProfile['body']['profile']['baby_name'] !== 'Alice Baby Lily', 'User B sees his own baby, not User A baby Lily');

$bobSettings = $clientB->request('settings.php', 'GET');
$t->assert($bobSettings['body']['settings']['parent_email'] !== "alice_secret_{$timestamp}@tinytales.app", 'User B cannot see User A settings');

// 4. USER B CREATES HIS OWN RECORDS
$bobMemRes = $clientB->request('memories.php', 'POST', [], [
    'title' => 'Bob Baby First Laugh',
    'desc' => 'Bob personal note',
    'date' => '2026-07-01',
    'photo_url' => 'uploads/photos/bob_photo.jpg'
]);
$bobMemId = $bobMemRes['body']['memory']['_id'] ?? null;
$t->assert(!empty($bobMemId), 'User B created his own memory');

// User B sees only his memory
$bobMemoriesAfter = $clientB->request('memories.php', 'GET');
$t->assert(count($bobMemoriesAfter['body']['memories']) === 1 && $bobMemoriesAfter['body']['memories'][0]['title'] === 'Bob Baby First Laugh', 'User B sees only his own memory');

// 5. USER B CANNOT ACCESS USER A'S RECORDS BY ID OR QUERY PARAMETER
$bobAccessAliceBaby = $clientB->request('profile.php', 'GET', ['id' => $aliceBabyId]);
$t->assert($bobAccessAliceBaby['status'] === 404, 'User B cannot retrieve User A baby profile by ID (404/denied)');

// 6. USER B CANNOT EDIT OR DELETE USER A'S RECORDS
$bobEditAliceBaby = $clientB->request('profile.php', 'POST', [], [
    'id' => $aliceBabyId,
    'baby_name' => 'Hacked by Bob'
]);
$t->assert($bobEditAliceBaby['status'] === 403, 'User B cannot edit User A baby profile (403 Forbidden)');

$bobDeleteAliceMemory = $clientB->request('memories.php?action=delete', 'POST', ['action' => 'delete'], [
    'id' => $aliceMemId
]);
$t->assert($bobDeleteAliceMemory['status'] === 403, 'User B cannot delete User A memory (403 Forbidden)');

$bobFavAliceMemory = $clientB->request('memories.php?action=toggle_favorite', 'POST', ['action' => 'toggle_favorite'], [
    'id' => $aliceMemId
]);
$t->assert($bobFavAliceMemory['status'] === 403, 'User B cannot toggle favorite on User A memory (403 Forbidden)');

$bobDeleteAliceMs = $clientB->request('milestones.php?action=delete', 'POST', ['action' => 'delete'], [
    'id' => $aliceMsId
]);
$t->assert($bobDeleteAliceMs['status'] === 403, 'User B cannot delete User A milestone (403 Forbidden)');

$bobDeleteAliceCap = $clientB->request('capsules.php?action=delete', 'POST', ['action' => 'delete'], [
    'id' => $aliceCapId
]);
$t->assert($bobDeleteAliceCap['status'] === 403, 'User B cannot delete User A capsule (403 Forbidden)');

// Verify User A memory is still intact
$aliceVerify = $clientA->request('memories.php', 'GET');
$t->assert(count($aliceVerify['body']['memories']) >= 1 && $aliceVerify['body']['memories'][0]['_id'] === $aliceMemId, 'User A memory was NOT deleted or altered by User B');

// 7. USER B CANNOT ATTACH RECORDS TO USER A'S BABY PROFILE
$bobAttachAliceBaby = $clientB->request('memories.php', 'POST', [], [
    'title' => 'Cross Baby Attack',
    'baby_id' => $aliceBabyId,
    'photo_url' => 'uploads/photos/cross.jpg'
]);
$t->assert($bobAttachAliceBaby['body']['memory']['baby_id'] !== $aliceBabyId, 'User B cannot bind records to User A baby profile');

// 8. USER B CANNOT ACCESS USER A'S NOTIFICATIONS
$aliceNotifs = $clientA->request('notifications.php', 'GET');
$aliceNotifId = $aliceNotifs['body']['notifications'][0]['_id'] ?? null;
$t->assert(!empty($aliceNotifId), 'User A has private notifications');

$bobReadAliceNotif = $clientB->request('notifications.php?action=mark_read', 'POST', ['action' => 'mark_read'], [
    'id' => $aliceNotifId
]);
$t->assert($bobReadAliceNotif['status'] === 403, 'User B cannot mark User A notification as read (403 Forbidden)');

$bobDeleteAliceNotif = $clientB->request('notifications.php?action=delete', 'POST', ['action' => 'delete'], [
    'id' => $aliceNotifId
]);
$t->assert($bobDeleteAliceNotif['status'] === 403, 'User B cannot delete User A notification (403 Forbidden)');

// 9. TIME CAPSULE CONTENTS ARE NEVER LEAKED BEFORE UNLOCK DATE
$aliceCapList = $clientA->request('capsules.php', 'GET');
$capEntry = $aliceCapList['body']['capsules'][0] ?? [];
$t->assert(!empty($capEntry['is_locked']), 'Future capsule is marked as is_locked = true');
$t->assert(!isset($capEntry['message']), 'Message content is stripped from API payload for locked capsule');
$t->assert(!isset($capEntry['media_path']), 'Media path is stripped from API payload for locked capsule');
$t->assert($capEntry['preview_message'] === 'Locked until 2040-01-01', 'Preview placeholder is returned instead of sensitive content');

// 10. DASHBOARD STATS REMAIN STRICTLY USER-SPECIFIC
$aliceStatsFinal = $clientA->request('stats.php', 'GET');
$bobStatsFinal = $clientB->request('stats.php', 'GET');

$t->assert($aliceStatsFinal['body']['stats']['total_memories'] === 1, 'User A dashboard total_memories = 1');
$t->assert($bobStatsFinal['body']['stats']['total_memories'] === 2, 'User B dashboard total_memories = 2');
$t->assert($aliceStatsFinal['body']['stats']['favorites'] === 1, 'User A favorites count = 1');
$t->assert($bobStatsFinal['body']['stats']['favorites'] === 0, 'User B favorites count = 0 (unaffected by User A)');

// 11. CALENDAR VIEW IS USER-SPECIFIC
$aliceCal = $clientA->request('calendar.php?year=2026&month=5', 'GET', ['year' => '2026', 'month' => '5']);
$bobCal = $clientB->request('calendar.php?year=2026&month=5', 'GET', ['year' => '2026', 'month' => '5']);

$t->assert(isset($aliceCal['body']['events']['2026-05-12']), 'User A calendar contains her May 12 event');
$t->assert(!isset($bobCal['body']['events']['2026-05-12']), 'User B calendar does NOT contain User A May 12 event');

// 12. SCRAPBOOK CLEAR ONLY AFFECTS CALLER'S RECORDS
$clientB->request('scrapbook.php', 'POST', [], [
    'title' => 'Bob Scrapbook Entry',
    'date' => '2026-07-10',
    'note' => 'Bob note'
]);
$clientB->request('scrapbook.php?action=clear', 'POST', ['action' => 'clear']);
$bobSbAfterClear = $clientB->request('scrapbook.php', 'GET');
$t->assert(count($bobSbAfterClear['body']['items']) === 0, 'User B cleared his own scrapbook');

$aliceSbAfterClear = $clientA->request('scrapbook.php', 'GET');
$t->assert(count($aliceSbAfterClear['body']['items']) === 1, 'User A scrapbook was untouched by User B clear operation');

echo "\n" . str_repeat('=', 65) . "\n";
echo "3. MODULE REGRESSION & END-TO-END WORKFLOW TESTS\n";
echo str_repeat('=', 65) . "\n";

// Multi-baby profile management
$aliceBaby2 = $clientA->request('profile.php?action=create', 'POST', ['action' => 'create'], [
    'baby_name' => 'Alice Baby Noah',
    'birth_date' => '2026-01-01'
]);
$t->assert($aliceBaby2['status'] === 201, 'User A can create a second baby profile (Noah)');
$noahId = $aliceBaby2['body']['profile']['_id'] ?? null;

$aliceAllBabies = $clientA->request('profile.php', 'GET');
$t->assert(count($aliceAllBabies['body']['babies']) === 2, 'User A has 2 baby profiles');

// Switch active baby
$switchRes = $clientA->request('profile.php?action=select', 'POST', ['action' => 'select'], [
    'id' => $noahId
]);
$t->assert($switchRes['status'] === 200, 'User A can switch active baby to Noah');

// Stats now shows Noah
$aliceStatsNoah = $clientA->request('stats.php', 'GET');
$t->assert($aliceStatsNoah['body']['baby_name'] === 'Alice Baby Noah', 'Dashboard reflects active baby switch');

// Switch back to Lily
$clientA->request('profile.php?action=select', 'POST', ['action' => 'select'], [
    'id' => $aliceBabyId
]);

// Memory deletion workflow
$memToDelete = $clientA->request('memories.php', 'POST', [], [
    'title' => 'Temp Memory to Delete',
    'photo_url' => 'uploads/photos/temp.jpg'
]);
$tempMemId = $memToDelete['body']['memory']['_id'] ?? null;
$delRes = $clientA->request('memories.php?action=delete', 'POST', ['action' => 'delete'], [
    'id' => $tempMemId
]);
$t->assert($delRes['status'] === 200 && !empty($delRes['body']['success']), 'User can delete their own memory');

// Milestone deletion workflow
$msToDelete = $clientA->request('milestones.php', 'POST', [], [
    'title' => 'Temp Milestone to Delete',
    'category' => 'social',
    'date' => '2026-08-01'
]);
$tempMsId = $msToDelete['body']['milestone']['_id'] ?? null;
$delMsRes = $clientA->request('milestones.php?action=delete', 'POST', ['action' => 'delete'], [
    'id' => $tempMsId
]);
$t->assert($delMsRes['status'] === 200, 'User can delete their own milestone');

// Time Capsule deletion workflow
$capToDelete = $clientA->request('capsules.php', 'POST', [], [
    'title' => 'Temp Capsule to Delete',
    'message' => 'To be deleted',
    'unlock_date' => '2030-01-01'
]);
$tempCapId = $capToDelete['body']['capsule']['_id'] ?? null;
$delCapRes = $clientA->request('capsules.php?action=delete', 'POST', ['action' => 'delete'], [
    'id' => $tempCapId
]);
$t->assert($delCapRes['status'] === 200, 'User can delete their own time capsule');

// Unlocked capsule workflow
$unlockedCap = $clientA->request('capsules.php', 'POST', [], [
    'title' => 'Past Unlocked Capsule',
    'message' => 'Welcome to the present day!',
    'unlock_date' => '2020-01-01' // In the past
]);
$unlockedList = $clientA->request('capsules.php', 'GET');
$pastCap = null;
foreach ($unlockedList['body']['capsules'] as $c) {
    if ($c['title'] === 'Past Unlocked Capsule') {
        $pastCap = $c;
        break;
    }
}
$t->assert(!empty($pastCap) && empty($pastCap['is_locked']), 'Capsule with past date is unlocked');
$t->assert(isset($pastCap['message']) && $pastCap['message'] === 'Welcome to the present day!', 'Unlocked capsule displays full message');

// Final summary
$success = $t->printSummary();
exit($success ? 0 : 1);
