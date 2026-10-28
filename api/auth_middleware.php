<?php
/**
 * Centralized Authentication & Authorization Middleware
 * TinyTales BabyBook
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db.php';

class Auth {
    /**
     * Get current authenticated user ID from session
     */
    public static function id() {
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Check if current session is authenticated
     */
    public static function check() {
        return !empty($_SESSION['user_id']);
    }

    /**
     * Get current authenticated user document (without sensitive hashes)
     */
    public static function user() {
        $userId = self::id();
        if (!$userId) {
            return null;
        }

        $user = DB::findOne('users', ['_id' => $userId]);
        if (!$user) {
            // User was deleted or invalid session
            self::logout();
            return null;
        }

        unset($user['password_hash']);
        unset($user['reset_token_hash']);
        unset($user['reset_token_expires']);
        return $user;
    }

    /**
     * Enforce authentication on protected endpoints
     * Halts execution with 401 if unauthenticated
     */
    public static function requireAuth() {
        $user = self::user();
        if (!$user) {
            jsonResponse([
                'success' => false,
                'error' => 'Authentication required. Please log in.',
                'authenticated' => false
            ], 401);
        }
        return $user;
    }

    /**
     * Enforce admin role
     * Halts execution with 403 if user is not an administrator
     */
    public static function requireAdmin() {
        $user = self::requireAuth();
        if (($user['role'] ?? 'user') !== 'admin') {
            jsonResponse([
                'success' => false,
                'error' => 'Access denied. Administrator privileges required.'
            ], 403);
        }
        return $user;
    }

    /**
     * Invalidate session and destroy cookies safely
     */
    public static function logout() {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /**
     * Get or initialize the active baby for the authenticated user
     */
    public static function getActiveBaby($userId) {
        $activeId = $_SESSION['active_baby_id'] ?? null;

        if ($activeId) {
            $baby = DB::findOne('profile', ['_id' => $activeId, 'user_id' => $userId]);
            if ($baby) {
                return $baby;
            }
        }

        // Check user's preferred baby in database
        $user = DB::findOne('users', ['_id' => $userId]);
        if (!empty($user['active_baby_id'])) {
            $baby = DB::findOne('profile', ['_id' => $user['active_baby_id'], 'user_id' => $userId]);
            if ($baby) {
                $_SESSION['active_baby_id'] = (string)$baby['_id'];
                return $baby;
            }
        }

        // Fallback: pick the first baby profile belonging to this user
        $baby = DB::findOne('profile', ['user_id' => $userId]);
        if ($baby) {
            $_SESSION['active_baby_id'] = (string)$baby['_id'];
            return $baby;
        }

        // If no baby profile exists yet for this user, create default
        $defaultBaby = [
            'user_id' => $userId,
            'baby_name' => 'Little One',
            'birth_date' => date('Y-m-d'),
            'parent_names' => $user['name'] ?? 'Parent',
            'bio' => 'Welcome to our baby scrapbook!',
            'avatar_path' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        $newId = DB::insert('profile', $defaultBaby);
        $defaultBaby['_id'] = $newId;
        $_SESSION['active_baby_id'] = $newId;
        return $defaultBaby;
    }

    /**
     * Verify that a baby profile belongs to the specified user
     */
    public static function verifyBabyOwnership($babyId, $userId) {
        if (!$babyId || !$userId) return false;
        $baby = DB::findOne('profile', ['_id' => $babyId, 'user_id' => $userId]);
        return $baby ? $baby : false;
    }

    /**
     * Verify ownership of a record in any collection
     * Returns the document if owned, false if forbidden, null if not found
     */
    public static function verifyOwnership($collection, $recordId, $userId) {
        if (!$recordId || !$userId) return null;
        $record = DB::findOne($collection, ['_id' => $recordId]);
        if (!$record) {
            return null;
        }
        if (!isset($record['user_id']) || $record['user_id'] != $userId) {
            return false;
        }
        return $record;
    }

    /**
     * Rate limiter for sensitive operations (login, password reset)
     */
    public static function checkRateLimit($key, $maxAttempts = 10, $decaySeconds = 60) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $bucketKey = "rate_{$key}_{$ip}";
        $now = time();

        if (!isset($_SESSION[$bucketKey])) {
            $_SESSION[$bucketKey] = ['count' => 1, 'start' => $now];
            return true;
        }

        $bucket = &$_SESSION[$bucketKey];
        if ($now - $bucket['start'] > $decaySeconds) {
            $bucket = ['count' => 1, 'start' => $now];
            return true;
        }

        $bucket['count']++;
        if ($bucket['count'] > $maxAttempts) {
            return false;
        }
        return true;
    }
}
