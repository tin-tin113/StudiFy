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
                    header('Location: subjects.php');
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
                    header('Location: subjects.php');
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
                header('Location: subjects.php');
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
                    <select class="form-select" name="semester_id" onchange="this.form.submit()" style="max-width: 400px;">
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
                <div class="row g-4">
                    <?php foreach ($subjects as $subject): 
                        $task_stats = getSubjectTaskStats($subject['id'], $conn);
                        $task_count = intval($task_stats['total']);
                        $task_done = intval($task_stats['completed']);
                        $task_pct = $task_count > 0 ? round(($task_done / $task_count) * 100) : 0;
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start gap-3 mb-3">
                                    <div style="width: 44px; height: 44px; border-radius: 12px; background: var(--primary-50); color: var(--primary); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                        <i class="fas fa-book" style="font-size: 18px;"></i>
                                    </div>
                                    <div style="flex: 1; min-width: 0;">
                                        <h5 class="fw-700 mb-1 truncate"><?php echo htmlspecialchars($subject['name']); ?></h5>
                                        <div class="text-muted" style="font-size: 12.5px;">
                                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($subject['instructor_name'] ?: 'No instructor'); ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex align-items-center gap-2 mb-3">
                                    <span class="badge bg-info"><?php echo $task_done; ?>/<?php echo $task_count; ?> Task<?php echo $task_count !== 1 ? 's' : ''; ?></span>
                                    <?php if ($task_count > 0): ?>
                                        <div class="flex-grow-1" style="height: 6px; background: var(--bg-secondary); border-radius: 3px; overflow: hidden;">
                                            <div style="width: <?php echo $task_pct; ?>%; height: 100%; background: <?php echo $task_pct >= 100 ? 'var(--success)' : 'var(--primary)'; ?>; border-radius: 3px; transition: width 0.3s;"></div>
                                        </div>
                                        <span class="text-muted" style="font-size: 11px; min-width: 32px;"><?php echo $task_pct; ?>%</span>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex gap-2">
                                    <a href="<?php echo BASE_URL; ?>student/tasks.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-primary flex-grow-1">
                                        <i class="fas fa-tasks"></i> Tasks
                                    </a>
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
</script>

<?php include '../includes/footer.php'; ?>
