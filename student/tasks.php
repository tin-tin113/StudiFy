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
if ($status_filter === 'In Progress') {
    $status_filter = 'Pending';
}
$error = '';
$success = '';

// Handle AJAX requests (toggle_status and delete only - add/edit handled below)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $action = $_POST['action'] ?? '';
    
    // Only handle toggle_status and delete here; let 'add' and 'edit' fall through to the main POST handler
    if ($action === 'toggle_status' || $action === 'delete') {
        header('Content-Type: application/json');
        // CSRF validation for AJAX
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit();
        }
        $task_id = intval($_POST['task_id'] ?? 0);
        
        if ($action === 'toggle_status' && $task_id > 0) {
            $query = "SELECT t.status FROM tasks t WHERE t.id = ? AND t.user_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $task_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                $allowed_statuses = ['Pending', 'Completed'];
                $requested_status = trim($_POST['next_status'] ?? '');

                // Backward compatibility: map old "In Progress" requests to "Pending"
                if ($requested_status === 'In Progress') {
                    $requested_status = 'Pending';
                }

                if (!empty($requested_status) && in_array($requested_status, $allowed_statuses, true)) {
                    $new_status = $requested_status;
                } else {
                    // Legacy toggle behavior for callers that don't pass next_status
                    $new_status = ($row['status'] === 'Completed') ? 'Pending' : 'Completed';
                }

                if ($new_status === $row['status']) {
                    echo json_encode(['success' => true, 'message' => "Task remains $new_status"]);
                    exit();
                }

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
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check CSRF but don't exit immediately - log the error for debugging
    if (!validateCSRFToken()) {
        error_log("CSRF Error in tasks.php: token validation failed");
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
            exit();
        }
        $_SESSION['message'] = 'Security token expired. Please try again.';
        $_SESSION['message_type'] = 'error';
        header("Location: tasks.php");
        exit();
    }
    
    $action = $_POST['action'] ?? '';
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    
    if ($action === 'add') {
        $subj_id = intval($_POST['subject_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $type = sanitize($_POST['type'] ?? 'Assignment');
        $priority = sanitize($_POST['priority'] ?? 'Medium');
        $deadline = $_POST['deadline'] ?? '';
        $is_recurring = intval($_POST['is_recurring'] ?? 0);
        // Only keep recurrence metadata when recurrence is enabled to avoid DB truncation errors
        $recurrence_type = $is_recurring ? sanitize($_POST['recurrence_type'] ?? '') : null;
        $recurrence_end = ($is_recurring && !empty($_POST['recurrence_end'])) ? $_POST['recurrence_end'] : null;
        $subj_id_val = $subj_id > 0 ? $subj_id : null;
        
        if (empty($title)) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Title is required.']);
                exit();
            }
            $error = 'Title is required.';
        } else {
            if (!empty($deadline)) {
            // Convert deadline to proper datetime format if needed
            // datetime-local format: YYYY-MM-DDTHH:MM -> YYYY-MM-DD HH:MM:SS
            if (strlen($deadline) === 16 && strpos($deadline, 'T') !== false) {
                $deadline = str_replace('T', ' ', $deadline) . ':00';
            }
            
            // Validate deadline format
            $deadline_dt = DateTime::createFromFormat('Y-m-d H:i:s', $deadline);
            if (!$deadline_dt) {
                // Try alternative format
                $deadline_dt = DateTime::createFromFormat('Y-m-d H:i', $deadline);
                if ($deadline_dt) {
                    $deadline = $deadline_dt->format('Y-m-d H:i:s');
                } else {
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Invalid deadline format.']);
                        exit();
                    }
                    $error = 'Invalid deadline format.';
                }
            }
            } else {
                $deadline = null;
            }
            
            if (empty($error)) {
                $stmt = $conn->prepare("INSERT INTO tasks (user_id, subject_id, title, description, type, priority, deadline, is_recurring, recurrence_type, recurrence_end) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                if (!$stmt) {
                    error_log('Error adding task (prepare): ' . $conn->error);
                    $error_msg = 'Unable to save task right now. Please try again.';
                    if ($is_ajax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => $error_msg]);
                        exit();
                    }
                    $error = $error_msg;
                } else {
                    $stmt->bind_param("iisssssiss", $user_id, $subj_id_val, $title, $description, $type, $priority, $deadline, $is_recurring, $recurrence_type, $recurrence_end);
                    if ($stmt->execute()) {
                        // Generate recurring copies
                        if ($is_recurring && !empty($recurrence_type) && !empty($recurrence_end)) {
                            $current = new DateTime($deadline);
                            $end = new DateTime($recurrence_end);
                            $max_copies = 365;
                            $copies = 0;
                            while ($copies < $max_copies) {
                                if ($recurrence_type === 'Daily') $current->modify('+1 day');
                                elseif ($recurrence_type === 'Weekly') $current->modify('+1 week');
                                elseif ($recurrence_type === 'Monthly') $current->modify('+1 month');
                                if ($current > $end) break;
                                $next_deadline = $current->format('Y-m-d H:i:s');
                                $ins = $conn->prepare("INSERT INTO tasks (user_id, subject_id, title, description, type, priority, deadline, is_recurring, recurrence_type, recurrence_end) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                                $ins->bind_param("iisssssiss", $user_id, $subj_id_val, $title, $description, $type, $priority, $next_deadline, $is_recurring, $recurrence_type, $recurrence_end);
                                $ins->execute();
                                $copies++;
                            }
                        }
                        
                        // Return JSON for AJAX requests
                        if ($is_ajax) {
                            header('Content-Type: application/json');
                            echo json_encode([
                                'success' => true, 
                                'message' => 'Task added successfully!' . ($is_recurring ? ' Recurring copies created.' : '')
                            ]);
                            exit();
                        }
                        
                        // Regular form submission - redirect
                        $_SESSION['message'] = 'Task added successfully!' . ($is_recurring ? ' Recurring copies created.' : '');
                        $_SESSION['message_type'] = 'success';
                        header('Location: tasks.php' . ($subject_id ? '?subject_id=' . $subject_id : ''));
                        exit();
                    } else { 
                        error_log('Error adding task (execute): ' . $stmt->error);
                        $error_msg = 'Unable to save task right now. Please try again.';
                        if ($is_ajax) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => $error_msg]);
                            exit();
                        }
                        $error = $error_msg; 
                    }
                }
            }
        }
    } elseif ($action === 'edit') {
        $task_id = intval($_POST['task_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $type = sanitize($_POST['type'] ?? 'Assignment');
        $priority = sanitize($_POST['priority'] ?? 'Medium');
        $status = sanitize($_POST['status'] ?? 'Pending');
        if (!in_array($status, ['Pending', 'Completed'], true)) {
            $status = 'Pending';
        }
        $deadline = $_POST['deadline'] ?? '';
        $deadline = !empty($deadline) ? $deadline : null;
        
        if (empty($title) || $task_id <= 0) {
            $error = 'Title is required.';
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
                    
                    $deadline = !empty($task['deadline']) ? new DateTime($task['deadline']) : null;
                    $now = new DateTime();
                    $is_overdue = $deadline && $deadline < $now && $task['status'] !== 'Completed';
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
                                    <?php if ($task['deadline']): ?>
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('M d, Y h:i A', strtotime($task['deadline'])); ?>
                                    <?php if ($is_overdue): ?>
                                        <span class="badge bg-danger ms-1" style="font-size: 9px;">OVERDUE</span>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <i class="fas fa-calendar-times text-muted"></i> <span class="text-muted">No due date</span>
                                    <?php endif; ?>
                                </span>
                                <span class="badge bg-<?php echo $task['priority'] === 'High' ? 'danger' : ($task['priority'] === 'Medium' ? 'warning' : 'success'); ?>">
                                    <?php echo $task['priority']; ?>
                                </span>
                                <span class="task-status-badge <?php if ($is_overdue): ?>status-overdue<?php else: ?>status-<?php echo $is_completed ? 'completed' : 'pending'; ?><?php endif; ?>">
                                    <?php if ($is_overdue): ?>
                                        <i class="fas fa-exclamation-triangle"></i>
                                        Overdue
                                    <?php elseif ($task['status'] === 'Completed'): ?>
                                        <i class="fas fa-check-circle"></i>
                                    <?php else: ?>
                                        <i class="fas fa-hourglass-half"></i>
                                    <?php endif; ?>
                                    <?php if (!$is_overdue): ?><?php echo $is_completed ? 'Completed' : 'Pending'; ?><?php endif; ?>
                                </span>
                                <span class="badge bg-secondary"><?php echo $task['type']; ?></span>
                                <?php if (!empty($task['is_recurring'])): ?>
                                    <span class="badge bg-dark"><i class="fas fa-redo"></i> <?php echo $task['recurrence_type'] ?? 'Recurring'; ?></span>
                                <?php endif; ?>
                            </div>

                            <!-- Attachments for this task -->
                            <div class="task-attachments" id="att-task-<?php echo $task['id']; ?>" style="margin-top:8px;display:flex;flex-wrap:wrap;gap:6px;">
                                <!-- Loaded by JS -->
                            </div>
                            <!-- Inline upload trigger -->
                            <div style="margin-top:6px;">
                                <label title="Attach a file" style="cursor:pointer;font-size:11px;color:var(--text-muted);">
                                    <i class="fas fa-paperclip"></i> Attach file
                                    <input type="file" style="display:none;" onchange="uploadTaskAttachment(<?php echo $task['id']; ?>, this)">
                                </label>
                            </div>
                        </div>
                        <div class="task-actions ms-3">
                            <?php if ($is_completed): ?>
                            <button class="btn btn-sm btn-warning" 
                                    onclick="StudifyConfirm.action('Reopen Task', 'Mark this task as pending again?', 'warning', function(){ toggleTaskStatus(<?php echo $task['id']; ?>, '<?php echo BASE_URL; ?>', 'Pending') })"
                                    title="Reopen Task">
                                <i class="fas fa-undo"></i>
                            </button>
                            <?php else: ?>
                            <button class="btn btn-sm btn-success" 
                                    onclick="StudifyConfirm.action('Complete Task', 'Mark this task as completed?', 'success', function(){ toggleTaskStatus(<?php echo $task['id']; ?>, '<?php echo BASE_URL; ?>', 'Completed') })"
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
                                    <?php if ($task['deadline']): ?>
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('M d, Y', strtotime($task['deadline'])); ?>
                                    <?php else: ?>
                                    <i class="fas fa-calendar-times text-muted"></i> <span class="text-muted">No due date</span>
                                    <?php endif; ?>
                                </span>
                                <span class="badge bg-secondary"><?php echo $task['type']; ?></span>
                                <span class="task-status-badge status-completed">
                                    <i class="fas fa-check-circle"></i> Completed
                                </span>
                            </div>
                        </div>
                        <div class="task-actions ms-3">
                            <button class="btn btn-sm btn-warning" 
                                    onclick="StudifyConfirm.action('Reopen Task', 'Mark this task as pending again?', 'warning', function(){ toggleTaskStatus(<?php echo $task['id']; ?>, '<?php echo BASE_URL; ?>', 'Pending') })"
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
            <form method="POST" action="tasks.php">
                <div class="modal-body">
                    <?php echo getCSRFField(); ?>
                    <input type="hidden" name="action" value="add">
                    
                    <?php
                    // Load task templates - wrap in try/catch in case table doesn't exist yet
                    $templates = [];
                    try {
                        if (function_exists('getTaskTemplates')) {
                            $templates = getTaskTemplates($user_id, $conn);
                        }
                    } catch (Throwable $e) {
                        error_log("Task Templates table might be missing: " . $e->getMessage());
                    }
                    if (!empty($templates)):
                    ?>
                    <div class="mb-3">
                        <label class="form-label"><i class="fas fa-layer-group"></i> Use Template <small class="text-muted">(optional)</small></label>
                        <select class="form-select" id="taskTemplate" onchange="loadTaskTemplate(this.value)">
                            <option value="">— Start from scratch —</option>
                            <?php foreach ($templates as $template): ?>
                            <option value="<?php echo $template['id']; ?>" data-template='<?php echo htmlspecialchars(json_encode($template), ENT_QUOTES); ?>'>
                                <?php echo htmlspecialchars($template['name']); ?>
                                <?php if ($template['is_system']): ?>
                                    <span class="badge bg-info ms-1" style="font-size: 9px;">System</span>
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Select a template to pre-fill the form</small>
                    </div>
                    <?php endif; ?>

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
                            <label for="addDeadline" class="form-label">Deadline</label>
                            <input type="datetime-local" class="form-control" id="addDeadline" name="deadline">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" id="addNoDueDate" onchange="document.getElementById('addDeadline').disabled = this.checked; if(this.checked) document.getElementById('addDeadline').value = '';">
                                <label class="form-check-label" for="addNoDueDate" style="font-size: 12px;">No due date</label>
                            </div>
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
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="editDeadline" class="form-label">Deadline</label>
                            <input type="datetime-local" class="form-control" id="editDeadline" name="deadline">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" id="editNoDueDate" onchange="document.getElementById('editDeadline').disabled = this.checked; if(this.checked) document.getElementById('editDeadline').value = '';">
                                <label class="form-check-label" for="editNoDueDate" style="font-size: 12px;">No due date</label>
                            </div>
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

function loadTaskTemplate(templateId) {
    if (!templateId) {
        // Reset form if no template selected
        document.getElementById('addTitle').value = '';
        document.getElementById('addDesc').value = '';
        document.getElementById('addType').value = 'Assignment';
        document.getElementById('addPriority').value = 'Medium';
        return;
    }
    
    const select = document.getElementById('taskTemplate');
    const option = select.options[select.selectedIndex];
    const template = JSON.parse(option.getAttribute('data-template'));
    
    if (template) {
        // Replace placeholders in title
        let title = template.title;
        title = title.replace('{week}', new Date().getWeek ? new Date().getWeek() : Math.ceil((new Date().getTime() - new Date(new Date().getFullYear(), 0, 1)) / (7 * 24 * 60 * 60 * 1000)));
        title = title.replace('{subject}', 'Subject');
        title = title.replace('{title}', 'Task');
        title = title.replace('{milestone}', 'Milestone');
        
        document.getElementById('addTitle').value = title;
        document.getElementById('addDesc').value = template.description || '';
        document.getElementById('addType').value = template.type || 'Assignment';
        document.getElementById('addPriority').value = template.priority || 'Medium';
        
        if (template.is_recurring) {
            document.getElementById('addRecurring').checked = true;
            document.getElementById('recurringFields').style.display = 'flex';
            document.getElementById('addRecurrenceType').value = template.recurrence_type || 'Weekly';
        } else {
            document.getElementById('addRecurring').checked = false;
            document.getElementById('recurringFields').style.display = 'none';
        }
        
        StudifyToast.success('Template Loaded', template.name);
    }
}

function fillTaskEditForm(task) {
    document.getElementById('editTaskId').value = task.id;
    document.getElementById('editTitle').value = task.title;
    document.getElementById('editDesc').value = task.description || '';
    document.getElementById('editType').value = task.type;
    document.getElementById('editPriority').value = task.priority;
    document.getElementById('editStatus').value = (task.status === 'Completed') ? 'Completed' : 'Pending';
    const deadlineInput = document.getElementById('editDeadline');
    const noDueDateCb = document.getElementById('editNoDueDate');
    if (task.deadline) {
        const dt = new Date(task.deadline);
        const formatted = dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2,'0') + '-' + String(dt.getDate()).padStart(2,'0') + 'T' + String(dt.getHours()).padStart(2,'0') + ':' + String(dt.getMinutes()).padStart(2,'0');
        deadlineInput.value = formatted;
        deadlineInput.disabled = false;
        noDueDateCb.checked = false;
    } else {
        deadlineInput.value = '';
        deadlineInput.disabled = true;
        noDueDateCb.checked = true;
    }
}

// ─── File Attachments ────────────────────────────────────────
const BASE_URL = '<?php echo BASE_URL; ?>';

function getFiletypeIcon(mime) {
    if (mime.startsWith('image/')) return 'fa-image';
    if (mime === 'application/pdf') return 'fa-file-pdf';
    if (mime.includes('word')) return 'fa-file-word';
    if (mime.includes('excel') || mime.includes('spreadsheet')) return 'fa-file-excel';
    if (mime.includes('powerpoint') || mime.includes('presentation')) return 'fa-file-powerpoint';
    if (mime.includes('zip')) return 'fa-file-archive';
    if (mime.startsWith('text/')) return 'fa-file-alt';
    return 'fa-file';
}

function formatBytes(bytes) {
    if (bytes < 1024) return bytes + ' B';
    if (bytes < 1024*1024) return (bytes/1024).toFixed(1) + ' KB';
    return (bytes/1024/1024).toFixed(1) + ' MB';
}

function renderAttachments(container, attachments) {
    container.innerHTML = '';
    attachments.forEach(att => {
        const chip = document.createElement('div');
        chip.style.cssText = 'display:inline-flex;align-items:center;gap:6px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:6px;padding:4px 8px;font-size:11px;max-width:180px;';
        const icon = getFiletypeIcon(att.file_type);
        chip.innerHTML = `
            <i class="fas ${icon}" style="color:var(--primary);flex-shrink:0;"></i>
            <a href="${att.url}" target="_blank" style="text-decoration:none;color:var(--text-primary);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;" title="${att.file_name}">${att.file_name}</a>
            <span style="color:var(--text-muted);flex-shrink:0;">${formatBytes(att.file_size)}</span>
            <button onclick="deleteAttachment(${att.id}, this)" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0;font-size:11px;flex-shrink:0;" title="Delete"><i class="fas fa-times"></i></button>
        `;
        container.appendChild(chip);
    });
}

function loadTaskAttachments(taskId) {
    const container = document.getElementById('att-task-' + taskId);
    if (!container) return;
    fetch(BASE_URL + 'student/attachments.php?action=list&task_id=' + taskId)
        .then(r => r.json())
        .then(data => { if (data.success) renderAttachments(container, data.attachments); });
}

function uploadTaskAttachment(taskId, input) {
    if (!input.files.length) return;
    const file = input.files[0];
    const fd   = new FormData();
    fd.append('action',    'upload');
    fd.append('task_id',   taskId);
    fd.append('file',      file);
    fd.append('csrf_token', getCSRFToken());

    StudifyToast.info('Uploading…', file.name);
    fetch(BASE_URL + 'student/attachments.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                StudifyToast.success('Uploaded', data.attachment.file_name);
                loadTaskAttachments(taskId);
            } else {
                StudifyToast.error('Upload Failed', data.message);
            }
        })
        .catch(() => StudifyToast.error('Error', 'Network error'));
    input.value = ''; // reset
}

function deleteAttachment(attId, btn) {
    StudifyConfirm.action('Delete File', 'Remove this attachment?', 'danger', function() {
        const fd = new FormData();
        fd.append('action',        'delete');
        fd.append('attachment_id', attId);
        fd.append('csrf_token',    getCSRFToken());
        fetch(BASE_URL + 'student/attachments.php', { method:'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    btn.closest('div[style]').remove();
                    StudifyToast.success('Deleted', 'Attachment removed');
                } else {
                    StudifyToast.error('Error', data.message);
                }
            });
    });
}

// Load all task attachments on page load
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.task-attachments').forEach(el => {
        const taskId = el.id.replace('att-task-', '');
        loadTaskAttachments(parseInt(taskId));
    });
});
</script>

<?php include '../includes/footer.php'; ?>
