<?php
/**
 * STUDIFY – Calendar View
 * FullCalendar integration with task events + Add Task
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

$page_title = 'Calendar';
$user_id = getCurrentUserId();
$error = '';
$success = '';

// Handle AJAX request for drag-to-reschedule
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }
    $action = $_POST['action'] ?? '';
    if ($action === 'reschedule') {
        $task_id = intval($_POST['task_id'] ?? 0);
        $new_deadline = $_POST['new_deadline'] ?? '';
        if ($task_id > 0 && !empty($new_deadline)) {
            $stmt = $conn->prepare("UPDATE tasks SET deadline = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("sii", $new_deadline, $task_id, $user_id);
            if ($stmt->execute() && $stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Task rescheduled!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Task not found or access denied.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        }
        exit();
    }
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Handle form submission for adding a task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    requireCSRF();
    $subj_id = intval($_POST['subject_id'] ?? 0);
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $type = sanitize($_POST['type'] ?? 'Assignment');
    $priority = sanitize($_POST['priority'] ?? 'Medium');
    $deadline = $_POST['deadline'] ?? '';

    if (empty($title) || empty($deadline)) {
        $error = 'Title and deadline are required.';
    } else {
        $subj_id_val = $subj_id > 0 ? $subj_id : null;
        // Verify subject belongs to current user (if selected)
        if ($subj_id_val) {
            $own_check = $conn->prepare("SELECT s.id FROM subjects s JOIN semesters sem ON s.semester_id = sem.id WHERE s.id = ? AND sem.user_id = ?");
            $own_check->bind_param("ii", $subj_id, $user_id);
            $own_check->execute();
            if ($own_check->get_result()->num_rows === 0) {
                $error = 'Subject not found or access denied.';
            }
        }
        if (empty($error)) {
            $stmt = $conn->prepare("INSERT INTO tasks (user_id, subject_id, title, description, type, priority, deadline) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisssss", $user_id, $subj_id_val, $title, $description, $type, $priority, $deadline);
            if ($stmt->execute()) {
                $_SESSION['message'] = 'Task added successfully!';
                $_SESSION['message_type'] = 'success';
                header('Location: calendar.php');
                exit();
            }
            else { $error = 'Error adding task.'; }
        }
    }
}

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

// Get tasks as JSON for FullCalendar
$tasks_json = getTasksAsJSON($user_id, $conn);
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-calendar-alt"></i> Calendar</h2>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskCalendarModal">
                    <i class="fas fa-plus"></i> Add Task
                </button>
                <a href="tasks.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> List View
                </a>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Priority Legend -->
        <div class="card mb-4">
            <div class="card-body py-2 px-3">
                <div class="d-flex flex-wrap align-items-center gap-4">
                    <span class="fw-semibold text-muted me-1" style="font-size: 13px;"><i class="fas fa-info-circle"></i> Legend:</span>
                    <span style="font-size: 13px;"><i class="fas fa-circle text-danger" style="font-size: 10px;"></i> High Priority</span>
                    <span style="font-size: 13px;"><i class="fas fa-circle text-warning" style="font-size: 10px;"></i> Medium Priority</span>
                    <span style="font-size: 13px;"><i class="fas fa-circle text-success" style="font-size: 10px;"></i> Low Priority</span>
                    <span style="font-size: 13px;"><i class="fas fa-circle" style="font-size: 10px; color: #9ca3af;"></i> Completed</span>
                </div>
            </div>
        </div>

        <!-- Calendar -->
        <div class="card">
            <div class="card-body p-3">
                <div id="calendar"></div>
            </div>
        </div>

        <!-- Task Details Modal -->
        <div class="modal fade" id="taskModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-clipboard-check"></i> <span id="taskTitle"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted" style="min-width: 90px;"><i class="fas fa-book"></i> Subject</span>
                                <span id="taskSubject" class="fw-medium"></span>
                            </div>
                            <div class="d-flex align-items-start gap-2">
                                <span class="text-muted" style="min-width: 90px;"><i class="fas fa-align-left"></i> Details</span>
                                <span id="taskDescription"></span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted" style="min-width: 90px;"><i class="fas fa-calendar"></i> Deadline</span>
                                <span id="taskDeadline" class="fw-medium"></span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted" style="min-width: 90px;"><i class="fas fa-flag"></i> Priority</span>
                                <span id="taskPriority" class="badge"></span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted" style="min-width: 90px;"><i class="fas fa-tag"></i> Type</span>
                                <span id="taskType" class="badge"></span>
                            </div>
                            <div class="d-flex align-items-center gap-2">
                                <span class="text-muted" style="min-width: 90px;"><i class="fas fa-spinner"></i> Status</span>
                                <span id="taskStatus" class="badge"></span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <a href="tasks.php" class="btn btn-primary"><i class="fas fa-external-link-alt"></i> Go to Tasks</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Task Modal (Calendar) -->
        <div class="modal fade" id="addTaskCalendarModal" tabindex="-1">
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
                                    <label for="calAddTitle" class="form-label">Task Title</label>
                                    <input type="text" class="form-control" id="calAddTitle" name="title" placeholder="e.g., Research Paper Draft" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="calAddSubject" class="form-label">Subject <small class="text-muted">(optional)</small></label>
                                    <select class="form-select" id="calAddSubject" name="subject_id">
                                        <option value="">— None —</option>
                                        <?php
                                        $subjects_by_sem = [];
                                        foreach ($all_subjects as $sub) {
                                            $subjects_by_sem[$sub['semester_name']][] = $sub;
                                        }
                                        foreach ($subjects_by_sem as $sem_name => $subs): ?>
                                        <optgroup label="📅 <?php echo htmlspecialchars($sem_name); ?>">
                                            <?php foreach ($subs as $sub): ?>
                                            <option value="<?php echo $sub['id']; ?>">
                                                <?php echo htmlspecialchars($sub['name']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="calAddDesc" class="form-label">Description <small class="text-muted">(optional)</small></label>
                                <textarea class="form-control" id="calAddDesc" name="description" rows="2" placeholder="Add details about this task..."></textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label for="calAddType" class="form-label">Type</label>
                                    <select class="form-select" id="calAddType" name="type">
                                        <option value="Assignment">Assignment</option>
                                        <option value="Quiz">Quiz</option>
                                        <option value="Exam">Exam</option>
                                        <option value="Project">Project</option>
                                        <option value="Report">Report</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="calAddPriority" class="form-label">Priority</label>
                                    <select class="form-select" id="calAddPriority" name="priority">
                                        <option value="Low">Low</option>
                                        <option value="Medium" selected>Medium</option>
                                        <option value="High">High</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="calAddDeadline" class="form-label">Deadline</label>
                                    <input type="datetime-local" class="form-control" id="calAddDeadline" name="deadline" required>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    if (!calendarEl) return;

    const hasSubjects = <?php echo count($all_subjects) > 0 ? 'true' : 'false'; ?>;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listMonth'
        },
        events: <?php echo $tasks_json; ?>,
        height: 'auto',
        navLinks: true,
        dayMaxEvents: 2,
        eventDisplay: 'block',
        displayEventTime: false,
        editable: true,
        eventDurationEditable: false,
        selectable: true,
        eventDrop: async function(info) {
            const newDate = info.event.start;
            const year = newDate.getFullYear();
            const month = String(newDate.getMonth() + 1).padStart(2, '0');
            const day = String(newDate.getDate()).padStart(2, '0');
            const newDeadline = year + '-' + month + '-' + day + ' 23:59:00';
            try {
                const body = new URLSearchParams({
                    action: 'reschedule',
                    task_id: info.event.id,
                    new_deadline: newDeadline,
                    csrf_token: csrfToken
                });
                const res = await fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: body.toString()
                });
                const data = await res.json();
                if (data.success) {
                    if (typeof showToast === 'function') showToast(data.message, 'success');
                } else {
                    info.revert();
                    if (typeof showToast === 'function') showToast(data.message || 'Failed to reschedule', 'error');
                }
            } catch (e) {
                info.revert();
                if (typeof showToast === 'function') showToast('Network error', 'error');
            }
        },
        dateClick: function(info) {
            // Pre-fill deadline with clicked date (set to 23:59)
            const clickedDate = info.dateStr;
            const deadlineInput = document.getElementById('calAddDeadline');
            if (deadlineInput) {
                deadlineInput.value = clickedDate + 'T23:59';
            }
            new bootstrap.Modal(document.getElementById('addTaskCalendarModal')).show();
        },
        eventClick: function(info) {
            const event = info.event;
            const props = event.extendedProps;

            document.getElementById('taskTitle').textContent = event.title;
            document.getElementById('taskSubject').textContent = props.subject || 'N/A';
            document.getElementById('taskDescription').textContent = props.description || 'No description provided';
            document.getElementById('taskDeadline').textContent = event.start ? event.start.toLocaleString() : 'N/A';

            const priorityBadge = document.getElementById('taskPriority');
            priorityBadge.textContent = props.priority || 'N/A';
            priorityBadge.className = 'badge';
            if (props.priority === 'High') priorityBadge.classList.add('bg-danger');
            else if (props.priority === 'Medium') priorityBadge.classList.add('bg-warning');
            else priorityBadge.classList.add('bg-success');

            const typeBadge = document.getElementById('taskType');
            typeBadge.textContent = props.type || 'N/A';
            typeBadge.className = 'badge bg-secondary';

            const statusBadge = document.getElementById('taskStatus');
            statusBadge.textContent = props.status || 'N/A';
            statusBadge.className = 'badge';
            if (props.status === 'Completed') statusBadge.classList.add('bg-success');
            else statusBadge.classList.add('bg-warning');

            new bootstrap.Modal(document.getElementById('taskModal')).show();
        }
    });
    calendar.render();
});
</script>

<?php include '../includes/footer.php'; ?>
