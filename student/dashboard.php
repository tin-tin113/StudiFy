<?php
/**
 * STUDIFY – Student Dashboard
 * Clean stat cards, charts, upcoming deadlines
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

$page_title = 'Dashboard';
$user_id = getCurrentUserId();
$user = getUserInfo($user_id, $conn);

if (!$user_id || !$user) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$stats = getDashboardStats($user_id, $conn);
$total_tasks = $stats['total_tasks'];
$pending_tasks = $stats['pending_tasks'];
$completed_tasks = $stats['completed_tasks'];
$completion_pct = $stats['completion_pct'];
$upcoming = getUpcomingTasks($user_id, $conn, 5);
$semesters = getUserSemesters($user_id, $conn);
$active_semester = getActiveSemester($user_id, $conn);

$in_progress = $stats['in_progress_tasks'];

$priority_query = "SELECT t.priority, COUNT(*) as count FROM tasks t 
                   WHERE t.user_id = ? GROUP BY t.priority";
$stmt = $conn->prepare($priority_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$priority_result = $stmt->get_result();
$priority_data = ['High' => 0, 'Medium' => 0, 'Low' => 0];
while ($row = $priority_result->fetch_assoc()) {
    $priority_data[$row['priority']] = $row['count'];
}

$week_study_query = "SELECT COALESCE(SUM(duration), 0) as total_minutes 
                     FROM study_sessions 
                     WHERE user_id = ? AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
$stmt = $conn->prepare($week_study_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$week_study = $stmt->get_result()->fetch_assoc()['total_minutes'];

// Weekly summary data
$week_completed_q = $conn->prepare("SELECT COUNT(*) as c FROM tasks t WHERE t.user_id = ? AND t.status = 'Completed' AND t.updated_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$week_completed_q->bind_param("i", $user_id);
$week_completed_q->execute();
$week_completed = $week_completed_q->get_result()->fetch_assoc()['c'];

$week_added_q = $conn->prepare("SELECT COUNT(*) as c FROM tasks t WHERE t.user_id = ? AND t.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$week_added_q->bind_param("i", $user_id);
$week_added_q->execute();
$week_added = $week_added_q->get_result()->fetch_assoc()['c'];

$week_sessions_q = $conn->prepare("SELECT COUNT(*) as c FROM study_sessions WHERE user_id = ? AND session_type = 'Focus' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$week_sessions_q->bind_param("i", $user_id);
$week_sessions_q->execute();
$week_sessions = $week_sessions_q->get_result()->fetch_assoc()['c'];

// Unread announcements
$ann_query = "SELECT a.* FROM announcements a 
              WHERE (a.expires_at IS NULL OR a.expires_at >= CURDATE())
              AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id = ?)
              ORDER BY a.created_at DESC";
$ann_stmt = $conn->prepare($ann_query);
$ann_stmt->bind_param("i", $user_id);
$ann_stmt->execute();
$unread_announcements = $ann_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$hour = date('H');
if ($hour < 12) $greeting = 'Good Morning';
elseif ($hour < 17) $greeting = 'Good Afternoon';
else $greeting = 'Good Evening';
?>
<?php include '../includes/header.php'; ?>

        <!-- Welcome Banner -->
        <div class="welcome-card mb-4">
            <div class="welcome-accent"></div>
            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2><?php echo $greeting; ?>, <?php echo htmlspecialchars($user['name']); ?></h2>
                    <p>
                        <?php if ($pending_tasks > 0): ?>
                            You have <strong><?php echo $pending_tasks; ?> pending task<?php echo $pending_tasks > 1 ? 's' : ''; ?></strong>. Let's stay productive today.
                        <?php else: ?>
                            All tasks are completed. Great work!
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Announcements Banner -->
        <?php if (count($unread_announcements) > 0): ?>
        <?php foreach ($unread_announcements as $ann):
            $ann_color = match($ann['priority']) { 'Urgent' => 'danger', 'Important' => 'warning', default => 'info' };
        ?>
        <div class="alert alert-<?php echo $ann_color; ?> fade show mb-3" role="alert" id="ann-<?php echo $ann['id']; ?>" style="display: flex; align-items: flex-start; justify-content: space-between;">
            <div class="d-flex align-items-start gap-2">
                <i class="fas fa-bullhorn mt-1"></i>
                <div>
                    <strong><?php echo htmlspecialchars($ann['title']); ?></strong>
                    <p class="mb-0" style="font-size: 13px;"><?php echo htmlspecialchars($ann['content']); ?></p>
                    <small class="text-muted"><?php echo formatDate($ann['created_at']); ?></small>
                </div>
            </div>
            <button type="button" class="btn-close" style="flex-shrink: 0; margin-left: 12px;" onclick="dismissAnnouncement(<?php echo $ann['id']; ?>, this)"></button>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- Weekly Summary Card -->
        <div class="card mb-4">
            <div class="card-body py-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="fas fa-calendar-week" style="color: var(--primary);"></i>
                    <span class="fw-600" style="font-size: 14px;">This Week</span>
                </div>
                <div class="row g-3 text-center">
                    <div class="col-3">
                        <div class="fw-bold" style="font-size: 20px; color: var(--primary);"><?php echo $week_completed; ?></div>
                        <div class="text-muted" style="font-size: 11px;">Tasks Done</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold" style="font-size: 20px; color: var(--info);"><?php echo $week_added; ?></div>
                        <div class="text-muted" style="font-size: 11px;">New Tasks</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold" style="font-size: 20px; color: var(--success);"><?php echo $week_sessions; ?></div>
                        <div class="text-muted" style="font-size: 11px;">Study Sessions</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold" style="font-size: 20px; color: var(--accent, #d97706);"><?php echo round($week_study / 60, 1); ?>h</div>
                        <div class="text-muted" style="font-size: 11px;">Hours Studied</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="dashboard-grid">
            <div class="card stat-card">
                <div class="stat-icon primary"><i class="fas fa-layer-group"></i></div>
                <div class="stat-number"><?php echo $total_tasks; ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="card stat-card warning">
                <div class="stat-icon warning"><i class="fas fa-hourglass-half"></i></div>
                <div class="stat-number"><?php echo $pending_tasks; ?></div>
                <div class="stat-label">Pending</div>
            </div>
            <div class="card stat-card" style="border-left-color: var(--success);">
                <div class="stat-icon success"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo $completed_tasks; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="card stat-card info">
                <div class="stat-icon info"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo round($week_study / 60, 1); ?>h</div>
                <div class="stat-label">Study (Week)</div>
            </div>
        </div>

        <div class="row">
            <!-- Charts -->
            <div class="col-lg-8 mb-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i> Overall Progress
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-500" style="font-size: 13px;">Task Completion</span>
                            <span class="fw-600" style="color: var(--primary); font-size: 13px;"><?php echo $completion_pct; ?>%</span>
                        </div>
                        <div class="progress progress-lg mb-4">
                            <div class="progress-bar bg-success" style="width: <?php echo $completion_pct; ?>%"></div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="chart-container">
                                    <canvas id="statusChart"></canvas>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="chart-container">
                                    <canvas id="priorityChart"></canvas>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($active_semester): ?>
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-graduation-cap"></i> Active Semester</span>
                        <a href="subjects.php?semester_id=<?php echo $active_semester['id']; ?>" class="btn btn-sm btn-primary">
                            View Subjects
                        </a>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-600 mb-3"><?php echo htmlspecialchars($active_semester['name']); ?></h6>
                        <?php 
                        $subjects = getSemesterSubjects($active_semester['id'], $conn);
                        if (count($subjects) > 0): ?>
                            <div class="row g-2">
                                <?php foreach ($subjects as $subject): 
                                    $task_count = count(getSubjectTasks($subject['id'], $conn));
                                ?>
                                <div class="col-md-6">
                                    <div class="d-flex align-items-center gap-3 p-3 rounded-md" style="background: var(--bg-card-hover); border: 1px solid var(--border-color);">
                                        <div style="width: 36px; height: 36px; border-radius: 8px; background: var(--primary-50); color: var(--primary); display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-book" style="font-size: 14px;"></i>
                                        </div>
                                        <div>
                                            <div class="fw-600" style="font-size: 13px;"><?php echo htmlspecialchars($subject['name']); ?></div>
                                            <div class="text-muted" style="font-size: 11px;"><?php echo $task_count; ?> tasks</div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0" style="font-size: 13px;">No subjects yet. <a href="subjects.php?semester_id=<?php echo $active_semester['id']; ?>">Add one</a>.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4 mb-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-bell"></i> Upcoming Deadlines
                    </div>
                    <div class="card-body" style="padding: 8px;">
                        <?php if (count($upcoming) > 0): ?>
                            <?php foreach ($upcoming as $task): 
                                $deadline = new DateTime($task['deadline']);
                                $now = new DateTime();
                                $diff = $now->diff($deadline);
                                $is_overdue = $deadline < $now;
                                $days_text = $is_overdue ? 'Overdue' : ($diff->days == 0 ? 'Today' : ($diff->days == 1 ? 'Tomorrow' : $diff->days . ' days'));
                            ?>
                            <div class="d-flex align-items-start gap-3 p-3" style="border-bottom: 1px solid var(--border-color);">
                                <div style="width: 32px; height: 32px; border-radius: 6px; display: flex; align-items: center; justify-content: center; flex-shrink: 0;
                                    background: <?php echo $is_overdue ? 'var(--danger-light)' : ($diff->days <= 1 ? 'var(--warning-light)' : 'var(--primary-50)'); ?>;
                                    color: <?php echo $is_overdue ? 'var(--danger)' : ($diff->days <= 1 ? 'var(--warning)' : 'var(--primary)'); ?>;">
                                    <i class="fas fa-<?php echo $is_overdue ? 'exclamation' : 'calendar-day'; ?>" style="font-size: 12px;"></i>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div class="fw-600" style="font-size: 12.5px;"><?php echo htmlspecialchars($task['title']); ?></div>
                                    <div class="text-muted" style="font-size: 11px;">
                                        <?php echo htmlspecialchars($task['subject_name']); ?> · <?php echo $days_text; ?>
                                    </div>
                                </div>
                                <span class="badge bg-<?php echo $is_overdue ? 'danger' : ($diff->days <= 1 ? 'warning' : 'secondary'); ?>" style="font-size: 10px;">
                                    <?php echo date('M d', strtotime($task['deadline'])); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state" style="padding: 24px;">
                                <i class="fas fa-calendar-check" style="font-size: 28px;"></i>
                                <p class="mt-2 mb-0" style="font-size: 12px;">No upcoming deadlines</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer text-center">
                        <a href="tasks.php" class="fw-600" style="font-size: 12px; color: var(--primary);">
                            View All Tasks <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="tasks.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> New Task
                            </a>
                            <a href="pomodoro.php" class="btn btn-secondary">
                                <i class="fas fa-clock"></i> Start Pomodoro
                            </a>
                            <a href="calendar.php" class="btn btn-secondary">
                                <i class="fas fa-calendar"></i> View Calendar
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Completed', 'Pending', 'In Progress'],
                datasets: [{
                    data: [<?php echo $completed_tasks; ?>, <?php echo $pending_tasks; ?>, <?php echo $in_progress; ?>],
                    backgroundColor: ['#16A34A', '#EAB308', '#2563EB'],
                    borderWidth: 0,
                    borderRadius: 3,
                    spacing: 2
                }]
            },
            options: {
                responsive: true,
                cutout: '68%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { padding: 14, usePointStyle: true, pointStyleWidth: 8, font: { family: 'Inter', size: 11, weight: '500' } }
                    },
                    title: {
                        display: true,
                        text: 'Task Status',
                        font: { family: 'Inter', size: 13, weight: '600' },
                        padding: { bottom: 12 }
                    }
                }
            }
        });
    }

    const priorityCtx = document.getElementById('priorityChart');
    if (priorityCtx) {
        new Chart(priorityCtx, {
            type: 'bar',
            data: {
                labels: ['High', 'Medium', 'Low'],
                datasets: [{
                    label: 'Tasks',
                    data: [<?php echo $priority_data['High']; ?>, <?php echo $priority_data['Medium']; ?>, <?php echo $priority_data['Low']; ?>],
                    backgroundColor: ['#DC2626', '#EAB308', '#16A34A'],
                    borderRadius: 6,
                    borderSkipped: false,
                    barThickness: 36
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, font: { family: 'Inter', size: 11 } }, grid: { color: 'rgba(0,0,0,0.04)' } },
                    x: { ticks: { font: { family: 'Inter', size: 11, weight: '500' } }, grid: { display: false } }
                },
                plugins: {
                    legend: { display: false },
                    title: {
                        display: true,
                        text: 'Tasks by Priority',
                        font: { family: 'Inter', size: 13, weight: '600' },
                        padding: { bottom: 12 }
                    }
                }
            }
        });
    }
});

// Dismiss announcement
function dismissAnnouncement(annId, btnElement) {
    var alertEl = document.getElementById('ann-' + annId);
    if (alertEl) {
        alertEl.style.transition = 'opacity 0.3s ease';
        alertEl.style.opacity = '0';
        setTimeout(function() { alertEl.remove(); }, 300);
    }

    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('dismiss_announcement.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'announcement_id=' + annId + '&csrf_token=' + csrfToken
    }).then(function(response) {
        return response.json();
    }).then(function(data) {
        if (!data.success) {
            console.error('Failed to dismiss announcement:', data.error);
        }
    }).catch(function(err) {
        console.error('Error dismissing announcement:', err);
    });
}

// Dismiss onboarding
function dismissOnboarding() {
    var card = document.getElementById('onboardingCard');
    if (card) {
        card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        card.style.opacity = '0';
        card.style.transform = 'translateY(-10px)';
        setTimeout(function() { card.remove(); }, 300);
    }
    
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('dismiss_onboarding.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'csrf_token=' + csrfToken
    });
}
</script>

<?php include '../includes/footer.php'; ?>
