<?php
/**
 * STUDIFY – Notification Checker
 * Generates in-app notifications by checking task deadlines and study streaks.
 * Included once per page load via header.php for student users.
 * Uses dedup keys (type + reference_id + date) to prevent duplicate alerts.
 */

function runNotificationChecker($user_id, $conn) {
    // Only run for authenticated students
    if (!$user_id || (isset($_SESSION['role']) && $_SESSION['role'] === 'admin')) {
        return;
    }

    // Throttle: run at most once every 60 seconds per session
    $throttle_key = 'notif_check_' . $user_id;
    if (isset($_SESSION[$throttle_key]) && (time() - $_SESSION[$throttle_key]) < 60) {
        return;
    }
    $_SESSION[$throttle_key] = time();

    // Load user preferences (defaults to all enabled)
    $prefs = getNotificationPreferences($user_id, $conn);

    $now = new DateTime();
    $today = $now->format('Y-m-d');

    // ─── 1. Overdue Tasks ───
    if ($prefs['overdue_alerts']) {
        $stmt = $conn->prepare(
            "SELECT t.id, t.title, t.deadline FROM tasks t
             WHERE t.user_id = ? AND t.status != 'Completed' AND t.deadline < NOW()
             AND t.id NOT IN (
                 SELECT n.reference_id FROM notifications n
                 WHERE n.user_id = ? AND n.type = 'overdue' AND n.reference_type = 'task'
                 AND DATE(n.created_at) = CURDATE()
             )
             LIMIT 10"
        );
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $overdue = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($overdue as $task) {
            $deadline = new DateTime($task['deadline']);
            $diff = $now->diff($deadline);
            $ago = $diff->days > 0 ? $diff->days . ' day' . ($diff->days > 1 ? 's' : '') . ' ago' : 'earlier today';

            createNotification(
                $user_id,
                'overdue',
                'Task Overdue',
                '"' . $task['title'] . '" was due ' . $ago . '. Consider completing or rescheduling it.',
                $conn,
                $task['id'],
                'task'
            );
        }
    }

    // ─── 2. Deadline in 1 Hour ───
    if ($prefs['deadline_1h']) {
        $stmt = $conn->prepare(
            "SELECT t.id, t.title, t.deadline FROM tasks t
             WHERE t.user_id = ? AND t.status != 'Completed'
             AND t.deadline > NOW() AND t.deadline <= DATE_ADD(NOW(), INTERVAL 1 HOUR)
             AND t.id NOT IN (
                 SELECT n.reference_id FROM notifications n
                 WHERE n.user_id = ? AND n.type = 'deadline_1h' AND n.reference_type = 'task'
                 AND DATE(n.created_at) = CURDATE()
             )
             LIMIT 10"
        );
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $due_1h = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($due_1h as $task) {
            createNotification(
                $user_id,
                'deadline_1h',
                'Due in Less Than 1 Hour',
                '"' . $task['title'] . '" is due at ' . date('g:i A', strtotime($task['deadline'])) . '. Finish it now!',
                $conn,
                $task['id'],
                'task'
            );
        }
    }

    // ─── 3. Deadline in 24 Hours ───
    if ($prefs['deadline_24h']) {
        $stmt = $conn->prepare(
            "SELECT t.id, t.title, t.deadline FROM tasks t
             WHERE t.user_id = ? AND t.status != 'Completed'
             AND t.deadline > DATE_ADD(NOW(), INTERVAL 1 HOUR)
             AND t.deadline <= DATE_ADD(NOW(), INTERVAL 24 HOUR)
             AND t.id NOT IN (
                 SELECT n.reference_id FROM notifications n
                 WHERE n.user_id = ? AND n.type = 'deadline_24h' AND n.reference_type = 'task'
                 AND DATE(n.created_at) = CURDATE()
             )
             LIMIT 10"
        );
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        $due_24h = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($due_24h as $task) {
            $deadline_dt = new DateTime($task['deadline']);
            $hours_left = max(1, round($now->diff($deadline_dt)->h + ($now->diff($deadline_dt)->days * 24)));
            $time_str = $hours_left . ' hour' . ($hours_left > 1 ? 's' : '');

            createNotification(
                $user_id,
                'deadline_24h',
                'Due in ' . $time_str,
                '"' . $task['title'] . '" is due ' . date('M d \a\t g:i A', strtotime($task['deadline'])) . '. Plan ahead!',
                $conn,
                $task['id'],
                'task'
            );
        }
    }

    // ─── 4. Study Streak at Risk ───
    if ($prefs['streak_alerts']) {
        // Check if user had a 3+ day streak and hasn't studied today
        $streak_q = $conn->prepare(
            "SELECT DATE(created_at) as day FROM study_sessions
             WHERE user_id = ? AND session_type = 'Focus'
             GROUP BY DATE(created_at) ORDER BY day DESC LIMIT 7"
        );
        $streak_q->bind_param("i", $user_id);
        $streak_q->execute();
        $days = $streak_q->get_result()->fetch_all(MYSQLI_ASSOC);

        if (count($days) > 0 && $days[0]['day'] !== $today) {
            // User hasn't studied today — check if they had a 3+ day streak before
            $streak = 0;
            $check_date = new DateTime($days[0]['day']);
            for ($i = 0; $i < count($days); $i++) {
                $d = new DateTime($days[$i]['day']);
                $expected = clone $check_date;
                if ($i > 0) $expected->modify('-1 day');
                if ($d->format('Y-m-d') === ($i === 0 ? $check_date->format('Y-m-d') : $expected->format('Y-m-d'))) {
                    $streak++;
                    $check_date = $d;
                } else {
                    break;
                }
            }

            if ($streak >= 3) {
                // Check we haven't already sent this today
                $dedup = $conn->prepare(
                    "SELECT id FROM notifications WHERE user_id = ? AND type = 'streak_risk' AND DATE(created_at) = CURDATE() LIMIT 1"
                );
                $dedup->bind_param("i", $user_id);
                $dedup->execute();
                if ($dedup->get_result()->num_rows === 0) {
                    createNotification(
                        $user_id,
                        'streak_risk',
                        'Study Streak at Risk!',
                        'You have a ' . $streak . '-day study streak. Study today to keep it alive!',
                        $conn,
                        null,
                        'general'
                    );
                }
            }
        }
    }

    // ─── Cleanup: remove notifications older than 30 days ───
    $cleanup = $conn->prepare("DELETE FROM notifications WHERE user_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    $cleanup->bind_param("i", $user_id);
    $cleanup->execute();
}
?>
