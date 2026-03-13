<?php
/**
 * STUDIFY – Notification API
 * AJAX endpoint for notification actions:
 *   mark_read, mark_all_read, dismiss, get_count, save_preferences
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit();
}

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$user_id = getCurrentUserId();
$action = $_POST['action'] ?? '';

switch ($action) {

    case 'mark_read':
        $notif_id = intval($_POST['notification_id'] ?? 0);
        if ($notif_id > 0 && markNotificationRead($notif_id, $user_id, $conn)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notification not found']);
        }
        break;

    case 'mark_all_read':
        markAllNotificationsRead($user_id, $conn);
        echo json_encode(['success' => true]);
        break;

    case 'dismiss':
        $notif_id = intval($_POST['notification_id'] ?? 0);
        if ($notif_id > 0 && dismissNotification($notif_id, $user_id, $conn)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Notification not found']);
        }
        break;

    case 'get_count':
        $count = getUnreadNotificationCount($user_id, $conn);
        echo json_encode(['success' => true, 'count' => $count]);
        break;

    case 'save_preferences':
        $prefs = [
            'deadline_24h' => intval($_POST['deadline_24h'] ?? 1),
            'deadline_1h' => intval($_POST['deadline_1h'] ?? 1),
            'overdue_alerts' => intval($_POST['overdue_alerts'] ?? 1),
            'study_reminders' => intval($_POST['study_reminders'] ?? 1),
            'streak_alerts' => intval($_POST['streak_alerts'] ?? 1)
        ];
        if (updateNotificationPreferences($user_id, $prefs, $conn)) {
            echo json_encode(['success' => true, 'message' => 'Preferences saved']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error saving preferences']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        break;
}
exit();
?>
