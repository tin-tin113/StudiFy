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
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

// Match inactivity timeout used in requireLogin(), but keep JSON response format
$timeout = 7200;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout) {
    secureLogout();
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Session expired']);
    exit();
}
$_SESSION['last_activity'] = time();

$query = trim($_GET['q'] ?? '');
if (strlen($query) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit();
}

$user_id = getCurrentUserId();
$results = globalSearch($user_id, $conn, $query, 15);

echo json_encode(['success' => true, 'results' => $results]);
?>
