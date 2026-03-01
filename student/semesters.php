<?php
/**
 * STUDIFY – Semesters Management
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

$page_title = 'Semesters';
$user_id = getCurrentUserId();
$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = sanitize($_POST['name'] ?? '');
        if (empty($name)) {
            $error = 'Semester name is required.';
        } else {
            $insert_query = "INSERT INTO semesters (user_id, name) VALUES (?, ?)";
            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param("is", $user_id, $name);
            if ($stmt->execute()) { $success = 'Semester added successfully!'; }
            else { $error = 'Error adding semester.'; }
        }
    } elseif ($action === 'activate') {
        $sem_id = intval($_POST['semester_id'] ?? 0);
        $conn->query("UPDATE semesters SET is_active = 0 WHERE user_id = $user_id");
        $stmt = $conn->prepare("UPDATE semesters SET is_active = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $sem_id, $user_id);
        if ($stmt->execute()) { $success = 'Semester activated!'; }
    } elseif ($action === 'delete') {
        $sem_id = intval($_POST['semester_id'] ?? 0);
        $stmt = $conn->prepare("DELETE FROM semesters WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $sem_id, $user_id);
        if ($stmt->execute()) { $success = 'Semester deleted!'; }
        else { $error = 'Error deleting semester.'; }
    }
}

$semesters = getUserSemesters($user_id, $conn);
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-calendar-alt"></i> Semesters</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSemesterModal">
                <i class="fas fa-plus"></i> Add Semester
            </button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (count($semesters) > 0): ?>
            <div class="row g-4">
                <?php foreach ($semesters as $sem): 
                    $subjects = getSemesterSubjects($sem['id'], $conn);
                    $subject_count = count($subjects);
                ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100" style="border-left: 4px solid <?php echo $sem['is_active'] ? 'var(--success)' : 'var(--border-color)'; ?>;">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="fw-700 mb-1"><?php echo htmlspecialchars($sem['name']); ?></h5>
                                    <span class="text-muted" style="font-size: 12px;">
                                        <i class="fas fa-book me-1"></i> <?php echo $subject_count; ?> Subject<?php echo $subject_count !== 1 ? 's' : ''; ?>
                                    </span>
                                </div>
                                <?php if ($sem['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($subject_count > 0): ?>
                                <div class="mb-3">
                                    <?php foreach (array_slice($subjects, 0, 3) as $subj): ?>
                                        <div class="d-flex align-items-center gap-2 py-1" style="font-size: 13px;">
                                            <i class="fas fa-circle text-primary" style="font-size: 6px;"></i>
                                            <?php echo htmlspecialchars($subj['name']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                    <?php if ($subject_count > 3): ?>
                                        <div class="text-muted" style="font-size: 12px; margin-top: 4px;">+<?php echo $subject_count - 3; ?> more</div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <div class="d-flex gap-2 mt-auto">
                                <a href="<?php echo BASE_URL; ?>student/subjects.php?semester_id=<?php echo $sem['id']; ?>" class="btn btn-sm btn-primary flex-grow-1">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if (!$sem['is_active']): ?>
                                    <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Activate Semester', 'This will deactivate all other semesters and set this one as active.', 'info');">
                                        <?php echo getCSRFField(); ?>
                                        <input type="hidden" name="action" value="activate">
                                        <input type="hidden" name="semester_id" value="<?php echo $sem['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-success" title="Set Active"><i class="fas fa-check"></i></button>
                                    </form>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Delete Semester', 'This will permanently delete this semester and ALL its subjects and tasks. This cannot be undone.', 'danger');">
                                    <?php echo getCSRFField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="semester_id" value="<?php echo $sem['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i></button>
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
                    <i class="fas fa-calendar-plus"></i>
                    <h5>No Semesters Yet</h5>
                    <p>Create your first semester to start organizing your academic life.</p>
                    <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addSemesterModal">
                        <i class="fas fa-plus"></i> Add Semester
                    </button>
                </div>
            </div>
        <?php endif; ?>

<!-- Add Semester Modal -->
<div class="modal fade" id="addSemesterModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> Add Semester</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo getCSRFField(); ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="name" class="form-label">Semester Name</label>
                        <input type="text" class="form-control" id="name" name="name" placeholder="e.g., 1st Semester 2024-2025" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Semester</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
