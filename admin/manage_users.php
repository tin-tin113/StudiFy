<?php
/**
 * STUDIFY – Manage Users (Admin)
 * Full user management: view, search, delete users
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

$page_title = 'Manage Users';
$user_id = getCurrentUserId();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    requireCSRF();

if ($_POST['action'] === 'delete_user') {
    $delete_id = intval($_POST['user_id'] ?? 0);
    if ($delete_id > 0 && $delete_id !== $user_id) {
        // Prevent deleting the last admin
        $admin_check = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
        $admin_check->execute();
        $target_role = $conn->prepare("SELECT role FROM users WHERE id = ?");
        $target_role->bind_param("i", $delete_id);
        $target_role->execute();
        $target_user = $target_role->get_result()->fetch_assoc();
        if ($target_user && $target_user['role'] === 'admin' && $admin_check->get_result()->fetch_assoc()['cnt'] <= 1) {
            $_SESSION['message'] = 'Cannot delete the last admin account.';
            $_SESSION['message_type'] = 'error';
        } else {
            // Clean up avatar file from disk before DB delete
            $avatar_q = $conn->prepare("SELECT profile_photo FROM users WHERE id = ?");
            $avatar_q->bind_param("i", $delete_id);
            $avatar_q->execute();
            $avatar_row = $avatar_q->get_result()->fetch_assoc();
            if ($avatar_row && !empty($avatar_row['profile_photo'])) {
                $avatar_path = __DIR__ . '/../' . $avatar_row['profile_photo'];
                if (file_exists($avatar_path)) {
                    unlink($avatar_path);
                }
            }

            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->bind_param("i", $delete_id);
            if ($stmt->execute()) {
                $_SESSION['message'] = 'User deleted successfully!';
                $_SESSION['message_type'] = 'success';
            } else {
                $_SESSION['message'] = 'Error deleting user.';
                $_SESSION['message_type'] = 'error';
            }
        }
        header("Location: manage_users.php");
        exit();
    }
} elseif ($_POST['action'] === 'change_role') {
    $target_id = intval($_POST['user_id'] ?? 0);
    $new_role = ($_POST['new_role'] === 'admin') ? 'admin' : 'student';
    if ($target_id > 0 && $target_id !== $user_id) {
        // Prevent demoting the last admin
        if ($new_role === 'student') {
            $admin_count = $conn->prepare("SELECT COUNT(*) as cnt FROM users WHERE role = 'admin'");
            $admin_count->execute();
            if ($admin_count->get_result()->fetch_assoc()['cnt'] <= 1) {
                $_SESSION['message'] = 'Cannot demote the last admin account.';
                $_SESSION['message_type'] = 'error';
                header("Location: manage_users.php");
                exit();
            }
        }
        $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->bind_param("si", $new_role, $target_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = 'User role updated successfully!';
            $_SESSION['message_type'] = 'success';
        } else {
            $_SESSION['message'] = 'Error updating role.';
            $_SESSION['message_type'] = 'error';
        }
        header("Location: manage_users.php");
        exit();
    }
}
} // end POST actions

$users = getAllUsers($conn);

// Count by role
$total_count = count($users);
$student_count = count(array_filter($users, fn($u) => $u['role'] === 'student'));
$admin_count = count(array_filter($users, fn($u) => $u['role'] === 'admin'));

// Filter
$role_filter = $_GET['role'] ?? '';
if ($role_filter) {
    $users = array_filter($users, fn($u) => $u['role'] === $role_filter);
    $users = array_values($users);
}
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-users-cog"></i> Manage Users</h2>
        </div>

        <!-- Filter Tabs -->
        <div class="card mb-4">
            <div class="card-body" style="padding: 12px 20px;">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="manage_users.php" class="btn btn-sm <?php echo empty($role_filter) ? 'btn-primary' : 'btn-secondary'; ?>">
                        All <span class="badge bg-light text-dark ms-1"><?php echo $total_count; ?></span>
                    </a>
                    <a href="manage_users.php?role=student" class="btn btn-sm <?php echo $role_filter === 'student' ? 'btn-info' : 'btn-secondary'; ?>">
                        Students <span class="badge bg-light text-dark ms-1"><?php echo $student_count; ?></span>
                    </a>
                    <a href="manage_users.php?role=admin" class="btn btn-sm <?php echo $role_filter === 'admin' ? 'btn-danger' : 'btn-secondary'; ?>">
                        Admins <span class="badge bg-light text-dark ms-1"><?php echo $admin_count; ?></span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="card">
            <div class="card-body">
                <?php if (count($users) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Course</th>
                                <th>Year</th>
                                <th>Role</th>
                                <th>Joined</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $u): 
                                $initials = strtoupper(substr($u['name'], 0, 1));
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px;"><?php echo $initials; ?></div>
                                        <div>
                                            <div class="fw-semibold"><?php echo htmlspecialchars($u['name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($u['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo $u['role'] === 'admin' ? '<span class="text-muted fst-italic">N/A</span>' : htmlspecialchars($u['course'] ?? '—'); ?></td>
                                <td><?php echo $u['role'] === 'admin' ? '<span class="text-muted fst-italic">N/A</span>' : ($u['year_level'] ? $u['year_level'] . ' Year' : '—'); ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : 'info'; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?php echo formatDate($u['created_at']); ?></small></td>
                                <td class="text-end">
                                    <a href="user_details.php?id=<?php echo $u['id']; ?>" class="btn btn-sm btn-info" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <?php if ($u['id'] !== $user_id): ?>
                                    <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Change User Role', 'This will change the role for this user. Are you sure?', 'warning');">
                                        <?php echo getCSRFField(); ?>
                                        <input type="hidden" name="action" value="change_role">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <input type="hidden" name="new_role" value="<?php echo $u['role'] === 'admin' ? 'student' : 'admin'; ?>">
                                        <button type="submit" class="btn btn-sm btn-warning" title="<?php echo $u['role'] === 'admin' ? 'Demote to Student' : 'Promote to Admin'; ?>">
                                            <i class="fas fa-<?php echo $u['role'] === 'admin' ? 'user' : 'user-shield'; ?>"></i>
                                        </button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Delete User', 'This will permanently delete this user and ALL their data (semesters, subjects, tasks, notes). This cannot be undone.', 'danger');">
                                        <?php echo getCSRFField(); ?>
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="user_id" value="<?php echo $u['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete User">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h5>No Users Found</h5>
                    <p>Users will appear here once they register.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

<?php include '../includes/footer.php'; ?>
