<?php
/**
 * Milestones API
 * BabyBook / TinyTales
 */

require_once __DIR__ . '/../db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

// GET: Fetch all milestones
if ($method === 'GET') {
    $milestones = DB::find('milestones', [], ['sort' => ['date' => -1]]);
    jsonResponse([
        'success' => true,
        'count' => count($milestones),
        'milestones' => $milestones
    ]);
}

// POST: Add new milestone or delete
if ($method === 'POST') {
    if ($action === 'delete') {
        $id = $_POST['id'] ?? ($_GET['id'] ?? '');
        if (empty($id)) {
            sendError('Milestone ID required');
        }
        DB::delete('milestones', ['_id' => $id]);
        jsonResponse(['success' => true, 'message' => 'Milestone deleted successfully']);
    }

    $raw = file_get_contents('php://input');
    $json = json_decode($raw, true);

    $title = trim($_POST['title'] ?? ($json['title'] ?? ''));
    $category = trim($_POST['category'] ?? ($json['category'] ?? 'social'));
    $date = trim($_POST['date'] ?? ($json['date'] ?? ''));

    if (empty($title) || empty($date)) {
        sendError('Milestone title and date are required');
    }

    $doc = [
        'title' => $title,
        'category' => strtolower($category),
        'date' => $date,
        'created_at' => date('Y-m-d H:i:s')
    ];

    $id = DB::insert('milestones', $doc);
    $doc['_id'] = $id;

    jsonResponse([
        'success' => true,
        'message' => 'Milestone saved successfully',
        'milestone' => $doc
    ], 201);
}

if ($method === 'DELETE') {
    $id = $_GET['id'] ?? '';
    if (empty($id)) {
        sendError('Milestone ID required');
    }
    DB::delete('milestones', ['_id' => $id]);
    jsonResponse(['success' => true, 'message' => 'Milestone deleted successfully']);
}

sendError('Method not allowed', 405);
