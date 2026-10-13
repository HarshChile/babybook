<?php
/**
 * Calendar API - Events & Memories by Date
 * BabyBook / TinyTales
 */

require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $year = $_GET['year'] ?? date('Y');
    $month = $_GET['month'] ?? date('m');
    $monthPadded = str_pad((string)$month, 2, '0', STR_PAD_LEFT);
    $prefix = "$year-$monthPadded";

    // Find memories for this month or all memories
    $allMemories = DB::find('memories', [], ['sort' => ['date' => 1]]);
    $allMilestones = DB::find('milestones', [], ['sort' => ['date' => 1]]);

    $eventsByDate = [];

    foreach ($allMemories as $m) {
        $d = $m['date'] ?? '';
        if ($d) {
            if (!isset($eventsByDate[$d])) {
                $eventsByDate[$d] = [];
            }
            $eventsByDate[$d][] = [
                'type' => 'memory',
                'id' => $m['_id'],
                'title' => $m['title'],
                'description' => $m['description'] ?? '',
                'photo_url' => $m['photo_path'] ?? '',
                'category' => $m['category'] ?? 'Memory'
            ];
        }
    }

    foreach ($allMilestones as $ms) {
        $d = $ms['date'] ?? '';
        if ($d) {
            if (!isset($eventsByDate[$d])) {
                $eventsByDate[$d] = [];
            }
            $eventsByDate[$d][] = [
                'type' => 'milestone',
                'id' => $ms['_id'],
                'title' => $ms['title'],
                'category' => $ms['category'] ?? 'Milestone'
            ];
        }
    }

    jsonResponse([
        'success' => true,
        'year' => (int)$year,
        'month' => (int)$month,
        'events' => $eventsByDate
    ]);
}

// POST: Add memory from calendar panel
if ($method === 'POST') {
    $date = trim($_POST['date'] ?? date('Y-m-d'));
    $title = trim($_POST['title'] ?? 'Calendar Memory');
    $desc = trim($_POST['desc'] ?? ($_POST['description'] ?? ''));

    $photoPath = handleFileUpload('image', 'photos');

    $doc = [
        'title' => $title,
        'description' => $desc,
        'date' => $date,
        'category' => 'Calendar',
        'photo_path' => $photoPath,
        'is_favorite' => false,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $id = DB::insert('memories', $doc);
    $doc['_id'] = $id;

    jsonResponse([
        'success' => true,
        'message' => 'Memory saved to calendar successfully',
        'memory' => $doc
    ]);
}

sendError('Method not allowed', 405);
