<?php
/**
 * STUDIFY – Subjects Management
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

// Handle AJAX request for subject tasks
if (isset($_GET['ajax']) && $_GET['ajax'] === 'tasks' && isset($_GET['subject_id'])) {
    header('Content-Type: application/json');
    $user_id = getCurrentUserId();
    $subj_id = intval($_GET['subject_id']);
    
    // Verify subject belongs to user
    $check = $conn->prepare("SELECT s.id FROM subjects s JOIN semesters sem ON s.semester_id = sem.id WHERE s.id = ? AND sem.user_id = ?");
    $check->bind_param("ii", $subj_id, $user_id);
    $check->execute();
    if ($check->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    $stmt = $conn->prepare("SELECT id, title, status, priority, type, deadline FROM tasks WHERE subject_id = ? AND user_id = ? AND parent_id IS NULL ORDER BY CASE WHEN status = 'Completed' THEN 1 ELSE 0 END, deadline ASC");
    $stmt->bind_param("ii", $subj_id, $user_id);
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode(['success' => true, 'tasks' => $tasks]);
    exit();
}

// Handle AJAX request to add a task from subjects page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_task') {
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'message' => 'Invalid security token']);
            exit();
        }
        
        $user_id = getCurrentUserId();
        $subj_id = intval($_POST['subject_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $type = sanitize($_POST['type'] ?? 'Assignment');
        $priority = sanitize($_POST['priority'] ?? 'Medium');
        $deadline = $_POST['deadline'] ?? '';
        
        if (empty($title) || empty($deadline)) {
            echo json_encode(['success' => false, 'message' => 'Title and deadline are required']);
            exit();
        }
        
        // Verify subject belongs to user
        $check = $conn->prepare("SELECT s.id FROM subjects s JOIN semesters sem ON s.semester_id = sem.id WHERE s.id = ? AND sem.user_id = ?");
        $check->bind_param("ii", $subj_id, $user_id);
        $check->execute();
        if ($check->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid subject']);
            exit();
        }
        
        $stmt = $conn->prepare("INSERT INTO tasks (user_id, subject_id, title, type, priority, deadline, status) VALUES (?, ?, ?, ?, ?, ?, 'Pending')");
        $stmt->bind_param("iissss", $user_id, $subj_id, $title, $type, $priority, $deadline);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Task added successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error adding task']);
        }
        exit();
    }
}

$page_title = 'Subjects';
$user_id = getCurrentUserId();
$semester_id = intval($_GET['semester_id'] ?? 0);
$error = '';
$success = '';

// Auto-select active semester if none specified
if ($semester_id <= 0) {
    $active_sem = $conn->prepare("SELECT id FROM semesters WHERE user_id = ? AND is_active = 1 LIMIT 1");
    $active_sem->bind_param("i", $user_id);
    $active_sem->execute();
    $active_result = $active_sem->get_result()->fetch_assoc();
    if ($active_result) {
        $semester_id = intval($active_result['id']);
    }
}

// Verify semester belongs to user
if ($semester_id > 0) {
    $check_query = "SELECT id FROM semesters WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($check_query);
    $stmt->bind_param("ii", $semester_id, $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) {
        header("Location: " . BASE_URL . "student/semesters.php");
        exit();
    }
}

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $sem_id = intval($_POST['semester_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $instructor = sanitize($_POST['instructor_name'] ?? '');
        
        if (empty($name) || $sem_id <= 0) {
            $error = 'Subject name and semester are required.';
        } else {
            // Verify semester belongs to current user
            $sem_check = $conn->prepare("SELECT id FROM semesters WHERE id = ? AND user_id = ?");
            $sem_check->bind_param("ii", $sem_id, $user_id);
            $sem_check->execute();
            if ($sem_check->get_result()->num_rows === 0) {
                $error = 'Semester not found or access denied.';
            } else {
                $stmt = $conn->prepare("INSERT INTO subjects (semester_id, name, instructor_name) VALUES (?, ?, ?)");
                $stmt->bind_param("iss", $sem_id, $name, $instructor);
                if ($stmt->execute()) {
                    $_SESSION['message'] = 'Subject added successfully!';
                    $_SESSION['message_type'] = 'success';
                    header('Location: subjects.php?semester_id=' . $sem_id);
                    exit();
                }
                else { $error = 'Error adding subject.'; }
            }
        }
    } elseif ($action === 'edit') {
        $subj_id = intval($_POST['subject_id'] ?? 0);
        $name = sanitize($_POST['name'] ?? '');
        $instructor = sanitize($_POST['instructor_name'] ?? '');
        
        if (empty($name) || $subj_id <= 0) {
            $error = 'Subject name is required.';
        } else {
            // Verify subject belongs to current user
            $check = $conn->prepare("SELECT s.id FROM subjects s JOIN semesters sem ON s.semester_id = sem.id WHERE s.id = ? AND sem.user_id = ?");
            $check->bind_param("ii", $subj_id, $user_id);
            $check->execute();
            if ($check->get_result()->num_rows === 0) {
                $error = 'Subject not found or access denied.';
            } else {
                $stmt = $conn->prepare("UPDATE subjects SET name = ?, instructor_name = ? WHERE id = ?");
                $stmt->bind_param("ssi", $name, $instructor, $subj_id);
                if ($stmt->execute()) {
                    $_SESSION['message'] = 'Subject updated!';
                    $_SESSION['message_type'] = 'success';
                    header('Location: subjects.php' . ($semester_id > 0 ? '?semester_id=' . $semester_id : ''));
                    exit();
                }
                else { $error = 'Error updating subject.'; }
            }
        }
    } elseif ($action === 'delete') {
        $subj_id = intval($_POST['subject_id'] ?? 0);
        if ($subj_id > 0) {
            // Verify subject belongs to current user before deleting
            $stmt = $conn->prepare("DELETE s FROM subjects s JOIN semesters sem ON s.semester_id = sem.id WHERE s.id = ? AND sem.user_id = ?");
            $stmt->bind_param("ii", $subj_id, $user_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = 'Subject deleted!';
                $_SESSION['message_type'] = 'success';
                header('Location: subjects.php' . ($semester_id > 0 ? '?semester_id=' . $semester_id : ''));
                exit();
            }
            else { $error = 'Error deleting subject.'; }
        }
    }
}

$semesters = getUserSemesters($user_id, $conn);
$subjects = [];
$selected_semester = null;

if ($semester_id > 0) {
    $subjects = getSemesterSubjects($semester_id, $conn);
    $stmt = $conn->prepare("SELECT * FROM semesters WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $semester_id, $user_id);
    $stmt->execute();
    $selected_semester = $stmt->get_result()->fetch_assoc();
}
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-book"></i> Subjects</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal" <?php echo $semester_id <= 0 ? 'disabled' : ''; ?>>
                <i class="fas fa-plus"></i> Add Subject
            </button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Semester Selector -->
        <div class="card mb-4">
            <div class="card-body" style="padding: 16px 24px;">
                <form method="GET" class="d-flex align-items-center gap-3">
                    <label class="fw-600 text-nowrap" style="font-size: 13.5px;"><i class="fas fa-calendar-alt text-primary me-2"></i>Semester:</label>
                    <select class="form-select" name="semester_id" id="semesterSelect" onchange="saveSemesterChoice(this.value); this.form.submit()" style="max-width: 400px;">
                        <option value="">– Select a Semester –</option>
                        <?php foreach ($semesters as $sem): ?>
                            <option value="<?php echo $sem['id']; ?>" <?php echo $semester_id == $sem['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sem['name']); ?> <?php echo $sem['is_active'] ? '(Active)' : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <!-- Subjects List -->
        <?php if ($semester_id > 0): ?>
            <?php if (count($subjects) > 0): ?>
                <div class="row g-3">
                    <?php foreach ($subjects as $subject): 
                        $task_stats = getSubjectTaskStats($subject['id'], $conn);
                        $task_count = intval($task_stats['total']);
                        $task_done = intval($task_stats['completed']);
                        $task_pct = $task_count > 0 ? round(($task_done / $task_count) * 100) : 0;
                    ?>
                    <div class="col-12">
                        <div class="card subject-card">
                            <div class="card-body" style="padding: 16px 20px;">
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <!-- Subject info -->
                                    <div style="width: 40px; height: 40px; border-radius: 10px; background: var(--primary-50); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="fas fa-book" style="font-size: 16px;"></i>
                                    </div>
                                    <div style="flex: 1; min-width: 150px;">
                                        <h6 class="fw-700 mb-0"><?php echo htmlspecialchars($subject['name']); ?></h6>
                                        <div class="text-muted" style="font-size: 12px;">
                                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($subject['instructor_name'] ?: 'No instructor'); ?>
                                        </div>
                                    </div>

                                    <!-- Task progress -->
                                    <div class="d-flex align-items-center gap-2" style="min-width: 160px;">
                                        <span class="badge bg-info" style="font-size: 11px;"><?php echo $task_done; ?>/<?php echo $task_count; ?> Task<?php echo $task_count !== 1 ? 's' : ''; ?></span>
                                        <?php if ($task_count > 0): ?>
                                            <div class="flex-grow-1" style="height: 6px; background: var(--bg-secondary); border-radius: 3px; overflow: hidden; min-width: 60px;">
                                                <div style="width: <?php echo $task_pct; ?>%; height: 100%; background: <?php echo $task_pct >= 100 ? 'var(--success)' : 'var(--primary)'; ?>; border-radius: 3px; transition: width 0.3s;"></div>
                                            </div>
                                            <span class="text-muted" style="font-size: 11px;"><?php echo $task_pct; ?>%</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Actions -->
                                    <div class="d-flex gap-2 flex-shrink-0">
                                        <button class="btn btn-sm btn-primary subject-tasks-toggle" data-subject-id="<?php echo $subject['id']; ?>" onclick="toggleSubjectTasks(this, <?php echo $subject['id']; ?>)">
                                            <i class="fas fa-tasks"></i> Tasks <i class="fas fa-chevron-down ms-1" style="font-size: 10px;"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editSubjectModal"
                                            onclick="fillEditForm(<?php echo $subject['id']; ?>, '<?php echo htmlspecialchars($subject['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($subject['instructor_name'] ?? '', ENT_QUOTES); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Delete Subject', 'This will permanently delete this subject and all its tasks. This cannot be undone.', 'danger');">
                                            <?php echo getCSRFField(); ?>
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="subject_id" value="<?php echo $subject['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </div>

                                <!-- Inline Task List (hidden by default) -->
                                <div class="subject-tasks-panel" id="taskPanel-<?php echo $subject['id']; ?>" style="display: none; margin-top: 14px; border-top: 1px solid var(--border-color); padding-top: 14px;">
                                    <div class="task-loading text-center text-muted" style="font-size: 12px; padding: 8px;">
                                        <i class="fas fa-spinner fa-spin"></i> Loading tasks...
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="empty-state">
                        <i class="fas fa-book-open"></i>
                        <h5>No Subjects Yet</h5>
                        <p>Add subjects to this semester to start tracking tasks.</p>
                        <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                            <i class="fas fa-plus"></i> Add Subject
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-hand-point-up"></i>
                    <h5>Select a Semester</h5>
                    <p>Choose a semester above to view and manage its subjects.</p>
                </div>
            </div>
        <?php endif; ?>

<!-- Add Subject Modal -->
<div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo getCSRFField(); ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="semester_id" value="<?php echo $semester_id; ?>">
                    <div class="mb-3">
                        <label for="name" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="e.g., Data Structures" required>
                    </div>
                    <div class="mb-3">
                        <label for="instructor_name" class="form-label">Instructor Name</label>
                        <input type="text" class="form-control" id="instructor_name" name="instructor_name" placeholder="e.g., Prof. Santos">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Subject</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Subject Modal -->
<div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Subject</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo getCSRFField(); ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="subject_id" id="editSubjectId">
                    <div class="mb-3">
                        <label for="editName" class="form-label">Subject Name</label>
                        <input type="text" class="form-control" id="editName" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="editInstructor" class="form-label">Instructor Name</label>
                        <input type="text" class="form-control" id="editInstructor" name="instructor_name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function fillEditForm(id, name, instructor) {
    document.getElementById('editSubjectId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editInstructor').value = instructor;
}

// ─── Semester Persistence ───
function saveSemesterChoice(semId) {
    if (semId) {
        localStorage.setItem('studify-last-semester', semId);
    } else {
        localStorage.removeItem('studify-last-semester');
    }
}

// On page load: if no semester_id in URL, try to restore the last one
(function() {
    const urlParams = new URLSearchParams(window.location.search);
    const currentSemId = <?php echo json_encode($semester_id); ?>;
    
    // Save current semester to localStorage if one is selected
    if (currentSemId > 0) {
        localStorage.setItem('studify-last-semester', currentSemId);
    }
    
    // If no semester selected and not coming from a form submission, try to restore
    if (currentSemId <= 0 && !urlParams.has('semester_id')) {
        const saved = localStorage.getItem('studify-last-semester');
        if (saved) {
            window.location.replace('subjects.php?semester_id=' + saved);
        }
    }
})();

function toggleSubjectTasks(btn, subjectId) {
    const panel = document.getElementById('taskPanel-' + subjectId);
    const chevron = btn.querySelector('.fa-chevron-down, .fa-chevron-up');
    const isOpening = panel.style.display === 'none';
    
    // Close ALL open panels first (accordion behavior)
    document.querySelectorAll('.subject-tasks-panel').forEach(function(p) {
        p.style.display = 'none';
    });
    // Reset ALL chevrons to down
    document.querySelectorAll('.subject-tasks-toggle .fa-chevron-up').forEach(function(icon) {
        icon.classList.replace('fa-chevron-up', 'fa-chevron-down');
    });
    
    // If we were opening this panel (not closing the already-open one), show it
    if (isOpening) {
        panel.style.display = 'block';
        if (chevron) chevron.classList.replace('fa-chevron-down', 'fa-chevron-up');
        loadSubjectTasks(subjectId, panel);
    }
}

function loadSubjectTasks(subjectId, panel) {
    const baseUrl = '<?php echo BASE_URL; ?>';
    panel.innerHTML = '<div class="text-center text-muted" style="font-size: 12px; padding: 8px;"><i class="fas fa-spinner fa-spin"></i> Loading tasks...</div>';
    
    fetch(baseUrl + 'student/subjects.php?ajax=tasks&subject_id=' + subjectId)
        .then(r => r.json())
        .then(data => {
            // Build the add-task form (always shown at top)
            const addFormHtml = `
                <div class="inline-add-task-form" id="addTaskForm-${subjectId}" style="margin-bottom: 10px;">
                    <div style="display: flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                        <i class="fas fa-plus-circle" style="color: var(--primary); font-size: 14px;"></i>
                        <span style="font-weight: 600; font-size: 12px; color: var(--text-secondary);">Quick Add Task</span>
                    </div>
                    <div style="display: flex; gap: 6px; margin-bottom: 6px;">
                        <input type="text" id="inlineTaskTitle-${subjectId}" placeholder="Task title..." 
                            style="flex: 1; padding: 6px 10px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 12px; background: var(--bg-card); color: var(--text-primary);" 
                            onkeydown="if(event.key==='Enter'){event.preventDefault(); inlineAddTask(${subjectId});}">
                        <input type="datetime-local" id="inlineTaskDeadline-${subjectId}" 
                            style="width: 170px; padding: 6px 8px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 11px; background: var(--bg-card); color: var(--text-primary);">
                    </div>
                    <div style="display: flex; gap: 6px; align-items: center;">
                        <select id="inlineTaskType-${subjectId}" style="padding: 5px 8px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 11px; background: var(--bg-card); color: var(--text-primary);">
                            <option value="Assignment">Assignment</option>
                            <option value="Quiz">Quiz</option>
                            <option value="Exam">Exam</option>
                            <option value="Project">Project</option>
                            <option value="Report">Report</option>
                            <option value="Presentation">Presentation</option>
                            <option value="Lab">Lab</option>
                            <option value="Other">Other</option>
                        </select>
                        <select id="inlineTaskPriority-${subjectId}" style="padding: 5px 8px; border: 1px solid var(--border-color); border-radius: 6px; font-size: 11px; background: var(--bg-card); color: var(--text-primary);">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                        <button onclick="inlineAddTask(${subjectId})" class="btn btn-sm btn-primary" style="font-size: 11px; padding: 4px 12px; white-space: nowrap;">
                            <i class="fas fa-plus"></i> Add
                        </button>
                    </div>
                </div>
                <hr style="margin: 8px 0; border-color: var(--border-color);">
            `;

            if (data.success && data.tasks.length > 0) {
                let html = addFormHtml + '<div class="subject-inline-tasks">';
                data.tasks.forEach(task => {
                    const deadline = new Date(task.deadline);
                    const now = new Date();
                    const isOverdue = deadline < now && task.status !== 'Completed';
                    const isCompleted = task.status === 'Completed';
                    const deadlineStr = deadline.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                    
                    let statusBadge = '';
                    if (isOverdue) {
                        statusBadge = '<span class="badge" style="background: rgba(220,38,38,0.1); color: var(--danger); font-size: 10px;"><i class="fas fa-exclamation-triangle"></i> Overdue</span>';
                    } else if (isCompleted) {
                        statusBadge = '<span class="badge" style="background: rgba(22,163,74,0.1); color: var(--success); font-size: 10px;"><i class="fas fa-check-circle"></i> Done</span>';
                    } else if (task.status === 'In Progress') {
                        statusBadge = '<span class="badge" style="background: rgba(37,99,235,0.1); color: var(--info); font-size: 10px;"><i class="fas fa-spinner"></i> In Progress</span>';
                    } else {
                        statusBadge = '<span class="badge" style="background: rgba(234,179,8,0.1); color: var(--warning); font-size: 10px;"><i class="fas fa-hourglass-half"></i> Pending</span>';
                    }
                    
                    const priorityColors = { High: 'var(--danger)', Medium: 'var(--warning)', Low: 'var(--success)' };
                    const priorityColor = priorityColors[task.priority] || 'var(--text-muted)';
                    
                    html += `
                        <div class="inline-task-item" id="inline-task-${task.id}" style="
                            display: flex; align-items: center; gap: 8px; padding: 8px 10px; 
                            border-radius: 8px; margin-bottom: 4px; font-size: 12.5px;
                            background: ${isCompleted ? 'var(--bg-secondary)' : 'transparent'};
                            border-left: 3px solid ${priorityColor};
                            opacity: ${isCompleted ? '0.7' : '1'};
                            transition: all 0.2s ease;
                        " onmouseover="this.style.background='var(--bg-card-hover)'" onmouseout="this.style.background='${isCompleted ? 'var(--bg-secondary)' : 'transparent'}'">
                            <button class="btn btn-sm p-0" style="width: 22px; height: 22px; border-radius: 50%; border: 2px solid ${isCompleted ? 'var(--success)' : 'var(--border-color)'}; background: ${isCompleted ? 'var(--success)' : 'transparent'}; color: ${isCompleted ? 'white' : 'transparent'}; display: flex; align-items: center; justify-content: center; flex-shrink: 0; font-size: 10px; cursor: pointer;"
                                onclick="inlineToggleTask(${task.id}, ${subjectId})" title="${isCompleted ? 'Reopen' : 'Complete'}">
                                <i class="fas fa-check"></i>
                            </button>
                            <div style="flex: 1; min-width: 0;">
                                <div style="font-weight: 500; ${isCompleted ? 'text-decoration: line-through; color: var(--text-muted);' : ''}" class="text-truncate">${escapeHtml(task.title)}</div>
                                <div style="display: flex; gap: 6px; align-items: center; margin-top: 2px;">
                                    <span style="font-size: 11px; color: var(--text-muted);"><i class="fas fa-calendar" style="font-size: 9px;"></i> ${deadlineStr}</span>
                                    ${statusBadge}
                                    <span class="badge bg-secondary" style="font-size: 10px;">${escapeHtml(task.type)}</span>
                                </div>
                            </div>
                            <button class="btn btn-sm p-0" style="width: 22px; height: 22px; border: none; background: transparent; color: var(--text-muted); cursor: pointer; font-size: 11px;" 
                                onclick="inlineDeleteTask(${task.id}, ${subjectId})" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                });
                html += '</div>';
                html += '<a href="' + baseUrl + 'student/tasks.php?subject_id=' + subjectId + '" class="btn btn-sm btn-secondary w-100 mt-2" style="font-size: 11px;"><i class="fas fa-external-link-alt"></i> Open Full Task View</a>';
                panel.innerHTML = html;
            } else if (data.success) {
                panel.innerHTML = addFormHtml + '<div class="text-center text-muted" style="font-size: 12px; padding: 12px;"><i class="fas fa-clipboard-list" style="font-size: 16px; display: block; margin-bottom: 4px; opacity: 0.3;"></i>No tasks yet. Use the form above to add one!</div>';
            } else {
                panel.innerHTML = '<div class="text-center text-danger" style="font-size: 12px; padding: 8px;"><i class="fas fa-exclamation-circle"></i> Error loading tasks</div>';
            }
        })
        .catch(() => {
            panel.innerHTML = '<div class="text-center text-danger" style="font-size: 12px; padding: 8px;"><i class="fas fa-exclamation-circle"></i> Network error</div>';
        });
}

function inlineToggleTask(taskId, subjectId) {
    const baseUrl = '<?php echo BASE_URL; ?>';
    fetch(baseUrl + 'student/tasks.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=toggle_status&task_id=' + taskId + '&csrf_token=' + getCSRFToken()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            StudifyToast.success('Task Updated', data.message);
            const panel = document.getElementById('taskPanel-' + subjectId);
            loadSubjectTasks(subjectId, panel);
            // Update the task count badge on the subject card
            setTimeout(() => location.reload(), 800);
        } else {
            StudifyToast.error('Error', data.message || 'Failed to update task');
        }
    })
    .catch(() => StudifyToast.error('Error', 'Network error'));
}

function inlineDeleteTask(taskId, subjectId) {
    StudifyConfirm.action('Delete Task', 'Are you sure you want to delete this task?', 'danger', function() {
        const baseUrl = '<?php echo BASE_URL; ?>';
        fetch(baseUrl + 'student/tasks.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: 'action=delete&task_id=' + taskId + '&csrf_token=' + getCSRFToken()
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                StudifyToast.success('Task Deleted', 'Task removed successfully');
                const item = document.getElementById('inline-task-' + taskId);
                if (item) {
                    item.style.transition = 'all 0.3s ease';
                    item.style.opacity = '0';
                    item.style.transform = 'translateX(20px)';
                    setTimeout(() => {
                        item.remove();
                        // Reload to update counters
                        setTimeout(() => location.reload(), 500);
                    }, 300);
                }
            } else {
                StudifyToast.error('Error', data.message || 'Failed to delete task');
            }
        })
        .catch(() => StudifyToast.error('Error', 'Network error'));
    });
}

function inlineAddTask(subjectId) {
    const titleEl = document.getElementById('inlineTaskTitle-' + subjectId);
    const deadlineEl = document.getElementById('inlineTaskDeadline-' + subjectId);
    const typeEl = document.getElementById('inlineTaskType-' + subjectId);
    const priorityEl = document.getElementById('inlineTaskPriority-' + subjectId);
    
    const title = titleEl.value.trim();
    const deadline = deadlineEl.value;
    const type = typeEl.value;
    const priority = priorityEl.value;
    
    if (!title) {
        StudifyToast.warning('Required', 'Please enter a task title');
        titleEl.focus();
        return;
    }
    if (!deadline) {
        StudifyToast.warning('Required', 'Please set a deadline');
        deadlineEl.focus();
        return;
    }
    
    const baseUrl = '<?php echo BASE_URL; ?>';
    fetch(baseUrl + 'student/subjects.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=add_task&subject_id=' + subjectId + '&title=' + encodeURIComponent(title) + '&type=' + encodeURIComponent(type) + '&priority=' + encodeURIComponent(priority) + '&deadline=' + encodeURIComponent(deadline) + '&csrf_token=' + getCSRFToken()
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            StudifyToast.success('Task Added', data.message);
            // Clear form fields
            titleEl.value = '';
            deadlineEl.value = '';
            typeEl.value = 'Assignment';
            priorityEl.value = 'Medium';
            // Reload inline task list
            const panel = document.getElementById('taskPanel-' + subjectId);
            loadSubjectTasks(subjectId, panel);
        } else {
            StudifyToast.error('Error', data.message || 'Failed to add task');
        }
    })
    .catch(() => StudifyToast.error('Error', 'Network error'));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>

<?php include '../includes/footer.php'; ?>
