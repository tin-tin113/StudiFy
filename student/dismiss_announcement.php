<?php
/**
 * STUDIFY – Dismiss Announcement (AJAX endpoint)
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';

// Set JSON header
header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = getCurrentUserId();
    $ann_id = intval($_POST['announcement_id'] ?? 0);

    if ($ann_id > 0) {
        $stmt = $conn->prepare("INSERT IGNORE INTO announcement_reads (announcement_id, user_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $ann_id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid announcement ID']);
    }
    exit();
}

echo json_encode(['success' => false, 'error' => 'Invalid request']);
exit();
