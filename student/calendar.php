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

// Handle form submission for adding a task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    requireCSRF();
    $subj_id = intval($_POST['subject_id'] ?? 0);
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $type = sanitize($_POST['type'] ?? 'Assignment');
    $priority = sanitize($_POST['priority'] ?? 'Medium');
    $deadline = $_POST['deadline'] ?? '';

    if (empty($title) || $subj_id <= 0 || empty($deadline)) {
        $error = 'Title, subject, and deadline are required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO tasks (subject_id, title, description, type, priority, deadline) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $subj_id, $title, $description, $type, $priority, $deadline);
        if ($stmt->execute()) { $success = 'Task added successfully!'; }
        else { $error = 'Error adding task.'; }
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
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskCalendarModal" <?php echo count($all_subjects) === 0 ? 'disabled title="Add a subject first"' : ''; ?>>
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
                                    <label for="calAddSubject" class="form-label">Subject</label>
                                    <select class="form-select" id="calAddSubject" name="subject_id" required>
                                        <option value="">Select Subject</option>
                                        <?php foreach ($all_subjects as $sub): ?>
                                            <option value="<?php echo $sub['id']; ?>">
                                                <?php echo htmlspecialchars($sub['name']); ?> (<?php echo htmlspecialchars($sub['semester_name']); ?>)
                                            </option>
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
        selectable: hasSubjects,
        dateClick: function(info) {
            if (!hasSubjects) return;
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
            else if (props.status === 'In Progress') statusBadge.classList.add('bg-info');
            else statusBadge.classList.add('bg-warning');

            new bootstrap.Modal(document.getElementById('taskModal')).show();
        }
    });
    calendar.render();
});
</script>

<?php include '../includes/footer.php'; ?>
