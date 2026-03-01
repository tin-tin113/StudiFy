<?php
/**
 * STUDIFY – Announcements Management (Admin)
 * Create, edit, delete announcements for students
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

$page_title = 'Announcements';
$user_id = getCurrentUserId();
$error = '';
$success = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $priority = sanitize($_POST['priority'] ?? 'Normal');
        $expires = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        if (empty($title) || empty($content)) {
            $error = 'Title and content are required.';
        } else {
            $stmt = $conn->prepare("INSERT INTO announcements (admin_id, title, content, priority, expires_at) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $user_id, $title, $content, $priority, $expires);
            if ($stmt->execute()) { $success = 'Announcement published!'; }
            else { $error = 'Error creating announcement.'; }
        }
    }

    if ($action === 'edit') {
        $ann_id = intval($_POST['announcement_id'] ?? 0);
        $title = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $priority = sanitize($_POST['priority'] ?? 'Normal');
        $expires = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        if ($ann_id > 0 && !empty($title) && !empty($content)) {
            $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, priority = ?, expires_at = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $title, $content, $priority, $expires, $ann_id);
            if ($stmt->execute()) { $success = 'Announcement updated!'; }
            else { $error = 'Error updating announcement.'; }
        }
    }

    if ($action === 'delete') {
        $ann_id = intval($_POST['announcement_id'] ?? 0);
        if ($ann_id > 0) {
            $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
            $stmt->bind_param("i", $ann_id);
            if ($stmt->execute()) { $success = 'Announcement deleted!'; }
            else { $error = 'Error deleting announcement.'; }
        }
    }
}

// Fetch all announcements
$result = $conn->query("SELECT a.*, u.name as admin_name,
                        (SELECT COUNT(*) FROM announcement_reads ar WHERE ar.announcement_id = a.id) as read_count
                        FROM announcements a
                        JOIN users u ON a.admin_id = u.id
                        ORDER BY a.created_at DESC");
$announcements = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$total_students = 0;
$sr = $conn->query("SELECT COUNT(*) as c FROM users WHERE role = 'student'");
if ($sr) $total_students = $sr->fetch_assoc()['c'];

function priorityBadge($p) {
    $map = ['Low' => 'secondary', 'Normal' => 'info', 'Important' => 'warning', 'Urgent' => 'danger'];
    return $map[$p] ?? 'secondary';
}
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-bullhorn"></i> Announcements</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                <i class="fas fa-plus"></i> New Announcement
            </button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card stat-card" style="border-left-color: var(--primary);">
                    <div class="stat-number"><?php echo count($announcements); ?></div>
                    <div class="stat-label">Total Announcements</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card" style="border-left-color: var(--info);">
                    <div class="stat-number"><?php echo count(array_filter($announcements, fn($a) => $a['expires_at'] === null || $a['expires_at'] >= date('Y-m-d'))); ?></div>
                    <div class="stat-label">Active</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card" style="border-left-color: var(--success);">
                    <div class="stat-number"><?php echo $total_students; ?></div>
                    <div class="stat-label">Student Audience</div>
                </div>
            </div>
        </div>

        <!-- Announcements List -->
        <?php if (count($announcements) > 0): ?>
            <?php foreach ($announcements as $ann):
                $is_expired = $ann['expires_at'] && $ann['expires_at'] < date('Y-m-d');
                $read_pct = $total_students > 0 ? round(($ann['read_count'] / $total_students) * 100) : 0;
            ?>
            <div class="card mb-3 <?php echo $is_expired ? 'opacity-75' : ''; ?>">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div style="flex: 1; min-width: 0;">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h6 class="fw-bold mb-0"><?php echo htmlspecialchars($ann['title']); ?></h6>
                                <span class="badge bg-<?php echo priorityBadge($ann['priority']); ?>"><?php echo $ann['priority']; ?></span>
                                <?php if ($is_expired): ?>
                                    <span class="badge bg-dark">Expired</span>
                                <?php endif; ?>
                            </div>
                            <p class="text-muted mb-2" style="font-size: 13px;"><?php echo nl2br(htmlspecialchars($ann['content'])); ?></p>
                            <div class="d-flex flex-wrap gap-3" style="font-size: 12px; color: var(--text-muted);">
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($ann['admin_name']); ?></span>
                                <span><i class="fas fa-calendar"></i> <?php echo formatDateTime($ann['created_at']); ?></span>
                                <?php if ($ann['expires_at']): ?>
                                    <span><i class="fas fa-hourglass-end"></i> Expires: <?php echo formatDate($ann['expires_at']); ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-eye"></i> Read by <?php echo $ann['read_count']; ?>/<?php echo $total_students; ?> students (<?php echo $read_pct; ?>%)</span>
                            </div>
                        </div>
                        <div class="d-flex gap-1 ms-3">
                            <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editAnnouncementModal"
                                onclick="fillEditAnn(<?php echo htmlspecialchars(json_encode($ann), ENT_QUOTES); ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Delete Announcement', 'This announcement will be permanently deleted. This cannot be undone.', 'danger');">
                                <?php echo getCSRFField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="card">
                <div class="empty-state">
                    <i class="fas fa-bullhorn"></i>
                    <h5>No Announcements</h5>
                    <p>Create your first announcement to notify all students.</p>
                </div>
            </div>
        <?php endif; ?>

<!-- Add Announcement Modal -->
<div class="modal fade" id="addAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-bullhorn"></i> New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo getCSRFField(); ?>
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="annTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="annTitle" name="title" placeholder="Announcement title..." required>
                    </div>
                    <div class="mb-3">
                        <label for="annContent" class="form-label">Content</label>
                        <textarea class="form-control" id="annContent" name="content" rows="4" placeholder="Write your announcement..." required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="annPriority" class="form-label">Priority</label>
                            <select class="form-select" id="annPriority" name="priority">
                                <option value="Low">Low</option>
                                <option value="Normal" selected>Normal</option>
                                <option value="Important">Important</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="annExpires" class="form-label">Expires On <small class="text-muted">(optional)</small></label>
                            <input type="date" class="form-control" id="annExpires" name="expires_at">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Publish</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Announcement Modal -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-edit"></i> Edit Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <?php echo getCSRFField(); ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="announcement_id" id="editAnnId">
                    <div class="mb-3">
                        <label for="editAnnTitle" class="form-label">Title</label>
                        <input type="text" class="form-control" id="editAnnTitle" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="editAnnContent" class="form-label">Content</label>
                        <textarea class="form-control" id="editAnnContent" name="content" rows="4" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="editAnnPriority" class="form-label">Priority</label>
                            <select class="form-select" id="editAnnPriority" name="priority">
                                <option value="Low">Low</option>
                                <option value="Normal">Normal</option>
                                <option value="Important">Important</option>
                                <option value="Urgent">Urgent</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="editAnnExpires" class="form-label">Expires On</label>
                            <input type="date" class="form-control" id="editAnnExpires" name="expires_at">
                        </div>
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
function fillEditAnn(ann) {
    document.getElementById('editAnnId').value = ann.id;
    document.getElementById('editAnnTitle').value = ann.title;
    document.getElementById('editAnnContent').value = ann.content;
    document.getElementById('editAnnPriority').value = ann.priority;
    document.getElementById('editAnnExpires').value = ann.expires_at || '';
}
</script>

<?php include '../includes/footer.php'; ?>
