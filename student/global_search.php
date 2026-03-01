<?php
/**
 * STUDIFY – Global Search Endpoint (AJAX)
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit();
}

$user_id = getCurrentUserId();
$results = globalSearch($user_id, $conn, $query, 15);

echo json_encode(['success' => true, 'results' => $results]);
?>
