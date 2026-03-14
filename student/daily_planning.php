<?php
/**
 * STUDIFY – Daily Planning View
 * Plan your day with time blocks and priorities
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

$page_title = 'Daily Planning';
$user_id = getCurrentUserId();

// Get selected date (default to today)
$selected_date = $_GET['date'] ?? date('Y-m-d');
$date_obj = new DateTime($selected_date);
$today = date('Y-m-d');
$is_today = $selected_date === $today;

// Get tasks for selected date
$tasks = getUserTasksFiltered($user_id, $conn, 0, '', 'deadline', 'ASC');
$day_tasks = [];
$upcoming_tasks = [];

foreach ($tasks as $task) {
    $task_date = date('Y-m-d', strtotime($task['deadline']));
    if ($task_date === $selected_date) {
        $day_tasks[] = $task;
    } elseif ($task_date > $selected_date && $task['status'] !== 'Completed') {
        $upcoming_tasks[] = $task;
    }
}

// Get study sessions for the day
$stmt = $conn->prepare("SELECT * FROM study_sessions 
    WHERE user_id = ? AND DATE(created_at) = ? 
    ORDER BY created_at ASC");
$stmt->bind_param("is", $user_id, $selected_date);
$stmt->execute();
$study_sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate total study time for the day
$total_study_minutes = 0;
foreach ($study_sessions as $session) {
    $total_study_minutes += $session['duration'];
}

// Get top 3 priorities (high priority tasks due today or soon)
$priority_tasks = array_filter($day_tasks, function($t) {
    return $t['priority'] === 'High' && $t['status'] !== 'Completed';
});
$priority_tasks = array_slice($priority_tasks, 0, 3);

// Get active semester and subjects
$active_semester = getActiveSemester($user_id, $conn);
$all_subjects = [];
if ($active_semester) {
    $all_subjects = getSemesterSubjects($active_semester['id'], $conn);
}

// Handle form submissions
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_time_block') {
        $subject_id = !empty($_POST['subject_id']) ? intval($_POST['subject_id']) : null;
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $description = sanitize($_POST['description'] ?? '');
        
        if (empty($start_time) || empty($end_time)) {
            $error = 'Start and end times are required.';
        } else {
            // Store time block in session or create a simple tasks table entry
            // For now, we'll create a task with a special type
            $start_datetime = $selected_date . ' ' . $start_time . ':00';
            $end_datetime = $selected_date . ' ' . $end_time . ':00';
            
            $stmt = $conn->prepare("INSERT INTO tasks 
                (user_id, subject_id, title, description, type, priority, deadline, status) 
                VALUES (?, ?, ?, ?, 'Other', 'Medium', ?, 'Pending')");
            $title = 'Study Block: ' . ($description ?: 'Focused Study');
            $stmt->bind_param("iisss", $user_id, $subject_id, $title, $description, $start_datetime);
            
            if ($stmt->execute()) {
                $success = 'Time block added successfully!';
                header('Location: daily_planning.php?date=' . $selected_date);
                exit();
            } else {
                $error = 'Error adding time block.';
            }
        }
    }
}

// Get time blocks (tasks with type 'Other' for the selected date)
$time_blocks = [];
foreach ($day_tasks as $task) {
    if ($task['type'] === 'Other' && strpos($task['title'], 'Study Block:') === 0) {
        $time_blocks[] = $task;
    }
}
?>
<?php include '../includes/header.php'; ?>

<style>
.time-block {
    border-left: 4px solid var(--primary);
    padding: 12px;
    margin-bottom: 8px;
    background: var(--bg-card);
    border-radius: var(--border-radius-sm);
}

.time-slot {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 12px;
    border-radius: var(--border-radius-sm);
    background: var(--bg-secondary);
    margin-bottom: 4px;
}

.hour-label {
    font-weight: 600;
    color: var(--text-muted);
    font-size: 12px;
    min-width: 60px;
}

.priority-badge-high {
    background: var(--danger-light);
    color: var(--danger);
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
}

.calendar-day {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    cursor: pointer;
    transition: all 0.2s;
}

.calendar-day:hover {
    background: var(--bg-secondary);
}

.calendar-day.active {
    background: var(--primary);
    color: white;
}

.calendar-day.today {
    border: 2px solid var(--primary);
}
</style>

<div class="page-header">
    <div>
        <h2><i class="fas fa-calendar-day"></i> Daily Planning</h2>
        <p class="text-muted mb-0" style="font-size: 13px;">Plan your day with time blocks and priorities</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="?date=<?php echo date('Y-m-d', strtotime($selected_date . ' -1 day')); ?>" 
           class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-chevron-left"></i>
        </a>
        <input type="date" class="form-control form-control-sm" style="width: 150px;" 
               value="<?php echo $selected_date; ?>" 
               onchange="window.location.href='?date=' + this.value">
        <a href="?date=<?php echo date('Y-m-d', strtotime($selected_date . ' +1 day')); ?>" 
           class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-chevron-right"></i>
        </a>
        <?php if (!$is_today): ?>
        <a href="?date=<?php echo $today; ?>" class="btn btn-sm btn-primary">Today</a>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: Time Blocks & Schedule -->
    <div class="col-lg-8">
        <!-- Top 3 Priorities -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-star text-warning"></i> Today's Top 3 Priorities</h5>
                <small class="text-muted"><?php echo date('l, M d', strtotime($selected_date)); ?></small>
            </div>
            <div class="card-body">
                <?php if (count($priority_tasks) > 0): ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($priority_tasks as $idx => $task): ?>
                        <div class="d-flex align-items-center gap-3 p-3 rounded" 
                             style="background: var(--bg-secondary);">
                            <div class="priority-badge-high">#<?php echo $idx + 1; ?></div>
                            <div style="flex: 1;">
                                <div class="fw-600"><?php echo htmlspecialchars($task['title']); ?></div>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> 
                                    <?php echo date('g:i A', strtotime($task['deadline'])); ?>
                                    <?php if ($task['subject_name']): ?>
                                        · <i class="fas fa-book"></i> <?php echo htmlspecialchars($task['subject_name']); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <a href="tasks.php?task_id=<?php echo $task['id']; ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-3 mb-0">
                        <i class="fas fa-info-circle"></i> No high-priority tasks for this day.
                        <br><small>Add tasks with high priority to see them here.</small>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Time Blocks Schedule -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-clock"></i> Time Blocks</h5>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addTimeBlockModal">
                    <i class="fas fa-plus"></i> Add Time Block
                </button>
            </div>
            <div class="card-body">
                <?php if (count($time_blocks) > 0): ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($time_blocks as $block): ?>
                        <div class="time-block">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-600"><?php echo htmlspecialchars(str_replace('Study Block: ', '', $block['title'])); ?></div>
                                    <small class="text-muted">
                                        <i class="fas fa-clock"></i> 
                                        <?php echo date('g:i A', strtotime($block['deadline'])); ?>
                                        <?php if ($block['subject_name']): ?>
                                            · <?php echo htmlspecialchars($block['subject_name']); ?>
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <a href="tasks.php?task_id=<?php echo $block['id']; ?>" 
                                   class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4 mb-0">
                        <i class="fas fa-calendar-plus" style="font-size: 32px; opacity: 0.3;"></i>
                        <br><br>No time blocks scheduled for this day.
                        <br><small>Click "Add Time Block" to schedule your study sessions.</small>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Tasks for the Day -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-tasks"></i> Tasks for <?php echo date('M d', strtotime($selected_date)); ?></h5>
            </div>
            <div class="card-body">
                <?php if (count($day_tasks) > 0): ?>
                    <div class="d-flex flex-column gap-2">
                        <?php foreach ($day_tasks as $task): 
                            $is_completed = $task['status'] === 'Completed';
                            $is_high_priority = $task['priority'] === 'High';
                        ?>
                        <div class="d-flex align-items-center gap-3 p-2 rounded <?php echo $is_completed ? 'opacity-50' : ''; ?>" 
                             style="background: var(--bg-secondary);">
                            <input type="checkbox" class="form-check-input" 
                                   <?php echo $is_completed ? 'checked' : ''; ?>
                                   onchange="updatePlanningTask(<?php echo $task['id']; ?>, this.checked)">
                            <div style="flex: 1;">
                                <div class="fw-600 <?php echo $is_completed ? 'text-decoration-line-through' : ''; ?>">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> <?php echo date('g:i A', strtotime($task['deadline'])); ?>
                                    <?php if ($task['subject_name']): ?>
                                        · <?php echo htmlspecialchars($task['subject_name']); ?>
                                    <?php endif; ?>
                                    <?php if ($is_high_priority): ?>
                                        · <span class="badge bg-danger" style="font-size: 9px;">High</span>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <a href="tasks.php?task_id=<?php echo $task['id']; ?>" 
                               class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted text-center py-4 mb-0">
                        <i class="fas fa-check-circle" style="font-size: 32px; opacity: 0.3;"></i>
                        <br><br>No tasks scheduled for this day.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Column: Stats & Quick Actions -->
    <div class="col-lg-4">
        <!-- Daily Stats -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-bar"></i> Daily Stats</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-3">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted" style="font-size: 12px;">Tasks</span>
                            <span class="fw-600"><?php echo count($day_tasks); ?></span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <?php 
                            $completed_count = count(array_filter($day_tasks, fn($t) => $t['status'] === 'Completed'));
                            $completion_pct = count($day_tasks) > 0 ? round(($completed_count / count($day_tasks)) * 100) : 0;
                            ?>
                            <div class="progress-bar bg-success" style="width: <?php echo $completion_pct; ?>%"></div>
                        </div>
                        <small class="text-muted"><?php echo $completed_count; ?> completed</small>
                    </div>

                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted" style="font-size: 12px;">Study Time</span>
                            <span class="fw-600"><?php echo round($total_study_minutes / 60, 1); ?>h</span>
                        </div>
                        <small class="text-muted"><?php echo count($study_sessions); ?> session<?php echo count($study_sessions) !== 1 ? 's' : ''; ?></small>
                    </div>

                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted" style="font-size: 12px;">Time Blocks</span>
                            <span class="fw-600"><?php echo count($time_blocks); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-2">
                    <a href="tasks.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-plus"></i> Add Task
                    </a>
                    <a href="pomodoro.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-stopwatch"></i> Start Pomodoro
                    </a>
                    <a href="calendar.php?date=<?php echo $selected_date; ?>" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-calendar"></i> View Calendar
                    </a>
                </div>
            </div>
        </div>

        <!-- Upcoming Tasks -->
        <?php if (count($upcoming_tasks) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-arrow-right"></i> Upcoming</h5>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-2">
                    <?php foreach (array_slice($upcoming_tasks, 0, 5) as $task): ?>
                    <div class="p-2 rounded" style="background: var(--bg-secondary);">
                        <div class="fw-600" style="font-size: 12px;"><?php echo htmlspecialchars($task['title']); ?></div>
                        <small class="text-muted">
                            <?php echo date('M d, g:i A', strtotime($task['deadline'])); ?>
                        </small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Time Block Modal -->
<div class="modal fade" id="addTimeBlockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-clock"></i> Add Time Block</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo getCSRFField(); ?>
                    <input type="hidden" name="action" value="add_time_block">
                    
                    <div class="mb-3">
                        <label class="form-label">Subject <small class="text-muted">(optional)</small></label>
                        <select class="form-select" name="subject_id">
                            <option value="">— None —</option>
                            <?php foreach ($all_subjects as $sub): ?>
                            <option value="<?php echo $sub['id']; ?>"><?php echo htmlspecialchars($sub['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="start_time" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="end_time" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description <small class="text-muted">(optional)</small></label>
                        <input type="text" class="form-control" name="description" 
                               placeholder="e.g., Review Chapter 3">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Time Block</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updatePlanningTask(taskId, completed) {
    const status = completed ? 'Completed' : 'Pending';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    
    fetch('<?php echo BASE_URL; ?>student/tasks.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=toggle_status&task_id=' + taskId + '&next_status=' + status + '&csrf_token=' + csrfToken
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
