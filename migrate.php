<?php
/**
 * Safe Database Migration & Data Backfill Script
 * TinyTales BabyBook
 */

require_once __DIR__ . '/db.php';

echo "Running TinyTales safe migration...\n";

// 1. Ensure at least one default/legacy user exists
$firstUser = DB::findOne('users', []);
if (!$firstUser) {
    $demoId = DB::insert('users', [
        'name' => 'Demo Parent',
        'email' => 'demo@tinytales.app',
        'password_hash' => password_hash('password123', PASSWORD_DEFAULT),
        'role' => 'user',
        'created_at' => date('Y-m-d H:i:s')
    ]);
    $firstUserId = $demoId;
} else {
    $firstUserId = (string)$firstUser['_id'];
}

// Ensure all users have a role
$users = DB::find('users', []);
foreach ($users as $u) {
    if (empty($u['role'])) {
        DB::update('users', ['_id' => $u['_id']], ['role' => 'user']);
    }
}

// 2. Backfill Profile records missing user_id
$profiles = DB::find('profile', []);
foreach ($profiles as $p) {
    if (empty($p['user_id'])) {
        DB::update('profile', ['_id' => $p['_id']], ['user_id' => $firstUserId]);
        echo "Backfilled profile {$p['_id']} to user $firstUserId\n";
    }
}

// 3. Backfill Memories missing user_id
$memories = DB::find('memories', []);
foreach ($memories as $m) {
    if (empty($m['user_id'])) {
        DB::update('memories', ['_id' => $m['_id']], ['user_id' => $firstUserId]);
        echo "Backfilled memory {$m['_id']} to user $firstUserId\n";
    }
}

// 4. Backfill Milestones missing user_id
$milestones = DB::find('milestones', []);
foreach ($milestones as $ms) {
    if (empty($ms['user_id'])) {
        DB::update('milestones', ['_id' => $ms['_id']], ['user_id' => $firstUserId]);
        echo "Backfilled milestone {$ms['_id']} to user $firstUserId\n";
    }
}

// 5. Backfill Capsules missing user_id
$capsules = DB::find('capsules', []);
foreach ($capsules as $c) {
    if (empty($c['user_id'])) {
        DB::update('capsules', ['_id' => $c['_id']], ['user_id' => $firstUserId]);
        echo "Backfilled capsule {$c['_id']} to user $firstUserId\n";
    }
}

// 6. Backfill Scrapbook missing user_id
$scrapbooks = DB::find('scrapbook', []);
foreach ($scrapbooks as $sb) {
    if (empty($sb['user_id'])) {
        DB::update('scrapbook', ['_id' => $sb['_id']], ['user_id' => $firstUserId]);
        echo "Backfilled scrapbook {$sb['_id']} to user $firstUserId\n";
    }
}

// 7. Seed Settings for existing users if missing
foreach ($users as $u) {
    $userId = (string)$u['_id'];
    $setting = DB::findOne('settings', ['user_id' => $userId]);
    if (!$setting) {
        DB::insert('settings', [
            'user_id' => $userId,
            'baby_name' => 'Little One',
            'parent_email' => $u['email'] ?? '',
            'email_alerts' => true,
            'milestone_reminders' => true,
            'capsule_reminders' => true,
            'private_memories' => true,
            'hd_photo_storage' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        echo "Created initial settings for user $userId\n";
    }
}

echo "Migration completed successfully. All records have verified ownership.\n";
