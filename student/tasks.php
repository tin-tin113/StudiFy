<?php
/**
 * STUDIFY – Tasks Management
 * Card-based task list with AJAX toggle/delete
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

$page_title = 'Tasks';
$user_id = getCurrentUserId();
$subject_id = intval($_GET['subject_id'] ?? 0);
$status_filter = $_GET['status'] ?? '';
$error = '';
$success = '';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    // CSRF validation for AJAX
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }
    $action = $_POST['action'] ?? '';
    $task_id = intval($_POST['task_id'] ?? 0);
    
    if ($action === 'toggle_status' && $task_id > 0) {
        $query = "SELECT t.status FROM tasks t WHERE t.id = ? AND t.user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $task_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $new_status = ($row['status'] === 'Completed') ? 'Pending' : 'Completed';
            $update = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ?");
            $update->bind_param("si", $new_status, $task_id);
            $update->execute();
            echo json_encode(['success' => true, 'message' => "Task marked as $new_status"]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Task not found']);
        }
        exit();
    }
    
    if ($action === 'delete' && $task_id > 0) {
        $query = "DELETE FROM tasks WHERE id = ? AND user_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $task_id, $user_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting task']);
        }
        exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $subj_id = intval($_POST['subject_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $type = sanitize($_POST['type'] ?? 'Assignment');
        $priority = sanitize($_POST['priority'] ?? 'Medium');
        $deadline = $_POST['deadline'] ?? '';
        $is_recurring = intval($_POST['is_recurring'] ?? 0);
        $recurrence_type = sanitize($_POST['recurrence_type'] ?? '');
        $recurrence_end = !empty($_POST['recurrence_end']) ? $_POST['recurrence_end'] : null;
        $subj_id_val = $subj_id > 0 ? $subj_id : null;
        
        if (empty($title) || empty($deadline)) {
            $error = 'Title and deadline are required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO tasks (user_id, subject_id, title, description, type, priority, deadline, is_recurring, recurrence_type, recurrence_end) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssssiss", $user_id, $subj_id_val, $title, $description, $type, $priority, $deadline, $is_recurring, $recurrence_type, $recurrence_end);
            if ($stmt->execute()) {
                // Generate recurring copies
                if ($is_recurring && !empty($recurrence_type) && !empty($recurrence_end)) {
                    $current = new DateTime($deadline);
                    $end = new DateTime($recurrence_end);
                    while (true) {
                        if ($recurrence_type === 'Daily') $current->modify('+1 day');
                        elseif ($recurrence_type === 'Weekly') $current->modify('+1 week');
                        elseif ($recurrence_type === 'Monthly') $current->modify('+1 month');
                        if ($current > $end) break;
                        $next_deadline = $current->format('Y-m-d H:i:s');
                        $ins = $conn->prepare("INSERT INTO tasks (user_id, subject_id, title, description, type, priority, deadline, is_recurring, recurrence_type, recurrence_end) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $ins->bind_param("iisssssiss", $user_id, $subj_id_val, $title, $description, $type, $priority, $next_deadline, $is_recurring, $recurrence_type, $recurrence_end);
                        $ins->execute();
                    }
                }
                $_SESSION['message'] = 'Task added successfully!' . ($is_recurring ? ' Recurring copies created.' : '');
                $_SESSION['message_type'] = 'success';
                header('Location: tasks.php' . ($subject_id ? '?subject_id=' . $subject_id : ''));
                exit();
            }
            else { $error = 'Error adding task.'; }
        }
    } elseif ($action === 'edit') {
        $task_id = intval($_POST['task_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $type = sanitize($_POST['type'] ?? 'Assignment');
        $priority = sanitize($_POST['priority'] ?? 'Medium');
        $status = sanitize($_POST['status'] ?? 'Pending');
        $deadline = $_POST['deadline'] ?? '';
        
        if (empty($title) || $task_id <= 0 || empty($deadline)) {
            $error = 'Title and deadline are required.';
        } else {
            // Verify task belongs to current user
            $check = $conn->prepare("SELECT t.id FROM tasks t WHERE t.id = ? AND t.user_id = ?");
            $check->bind_param("ii", $task_id, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                $error = 'Task not found or access denied.';
            } else {
                $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, type = ?, priority = ?, status = ?, deadline = ? WHERE id = ?");
                $stmt->bind_param("ssssssi", $title, $description, $type, $priority, $status, $deadline, $task_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = 'Task updated!';
                    $_SESSION['message_type'] = 'success';
                    header('Location: tasks.php' . ($subject_id ? '?subject_id=' . $subject_id : ''));
                    exit();
                }
                else { $error = 'Error updating task.'; }
            }
        }
    }
}

// Build filter query using SQL-level filtering (single optimized query)
$sort = $_GET['sort'] ?? 'deadline';
$sort_dir = $_GET['dir'] ?? 'ASC';
$tasks = getUserTasksFiltered($user_id, $conn, $subject_id, $status_filter, $sort, $sort_dir);

// Get all subjects for add form
$all_subjects = [];
$semesters = getUserSemesters($user_id, $conn);
foreach ($semesters as $sem) {
    $subs = getSemesterSubjects($sem['id'], $conn);
    foreach ($subs as $sub) {
        $sub['semester_name'] = $sem['name'];
        $all_subjects[] = $sub;
    }
}

// Count by status (single query instead of fetching all tasks twice)
$status_counts = getTaskStatusCounts($user_id, $conn);
$count_all = intval($status_counts['total']);
$count_pending = intval($status_counts['pending']);
$count_progress = intval($status_counts['in_progress']);
$count_done = intval($status_counts['completed']);
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-check-circle"></i> Tasks</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                <i class="fas fa-plus"></i> Add Task
            </button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="card mb-4">
            <div class="card-body" style="padding: 12px 20px;">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="tasks.php" class="btn btn-sm <?php echo empty($status_filter) ? 'btn-primary' : 'btn-secondary'; ?>">
                        All <span class="badge bg-light text-dark ms-1"><?php echo $count_all; ?></span>
                    </a>
                    <a href="tasks.php?status=Pending" class="btn btn-sm <?php echo $status_filter === 'Pending' ? 'btn-warning' : 'btn-secondary'; ?>">
                        Pending <span class="badge bg-light text-dark ms-1"><?php echo $count_pending; ?></span>
                    </a>
                    <a href="tasks.php?status=In Progress" class="btn btn-sm <?php echo $status_filter === 'In Progress' ? 'btn-info' : 'btn-secondary'; ?>">
                        In Progress <span class="badge bg-light text-dark ms-1"><?php echo $count_progress; ?></span>
                    </a>
                    <a href="tasks.php?status=Completed" class="btn btn-sm <?php echo $status_filter === 'Completed' ? 'btn-success' : 'btn-secondary'; ?>">
                        Completed <span class="badge bg-light text-dark ms-1"><?php echo $count_done; ?></span>
                    </a>

                    <?php if ($subject_id > 0): ?>
                        <span class="ms-auto">
                            <a href="tasks.php" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i> Clear Filter</a>
                        </span>
                    <?php endif; ?>

                    <span class="<?php echo $subject_id > 0 ? '' : 'ms-auto'; ?>">
                        <div class="dropdown d-inline">
                            <button class="btn btn-sm btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-sort"></i> Sort
                            </button>
                            <?php
                                // Build query string for sort links preserving current filters
                                $filter_qs = '';
                                if ($status_filter) $filter_qs .= '&status=' . urlencode($status_filter);
                                if ($subject_id) $filter_qs .= '&subject_id=' . $subject_id;
                            ?>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item <?php echo $sort === 'deadline' && $sort_dir === 'ASC' ? 'active' : ''; ?>" href="tasks.php?sort=deadline&dir=ASC<?php echo $filter_qs; ?>"><i class="fas fa-calendar-alt"></i> Deadline (Earliest)</a></li>
                                <li><a class="dropdown-item <?php echo $sort === 'deadline' && $sort_dir === 'DESC' ? 'active' : ''; ?>" href="tasks.php?sort=deadline&dir=DESC<?php echo $filter_qs; ?>"><i class="fas fa-calendar-alt"></i> Deadline (Latest)</a></li>
                                <li><a class="dropdown-item <?php echo $sort === 'priority' ? 'active' : ''; ?>" href="tasks.php?sort=priority&dir=ASC<?php echo $filter_qs; ?>"><i class="fas fa-flag"></i> Priority (High First)</a></li>
                                <li><a class="dropdown-item <?php echo $sort === 'created_at' ? 'active' : ''; ?>" href="tasks.php?sort=created_at&dir=DESC<?php echo $filter_qs; ?>"><i class="fas fa-clock"></i> Recently Added</a></li>
                                <li><a class="dropdown-item <?php echo $sort === 'title' ? 'active' : ''; ?>" href="tasks.php?sort=title&dir=ASC<?php echo $filter_qs; ?>"><i class="fas fa-font"></i> Alphabetical</a></li>
                            </ul>
                        </div>
                    </span>
                </div>
            </div>
        </div>

        <?php
            // Separate tasks into active and completed (only when showing "All")
            $active_tasks = [];
            $completed_tasks = [];
            foreach ($tasks as $task) {
                if ($task['status'] === 'Completed') {
                    $completed_tasks[] = $task;
                } else {
                    $active_tasks[] = $task;
                }
            }

            // When filtering by a specific status, show all results in a single flat list
            $show_split = empty($status_filter) || $status_filter === 'Pending';
            $display_tasks = $show_split ? $active_tasks : $tasks;
        ?>

        <?php if (count($display_tasks) > 0): ?>
        <!-- Task List -->
        <div class="task-list">
                <?php foreach ($display_tasks as $task): 
                    $priority_class = '';
                    if ($task['priority'] === 'High') $priority_class = 'priority-high-border';
                    elseif ($task['priority'] === 'Medium') $priority_class = 'priority-medium-border';
                    else $priority_class = 'priority-low-border';
                    
                    $deadline = new DateTime($task['deadline']);
                    $now = new DateTime();
                    $is_overdue = $deadline < $now && $task['status'] !== 'Completed';
                    $is_completed = $task['status'] === 'Completed';
                ?>
                <div class="card task-card <?php echo $priority_class; ?> <?php echo $is_completed ? 'task-done' : ''; ?> status-<?php echo strtolower(str_replace(' ', '-', $task['status'])); ?>" id="task-<?php echo $task['id']; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div style="flex: 1; min-width: 0;">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <?php if ($is_completed): ?>
                                    <i class="fas fa-check-circle text-success" style="font-size: 16px;"></i>
                                <?php endif; ?>
                                <h6 class="task-title mb-0 <?php echo $is_completed ? 'completed' : ''; ?>">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </h6>
                            </div>
                            <?php if (!empty($task['description']) && !$is_completed): ?>
                                <p class="text-muted mb-2" style="font-size: 12.5px;"><?php echo htmlspecialchars($task['description']); ?></p>
                            <?php endif; ?>
                            <div class="task-meta">
                                <?php if (!empty($task['subject_name']) && $task['subject_name'] !== 'General'): ?>
                                <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($task['subject_name']); ?></span>
                                <?php else: ?>
                                <span class="text-muted"><i class="fas fa-tag"></i> No subject</span>
                                <?php endif; ?>
                                <span>
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('M d, Y h:i A', strtotime($task['deadline'])); ?>
                                    <?php if ($is_overdue): ?>
                                        <span class="badge bg-danger ms-1" style="font-size: 9px;">OVERDUE</span>
                                    <?php endif; ?>
                                </span>
                                <span class="badge bg-<?php echo $task['priority'] === 'High' ? 'danger' : ($task['priority'] === 'Medium' ? 'warning' : 'success'); ?>">
                                    <?php echo $task['priority']; ?>
                                </span>
                                <span class="task-status-badge status-<?php echo strtolower(str_replace(' ', '-', $task['status'])); ?>">
                                    <?php if ($task['status'] === 'Completed'): ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php elseif ($task['status'] === 'In Progress'): ?>
                                        <i class="fas fa-spinner fa-pulse"></i>
                                    <?php else: ?>
                                        <i class="fas fa-hourglass-half"></i>
                                    <?php endif; ?>
                                    <?php echo $task['status']; ?>
                                </span>
                                <span class="badge bg-secondary"><?php echo $task['type']; ?></span>
                                <?php if (!empty($task['is_recurring'])): ?>
                                    <span class="badge bg-dark"><i class="fas fa-redo"></i> <?php echo $task['recurrence_type'] ?? 'Recurring'; ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="task-actions ms-3">
                            <?php if ($is_completed): ?>
                            <button class="btn btn-sm btn-warning" 
                                    onclick="StudifyConfirm.action('Reopen Task', 'Mark this task as pending again?', 'warning', function(){ toggleTaskStatus(<?php echo $task['id']; ?>, '<?php echo BASE_URL; ?>') })"
                                    title="Reopen Task">
                                <i class="fas fa-undo"></i>
                            </button>
                            <?php else: ?>
                            <button class="btn btn-sm btn-success" 
                                    onclick="StudifyConfirm.action('Complete Task', 'Mark this task as completed?', 'success', function(){ toggleTaskStatus(<?php echo $task['id']; ?>, '<?php echo BASE_URL; ?>') })"
                                    title="Mark Complete">
                                <i class="fas fa-check"></i>
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editTaskModal"
                                onclick="fillTaskEditForm(<?php echo htmlspecialchars(json_encode($task), ENT_QUOTES); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="StudifyConfirm.action('Delete Task', 'Are you sure you want to delete this task? This cannot be undone.', 'danger', function(){ deleteTask(<?php echo $task['id']; ?>, '<?php echo BASE_URL; ?>') })">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
        </div>
        <?php else: ?>
            <?php if ($status_filter): ?>
                <div class="card">
                    <div class="empty-state">
                        <i class="fas fa-filter"></i>
                        <h5>No <?php echo htmlspecialchars($status_filter); ?> Tasks</h5>
                        <p>No tasks match the current filter.</p>
                        <a href="tasks.php" class="btn btn-secondary mt-2"><i class="fas fa-times"></i> Clear Filter</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h5>No Active Tasks</h5>
                        <p>All caught up! Create a new task or check your completed ones below.</p>
                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addTaskModal">
                            <i class="fas fa-plus"></i> Add Task
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Completed Tasks Section (only shown on All/Pending view) -->
        <?php if ($show_split && count($completed_tasks) > 0): ?>
        <div class="completed-tasks-section mt-4">
            <div class="completed-tasks-header" onclick="toggleCompletedTasks()">
                <div class="d-flex align-items-center gap-2">
                    <i class="fas fa-check-double text-success"></i>
                    <h6 class="mb-0">Completed Tasks</h6>
                    <span class="badge bg-success rounded-pill"><?php echo count($completed_tasks); ?></span>
                </div>
                <i class="fas fa-chevron-down completed-toggle-icon" id="completedToggleIcon"></i>
            </div>
            <div id="completedTasksList" class="completed-tasks-body" style="display: none;">
                <?php foreach ($completed_tasks as $task): ?>
                <div class="card task-card task-done status-completed" id="task-<?php echo $task['id']; ?>">
                    <div class="d-flex justify-content-between align-items-start">
                        <div style="flex: 1; min-width: 0;">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="fas fa-check-circle text-success" style="font-size: 16px;"></i>
                                <h6 class="task-title mb-0 completed">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                </h6>
                            </div>
                            <div class="task-meta">
                                <?php if (!empty($task['subject_name']) && $task['subject_name'] !== 'General'): ?>
                                <span><i class="fas fa-book"></i> <?php echo htmlspecialchars($task['subject_name']); ?></span>
                                <?php endif; ?>
                                <span>
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('M d, Y', strtotime($task['deadline'])); ?>
                                </span>
                                <span class="badge bg-secondary"><?php echo $task['type']; ?></span>
                                <span class="task-status-badge status-completed">
                                    <i class="fas fa-check-circle"></i> Completed
                                </span>
                            </div>
                        </div>
                        <div class="task-actions ms-3">
                            <button class="btn btn-sm btn-warning" 
                                    onclick="StudifyConfirm.action('Reopen Task', 'Mark this task as pending again?', 'warning', function(){ toggleTaskStatus(<?php echo $task['id']; ?>, '<?php echo BASE_URL; ?>') })"
                                    title="Reopen Task">
                                <i class="fas fa-undo"></i>
                            </button>
                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editTaskModal"
                                onclick="fillTaskEditForm(<?php echo htmlspecialchars(json_encode($task), ENT_QUOTES); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-sm btn-danger" onclick="StudifyConfirm.action('Delete Task', 'Are you sure you want to delete this task? This cannot be undone.', 'danger', function(){ deleteTask(<?php echo $task['id']; ?>, '<?php echo BASE_URL; ?>') })">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

<!-- Add Task Modal -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo getCSRFField(); ?>
                    <input type="hidden" name="action" value="add">

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="addTitle" class="form-label">Task Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="addTitle" name="title" placeholder="e.g., Research Paper Draft" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="addSubject" class="form-label">
                                Subject <small class="text-muted">(optional)</small>
                            </label>
                            <select class="form-select" id="addSubject" name="subject_id">
                                <option value="">— None —</option>
                                <?php
                                // Group subjects by semester using optgroups
                                $subjects_by_sem = [];
                                foreach ($all_subjects as $sub) {
                                    $subjects_by_sem[$sub['semester_name']][] = $sub;
                                }
                                foreach ($subjects_by_sem as $sem_name => $subs): ?>
                                <optgroup label="📅 <?php echo htmlspecialchars($sem_name); ?>">
                                    <?php foreach ($subs as $sub): ?>
                                    <option value="<?php echo $sub['id']; ?>" <?php echo $subject_id == $sub['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sub['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php endforeach; ?>
                            </select>
                            <?php if (count($all_subjects) === 0): ?>
                            <div class="mt-1" style="font-size: 11px;">
                                <span class="text-muted">Want to organize by subject?</span>
                                <a href="subjects.php" class="text-primary" style="text-decoration: none;">Add Subject</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="addDesc" class="form-label">Description <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" id="addDesc" name="description" rows="2" placeholder="Add details about this task..."></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="addType" class="form-label">Type</label>
                            <select class="form-select" id="addType" name="type">
                                <option value="Assignment">Assignment</option>
                                <option value="Quiz">Quiz</option>
                                <option value="Exam">Exam</option>
                                <option value="Project">Project</option>
                                <option value="Report">Report</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="addPriority" class="form-label">Priority</label>
                            <select class="form-select" id="addPriority" name="priority">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="addDeadline" class="form-label">Deadline <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" id="addDeadline" name="deadline" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="addRecurring" name="is_recurring" value="1" onchange="document.getElementById('recurringFields').style.display = this.checked ? 'flex' : 'none';">
                                <label class="form-check-label" for="addRecurring"><i class="fas fa-redo"></i> Recurring Task</label>
                            </div>
                        </div>
                        <div class="col-md-8" id="recurringFields" style="display:none;">
                            <div class="row">
                                <div class="col-6 mb-3">
                                    <label for="addRecurrenceType" class="form-label">Repeat</label>
                                    <select class="form-select" id="addRecurrenceType" name="recurrence_type">
                                        <option value="Daily">Daily</option>
                                        <option value="Weekly" selected>Weekly</option>
                                        <option value="Monthly">Monthly</option>
                                    </select>
                                </div>
                                <div class="col-6 mb-3">
                                    <label for="addRecurrenceEnd" class="form-label">Until</label>
                                    <input type="date" class="form-control" id="addRecurrenceEnd" name="recurrence_end">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Task Modal -->
<div class="modal fade" id="editTaskModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo getCSRFField(); ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="task_id" id="editTaskId">
                    <div class="mb-3">
                        <label for="editTitle" class="form-label">Task Title</label>
                        <input type="text" class="form-control" id="editTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="editDesc" class="form-label">Description</label>
                        <textarea class="form-control" id="editDesc" name="description" rows="2"></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label for="editType" class="form-label">Type</label>
                            <select class="form-select" id="editType" name="type">
                                <option value="Assignment">Assignment</option>
                                <option value="Quiz">Quiz</option>
                                <option value="Exam">Exam</option>
                                <option value="Project">Project</option>
                                <option value="Report">Report</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="editPriority" class="form-label">Priority</label>
                            <select class="form-select" id="editPriority" name="priority">
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="editStatus" class="form-label">Status</label>
                            <select class="form-select" id="editStatus" name="status">
                                <option value="Pending">Pending</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="editDeadline" class="form-label">Deadline</label>
                            <input type="datetime-local" class="form-control" id="editDeadline" name="deadline" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function toggleCompletedTasks() {
    const list = document.getElementById('completedTasksList');
    const icon = document.getElementById('completedToggleIcon');
    if (list.style.display === 'none') {
        list.style.display = 'block';
        icon.classList.replace('fa-chevron-down', 'fa-chevron-up');
    } else {
        list.style.display = 'none';
        icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
    }
}

// Auto-expand completed section when filtering by Completed
<?php if ($status_filter === 'Completed'): ?>
document.addEventListener('DOMContentLoaded', function() {
    const list = document.getElementById('completedTasksList');
    const icon = document.getElementById('completedToggleIcon');
    if (list) { list.style.display = 'block'; }
    if (icon) { icon.classList.replace('fa-chevron-down', 'fa-chevron-up'); }
});
<?php endif; ?>

function fillTaskEditForm(task) {
    document.getElementById('editTaskId').value = task.id;
    document.getElementById('editTitle').value = task.title;
    document.getElementById('editDesc').value = task.description || '';
    document.getElementById('editType').value = task.type;
    document.getElementById('editPriority').value = task.priority;
    document.getElementById('editStatus').value = task.status;
    
    // Format datetime for input
    if (task.deadline) {
        const dt = new Date(task.deadline);
        const formatted = dt.getFullYear() + '-' + 
            String(dt.getMonth() + 1).padStart(2, '0') + '-' + 
            String(dt.getDate()).padStart(2, '0') + 'T' + 
            String(dt.getHours()).padStart(2, '0') + ':' + 
            String(dt.getMinutes()).padStart(2, '0');
        document.getElementById('editDeadline').value = formatted;
    }
}
</script>

<?php include '../includes/footer.php'; ?>
