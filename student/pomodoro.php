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
    $duration = intval($_POST['duration'] ?? 0);
    $type = sanitize($_POST['type'] ?? 'Focus');
    if ($duration > 0) {
        $stmt = $conn->prepare("INSERT INTO study_sessions (user_id, duration, session_type) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $duration, $type);
        $stmt->execute();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit();
}

// Handle form POST (fallback)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_session'])) {
    $duration = intval($_POST['duration'] ?? 0);
    $type = sanitize($_POST['type'] ?? 'Focus');
    if ($duration > 0) {
        $stmt = $conn->prepare("INSERT INTO study_sessions (user_id, duration, session_type) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $duration, $type);
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

                        <div class="mt-4">
                            <span class="text-muted" style="font-size: 13px;">
                                <i class="fas fa-fire"></i> Sessions Completed: <strong id="sessionCounter">0</strong>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Timer Settings -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-sliders-h"></i> Timer Settings</h6>
                        <div class="row g-3">
                            <div class="col-6">
                                <label for="focusTime" class="form-label">Focus (min)</label>
                                <input type="number" class="form-control" id="focusTime" value="25" min="1" max="90">
                            </div>
                            <div class="col-6">
                                <label for="breakTime" class="form-label">Break (min)</label>
                                <input type="number" class="form-control" id="breakTime" value="5" min="1" max="30">
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <button class="btn btn-sm btn-secondary" onclick="applyPreset(25, 5)">25 / 5</button>
                            <button class="btn btn-sm btn-secondary" onclick="applyPreset(50, 10)">50 / 10</button>
                            <button class="btn btn-sm btn-secondary" onclick="applyPreset(90, 20)">90 / 20</button>
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
                            <div class="stat-number" style="color: var(--info);">
                                <?php echo $stats['total_sessions'] ?? 0; ?>
                            </div>
                            <div class="stat-label">30-Day Sessions</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card stat-card">
                            <div class="stat-number" style="color: var(--accent);">
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

                <!-- Tips -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-lightbulb text-warning"></i> Pomodoro Tips</h6>
                        <ul class="mb-0" style="font-size: 13px; line-height: 2;">
                            <li>Focus on <strong>one task</strong> per session</li>
                            <li>Remove all distractions — phone, social media, etc.</li>
                            <li>After <strong>4 sessions</strong>, take a longer 15-30 min break</li>
                            <li>Use break time to stretch and rest your eyes</li>
                            <li>Adjust durations to match your productivity rhythm</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

<!-- Hidden fallback form -->
<form id="sessionForm" method="POST" style="display:none;">
    <input type="hidden" name="save_session" value="1">
    <input type="hidden" id="durationInput" name="duration">
    <input type="hidden" id="typeInput" name="type">
</form>

<script>
// ---- Pomodoro Timer (page-specific, works with SVG ring) ----
let pomSeconds = 0;
let pomRunning = false;
let pomInterval = null;
let pomSessionCount = 0;
let pomIsBreak = false;
let pomTotalSeconds = 0;

const ring = document.getElementById('progressRing');
const circumference = 2 * Math.PI * 115; // r=115
ring.setAttribute('stroke-dasharray', circumference);

function initTimer() {
    const focusMin = parseInt(document.getElementById('focusTime').value) || 25;
    pomSeconds = focusMin * 60;
    pomTotalSeconds = pomSeconds;
    updateDisplay();
    updateRing();
}
initTimer();

function updateDisplay() {
    const m = Math.floor(pomSeconds / 60);
    const s = pomSeconds % 60;
    document.getElementById('timerDisplay').textContent =
        String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
}

function updateRing() {
    const progress = pomTotalSeconds > 0 ? pomSeconds / pomTotalSeconds : 1;
    const offset = circumference * progress;
    ring.setAttribute('stroke-dashoffset', circumference - offset);
    ring.style.stroke = pomIsBreak ? 'var(--success)' : 'var(--primary)';
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

    pomInterval = setInterval(() => {
        if (pomSeconds <= 0) {
            clearInterval(pomInterval);
            pomRunning = false;
            playSound();

            if (!pomIsBreak) {
                pomSessionCount++;
                document.getElementById('sessionCounter').textContent = pomSessionCount;
                saveSession(parseInt(document.getElementById('focusTime').value), 'Focus');

                pomIsBreak = true;
                const breakMin = parseInt(document.getElementById('breakTime').value) || 5;
                pomSeconds = breakMin * 60;
                pomTotalSeconds = pomSeconds;
                document.getElementById('sessionType').innerHTML = '<i class="fas fa-coffee"></i> Break Time';
                document.getElementById('timerSubtext').textContent = 'BREAK READY';
                if (typeof FocusAmbiance !== 'undefined') FocusAmbiance.onBreakStart();
                if (typeof StudifyToast !== 'undefined') StudifyToast.success('Focus session complete! Take a break.');
            } else {
                pomIsBreak = false;
                const focusMin = parseInt(document.getElementById('focusTime').value) || 25;
                pomSeconds = focusMin * 60;
                pomTotalSeconds = pomSeconds;
                document.getElementById('sessionType').innerHTML = '<i class="fas fa-brain"></i> Focus Session';
                document.getElementById('timerSubtext').textContent = 'READY';
                if (typeof StudifyToast !== 'undefined') StudifyToast.info('Break over! Ready for another focus session?');
            }
            updateDisplay();
            updateRing();
            document.getElementById('startBtn').style.display = 'inline-block';
            document.getElementById('pauseBtn').style.display = 'none';
            return;
        }
        pomSeconds--;
        updateDisplay();
        updateRing();
    }, 1000);
}

function pomodoroPause() {
    clearInterval(pomInterval);
    pomRunning = false;
    document.getElementById('startBtn').style.display = 'inline-block';
    document.getElementById('pauseBtn').style.display = 'none';
    document.getElementById('timerSubtext').textContent = 'PAUSED';
    if (typeof FocusAmbiance !== 'undefined') FocusAmbiance.onPause();
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
    if (typeof FocusAmbiance !== 'undefined') FocusAmbiance.stopAll();
}

function applyPreset(focus, brk) {
    document.getElementById('focusTime').value = focus;
    document.getElementById('breakTime').value = brk;
    if (!pomRunning) pomodoroReset();
}

function saveSession(duration, type) {
    const formData = new FormData();
    formData.append('duration', duration);
    formData.append('type', type);
    fetch(window.location.href, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    }).catch(() => {
        // Fallback to form submit
        document.getElementById('durationInput').value = duration;
        document.getElementById('typeInput').value = type;
        document.getElementById('sessionForm').submit();
    });
}

function playSound() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.frequency.value = 800;
        osc.type = 'sine';
        gain.gain.setValueAtTime(0.3, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.5);
        osc.start(ctx.currentTime);
        osc.stop(ctx.currentTime + 0.5);
    } catch (e) {}
}

document.getElementById('focusTime').addEventListener('change', function() {
    if (!pomRunning && !pomIsBreak) { initTimer(); }
});

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
