<?php
/**
 * STUDIFY – Study Streak & Achievements Helper
 * All streak / gamification logic lives here
 */

/**
 * Calculate current study streak (consecutive days with completed tasks OR study sessions)
 * Uses static cache to avoid recomputing on the same request (dashboard calls this 2-3x).
 * Bounded to last 90 days to avoid unbounded full-history scans.
 */
function getStudyStreak(int $user_id, $conn): array {
    static $cache = [];
    if (isset($cache[$user_id])) return $cache[$user_id];

    // Only scan the last 90 days — a streak longer than that is extremely rare
    $sql = "SELECT DISTINCT DATE(activity_date) as day FROM (
        SELECT updated_at AS activity_date FROM tasks
          WHERE user_id = ? AND status = 'Completed'
          AND updated_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        UNION ALL
        SELECT created_at AS activity_date FROM study_sessions
          WHERE user_id = ? AND session_type = 'Focus'
          AND created_at >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
    ) combined
    ORDER BY day DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    $days = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'day');

    if (empty($days)) return ['current' => 0, 'longest' => 0, 'today' => false, 'last_7' => []];

    $today     = new DateTime('today');
    $yesterday = (new DateTime('today'))->modify('-1 day');

    $current  = 0;
    $longest  = 0;
    $temp     = 0;
    $has_today = $days[0] === $today->format('Y-m-d');

    // Check if streak is broken (no activity today or yesterday)
    $last_day = new DateTime($days[0]);
    $diff = (int)$today->diff($last_day)->days;
    if ($diff > 1) {
        $current = 0;
    } else {
        $prev = null;
        foreach ($days as $d) {
            $date = new DateTime($d);
            if ($prev === null) {
                $temp = 1;
            } else {
                $gap = (int)$prev->diff($date)->days;
                if ($gap === 1) {
                    $temp++;
                } else {
                    if ($temp > $longest) $longest = $temp;
                    $temp = 1;
                }
            }
            $prev = $date;
        }
        if ($temp > $longest) $longest = $temp;
        $current = $temp; // Since sorted desc, $temp at end = current streak
    }

    // Last 7 days activity map
    $last7 = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = (new DateTime('today'))->modify("-$i days")->format('Y-m-d');
        $last7[$d] = in_array($d, $days);
    }

    return $cache[$user_id] = [
        'current' => $current,
        'longest' => $longest,
        'today'   => $has_today,
        'last_7'  => $last7,
    ];
}

/**
 * Get all achievements a user has unlocked + which they are eligible for but haven't claimed.
 * Accepts optional pre-computed $streak to avoid duplicate getStudyStreak() call.
 */
function checkAndAwardAchievements(int $user_id, $conn, ?array $streak = null): array {
    // Gather user stats — combined into a single query (was 3 separate queries)
    $stats_q = $conn->prepare("SELECT
        (SELECT COUNT(*) FROM tasks WHERE user_id = ? AND parent_id IS NULL) as total_tasks,
        (SELECT COALESCE(SUM(status = 'Completed'), 0) FROM tasks WHERE user_id = ? AND parent_id IS NULL) as completed_tasks,
        (SELECT COUNT(*) FROM notes WHERE user_id = ?) as note_count,
        (SELECT COALESCE(SUM(duration), 0) FROM study_sessions WHERE user_id = ? AND session_type = 'Focus') as pomo_mins");
    $stats_q->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stats_q->execute();
    $stats = $stats_q->get_result()->fetch_assoc();

    $note_count = intval($stats['note_count']);
    $pomo_mins  = intval($stats['pomo_mins']);

    // Reuse pre-computed streak if provided, otherwise compute (static-cached)
    if ($streak === null) {
        $streak = getStudyStreak($user_id, $conn);
    }

    $total     = intval($stats['total_tasks'] ?? 0);
    $completed = intval($stats['completed_tasks'] ?? 0);
    $pomo_hrs  = round($pomo_mins / 60, 1);

    // All possible achievements definition
    $all_achievements = [
        'first_task'      => ['icon' => '✅', 'title' => 'First Step',          'desc' => 'Add your first task',                    'color' => '#16a34a'],
        'tasks_10'        => ['icon' => '📋', 'title' => 'Task Master',          'desc' => 'Complete 10 tasks',                       'color' => '#2563eb'],
        'tasks_50'        => ['icon' => '🏅', 'title' => 'Achiever',             'desc' => 'Complete 50 tasks',                       'color' => '#d97706'],
        'tasks_100'       => ['icon' => '🏆', 'title' => 'Century Club',         'desc' => 'Complete 100 tasks',                      'color' => '#7c3aed'],
        'streak_3'        => ['icon' => '🔥', 'title' => 'On Fire',              'desc' => '3-day study streak',                      'color' => '#dc2626'],
        'streak_7'        => ['icon' => '⚡', 'title' => 'Week Warrior',         'desc' => '7-day study streak',                      'color' => '#ea580c'],
        'streak_30'       => ['icon' => '💎', 'title' => 'Diamond Dedication',   'desc' => '30-day study streak',                     'color' => '#0ea5e9'],
        'pomodoro_10hrs'  => ['icon' => '⏱️', 'title' => 'Focus Machine',        'desc' => '10 hours of focused study',               'color' => '#16a34a'],
        'pomodoro_50hrs'  => ['icon' => '🧠', 'title' => 'Deep Worker',          'desc' => '50 hours of focused study',               'color' => '#7c3aed'],
        'notes_5'         => ['icon' => '📝', 'title' => 'Note Taker',           'desc' => 'Write 5 notes',                           'color' => '#0891b2'],
        'all_rounder'     => ['icon' => '🌟', 'title' => 'All-Rounder',          'desc' => 'Have tasks, notes & study sessions',      'color' => '#d97706'],
    ];

    // Conditions map
    $conditions = [
        'first_task'     => $total >= 1,
        'tasks_10'       => $completed >= 10,
        'tasks_50'       => $completed >= 50,
        'tasks_100'      => $completed >= 100,
        'streak_3'       => $streak['current'] >= 3,
        'streak_7'       => $streak['current'] >= 7,
        'streak_30'      => $streak['current'] >= 30,
        'pomodoro_10hrs' => $pomo_hrs >= 10,
        'pomodoro_50hrs' => $pomo_hrs >= 50,
        'notes_5'        => $note_count >= 5,
        'all_rounder'    => ($total > 0 && $note_count > 0 && $pomo_mins > 0),
    ];

    // Load already-unlocked
    $unlocked_q = $conn->prepare("SELECT achievement_key, unlocked_at FROM user_achievements WHERE user_id = ?");
    $unlocked_q->bind_param("i", $user_id);
    $unlocked_q->execute();
    $unlocked_rows = $unlocked_q->get_result()->fetch_all(MYSQLI_ASSOC);
    $unlocked_map  = array_column($unlocked_rows, 'unlocked_at', 'achievement_key');

    $newly_awarded = [];

    // Prepare INSERT once outside the loop (was prepared inside on every iteration)
    $ins = $conn->prepare("INSERT IGNORE INTO user_achievements (user_id, achievement_key) VALUES (?, ?)");

    foreach ($conditions as $key => $met) {
        if ($met && !isset($unlocked_map[$key])) {
            $ins->bind_param("is", $user_id, $key);
            $ins->execute();
            $unlocked_map[$key] = date('Y-m-d H:i:s');
            $newly_awarded[] = array_merge($all_achievements[$key], ['key' => $key]);
        }
    }

    // Build full list with locked/unlocked status
    $result = [];
    foreach ($all_achievements as $key => $ach) {
        $result[] = array_merge($ach, [
            'key'          => $key,
            'unlocked'     => isset($unlocked_map[$key]),
            'unlocked_at'  => $unlocked_map[$key] ?? null,
            'eligible'     => $conditions[$key] ?? false,
        ]);
    }

    return [
        'achievements'  => $result,
        'newly_awarded' => $newly_awarded,
        'stats'         => [
            'total_tasks'  => $total,
            'completed'    => $completed,
            'notes'        => $note_count,
            'pomo_hrs'     => $pomo_hrs,
            'streak'       => $streak,
        ],
    ];
}

/**
 * Get dashboard widget config for a user, filling in defaults if not set yet
 */
function getDashboardWidgets(int $user_id, $conn): array {
    $defaults = [
        ['key' => 'streak',      'label' => 'Study Streak',       'icon' => 'fas fa-fire',          'position' => 0, 'visible' => true],
        ['key' => 'weekly',      'label' => 'Weekly Summary',      'icon' => 'fas fa-calendar-week', 'position' => 1, 'visible' => true],
        ['key' => 'stats',       'label' => 'Task Stats',          'icon' => 'fas fa-chart-bar',     'position' => 2, 'visible' => true],
        ['key' => 'upcoming',    'label' => 'Upcoming Deadlines',  'icon' => 'fas fa-bell',          'position' => 3, 'visible' => true],
        ['key' => 'progress',    'label' => 'Overall Progress',    'icon' => 'fas fa-chart-line',    'position' => 4, 'visible' => true],
        ['key' => 'semester',    'label' => 'Active Semester',     'icon' => 'fas fa-graduation-cap','position' => 5, 'visible' => true],
        ['key' => 'quickact',    'label' => 'Quick Actions',       'icon' => 'fas fa-bolt',          'position' => 6, 'visible' => true],
        ['key' => 'achievements','label' => 'Achievements',        'icon' => 'fas fa-trophy',        'position' => 7, 'visible' => true],
    ];

    $stmt = $conn->prepare("SELECT widget_key, position, is_visible FROM dashboard_widgets WHERE user_id = ? ORDER BY position ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $saved = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $saved_map = [];
    foreach ($saved as $row) {
        $saved_map[$row['widget_key']] = $row;
    }

    $result = [];
    foreach ($defaults as $w) {
        if (isset($saved_map[$w['key']])) {
            $w['position'] = intval($saved_map[$w['key']]['position']);
            $w['visible']  = (bool)$saved_map[$w['key']]['is_visible'];
        }
        $result[$w['key']] = $w;
    }

    usort($result, fn($a, $b) => $a['position'] <=> $b['position']);

    return $result;
}
