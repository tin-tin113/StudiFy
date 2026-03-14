<?php
/**
 * STUDIFY – Save Dashboard Widget Preferences AJAX
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
header('Content-Type: application/json');

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']); exit();
}

$user_id = getCurrentUserId();
$action  = $_POST['action'] ?? '';

// Save full widget layout (positions + visibility)
if ($action === 'save_layout') {
    $layout = json_decode($_POST['layout'] ?? '[]', true);
    if (!is_array($layout)) {
        echo json_encode(['success' => false, 'message' => 'Invalid layout']); exit();
    }

    $stmt = $conn->prepare(
        "INSERT INTO dashboard_widgets (user_id, widget_key, position, is_visible)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE position = VALUES(position), is_visible = VALUES(is_visible)"
    );

    foreach ($layout as $item) {
        $key      = sanitize($item['key'] ?? '');
        $pos      = intval($item['position'] ?? 0);
        $visible  = intval($item['visible'] ?? 1);
        if (empty($key)) continue;
        $stmt->bind_param("isii", $user_id, $key, $pos, $visible);
        $stmt->execute();
    }

    echo json_encode(['success' => true, 'message' => 'Layout saved']);
    exit();
}

// Toggle single widget visibility
if ($action === 'toggle_widget') {
    $key     = sanitize($_POST['widget_key'] ?? '');
    $visible = intval($_POST['visible'] ?? 0);

    $stmt = $conn->prepare(
        "INSERT INTO dashboard_widgets (user_id, widget_key, is_visible, position)
         VALUES (?, ?, ?, 99)
         ON DUPLICATE KEY UPDATE is_visible = VALUES(is_visible)"
    );
    $stmt->bind_param("isi", $user_id, $key, $visible);
    $stmt->execute();

    echo json_encode(['success' => true]);
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
