<?php
/**
 * STUDIFY – Pomodoro Timer
 * Circular SVG timer with focus/break sessions and study stats
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdminRole()) {
    header("Location: " . BASE_URL . "admin/admin_dashboard.php");
    exit();
}

$page_title = 'Pomodoro Timer';
$user_id = getCurrentUserId();

// Handle AJAX session saving
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }

    // Mark task complete endpoint
    if (isset($_POST['action']) && $_POST['action'] === 'complete_task') {
        $task_id = intval($_POST['task_id'] ?? 0);
        if ($task_id > 0) {
            $check = $conn->prepare("SELECT id, title, status FROM tasks WHERE id = ? AND user_id = ?");
            $check->bind_param("ii", $task_id, $user_id);
            $check->execute();
            $task_row = $check->get_result()->fetch_assoc();
            if ($task_row && $task_row['status'] !== 'Completed') {
                $upd = $conn->prepare("UPDATE tasks SET status = 'Completed' WHERE id = ? AND user_id = ?");
                $upd->bind_param("ii", $task_id, $user_id);
                $upd->execute();
                // Also complete any subtasks
                $sub = $conn->prepare("UPDATE tasks SET status = 'Completed' WHERE parent_id = ? AND user_id = ?");
                $sub->bind_param("ii", $task_id, $user_id);
                $sub->execute();
                echo json_encode(['success' => true, 'message' => 'Task marked as completed!', 'task_title' => $task_row['title']]);
            } else if ($task_row && $task_row['status'] === 'Completed') {
                echo json_encode(['success' => true, 'message' => 'Task is already completed', 'already' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Task not found']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid task ID']);
        }
        exit();
    }

    // Session history endpoint
    if (isset($_POST['action']) && $_POST['action'] === 'get_history') {
        $page = max(1, intval($_POST['page'] ?? 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $hist_q = "SELECT ss.id, ss.duration, ss.session_type, ss.created_at,
                          t.title as task_title, s.name as subject_name
                   FROM study_sessions ss
                   LEFT JOIN tasks t ON ss.task_id = t.id
                   LEFT JOIN subjects s ON ss.subject_id = s.id
                   WHERE ss.user_id = ? ORDER BY ss.created_at DESC LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($hist_q);
        $stmt->bind_param("iii", $user_id, $limit, $offset);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $count_q = $conn->prepare("SELECT COUNT(*) as total FROM study_sessions WHERE user_id = ?");
        $count_q->bind_param("i", $user_id);
        $count_q->execute();
        $total = $count_q->get_result()->fetch_assoc()['total'];
        echo json_encode(['success' => true, 'sessions' => $rows, 'total' => $total, 'page' => $page, 'pages' => ceil($total / $limit)]);
        exit();
    }

    $duration = intval($_POST['duration'] ?? 0);
    $type = sanitize($_POST['type'] ?? 'Focus');
    if (!in_array($type, ['Focus', 'Break'], true)) { $type = 'Focus'; }
    $task_id = !empty($_POST['task_id']) ? intval($_POST['task_id']) : null;
    $subject_id = !empty($_POST['subject_id']) ? intval($_POST['subject_id']) : null;
    if ($duration > 0 && $duration <= 120) {
        $stmt = $conn->prepare("INSERT INTO study_sessions (user_id, duration, session_type, task_id, subject_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisii", $user_id, $duration, $type, $task_id, $subject_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid duration']);
    }
    exit();
}

// Handle form POST (fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_session'])) {
    requireCSRF();
    $duration = intval($_POST['duration'] ?? 0);
    $type = sanitize($_POST['type'] ?? 'Focus');
    if (!in_array($type, ['Focus', 'Break'], true)) { $type = 'Focus'; }
    $task_id = !empty($_POST['task_id']) ? intval($_POST['task_id']) : null;
    $subject_id = !empty($_POST['subject_id']) ? intval($_POST['subject_id']) : null;
    if ($duration > 0 && $duration <= 120) {
        $stmt = $conn->prepare("INSERT INTO study_sessions (user_id, duration, session_type, task_id, subject_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("iisii", $user_id, $duration, $type, $task_id, $subject_id);
        $stmt->execute();
    }
}

// Study statistics
$query = "SELECT COUNT(*) as total_sessions, COALESCE(SUM(duration),0) as total_minutes
          FROM study_sessions WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats = $stmt->get_result()->fetch_assoc();

$today_query = "SELECT COUNT(*) as today_sessions, COALESCE(SUM(duration),0) as today_minutes
                FROM study_sessions WHERE user_id = ? AND DATE(created_at) = CURDATE()";
$stmt = $conn->prepare($today_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$today = $stmt->get_result()->fetch_assoc();

// Best streak (consecutive days with at least one focus session)
$streak_query = "SELECT DATE(created_at) as day FROM study_sessions 
                 WHERE user_id = ? AND session_type = 'Focus' 
                 GROUP BY DATE(created_at) ORDER BY day DESC";
$stmt = $conn->prepare($streak_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$streak_result = $stmt->get_result();
$current_streak = 0;
$expected_date = date('Y-m-d');
while ($row = $streak_result->fetch_assoc()) {
    if ($row['day'] === $expected_date) {
        $current_streak++;
        $expected_date = date('Y-m-d', strtotime($expected_date . ' -1 day'));
    } else {
        break;
    }
}

// Average focus session length
$avg_query = "SELECT ROUND(AVG(duration), 0) as avg_min FROM study_sessions 
              WHERE user_id = ? AND session_type = 'Focus' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$stmt = $conn->prepare($avg_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$avg_session = $stmt->get_result()->fetch_assoc()['avg_min'] ?? 0;

// Fetch user's subjects and tasks for linking
$subj_query = "SELECT s.id, s.name FROM subjects s
               JOIN semesters sem ON s.semester_id = sem.id
               WHERE sem.user_id = ? ORDER BY s.name";
$stmt = $conn->prepare($subj_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$subjects_result = $stmt->get_result();
$user_subjects = [];
while ($row = $subjects_result->fetch_assoc()) {
    $user_subjects[] = $row;
}

$task_query = "SELECT t.id, t.title, COALESCE(s.name, '') as subject_name FROM tasks t
               LEFT JOIN subjects s ON t.subject_id = s.id
               WHERE t.user_id = ? AND t.status != 'Completed' AND t.parent_id IS NULL
               ORDER BY t.deadline ASC";
$stmt = $conn->prepare($task_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$tasks_result = $stmt->get_result();
$user_tasks = [];
while ($row = $tasks_result->fetch_assoc()) {
    $user_tasks[] = $row;
}

// Weekly data for chart — always show all 7 days
$week_query = "SELECT DATE(created_at) as day, COALESCE(SUM(duration),0) as mins
               FROM study_sessions WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
               GROUP BY DATE(created_at) ORDER BY day";
$stmt = $conn->prepare($week_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$week_result = $stmt->get_result();
$week_raw = [];
while ($row = $week_result->fetch_assoc()) {
    $week_raw[$row['day']] = (int) $row['mins'];
}
// Fill all 7 days (today and 6 days back)
$week_labels = [];
$week_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $week_labels[] = date('D', strtotime($date));
    $week_data[] = $week_raw[$date] ?? 0;
}
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-clock"></i> Pomodoro Timer</h2>
            <div class="d-flex align-items-center gap-3">
                <?php if ($current_streak > 0): ?>
                <span class="badge bg-warning text-dark px-3 py-2" style="font-size: 13px;">
                    <i class="fas fa-fire"></i> <?php echo $current_streak; ?>-day streak
                </span>
                <?php endif; ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#dailyGoalModal">
                    <i class="fas fa-bullseye"></i> Set Daily Goal
                </button>
            </div>
        </div>

        <!-- Daily Goal Progress Bar -->
        <div class="card mb-4" id="dailyGoalCard" style="display: none;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size: 13px; font-weight: 600;"><i class="fas fa-bullseye text-primary"></i> Daily Goal</span>
                    <span id="goalText" style="font-size: 13px;" class="text-muted"></span>
                </div>
                <div class="progress" style="height: 10px; border-radius: 6px;">
                    <div id="goalProgress" class="progress-bar bg-primary" role="progressbar" style="width: 0%; transition: width 0.5s ease;"></div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Timer Column -->
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div class="mb-3">
                            <span class="badge bg-primary px-3 py-2" id="sessionType" style="font-size: 14px;">
                                <i class="fas fa-brain"></i> Focus Session
                            </span>
                        </div>

                        <!-- Circular SVG Timer -->
                        <div class="timer-circle mx-auto" style="width: 260px; height: 260px; position: relative;">
                            <svg viewBox="0 0 260 260" style="width: 100%; height: 100%; transform: rotate(-90deg);">
                                <circle cx="130" cy="130" r="115" fill="none" stroke="var(--bg-secondary)" stroke-width="10" />
                                <circle id="progressRing" class="progress-ring" cx="130" cy="130" r="115" fill="none"
                                        stroke="var(--primary)" stroke-width="10" stroke-linecap="round"
                                        stroke-dasharray="723" stroke-dashoffset="0"
                                        style="transition: stroke-dashoffset 0.5s ease;" />
                            </svg>
                            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center;">
                                <div id="timerDisplay" style="font-size: 3rem; font-weight: 700; font-family: 'Inter', monospace; color: var(--text-primary);">25:00</div>
                                <div id="timerSubtext" class="text-muted" style="font-size: 12px;">READY</div>
                            </div>
                        </div>

                        <!-- Controls -->
                        <div class="d-flex justify-content-center gap-3 mt-4">
                            <button class="btn btn-success btn-lg px-4" id="startBtn" onclick="pomodoroStart()">
                                <i class="fas fa-play"></i> Start
                            </button>
                            <button class="btn btn-warning btn-lg px-4" id="pauseBtn" onclick="pomodoroPause()" style="display:none;">
                                <i class="fas fa-pause"></i> Pause
                            </button>
                            <button class="btn btn-danger btn-lg px-4" onclick="pomodoroReset()">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                        </div>

                        <!-- Task/Subject Linking -->
                        <div class="pom-link-bar mt-4">
                            <div class="pom-link-heading mb-2">
                                <i class="fas fa-link"></i> Link to Subject / Task
                            </div>
                            <div class="row g-2">
                                <div class="col-sm-6">
                                    <label class="pom-link-label" for="pomSubject"><i class="fas fa-book"></i> Subject</label>
                                    <select class="form-select" id="pomSubject" onchange="onSubjectChange()">
                                        <option value="">— No subject —</option>
                                        <?php foreach ($user_subjects as $subj): ?>
                                        <option value="<?php echo $subj['id']; ?>"><?php echo htmlspecialchars($subj['name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="pom-link-label" for="pomTask"><i class="fas fa-tasks"></i> Task</label>
                                    <select class="form-select" id="pomTask">
                                        <option value="">— No task —</option>
                                        <?php foreach ($user_tasks as $task): ?>
                                        <option value="<?php echo $task['id']; ?>" data-subject="<?php echo htmlspecialchars($task['subject_name']); ?>">
                                            <?php echo htmlspecialchars($task['title']); ?> — <?php echo htmlspecialchars($task['subject_name']); ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 d-flex justify-content-center gap-4">
                            <span class="text-muted" style="font-size: 13px;">
                                <i class="fas fa-fire"></i> Sessions: <strong id="sessionCounter">0</strong>
                            </span>
                            <span class="text-muted" style="font-size: 13px;" id="longBreakHint">
                                <i class="fas fa-mug-hot"></i> Long break in <strong id="untilLongBreak">4</strong> sessions
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Timer Settings -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-sliders-h"></i> Timer Settings</h6>
                        <div class="row g-3">
                            <div class="col-4">
                                <label for="focusTime" class="form-label" style="font-size: 12px;">Focus (min)</label>
                                <input type="number" class="form-control" id="focusTime" value="25" min="1" max="90">
                            </div>
                            <div class="col-4">
                                <label for="breakTime" class="form-label" style="font-size: 12px;">Break (min)</label>
                                <input type="number" class="form-control" id="breakTime" value="5" min="1" max="30">
                            </div>
                            <div class="col-4">
                                <label for="longBreakTime" class="form-label" style="font-size: 12px;">Long Break (min)</label>
                                <input type="number" class="form-control" id="longBreakTime" value="15" min="5" max="45">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3 flex-wrap">
                            <button class="btn btn-sm btn-secondary" onclick="applyPreset(25, 5, 15)">Classic 25/5</button>
                            <button class="btn btn-sm btn-secondary" onclick="applyPreset(50, 10, 25)">Deep 50/10</button>
                            <button class="btn btn-sm btn-secondary" onclick="applyPreset(90, 20, 30)">Marathon 90/20</button>
                        </div>
                        <div class="form-check form-switch mt-3">
                            <input class="form-check-input" type="checkbox" id="autoStartBreak" checked>
                            <label class="form-check-label" for="autoStartBreak" style="font-size: 12px;">Auto-start breaks</label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats Column -->
            <div class="col-lg-6">
                <!-- Focus Ambiance -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0"><i class="fas fa-headphones text-primary"></i> Focus Ambiance</h6>
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="ambianceAutoPlay" checked>
                                <label class="form-check-label" for="ambianceAutoPlay" style="font-size: 11px;">Auto-play</label>
                            </div>
                        </div>
                        <div class="ambiance-grid">
                            <button type="button" class="ambiance-btn" data-sound="rain" title="Rain">
                                <span class="ambiance-icon">🌧️</span>
                                <span class="ambiance-label">Rain</span>
                                <div class="ambiance-volume-wrap">
                                    <input type="range" class="ambiance-volume" min="0" max="100" value="50">
                                </div>
                            </button>
                            <button type="button" class="ambiance-btn" data-sound="fire" title="Fireplace">
                                <span class="ambiance-icon">🔥</span>
                                <span class="ambiance-label">Fire</span>
                                <div class="ambiance-volume-wrap">
                                    <input type="range" class="ambiance-volume" min="0" max="100" value="50">
                                </div>
                            </button>
                            <button type="button" class="ambiance-btn" data-sound="waves" title="Ocean Waves">
                                <span class="ambiance-icon">🌊</span>
                                <span class="ambiance-label">Waves</span>
                                <div class="ambiance-volume-wrap">
                                    <input type="range" class="ambiance-volume" min="0" max="100" value="50">
                                </div>
                            </button>
                            <button type="button" class="ambiance-btn" data-sound="birds" title="Forest Birds">
                                <span class="ambiance-icon">🐦</span>
                                <span class="ambiance-label">Birds</span>
                                <div class="ambiance-volume-wrap">
                                    <input type="range" class="ambiance-volume" min="0" max="100" value="50">
                                </div>
                            </button>
                            <button type="button" class="ambiance-btn" data-sound="coffee" title="Coffee Shop">
                                <span class="ambiance-icon">☕</span>
                                <span class="ambiance-label">Café</span>
                                <div class="ambiance-volume-wrap">
                                    <input type="range" class="ambiance-volume" min="0" max="100" value="50">
                                </div>
                            </button>
                            <button type="button" class="ambiance-btn" data-sound="wind" title="Wind">
                                <span class="ambiance-icon">🍃</span>
                                <span class="ambiance-label">Wind</span>
                                <div class="ambiance-volume-wrap">
                                    <input type="range" class="ambiance-volume" min="0" max="100" value="50">
                                </div>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted" id="ambianceStatus"><i class="fas fa-volume-mute"></i> No sounds active</small>
                            <button class="btn btn-sm btn-outline-secondary" id="ambianceStopAll" style="font-size: 11px; display: none;">
                                <i class="fas fa-stop"></i> Stop All
                            </button>
                        </div>
                        <!-- Ambiance Presets -->
                        <div class="mt-3 pt-3" style="border-top: 1px solid var(--border-color);">
                            <small class="text-muted d-block mb-2"><i class="fas fa-magic"></i> Quick Presets</small>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-sm btn-outline-primary amb-preset-btn" onclick="applyAmbiancePreset('cozy')" title="Rain + Fire">
                                    🌧️🔥 Cozy
                                </button>
                                <button class="btn btn-sm btn-outline-primary amb-preset-btn" onclick="applyAmbiancePreset('nature')" title="Birds + Waves">
                                    🐦🌊 Nature
                                </button>
                                <button class="btn btn-sm btn-outline-primary amb-preset-btn" onclick="applyAmbiancePreset('cafe')" title="Café + Rain">
                                    ☕🌧️ Study Café
                                </button>
                                <button class="btn btn-sm btn-outline-primary amb-preset-btn" onclick="applyAmbiancePreset('storm')" title="Rain + Wind">
                                    🌧️🍃 Storm
                                </button>
                                <button class="btn btn-sm btn-outline-primary amb-preset-btn" onclick="applyAmbiancePreset('zen')" title="Birds + Wind">
                                    🐦🍃 Zen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Today's Progress -->
                <div class="row g-3 mb-4">
                    <div class="col-6">
                        <div class="card stat-card">
                            <div class="stat-number" style="color: var(--primary);">
                                <?php echo $today['today_sessions'] ?? 0; ?>
                            </div>
                            <div class="stat-label">Today's Sessions</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card stat-card">
                            <div class="stat-number" style="color: var(--success);">
                                <?php echo $today['today_minutes'] ?? 0; ?><small style="font-size: 14px;">m</small>
                            </div>
                            <div class="stat-label">Today's Focus Time</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card stat-card">
                            <div class="stat-number" style="color: var(--warning);">
                                <?php echo $current_streak; ?>
                            </div>
                            <div class="stat-label">Day Streak <i class="fas fa-fire text-warning" style="font-size: 12px;"></i></div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card stat-card">
                            <div class="stat-number" style="color: var(--accent);">
                                <?php echo $avg_session; ?><small style="font-size: 14px;">m</small>
                            </div>
                            <div class="stat-label">Avg. Session</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card stat-card">
                            <div class="stat-number" style="color: var(--info);">
                                <?php echo $stats['total_sessions'] ?? 0; ?>
                            </div>
                            <div class="stat-label">30-Day Sessions</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card stat-card">
                            <div class="stat-number" style="color: var(--danger);">
                                <?php echo round(($stats['total_minutes'] ?? 0) / 60, 1); ?><small style="font-size: 14px;">h</small>
                            </div>
                            <div class="stat-label">30-Day Hours</div>
                        </div>
                    </div>
                </div>

                <!-- Weekly Chart -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-chart-bar"></i> This Week's Study Activity</h6>
                        <canvas id="weeklyChart" height="180"></canvas>
                    </div>
                </div>

                <!-- Session History -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0"><i class="fas fa-history"></i> Session History</h6>
                            <button class="btn btn-sm btn-outline-secondary" onclick="loadSessionHistory(1)" id="histRefreshBtn" style="font-size: 11px;">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>
                        <div id="sessionHistoryList">
                            <div class="text-center text-muted py-3" style="font-size: 13px;">
                                <i class="fas fa-spinner fa-spin"></i> Loading...
                            </div>
                        </div>
                        <div id="historyPagination" class="d-flex justify-content-center gap-2 mt-2" style="display: none !important;"></div>
                    </div>
                </div>

                <!-- Keyboard Shortcuts -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-keyboard"></i> Keyboard Shortcuts</h6>
                        <div class="pom-shortcuts">
                            <div class="pom-shortcut-row">
                                <kbd>Space</kbd>
                                <span>Start / Pause</span>
                            </div>
                            <div class="pom-shortcut-row">
                                <kbd>R</kbd>
                                <span>Reset Timer</span>
                            </div>
                            <div class="pom-shortcut-row">
                                <kbd>S</kbd>
                                <span>Skip Session</span>
                            </div>
                            <div class="pom-shortcut-row">
                                <kbd>M</kbd>
                                <span>Mute / Unmute Ambiance</span>
                            </div>
                            <div class="pom-shortcut-row">
                                <kbd>1</kbd> <kbd>2</kbd> <kbd>3</kbd>
                                <span>Timer Presets</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<!-- Daily Goal Modal -->
<div class="modal fade" id="dailyGoalModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-bullseye"></i> Set Daily Goal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label for="dailyGoalInput" class="form-label">Focus sessions per day</label>
                <input type="number" class="form-control" id="dailyGoalInput" min="1" max="20" value="4">
                <small class="text-muted">How many focus sessions do you want to complete daily?</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="saveDailyGoal()">
                    <i class="fas fa-check"></i> Save Goal
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Goal Celebration Overlay -->
<div id="goalCelebration" class="pom-celebration" style="display: none;">
    <div class="pom-celebration-content">
        <div class="pom-celebration-emoji">🎉</div>
        <h3>Daily Goal Complete!</h3>
        <p>You crushed your study goal today. Keep the momentum going!</p>
        <button class="btn btn-success btn-lg mt-2" onclick="dismissCelebration()">
            <i class="fas fa-check"></i> Awesome!
        </button>
    </div>
</div>

<!-- Task Completion Prompt -->
<div id="taskCompletePrompt" class="pom-task-prompt" style="display: none;">
    <div class="pom-task-prompt-content">
        <div class="pom-task-prompt-icon">✅</div>
        <h4>Session Complete!</h4>
        <p class="text-muted mb-2" style="font-size: 13px;">You were working on:</p>
        <div class="pom-task-prompt-name" id="promptTaskName"></div>
        <p class="text-muted mt-3" style="font-size: 13px;">Is this task finished?</p>
        <div class="d-flex justify-content-center gap-2 mt-3">
            <button class="btn btn-success px-4" onclick="confirmCompleteTask()">
                <i class="fas fa-check-circle"></i> Yes, Mark Complete
            </button>
            <button class="btn btn-outline-secondary px-4" onclick="dismissTaskPrompt()">
                <i class="fas fa-clock"></i> Not Yet
            </button>
        </div>
    </div>
</div>

<!-- Hidden fallback form -->
<form id="sessionForm" method="POST" style="display:none;">
    <input type="hidden" name="save_session" value="1">
    <input type="hidden" id="durationInput" name="duration">
    <input type="hidden" id="typeInput" name="type">
    <input type="hidden" id="taskIdInput" name="task_id">
    <input type="hidden" id="subjectIdInput" name="subject_id">
</form>

<script>
// ---- Pomodoro Timer (Enhanced Professional Version) ----
let pomSeconds = 0;
let pomRunning = false;
let pomInterval = null;
let pomSessionCount = 0;
let pomIsBreak = false;
let pomTotalSeconds = 0;
let pomGoalCelebrated = false;
const LONG_BREAK_INTERVAL = 4;
const originalTitle = document.title;

const ring = document.getElementById('progressRing');
const circumference = 2 * Math.PI * 115;
ring.setAttribute('stroke-dasharray', circumference);

// ---- LocalStorage Persistence ----
function loadSettings() {
    const saved = JSON.parse(localStorage.getItem('studify_pom_settings') || '{}');
    if (saved.focus) document.getElementById('focusTime').value = saved.focus;
    if (saved.break) document.getElementById('breakTime').value = saved.break;
    if (saved.longBreak) document.getElementById('longBreakTime').value = saved.longBreak;
    if (saved.autoStart !== undefined) document.getElementById('autoStartBreak').checked = saved.autoStart;
    if (saved.dailyGoal) {
        document.getElementById('dailyGoalInput').value = saved.dailyGoal;
    }
    // Restore linked task/subject
    if (saved.linkedSubject) {
        const sel = document.getElementById('pomSubject');
        if (sel) sel.value = saved.linkedSubject;
    }
    if (saved.linkedTask) {
        const sel = document.getElementById('pomTask');
        if (sel) sel.value = saved.linkedTask;
    }
}

function saveSettings() {
    const settings = {
        focus: parseInt(document.getElementById('focusTime').value),
        break: parseInt(document.getElementById('breakTime').value),
        longBreak: parseInt(document.getElementById('longBreakTime').value),
        autoStart: document.getElementById('autoStartBreak').checked,
        dailyGoal: parseInt(document.getElementById('dailyGoalInput').value) || 4,
        linkedSubject: document.getElementById('pomSubject')?.value || '',
        linkedTask: document.getElementById('pomTask')?.value || ''
    };
    localStorage.setItem('studify_pom_settings', JSON.stringify(settings));
}

// ---- Task/Subject Linking ----
function onSubjectChange() {
    saveSettings();
}

// Persist task selection
document.getElementById('pomTask')?.addEventListener('change', saveSettings);
document.getElementById('pomSubject')?.addEventListener('change', saveSettings);

// ---- Daily Goal ----
function updateDailyGoal() {
    const goal = parseInt(localStorage.getItem('studify_pom_settings') ? 
        JSON.parse(localStorage.getItem('studify_pom_settings')).dailyGoal : 0) || 0;
    const card = document.getElementById('dailyGoalCard');
    if (goal > 0) {
        card.style.display = 'block';
        const todaySessions = <?php echo intval($today['today_sessions'] ?? 0); ?> + pomSessionCount;
        const pct = Math.min(100, Math.round((todaySessions / goal) * 100));
        document.getElementById('goalProgress').style.width = pct + '%';
        document.getElementById('goalText').textContent = todaySessions + ' / ' + goal + ' sessions (' + pct + '%)';
        if (pct >= 100) {
            document.getElementById('goalProgress').classList.remove('bg-primary');
            document.getElementById('goalProgress').classList.add('bg-success');
            // Trigger celebration once
            if (!pomGoalCelebrated && pomSessionCount > 0) {
                pomGoalCelebrated = true;
                showGoalCelebration();
            }
        }
    } else {
        card.style.display = 'none';
    }
}

function saveDailyGoal() {
    saveSettings();
    pomGoalCelebrated = false; // Allow re-celebration with new goal
    updateDailyGoal();
    bootstrap.Modal.getInstance(document.getElementById('dailyGoalModal')).hide();
    if (typeof StudifyToast !== 'undefined') StudifyToast.success('Daily goal saved!');
}

// ---- Goal Celebration ----
function showGoalCelebration() {
    const overlay = document.getElementById('goalCelebration');
    if (!overlay) return;
    overlay.style.display = 'flex';
    // Confetti particles
    createConfetti(overlay);
    playSound(); // Chime
}

function dismissCelebration() {
    const overlay = document.getElementById('goalCelebration');
    if (overlay) {
        overlay.style.display = 'none';
        overlay.querySelectorAll('.confetti-particle').forEach(p => p.remove());
    }
}

function createConfetti(container) {
    const colors = ['#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6', '#ec4899'];
    for (let i = 0; i < 50; i++) {
        const p = document.createElement('div');
        p.className = 'confetti-particle';
        p.style.cssText = `
            position: absolute;
            width: ${6 + Math.random() * 8}px;
            height: ${6 + Math.random() * 8}px;
            background: ${colors[Math.floor(Math.random() * colors.length)]};
            left: ${Math.random() * 100}%;
            top: -10px;
            border-radius: ${Math.random() > 0.5 ? '50%' : '2px'};
            animation: confettiFall ${2 + Math.random() * 3}s ease-in forwards;
            animation-delay: ${Math.random() * 0.5}s;
            opacity: 0.9;
        `;
        container.appendChild(p);
    }
}

// ---- Timer Core ----
function initTimer() {
    const focusMin = parseInt(document.getElementById('focusTime').value) || 25;
    pomSeconds = focusMin * 60;
    pomTotalSeconds = pomSeconds;
    updateDisplay();
    updateRing();
    updateLongBreakHint();
}

function updateDisplay() {
    const m = Math.floor(pomSeconds / 60);
    const s = pomSeconds % 60;
    const timeStr = String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
    document.getElementById('timerDisplay').textContent = timeStr;
    
    // Update browser tab title with countdown
    if (pomRunning) {
        const mode = pomIsBreak ? '☕' : '🎯';
        document.title = mode + ' ' + timeStr + ' — Studify';
    }
}

function updateRing() {
    const progress = pomTotalSeconds > 0 ? pomSeconds / pomTotalSeconds : 1;
    const offset = circumference * progress;
    ring.setAttribute('stroke-dashoffset', circumference - offset);
    ring.style.stroke = pomIsBreak ? 'var(--success)' : 'var(--primary)';
}

function updateLongBreakHint() {
    const remaining = LONG_BREAK_INTERVAL - (pomSessionCount % LONG_BREAK_INTERVAL);
    const hint = document.getElementById('longBreakHint');
    const counter = document.getElementById('untilLongBreak');
    if (remaining === LONG_BREAK_INTERVAL && pomSessionCount > 0) {
        hint.innerHTML = '<i class="fas fa-mug-hot text-success"></i> <strong>Long break earned!</strong>';
    } else {
        counter.textContent = remaining;
    }
}

// ---- Persistent Timer State ----
function savePomState() {
    const state = {
        running: pomRunning,
        isBreak: pomIsBreak,
        totalSeconds: pomTotalSeconds,
        sessionCount: pomSessionCount,
        savedAt: Date.now(),
        remainingSeconds: pomSeconds,
        focusMin: parseInt(document.getElementById('focusTime').value) || 25
    };
    localStorage.setItem('studify_pom_state', JSON.stringify(state));
}

function loadPomState() {
    const raw = localStorage.getItem('studify_pom_state');
    if (!raw) return false;
    try {
        const state = JSON.parse(raw);
        if (!state.savedAt) return false;
        
        pomSessionCount = state.sessionCount || 0;
        document.getElementById('sessionCounter').textContent = pomSessionCount;
        
        if (state.running) {
            // Calculate elapsed time since save
            const elapsed = Math.floor((Date.now() - state.savedAt) / 1000);
            const remaining = state.remainingSeconds - elapsed;
            
            if (remaining > 0) {
                pomIsBreak = state.isBreak;
                pomTotalSeconds = state.totalSeconds;
                pomSeconds = remaining;
                updateDisplay();
                updateRing();
                updateSessionTypeUI();
                // Auto-resume
                pomodoroStart();
                if (typeof StudifyToast !== 'undefined') StudifyToast.info('Timer resumed from where you left off!');
                return true;
            } else {
                // Timer would have ended — just restore session count
                clearPomState();
            }
        }
        return false;
    } catch(e) {
        return false;
    }
}

function clearPomState() {
    localStorage.removeItem('studify_pom_state');
}

function updateSessionTypeUI() {
    const badge = document.getElementById('sessionType');
    if (pomIsBreak) {
        badge.innerHTML = '<i class="fas fa-coffee"></i> Break';
    } else {
        badge.innerHTML = '<i class="fas fa-brain"></i> Focus Session';
    }
}

function pomodoroStart() {
    if (pomRunning) return;
    pomRunning = true;
    document.getElementById('startBtn').style.display = 'none';
    document.getElementById('pauseBtn').style.display = 'inline-block';
    document.getElementById('timerSubtext').textContent = pomIsBreak ? 'BREAK' : 'FOCUSING';

    // Auto-play ambiance on focus start
    if (!pomIsBreak && typeof FocusAmbiance !== 'undefined') {
        FocusAmbiance.onFocusStart();
    }

    savePomState();

    pomInterval = setInterval(() => {
        if (pomSeconds <= 0) {
            clearInterval(pomInterval);
            pomRunning = false;
            playSound();
            sendNotification();

            if (!pomIsBreak) {
                // Focus session completed
                pomSessionCount++;
                document.getElementById('sessionCounter').textContent = pomSessionCount;
                saveSession(parseInt(document.getElementById('focusTime').value), 'Focus');
                updateDailyGoal();

                // Ask user if they want to mark the linked task as complete
                showTaskCompletePrompt();

                // Determine break type: long break every 4 sessions
                const isLongBreak = pomSessionCount % LONG_BREAK_INTERVAL === 0;
                pomIsBreak = true;

                let breakMin;
                if (isLongBreak) {
                    breakMin = parseInt(document.getElementById('longBreakTime').value) || 15;
                    document.getElementById('sessionType').innerHTML = '<i class="fas fa-mug-hot"></i> Long Break — You earned it!';
                    if (typeof StudifyToast !== 'undefined') StudifyToast.success('🎉 ' + LONG_BREAK_INTERVAL + ' sessions done! Enjoy a long break.');
                } else {
                    breakMin = parseInt(document.getElementById('breakTime').value) || 5;
                    document.getElementById('sessionType').innerHTML = '<i class="fas fa-coffee"></i> Short Break';
                    if (typeof StudifyToast !== 'undefined') StudifyToast.success('Focus session complete! Take a break.');
                }

                pomSeconds = breakMin * 60;
                pomTotalSeconds = pomSeconds;
                document.getElementById('timerSubtext').textContent = 'BREAK READY';
                if (typeof FocusAmbiance !== 'undefined') FocusAmbiance.onBreakStart();
                updateLongBreakHint();

                savePomState();

                // Auto-start break if enabled
                if (document.getElementById('autoStartBreak').checked) {
                    setTimeout(() => pomodoroStart(), 1000);
                    return;
                }
            } else {
                // Break completed
                pomIsBreak = false;
                const focusMin = parseInt(document.getElementById('focusTime').value) || 25;
                pomSeconds = focusMin * 60;
                pomTotalSeconds = pomSeconds;
                document.getElementById('sessionType').innerHTML = '<i class="fas fa-brain"></i> Focus Session';
                document.getElementById('timerSubtext').textContent = 'READY';
                updateLongBreakHint();
                if (typeof StudifyToast !== 'undefined') StudifyToast.info('Break over! Ready for another focus session?');
            }
            updateDisplay();
            updateRing();
            document.getElementById('startBtn').style.display = 'inline-block';
            document.getElementById('pauseBtn').style.display = 'none';
            document.title = originalTitle;
            savePomState();
            return;
        }
        pomSeconds--;
        updateDisplay();
        updateRing();
        // Save state every 10 seconds for persistence
        if (pomSeconds % 10 === 0) savePomState();
    }, 1000);
}

function pomodoroPause() {
    clearInterval(pomInterval);
    pomRunning = false;
    document.getElementById('startBtn').style.display = 'inline-block';
    document.getElementById('pauseBtn').style.display = 'none';
    document.getElementById('timerSubtext').textContent = 'PAUSED';
    document.title = '⏸ PAUSED — Studify';
    if (typeof FocusAmbiance !== 'undefined') FocusAmbiance.onPause();
    savePomState();
}

function pomodoroReset() {
    clearInterval(pomInterval);
    pomRunning = false;
    pomIsBreak = false;
    initTimer();
    document.getElementById('sessionType').innerHTML = '<i class="fas fa-brain"></i> Focus Session';
    document.getElementById('timerSubtext').textContent = 'READY';
    document.getElementById('startBtn').style.display = 'inline-block';
    document.getElementById('pauseBtn').style.display = 'none';
    document.title = originalTitle;
    if (typeof FocusAmbiance !== 'undefined') FocusAmbiance.stopAll();
    clearPomState();
}

function pomodoroSkip() {
    clearInterval(pomInterval);
    pomRunning = false;
    if (pomIsBreak) {
        // Skip break → go to focus
        pomIsBreak = false;
        const focusMin = parseInt(document.getElementById('focusTime').value) || 25;
        pomSeconds = focusMin * 60;
        pomTotalSeconds = pomSeconds;
        document.getElementById('sessionType').innerHTML = '<i class="fas fa-brain"></i> Focus Session';
        document.getElementById('timerSubtext').textContent = 'READY';
        if (typeof StudifyToast !== 'undefined') StudifyToast.info('Break skipped. Ready to focus!');
    } else {
        // Skip focus → go to break (don't count session)
        pomIsBreak = true;
        const breakMin = parseInt(document.getElementById('breakTime').value) || 5;
        pomSeconds = breakMin * 60;
        pomTotalSeconds = pomSeconds;
        document.getElementById('sessionType').innerHTML = '<i class="fas fa-coffee"></i> Short Break';
        document.getElementById('timerSubtext').textContent = 'BREAK READY';
        if (typeof StudifyToast !== 'undefined') StudifyToast.info('Session skipped. Take a break.');
    }
    updateDisplay();
    updateRing();
    document.getElementById('startBtn').style.display = 'inline-block';
    document.getElementById('pauseBtn').style.display = 'none';
    document.title = originalTitle;
    savePomState();
}

function applyPreset(focus, brk, longBrk) {
    document.getElementById('focusTime').value = focus;
    document.getElementById('breakTime').value = brk;
    document.getElementById('longBreakTime').value = longBrk || 15;
    saveSettings();
    if (!pomRunning) pomodoroReset();
}

// ---- Ambiance Presets ----
function applyAmbiancePreset(preset) {
    if (typeof FocusAmbiance === 'undefined') return;
    FocusAmbiance.stopAll();
    const presets = {
        cozy:   ['rain', 'fire'],
        nature: ['birds', 'waves'],
        cafe:   ['coffee', 'rain'],
        storm:  ['rain', 'wind'],
        zen:    ['birds', 'wind']
    };
    const sounds = presets[preset] || [];
    sounds.forEach(s => FocusAmbiance.start(s));
    if (typeof StudifyToast !== 'undefined') {
        const names = { cozy: 'Cozy', nature: 'Nature', cafe: 'Study Café', storm: 'Storm', zen: 'Zen' };
        StudifyToast.info('🎵 ' + (names[preset] || preset) + ' ambiance activated');
    }
}

// ---- Task Completion Prompt ----
let pendingCompleteTaskId = null;
let pendingCompleteTaskTitle = '';

function showTaskCompletePrompt() {
    const taskSelect = document.getElementById('pomTask');
    const taskId = taskSelect?.value;
    if (!taskId) return; // No task linked, skip

    const taskName = taskSelect.options[taskSelect.selectedIndex]?.text || 'this task';
    pendingCompleteTaskId = taskId;
    pendingCompleteTaskTitle = taskName;

    document.getElementById('promptTaskName').textContent = taskName;
    document.getElementById('taskCompletePrompt').style.display = 'flex';
}

function dismissTaskPrompt() {
    document.getElementById('taskCompletePrompt').style.display = 'none';
    pendingCompleteTaskId = null;
    pendingCompleteTaskTitle = '';
}

function confirmCompleteTask() {
    if (!pendingCompleteTaskId) return;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const formData = new FormData();
    formData.append('action', 'complete_task');
    formData.append('task_id', pendingCompleteTaskId);
    formData.append('csrf_token', csrfToken);

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Remove the completed task from the dropdown
            const taskSelect = document.getElementById('pomTask');
            const option = taskSelect.querySelector('option[value="' + pendingCompleteTaskId + '"]');
            if (option) option.remove();
            taskSelect.value = '';
            saveSettings();

            if (typeof StudifyToast !== 'undefined') {
                StudifyToast.success('🎉 "' + (data.task_title || pendingCompleteTaskTitle) + '" marked as complete!');
            }
        } else {
            if (typeof StudifyToast !== 'undefined') StudifyToast.error(data.message || 'Could not complete task');
        }
    })
    .catch(() => {
        if (typeof StudifyToast !== 'undefined') StudifyToast.error('Network error. Please try again.');
    })
    .finally(() => {
        dismissTaskPrompt();
    });
}

// ---- Session Save (AJAX with CSRF) ----
function saveSession(duration, type) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const taskId = document.getElementById('pomTask')?.value || '';
    const subjectId = document.getElementById('pomSubject')?.value || '';
    const formData = new FormData();
    formData.append('duration', duration);
    formData.append('type', type);
    formData.append('csrf_token', csrfToken);
    if (taskId) formData.append('task_id', taskId);
    if (subjectId) formData.append('subject_id', subjectId);
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    }).then(() => {
        // Refresh session history after saving
        loadSessionHistory(1);
    }).catch(() => {
        // Fallback to form submit
        document.getElementById('durationInput').value = duration;
        document.getElementById('typeInput').value = type;
        document.getElementById('taskIdInput').value = taskId;
        document.getElementById('subjectIdInput').value = subjectId;
        document.getElementById('sessionForm').submit();
    });
}

// ---- Session History ----
function loadSessionHistory(page) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const formData = new FormData();
    formData.append('action', 'get_history');
    formData.append('page', page);
    formData.append('csrf_token', csrfToken);
    
    const list = document.getElementById('sessionHistoryList');
    list.innerHTML = '<div class="text-center text-muted py-3" style="font-size: 13px;"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success || !data.sessions.length) {
            list.innerHTML = '<div class="text-center text-muted py-3" style="font-size: 13px;"><i class="fas fa-clock"></i> No sessions recorded yet</div>';
            document.getElementById('historyPagination').style.display = 'none';
            return;
        }
        let html = '<div class="pom-history-list">';
        data.sessions.forEach(s => {
            const date = new Date(s.created_at);
            const timeStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ' ' +
                            date.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
            const icon = s.session_type === 'Focus' ? 'fa-brain text-primary' : 'fa-coffee text-success';
            const badge = s.session_type === 'Focus' ? 'bg-primary' : 'bg-success';
            const taskLabel = s.task_title ? `<span class="pom-hist-tag"><i class="fas fa-tasks"></i> ${escHtml(s.task_title)}</span>` : '';
            const subjLabel = s.subject_name ? `<span class="pom-hist-tag"><i class="fas fa-book"></i> ${escHtml(s.subject_name)}</span>` : '';
            html += `
                <div class="pom-hist-row">
                    <div class="pom-hist-icon"><i class="fas ${icon}"></i></div>
                    <div class="pom-hist-body">
                        <div class="pom-hist-head">
                            <span class="badge ${badge}" style="font-size: 10px;">${s.session_type}</span>
                            <strong>${s.duration} min</strong>
                        </div>
                        <div class="pom-hist-meta">
                            ${taskLabel}${subjLabel}
                            <span class="pom-hist-time"><i class="far fa-clock"></i> ${timeStr}</span>
                        </div>
                    </div>
                </div>`;
        });
        html += '</div>';
        list.innerHTML = html;

        // Pagination
        const pag = document.getElementById('historyPagination');
        if (data.pages > 1) {
            pag.style.display = 'flex';
            pag.removeAttribute('style');
            let pagHtml = '';
            if (page > 1) pagHtml += `<button class="btn btn-sm btn-outline-secondary" onclick="loadSessionHistory(${page - 1})"><i class="fas fa-chevron-left"></i></button>`;
            pagHtml += `<span class="text-muted" style="font-size: 12px; line-height: 32px;">${page} / ${data.pages}</span>`;
            if (page < data.pages) pagHtml += `<button class="btn btn-sm btn-outline-secondary" onclick="loadSessionHistory(${page + 1})"><i class="fas fa-chevron-right"></i></button>`;
            pag.innerHTML = pagHtml;
        } else {
            pag.style.display = 'none';
        }
    })
    .catch(() => {
        list.innerHTML = '<div class="text-center text-muted py-3" style="font-size: 13px;"><i class="fas fa-exclamation-circle"></i> Failed to load history</div>';
    });
}

function escHtml(str) {
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}

// ---- Sound & Notification ----
function playSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        // Play two-tone chime (more noticeable)
        [800, 1000].forEach((freq, i) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = freq;
            osc.type = 'sine';
            const start = ctx.currentTime + (i * 0.25);
            gain.gain.setValueAtTime(0.3, start);
            gain.gain.exponentialRampToValueAtTime(0.01, start + 0.4);
            osc.start(start);
            osc.stop(start + 0.4);
        });
    } catch (e) {}
}

function sendNotification() {
    if (Notification.permission === 'granted') {
        const msg = pomIsBreak ? 'Break is over! Ready for focus?' : 'Focus session complete! Take a break.';
        new Notification('Studify Pomodoro', { body: msg, icon: '<?php echo BASE_URL; ?>assets/images/icon-192.png' });
    }
}

// Request notification permission on first start
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
    }
}

// ---- Keyboard Shortcuts ----
document.addEventListener('keydown', function(e) {
    // Don't trigger shortcuts when typing in inputs
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;
    
    switch (e.code) {
        case 'Space':
            e.preventDefault();
            if (pomRunning) {
                pomodoroPause();
            } else {
                pomodoroStart();
            }
            break;
        case 'KeyR':
            if (!e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                pomodoroReset();
            }
            break;
        case 'KeyS':
            if (!e.ctrlKey && !e.metaKey) {
                e.preventDefault();
                pomodoroSkip();
            }
            break;
        case 'KeyM':
            e.preventDefault();
            if (typeof FocusAmbiance !== 'undefined') {
                if (FocusAmbiance.active.size > 0) {
                    FocusAmbiance._mutedSounds = new Set(FocusAmbiance.active);
                    FocusAmbiance.stopAll();
                    if (typeof StudifyToast !== 'undefined') StudifyToast.info('🔇 Ambiance muted');
                } else if (FocusAmbiance._mutedSounds && FocusAmbiance._mutedSounds.size > 0) {
                    [...FocusAmbiance._mutedSounds].forEach(s => FocusAmbiance.start(s));
                    FocusAmbiance._mutedSounds = new Set();
                    if (typeof StudifyToast !== 'undefined') StudifyToast.info('🔊 Ambiance unmuted');
                }
            }
            break;
        case 'Digit1':
            if (!e.ctrlKey && !e.metaKey) { e.preventDefault(); applyPreset(25, 5, 15); if (typeof StudifyToast !== 'undefined') StudifyToast.info('Classic 25/5 preset applied'); }
            break;
        case 'Digit2':
            if (!e.ctrlKey && !e.metaKey) { e.preventDefault(); applyPreset(50, 10, 25); if (typeof StudifyToast !== 'undefined') StudifyToast.info('Deep 50/10 preset applied'); }
            break;
        case 'Digit3':
            if (!e.ctrlKey && !e.metaKey) { e.preventDefault(); applyPreset(90, 20, 30); if (typeof StudifyToast !== 'undefined') StudifyToast.info('Marathon 90/20 preset applied'); }
            break;
    }
});

// ---- Prevent Accidental Page Leave ----
window.addEventListener('beforeunload', function(e) {
    if (pomRunning) {
        savePomState(); // Save state before leaving
        e.preventDefault();
        e.returnValue = 'Timer is running! Are you sure you want to leave?';
    }
});

// ---- Settings Change Listeners ----
['focusTime', 'breakTime', 'longBreakTime'].forEach(id => {
    document.getElementById(id).addEventListener('change', function() {
        saveSettings();
        if (!pomRunning && !pomIsBreak) { initTimer(); }
    });
});
document.getElementById('autoStartBreak').addEventListener('change', saveSettings);

// ---- Init ----
loadSettings();
// Try to restore persistent timer state first
if (!loadPomState()) {
    initTimer();
}
updateDailyGoal();
requestNotificationPermission();
loadSessionHistory(1);

// Weekly Study Chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('weeklyChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($week_labels); ?>,
                datasets: [{
                    label: 'Minutes Studied',
                    data: <?php echo json_encode($week_data); ?>,
                    backgroundColor: 'rgba(22, 163, 74, 0.6)',
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 15 }, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
