<?php
/**
 * Settings API
 * BabyBook / TinyTales
 */

require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $settings = DB::findOne('settings', []);
    if (!$settings) {
        $settings = [
            'baby_name' => 'Emma',
            'parent_email' => 'emma.parents@tinytales.app',
            'email_alerts' => true,
            'milestone_reminders' => true,
            'private_memories' => true
        ];
    }
    jsonResponse(['success' => true, 'settings' => $settings]);
}

if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true) ?? [];

    $babyName = trim($_POST['baby_name'] ?? ($json['baby_name'] ?? ''));
    $parentEmail = trim($_POST['parent_email'] ?? ($json['parent_email'] ?? ''));
    $emailAlerts = isset($_POST['email_alerts']) ? (bool)$_POST['email_alerts'] : (bool)($json['email_alerts'] ?? true);
    $milestoneReminders = isset($_POST['milestone_reminders']) ? (bool)$_POST['milestone_reminders'] : (bool)($json['milestone_reminders'] ?? true);
    $privateMemories = isset($_POST['private_memories']) ? (bool)$_POST['private_memories'] : (bool)($json['private_memories'] ?? true);

    $existing = DB::findOne('settings', []);

    $data = [
        'baby_name' => $babyName,
        'parent_email' => $parentEmail,
        'email_alerts' => $emailAlerts,
        'milestone_reminders' => $milestoneReminders,
        'private_memories' => $privateMemories,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    if ($existing && isset($existing['_id'])) {
        DB::update('settings', ['_id' => $existing['_id']], $data);
    } else {
        DB::insert('settings', $data);
    }

    // Also sync baby name to profile if provided
    if (!empty($babyName)) {
        $prof = DB::findOne('profile', []);
        if ($prof) {
            DB::update('profile', ['_id' => $prof['_id']], ['baby_name' => $babyName]);
        }
    }

    jsonResponse(['success' => true, 'message' => 'Settings saved successfully', 'settings' => $data]);
}

sendError('Method not allowed', 405);
