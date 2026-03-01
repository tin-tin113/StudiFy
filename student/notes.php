<?php
/**
 * STUDIFY – Notes per Subject
 * Create, view, edit, delete text notes tied to subjects
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdminRole()) { header("Location: " . BASE_URL . "admin/admin_dashboard.php"); exit(); }

$page_title = 'Notes';
$user_id = getCurrentUserId();
$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $subject_id = intval($_POST['subject_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';

        if (empty($title)) {
            $error = 'Title is required.';
        } else {
            // Verify subject belongs to user if one is selected
            if ($subject_id > 0) {
                $check = $conn->prepare("SELECT s.id FROM subjects s JOIN semesters sem ON s.semester_id = sem.id WHERE s.id = ? AND sem.user_id = ?");
                $check->bind_param("ii", $subject_id, $user_id);
                $check->execute();
                if ($check->get_result()->num_rows === 0) {
                    $error = 'Invalid subject.';
                    $subject_id = 0;
                }
            }
            if (empty($error)) {
                $sub_val = $subject_id > 0 ? $subject_id : null;
                $stmt = $conn->prepare("INSERT INTO notes (subject_id, user_id, title, content) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("iiss", $sub_val, $user_id, $title, $content);
                if ($stmt->execute()) { $success = 'Note saved!'; }
                else { $error = 'Error saving note.'; }
            }
        }
    }

    if ($action === 'edit') {
        $note_id = intval($_POST['note_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';

        if ($note_id > 0 && !empty($title)) {
            $stmt = $conn->prepare("UPDATE notes SET title = ?, content = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ssii", $title, $content, $note_id, $user_id);
            if ($stmt->execute()) { $success = 'Note updated!'; }
            else { $error = 'Error updating note.'; }
        }
    }

    if ($action === 'delete') {
        $note_id = intval($_POST['note_id'] ?? 0);
        if ($note_id > 0) {
            $stmt = $conn->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $note_id, $user_id);
            if ($stmt->execute()) { $success = 'Note deleted!'; }
            else { $error = 'Error deleting note.'; }
        }
    }
}

// Get all subjects for dropdown
$all_subjects = [];
$semesters = getUserSemesters($user_id, $conn);
foreach ($semesters as $sem) {
    $subs = getSemesterSubjects($sem['id'], $conn);
    foreach ($subs as $s) { $s['semester_name'] = $sem['name']; $all_subjects[] = $s; }
}

// Filter
$filter_subject = intval($_GET['subject_id'] ?? 0);
$search_q = trim($_GET['search'] ?? '');

// Get notes
$notes_query = "SELECT n.*, COALESCE(s.name, 'General') as subject_name, COALESCE(sem.name, '') as semester_name
                FROM notes n
                LEFT JOIN subjects s ON n.subject_id = s.id
                LEFT JOIN semesters sem ON s.semester_id = sem.id
                WHERE n.user_id = ?";
$params = [$user_id];
$types = "i";

if ($filter_subject == -1) {
    $notes_query .= " AND n.subject_id IS NULL";
} elseif ($filter_subject > 0) {
    $notes_query .= " AND n.subject_id = ?";
    $params[] = $filter_subject;
    $types .= "i";
}
if (!empty($search_q)) {
    $notes_query .= " AND (n.title LIKE ? OR n.content LIKE ?)";
    $search_like = "%{$search_q}%";
    $params[] = $search_like;
    $params[] = $search_like;
    $types .= "ss";
}

$notes_query .= " ORDER BY n.updated_at DESC";
$stmt = $conn->prepare($notes_query);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Total notes
$total_stmt = $conn->prepare("SELECT COUNT(*) as c FROM notes WHERE user_id = ?");
$total_stmt->bind_param("i", $user_id);
$total_stmt->execute();
$total_notes = $total_stmt->get_result()->fetch_assoc()['c'];
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-sticky-note"></i> Notes</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNoteModal">
                <i class="fas fa-plus"></i> New Note
            </button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Search & Filter -->
        <div class="card mb-4">
            <div class="card-body" style="padding: 12px 20px;">
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="flex-grow-1" style="min-width: 200px;">
                        <input type="text" class="form-control form-control-sm" name="search" placeholder="Search notes..." value="<?php echo htmlspecialchars($search_q); ?>">
                    </div>
                    <select class="form-select form-select-sm" name="subject_id" style="max-width: 220px;">
                        <option value="0">All Notes</option>
                        <option value="-1" <?php echo $filter_subject == -1 ? 'selected' : ''; ?>>General (No Subject)</option>
                        <?php foreach ($all_subjects as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo $filter_subject == $s['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search"></i> Filter</button>
                    <?php if ($filter_subject != 0 || !empty($search_q)): ?>
                        <a href="notes.php" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                    <span class="text-muted ms-auto" style="font-size: 12px;"><?php echo count($notes); ?> of <?php echo $total_notes; ?> notes</span>
                </form>
            </div>
        </div>

        <!-- Notes Grid -->
        <?php if (count($notes) > 0): ?>
        <div class="row g-3">
            <?php foreach ($notes as $note): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100" style="cursor: pointer;" onclick="viewNote(<?php echo htmlspecialchars(json_encode($note), ENT_QUOTES); ?>)">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="fw-bold mb-0" style="font-size: 14px;"><?php echo htmlspecialchars($note['title']); ?></h6>
                            <div class="d-flex gap-1" onclick="event.stopPropagation();">
                                <button class="btn btn-sm btn-info" style="padding: 2px 6px; font-size: 11px;" data-bs-toggle="modal" data-bs-target="#editNoteModal"
                                    onclick="fillEditNote(<?php echo htmlspecialchars(json_encode($note), ENT_QUOTES); ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Delete Note', 'This note will be permanently deleted. This cannot be undone.', 'danger');">
                                    <?php echo getCSRFField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="note_id" value="<?php echo $note['id']; ?>">
                                    <button type="submit" class="btn btn-sm btn-danger" style="padding: 2px 6px; font-size: 11px;"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                        <p class="text-muted mb-2" style="font-size: 12.5px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                            <?php echo htmlspecialchars($note['content'] ?: 'No content'); ?>
                        </p>
                        <div class="d-flex justify-content-between align-items-center" style="font-size: 11px; color: var(--text-muted);">
                            <span class="badge bg-<?php echo ($note['subject_id'] ? 'primary' : 'secondary'); ?>" style="font-size: 10px;"><?php echo htmlspecialchars($note['subject_name']); ?></span>
                            <span><i class="fas fa-clock"></i> <?php echo formatDate($note['updated_at']); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-sticky-note"></i>
                    <h5>No Notes Found</h5>
                    <p><?php echo !empty($search_q) || $filter_subject > 0 ? 'Try a different search or filter.' : 'Create your first note to get started.'; ?></p>
                </div>
            </div>
        <?php endif; ?>

<!-- View Note Modal -->
<div class="modal fade" id="viewNoteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewNoteTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-2">
                    <span class="badge bg-primary" id="viewNoteSubject"></span>
                    <small class="text-muted ms-2" id="viewNoteDate"></small>
                </div>
                <hr>
                <div id="viewNoteContent" style="white-space: pre-wrap; font-size: 14px; line-height: 1.7;"></div>
            </div>
        </div>
    </div>
</div>

<!-- Add Note Modal -->
<div class="modal fade" id="addNoteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-plus"></i> New Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo getCSRFField(); ?>
                    <input type="hidden" name="action" value="add">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="noteTitle" class="form-label">Title</label>
                            <input type="text" class="form-control" id="noteTitle" name="title" placeholder="Note title..." required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="noteSubject" class="form-label">Subject <small class="text-muted">(optional)</small></label>
                            <select class="form-select" id="noteSubject" name="subject_id">
                                <option value="0">General (No Subject)</option>
                                <?php foreach ($all_subjects as $s): ?>
                                    <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?> (<?php echo htmlspecialchars($s['semester_name']); ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="noteContent" class="form-label">Content</label>
                        <textarea class="form-control" id="noteContent" name="content" rows="10" placeholder="Write your notes here..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Note Modal -->
<div class="modal fade" id="editNoteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Note</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo getCSRFField(); ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="note_id" id="editNoteId">
                    <div class="mb-3">
                        <label for="editNoteTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="editNoteTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="editNoteContent" class="form-label">Content</label>
                        <textarea class="form-control" id="editNoteContent" name="content" rows="10"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function viewNote(note) {
    document.getElementById('viewNoteTitle').textContent = note.title;
    document.getElementById('viewNoteSubject').textContent = note.subject_name || 'General';
    document.getElementById('viewNoteSubject').className = 'badge bg-' + (note.subject_id ? 'primary' : 'secondary');
    document.getElementById('viewNoteDate').textContent = 'Updated: ' + note.updated_at;
    document.getElementById('viewNoteContent').textContent = note.content || 'No content.';
    new bootstrap.Modal(document.getElementById('viewNoteModal')).show();
}

function fillEditNote(note) {
    document.getElementById('editNoteId').value = note.id;
    document.getElementById('editNoteTitle').value = note.title;
    document.getElementById('editNoteContent').value = note.content || '';
}
</script>

<?php include '../includes/footer.php'; ?>
