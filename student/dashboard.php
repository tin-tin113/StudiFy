<?php
/**
 * STUDIFY – Student Dashboard v5.0
 * Customizable widgets · Study Streak · Achievements
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/gamification.php';

requireLogin();
if (isAdminRole()) { header("Location: " . BASE_URL . "admin/admin_dashboard.php"); exit(); }

$page_title = 'Dashboard';
$user_id    = getCurrentUserId();
$user       = getUserInfo($user_id, $conn);

if (!$user_id || !$user) { header("Location: " . BASE_URL . "auth/login.php"); exit(); }

$stats           = getDashboardStats($user_id, $conn);
$total_tasks     = $stats['total_tasks'];
$pending_tasks   = $stats['pending_tasks'];
$completed_tasks = $stats['completed_tasks'];
$completion_pct  = $stats['completion_pct'];
$upcoming        = getUpcomingTasks($user_id, $conn, 5);
$active_semester = getActiveSemester($user_id, $conn);

// Charts data
$priority_q = $conn->prepare("SELECT priority, COUNT(*) as count FROM tasks WHERE user_id=? GROUP BY priority");
$priority_q->bind_param("i", $user_id);
$priority_q->execute();
$priority_res  = $priority_q->get_result();
$priority_data = ['High' => 0, 'Medium' => 0, 'Low' => 0];
while ($row = $priority_res->fetch_assoc()) $priority_data[$row['priority']] = $row['count'];

// Weekly stats
$week_study_q = $conn->prepare("SELECT COALESCE(SUM(duration),0) as m FROM study_sessions WHERE user_id=? AND DATE(created_at)>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)");
$week_study_q->bind_param("i", $user_id); $week_study_q->execute();
$week_study = $week_study_q->get_result()->fetch_assoc()['m'];

$week_done_q = $conn->prepare("SELECT COUNT(*) c FROM tasks WHERE user_id=? AND status='Completed' AND updated_at>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)");
$week_done_q->bind_param("i", $user_id); $week_done_q->execute();
$week_completed = $week_done_q->get_result()->fetch_assoc()['c'];

$week_added_q = $conn->prepare("SELECT COUNT(*) c FROM tasks WHERE user_id=? AND created_at>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)");
$week_added_q->bind_param("i", $user_id); $week_added_q->execute();
$week_added = $week_added_q->get_result()->fetch_assoc()['c'];

$week_sess_q = $conn->prepare("SELECT COUNT(*) c FROM study_sessions WHERE user_id=? AND session_type='Focus' AND DATE(created_at)>=DATE_SUB(CURDATE(),INTERVAL 7 DAY)");
$week_sess_q->bind_param("i", $user_id); $week_sess_q->execute();
$week_sessions = $week_sess_q->get_result()->fetch_assoc()['c'];

// Announcements
$ann_q = $conn->prepare("SELECT a.* FROM announcements a WHERE (a.expires_at IS NULL OR a.expires_at>=CURDATE()) AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id=?) ORDER BY a.created_at DESC");
$ann_q->bind_param("i", $user_id); $ann_q->execute();
$unread_announcements = $ann_q->get_result()->fetch_all(MYSQLI_ASSOC);

// Gamification
$streak       = getStudyStreak($user_id, $conn);
$gamification = checkAndAwardAchievements($user_id, $conn);
$widgets      = getDashboardWidgets($user_id, $conn);

$hour = date('H');
$greeting = $hour < 12 ? 'Good Morning' : ($hour < 17 ? 'Good Afternoon' : 'Good Evening');
?>
<?php include '../includes/header.php'; ?>

<!-- SortableJS CDN -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<style>
/* ── Dashboard Widget System ── */
.widget-grid { display: flex; flex-direction: column; gap: 20px; }
.widget-card { position: relative; transition: box-shadow .2s, transform .15s; }
.widget-card.dragging { opacity: .6; transform: scale(0.98); box-shadow: 0 12px 32px rgba(0,0,0,.2) !important; }
.widget-drag-handle { cursor: grab; color: var(--text-muted); padding: 4px 6px; border-radius: 4px; }
.widget-drag-handle:hover { background: var(--bg-secondary); color: var(--text-primary); }
.widget-drag-handle:active { cursor: grabbing; }
.widget-hidden { display: none !important; }

/* ── Streak Widget ── */
.streak-widget { background: linear-gradient(135deg, #16a34a 0%, #059669 50%, #0d9488 100%); color: #fff; border-radius: var(--border-radius); padding: 24px; }
.streak-number { font-size: 52px; font-weight: 800; line-height: 1; }
.streak-days-grid { display: flex; gap: 6px; margin-top: 16px; }
.streak-day { flex: 1; text-align: center; }
.streak-day-dot { width: 28px; height: 28px; border-radius: 50%; margin: 0 auto 4px; display: flex; align-items: center; justify-content: center; font-size: 11px; }
.streak-day-dot.active { background: rgba(255,255,255,0.9); color: #16a34a; }
.streak-day-dot.inactive { background: rgba(255,255,255,0.2); color: rgba(255,255,255,0.6); }
.streak-day-dot.today { border: 2px solid #fff; }
.streak-day-label { font-size: 10px; opacity: .75; }

/* ── Achievement Cards ── */
.achievements-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; }
.achievement-card { text-align: center; padding: 16px 12px; border-radius: 12px; border: 2px solid; transition: transform .15s; position: relative; }
.achievement-card.unlocked { transform: none; }
.achievement-card.locked { filter: grayscale(0.7); opacity: .55; border-style: dashed; }
.achievement-card:hover { transform: translateY(-2px); }
.achievement-icon { font-size: 28px; margin-bottom: 8px; }
.achievement-title { font-weight: 700; font-size: 12px; margin-bottom: 4px; }
.achievement-desc { font-size: 10.5px; color: var(--text-muted); line-height: 1.4; }
.achievement-new-badge { position: absolute; top: -6px; right: -6px; background: #dc2626; color: #fff; border-radius: 999px; font-size: 9px; padding: 2px 6px; font-weight: 700; }

/* ── Widget Customize Panel ── */
.customize-btn { position: fixed; bottom: 24px; right: 24px; z-index: 500; width: 52px; height: 52px; border-radius: 50%; background: var(--primary); color: #fff; border: none; box-shadow: 0 4px 16px rgba(22,163,74,.4); display: flex; align-items: center; justify-content: center; font-size: 18px; cursor: pointer; transition: transform .2s, box-shadow .2s; }
.customize-btn:hover { transform: scale(1.1) rotate(20deg); box-shadow: 0 8px 24px rgba(22,163,74,.5); }
.widget-toggle-item { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid var(--border-color); }
.widget-toggle-item:last-child { border-bottom: none; }
</style>

        <?php
                $overdue_stmt = $conn->prepare(
                        "SELECT COUNT(*) as cnt
                         FROM notifications
                         WHERE user_id = ?
                             AND type = 'overdue'
                             AND is_read = 0
                             AND is_dismissed = 0"
                );
                $overdue_stmt->bind_param("i", $user_id);
                $overdue_stmt->execute();
                $overdue_count = intval($overdue_stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
        // Show newly-awarded achievement toasts via JS below
        $newly_awarded = $gamification['newly_awarded'];
        ?>

        <?php if ($overdue_count > 0): ?>
        <div class="overdue-banner mb-4" id="overdueBanner">
            <div class="d-flex align-items-center gap-3">
                <div class="overdue-banner-icon"><i class="fas fa-exclamation-triangle"></i></div>
                <div>
                    <div class="fw-700" style="font-size:14px;">You have <?php echo $overdue_count; ?> overdue task<?php echo $overdue_count > 1 ? 's' : ''; ?></div>
                    <div style="font-size:12px;opacity:.9;">These tasks have passed their deadline. Consider completing or rescheduling them.</div>
                </div>
                <div class="ms-auto d-flex gap-2" style="white-space:nowrap;">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="dismissOverdueBanner(this)">
                        <i class="fas fa-times"></i> Dismiss
                    </button>
                    <a href="tasks.php?status=overdue" class="btn btn-sm btn-light"><i class="fas fa-eye"></i> View Tasks</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="welcome-card mb-4">
            <div class="welcome-accent"></div>
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                <div>
                    <h2><?php echo $greeting; ?>, <?php echo htmlspecialchars($user['name']); ?> <?php if ($streak['current'] >= 3): ?>🔥<?php endif; ?></h2>
                    <p>
                        <?php if ($pending_tasks > 0): ?>
                            You have <strong><?php echo $pending_tasks; ?> pending task<?php echo $pending_tasks > 1 ? 's' : ''; ?></strong>. Let's stay productive today.
                        <?php else: ?> All tasks are completed. Great work! <?php endif; ?>
                        <?php if ($streak['current'] > 0): ?>
                            &nbsp;·&nbsp; <span style="color:var(--primary);font-weight:600;"><i class="fas fa-fire"></i> <?php echo $streak['current']; ?>-day streak!</span>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Announcements -->
        <?php foreach ($unread_announcements as $ann):
            $ann_color = match($ann['priority']) { 'Urgent' => 'danger', 'Important' => 'warning', default => 'info' };
        ?>
        <div class="alert alert-<?php echo $ann_color; ?> fade show mb-3" id="ann-<?php echo $ann['id']; ?>" style="display:flex;align-items:flex-start;justify-content:space-between;">
            <div class="d-flex align-items-start gap-2">
                <i class="fas fa-bullhorn mt-1"></i>
                <div>
                    <strong><?php echo htmlspecialchars($ann['title']); ?></strong>
                    <p class="mb-0" style="font-size:13px;"><?php echo htmlspecialchars($ann['content']); ?></p>
                    <small class="text-muted"><?php echo formatDate($ann['created_at']); ?></small>
                </div>
            </div>
            <button type="button" class="btn-close" style="flex-shrink:0;margin-left:12px;" onclick="dismissAnnouncement(<?php echo $ann['id']; ?>, this)"></button>
        </div>
        <?php endforeach; ?>

        <!-- ─── WIDGET GRID ─── -->
        <div class="widget-grid" id="widgetGrid">
        <?php foreach ($widgets as $w):
            $wkey    = $w['key'];
            $visible = $w['visible'];
        ?>
        <div class="widget-card <?php echo $visible ? '' : 'widget-hidden'; ?>" data-widget="<?php echo $wkey; ?>" id="widget-<?php echo $wkey; ?>">

        <?php if ($wkey === 'streak'): ?>
        <!-- ══ STREAK WIDGET ══ -->
        <div class="streak-widget card">
            <div class="d-flex align-items-start justify-content-between">
                <div>
                    <div style="font-size:13px;opacity:.85;margin-bottom:4px;"><i class="fas fa-fire"></i> Study Streak</div>
                    <div class="streak-number"><?php echo $streak['current']; ?></div>
                    <div style="font-size:13px;opacity:.85;margin-top:4px;">day<?php echo $streak['current'] !== 1 ? 's' : ''; ?> in a row</div>
                    <?php if ($streak['longest'] > $streak['current']): ?>
                    <div style="font-size:11px;opacity:.7;margin-top:4px;">Best: <?php echo $streak['longest']; ?> days</div>
                    <?php endif; ?>
                    <?php if (!$streak['today']): ?>
                    <div style="font-size:11px;opacity:.8;margin-top:8px;">⚡ Complete a task today to keep your streak!</div>
                    <?php endif; ?>
                </div>
                <div style="text-align:right;">
                    <div style="font-size:11px;opacity:.75;margin-bottom:8px;">Last 7 Days</div>
                    <div class="streak-days-grid" style="flex-direction:column;gap:4px;">
                    <?php foreach ($streak['last_7'] as $day => $active):
                        $dayLabel = date('D', strtotime($day));
                        $isToday = $day === date('Y-m-d');
                    ?>
                        <div style="display:flex;align-items:center;gap:8px;justify-content:flex-end;">
                            <span style="font-size:10px;opacity:.75;"><?php echo $dayLabel; ?></span>
                            <div class="streak-day-dot <?php echo $active ? 'active' : 'inactive'; ?> <?php echo $isToday ? 'today' : ''; ?>">
                                <?php echo $active ? '✓' : ''; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($wkey === 'weekly'): ?>
        <!-- ══ WEEKLY SUMMARY ══ -->
        <div class="card">
            <div class="card-body py-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <i class="fas fa-calendar-week" style="color:var(--primary);"></i>
                        <span class="fw-600" style="font-size:14px;">This Week</span>
                    </div>
                    <span class="badge bg-primary" style="font-size:10px;">Last 7 days</span>
                </div>
                <div class="row g-3 text-center">
                    <div class="col-3">
                        <div class="fw-bold" style="font-size:22px;color:var(--primary);"><?php echo $week_completed; ?></div>
                        <div class="text-muted" style="font-size:11px;">Tasks Done</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold" style="font-size:22px;color:var(--info);"><?php echo $week_added; ?></div>
                        <div class="text-muted" style="font-size:11px;">New Tasks</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold" style="font-size:22px;color:var(--success);"><?php echo $week_sessions; ?></div>
                        <div class="text-muted" style="font-size:11px;">Pomodoros</div>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold" style="font-size:22px;color:var(--accent,#d97706);"><?php echo round($week_study/60,1); ?>h</div>
                        <div class="text-muted" style="font-size:11px;">Hours Studied</div>
                    </div>
                </div>
            </div>
        </div>

        <?php elseif ($wkey === 'stats'): ?>
        <!-- ══ STATS CARDS ══ -->
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
            <div class="card stat-card" style="border-left-color:var(--success);">
                <div class="stat-icon success"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo $completed_tasks; ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="card stat-card info">
                <div class="stat-icon info"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo round($week_study/60,1); ?>h</div>
                <div class="stat-label">Study (Week)</div>
            </div>
        </div>

        <?php elseif ($wkey === 'progress'): ?>
        <!-- ══ PROGRESS CHARTS ══ -->
        <div class="card">
            <div class="card-header"><i class="fas fa-chart-line"></i> Overall Progress</div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-500" style="font-size:13px;">Task Completion</span>
                    <span class="fw-600" style="color:var(--primary);font-size:13px;"><?php echo $completion_pct; ?>%</span>
                </div>
                <div class="progress progress-lg mb-4">
                    <div class="progress-bar bg-success" style="width:<?php echo $completion_pct; ?>%"></div>
                </div>
                <div class="row">
                    <div class="col-md-6"><div class="chart-container"><canvas id="statusChart"></canvas></div></div>
                    <div class="col-md-6"><div class="chart-container"><canvas id="priorityChart"></canvas></div></div>
                </div>
            </div>
        </div>

        <?php elseif ($wkey === 'upcoming'): ?>
        <!-- ══ UPCOMING DEADLINES ══ -->
        <div class="card">
            <div class="card-header"><i class="fas fa-bell"></i> Upcoming Deadlines</div>
            <div class="card-body" style="padding:8px;">
                <?php if (count($upcoming) > 0): ?>
                    <?php foreach ($upcoming as $task):
                        $dl = new DateTime($task['deadline']); $now = new DateTime();
                        $diff = $now->diff($dl); $is_overdue = $dl < $now;
                        $days_text = $is_overdue ? 'Overdue' : ($diff->days == 0 ? 'Today' : ($diff->days == 1 ? 'Tomorrow' : $diff->days . ' days'));
                    ?>
                    <div class="d-flex align-items-start gap-3 p-3" style="border-bottom:1px solid var(--border-color);">
                        <div style="width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;
                            background:<?php echo $is_overdue?'var(--danger-light)':($diff->days<=1?'var(--warning-light)':'var(--primary-50)');?>;
                            color:<?php echo $is_overdue?'var(--danger)':($diff->days<=1?'var(--warning)':'var(--primary)');?>;">
                            <i class="fas fa-<?php echo $is_overdue?'exclamation':'calendar-day';?>" style="font-size:12px;"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="fw-600" style="font-size:12.5px;"><?php echo htmlspecialchars($task['title']); ?></div>
                            <div class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($task['subject_name']); ?> · <?php echo $days_text; ?></div>
                        </div>
                        <span class="badge bg-<?php echo $is_overdue?'danger':($diff->days<=1?'warning':'secondary');?>" style="font-size:10px;">
                            <?php echo date('M d', strtotime($task['deadline'])); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state" style="padding:24px;">
                        <i class="fas fa-calendar-check" style="font-size:28px;"></i>
                        <p class="mt-2 mb-0" style="font-size:12px;">No upcoming deadlines</p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center">
                <a href="tasks.php" class="fw-600" style="font-size:12px;color:var(--primary);">View All Tasks <i class="fas fa-arrow-right ms-1"></i></a>
            </div>
        </div>

        <?php elseif ($wkey === 'semester'): ?>
        <!-- ══ ACTIVE SEMESTER ══ -->
        <?php if ($active_semester): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-graduation-cap"></i> Active Semester</span>
                <a href="subjects.php?semester_id=<?php echo $active_semester['id']; ?>" class="btn btn-sm btn-primary">View Subjects</a>
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
                        <div class="d-flex align-items-center gap-3 p-3 rounded-md" style="background:var(--bg-card-hover);border:1px solid var(--border-color);">
                            <div style="width:36px;height:36px;border-radius:8px;background:var(--primary-50);color:var(--primary);display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-book" style="font-size:14px;"></i>
                            </div>
                            <div>
                                <div class="fw-600" style="font-size:13px;"><?php echo htmlspecialchars($subject['name']); ?></div>
                                <div class="text-muted" style="font-size:11px;"><?php echo $task_count; ?> tasks</div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <p class="text-muted mb-0" style="font-size:13px;">No subjects yet. <a href="subjects.php?semester_id=<?php echo $active_semester['id']; ?>">Add one</a>.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php elseif ($wkey === 'quickact'): ?>
        <!-- ══ QUICK ACTIONS ══ -->
        <div class="card">
            <div class="card-header"><i class="fas fa-bolt"></i> Quick Actions</div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="tasks.php" class="btn btn-primary"><i class="fas fa-plus"></i> New Task</a>
                    <a href="pomodoro.php" class="btn btn-secondary"><i class="fas fa-clock"></i> Start Pomodoro</a>
                    <a href="calendar.php" class="btn btn-secondary"><i class="fas fa-calendar"></i> View Calendar</a>
                    <a href="notes.php" class="btn btn-secondary"><i class="fas fa-sticky-note"></i> My Notes</a>
                </div>
            </div>
        </div>

        <?php elseif ($wkey === 'achievements'): ?>
        <!-- ══ ACHIEVEMENTS ══ -->
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span><i class="fas fa-trophy"></i> Achievements</span>
                <span class="badge bg-primary" style="font-size:10px;">
                    <?php echo count(array_filter($gamification['achievements'], fn($a) => $a['unlocked'])); ?> / <?php echo count($gamification['achievements']); ?> Unlocked
                </span>
            </div>
            <div class="card-body">
                <div class="achievements-grid">
                    <?php foreach ($gamification['achievements'] as $ach): ?>
                    <div class="achievement-card <?php echo $ach['unlocked'] ? 'unlocked' : 'locked'; ?>"
                         style="border-color:<?php echo $ach['unlocked'] ? $ach['color'] : 'var(--border-color)'; ?>; background:<?php echo $ach['unlocked'] ? $ach['color'].'15' : 'var(--bg-secondary)'; ?>;"
                         title="<?php echo htmlspecialchars($ach['desc']); ?>">
                        <?php if (in_array($ach['key'], array_column($newly_awarded, 'key'))): ?>
                        <span class="achievement-new-badge">NEW!</span>
                        <?php endif; ?>
                        <div class="achievement-icon"><?php echo $ach['icon']; ?></div>
                        <div class="achievement-title" style="color:<?php echo $ach['unlocked'] ? $ach['color'] : 'var(--text-muted)'; ?>;"><?php echo htmlspecialchars($ach['title']); ?></div>
                        <div class="achievement-desc"><?php echo htmlspecialchars($ach['desc']); ?></div>
                        <?php if ($ach['unlocked'] && $ach['unlocked_at']): ?>
                        <div style="font-size:9px;color:var(--text-muted);margin-top:6px;"><?php echo date('M j, Y', strtotime($ach['unlocked_at'])); ?></div>
                        <?php elseif (!$ach['unlocked']): ?>
                        <div style="font-size:9px;color:var(--text-muted);margin-top:6px;"><i class="fas fa-lock"></i> Locked</div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        </div><!-- end widget-card -->
        <?php endforeach; ?>
        </div><!-- end widget-grid -->

<!-- ── Customize Widgets Panel (FAB) ── -->
<button class="customize-btn" onclick="document.getElementById('widgetCustomizer').classList.toggle('d-none')" title="Customize Dashboard">
    <i class="fas fa-sliders-h"></i>
</button>

<div id="widgetCustomizer" class="d-none" style="position:fixed;bottom:88px;right:24px;z-index:499;width:280px;">
    <div class="card" style="box-shadow:0 8px 32px rgba(0,0,0,.15);">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span style="font-size:13px;font-weight:700;"><i class="fas fa-sliders-h"></i> Customize Dashboard</span>
            <button class="btn btn-sm btn-secondary" onclick="document.getElementById('widgetCustomizer').classList.add('d-none')" style="padding:2px 8px;font-size:11px;">✕</button>
        </div>
        <div class="card-body" style="padding:12px 16px;">
            <p style="font-size:11px;color:var(--text-muted);margin-bottom:12px;"><i class="fas fa-arrows-alt"></i> Drag cards to reorder. Toggle to show/hide.</p>
            <div id="widgetToggleList">
            <?php foreach ($widgets as $w): ?>
            <div class="widget-toggle-item">
                <div style="display:flex;align-items:center;gap:8px;">
                    <i class="<?php echo $w['icon']; ?>" style="width:14px;color:var(--primary);font-size:12px;"></i>
                    <span style="font-size:12px;"><?php echo htmlspecialchars($w['label']); ?></span>
                </div>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" <?php echo $w['visible'] ? 'checked' : ''; ?>
                           onchange="toggleWidget('<?php echo $w['key']; ?>', this.checked)"
                           style="cursor:pointer;">
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// ─── Charts ───────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', function() {
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type:'doughnut',
            data:{ labels:['Completed','Pending'],
                datasets:[{ data:[<?php echo $completed_tasks;?>,<?php echo $pending_tasks;?>],
                    backgroundColor:['#16A34A','#EAB308'], borderWidth:0, borderRadius:3, spacing:2 }] },
            options:{ responsive:true, cutout:'68%',
                plugins:{ legend:{ position:'bottom', labels:{ padding:14, usePointStyle:true, font:{family:'Inter',size:11,weight:'500'} } },
                    title:{ display:true, text:'Task Status', font:{family:'Inter',size:13,weight:'600'}, padding:{bottom:12} } } }
        });
    }
    const priorityCtx = document.getElementById('priorityChart');
    if (priorityCtx) {
        new Chart(priorityCtx, {
            type:'bar',
            data:{ labels:['High','Medium','Low'],
                datasets:[{ label:'Tasks', data:[<?php echo $priority_data['High'];?>,<?php echo $priority_data['Medium'];?>,<?php echo $priority_data['Low'];?>],
                    backgroundColor:['#DC2626','#EAB308','#16A34A'], borderRadius:6, borderSkipped:false, barThickness:36 }] },
            options:{ responsive:true,
                scales:{ y:{beginAtZero:true, ticks:{stepSize:1,font:{family:'Inter',size:11}}, grid:{color:'rgba(0,0,0,0.04)'}},
                    x:{ticks:{font:{family:'Inter',size:11,weight:'500'}}, grid:{display:false}} },
                plugins:{ legend:{display:false},
                    title:{display:true,text:'Tasks by Priority',font:{family:'Inter',size:13,weight:'600'},padding:{bottom:12}} } }
        });
    }

    // ─── SortableJS drag-and-drop ─────────────────────────────
    Sortable.create(document.getElementById('widgetGrid'), {
        animation: 200,
        handle: '.widget-drag-handle',
        ghostClass: 'dragging',
        onEnd: function() { saveWidgetLayout(); }
    });

    // ─── Newly awarded achievement toasts ─────────────────────
    <?php foreach ($newly_awarded as $na): ?>
    setTimeout(() => StudifyToast.success('🏆 Achievement Unlocked!', '<?php echo addslashes($na['icon'] . ' ' . $na['title']); ?>'), 1500);
    <?php endforeach; ?>
});

// ─── Toggle widget visibility ─────────────────────────────────
function toggleWidget(key, visible) {
    const el = document.getElementById('widget-' + key);
    if (el) el.classList.toggle('widget-hidden', !visible);
    saveWidgetLayout();
}

// ─── Save full layout to server ───────────────────────────────
function saveWidgetLayout() {
    const cards = document.querySelectorAll('#widgetGrid .widget-card');
    const layout = [];
    cards.forEach((card, idx) => {
        const key = card.dataset.widget;
        layout.push({
            key:      key,
            position: idx,
            visible:  card.classList.contains('widget-hidden') ? 0 : 1
        });
    });

    const baseUrl = '<?php echo BASE_URL; ?>';
    fetch(baseUrl + 'student/save_widgets.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=save_layout&layout=' + encodeURIComponent(JSON.stringify(layout)) + '&csrf_token=' + getCSRFToken()
    });
}

// ─── Dismiss announcement ─────────────────────────────────────
function dismissAnnouncement(annId, btn) {
    var el = document.getElementById('ann-' + annId);
    if (el) { el.style.transition='opacity .3s'; el.style.opacity='0'; setTimeout(()=>el.remove(),300); }
    fetch('dismiss_announcement.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'announcement_id='+annId+'&csrf_token='+getCSRFToken() });
}

// ─── Dismiss overdue banner & notifications ───────────────────
function dismissOverdueBanner(btn) {
    const banner = document.getElementById('overdueBanner');
    if (btn) btn.disabled = true;

    fetch('notification_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=dismiss_overdue&csrf_token=' + encodeURIComponent(getCSRFToken())
    }).then(r => r.json()).then(data => {
        if (data.success && banner) {
            banner.style.transition = 'opacity .3s ease, transform .3s ease';
            banner.style.opacity = '0';
            banner.style.transform = 'translateY(-6px)';
            setTimeout(() => banner.remove(), 300);
        } else if (btn) {
            btn.disabled = false;
        }
    }).catch(() => { if (btn) btn.disabled = false; });
}
</script>

<?php include '../includes/footer.php'; ?>
