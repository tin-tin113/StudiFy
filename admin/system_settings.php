<?php
/**
 * STUDIFY – System Settings (Admin)
 * Platform-wide settings and maintenance tools
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

$page_title = 'System Settings';
$user_id = getCurrentUserId();
$success = '';
$error = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';

    if ($action === 'reset_password') {
        $target_id = intval($_POST['user_id'] ?? 0);
        $new_pass = $_POST['new_password'] ?? '';
        if ($target_id > 0 && strlen($new_pass) >= 6) {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param("si", $hashed, $target_id);
            if ($stmt->execute()) {
                $success = 'Password reset successfully!';
            } else {
                $error = 'Error resetting password.';
            }
        } else {
            $error = 'Invalid user or password must be at least 6 characters.';
        }
    }

    if ($action === 'cleanup_completed') {
        $days = intval($_POST['days'] ?? 90);
        if ($days < 30) { $days = 30; }
        $stmt = $conn->prepare("DELETE FROM tasks WHERE status = 'Completed' AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $days);
        if ($stmt->execute()) {
            $deleted = $stmt->affected_rows;
            $success = "Cleaned up $deleted completed task(s) older than $days days.";
        } else {
            $error = 'Error performing cleanup.';
        }
    }

    if ($action === 'cleanup_sessions') {
        $days = intval($_POST['days'] ?? 90);
        if ($days < 30) { $days = 30; }
        $stmt = $conn->prepare("DELETE FROM study_sessions WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $days);
        if ($stmt->execute()) {
            $deleted = $stmt->affected_rows;
            $success = "Cleaned up $deleted study session(s) older than $days days.";
        } else {
            $error = 'Error performing cleanup.';
        }
    }
}

// DB stats
$db_stats = [];
$tables = ['users', 'semesters', 'subjects', 'tasks', 'study_sessions'];
foreach ($tables as $tbl) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $tbl");
    $db_stats[$tbl] = $result ? $result->fetch_assoc()['count'] : 0;
}

// All users for password reset dropdown
$all_users = getAllUsers($conn);
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-cogs"></i> System Settings</h2>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Database Overview -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-database"></i> Database Overview
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-2" style="font-size: 13px;">
                            <?php 
                            $icons = ['users' => 'fa-users', 'semesters' => 'fa-calendar', 'subjects' => 'fa-book', 'tasks' => 'fa-tasks', 'study_sessions' => 'fa-stopwatch'];
                            $labels = ['users' => 'Users', 'semesters' => 'Semesters', 'subjects' => 'Subjects', 'tasks' => 'Tasks', 'study_sessions' => 'Study Sessions'];
                            foreach ($db_stats as $table => $count): ?>
                            <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid var(--border-color);">
                                <span class="text-muted"><i class="fas <?php echo $icons[$table]; ?> me-2"></i> <?php echo $labels[$table]; ?></span>
                                <span class="fw-600"><?php echo number_format($count); ?> records</span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Reset User Password -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-key"></i> Reset User Password
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo getCSRFField(); ?>
                            <input type="hidden" name="action" value="reset_password">
                            <div class="mb-3">
                                <label for="resetUser" class="form-label">Select User</label>
                                <select class="form-select" id="resetUser" name="user_id" required>
                                    <option value="">— Choose a user —</option>
                                    <?php foreach ($all_users as $u): ?>
                                        <?php if ($u['id'] !== $user_id): ?>
                                        <option value="<?php echo $u['id']; ?>">
                                            <?php echo htmlspecialchars($u['name']); ?> (<?php echo htmlspecialchars($u['email']); ?>)
                                        </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="newPassword" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="newPassword" name="new_password" placeholder="Min. 6 characters" minlength="6" required>
                            </div>
                            <button type="submit" class="btn btn-warning" onclick="return StudifyConfirm.buttonConfirm(event, 'Reset Password', 'This will reset the password for the selected user. Are you sure?', 'warning');">
                                <i class="fas fa-key"></i> Reset Password
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Data Cleanup -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-broom"></i> Data Cleanup – Completed Tasks
                    </div>
                    <div class="card-body">
                        <p class="text-muted" style="font-size: 13px;">Remove old completed tasks to keep the database clean. Only tasks marked as "Completed" will be deleted.</p>
                        <form method="POST" onsubmit="return StudifyConfirm.form(event, 'Clean Up Tasks', 'This will permanently delete all completed tasks older than the selected period. This cannot be undone.', 'danger');">
                            <?php echo getCSRFField(); ?>
                            <input type="hidden" name="action" value="cleanup_completed">
                            <div class="mb-3">
                                <label for="cleanupDays" class="form-label">Older than (days)</label>
                                <select class="form-select" id="cleanupDays" name="days">
                                    <option value="30">30 days</option>
                                    <option value="60">60 days</option>
                                    <option value="90" selected>90 days</option>
                                    <option value="180">180 days</option>
                                    <option value="365">1 year</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash-alt"></i> Clean Up Tasks
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Study Session Cleanup -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-broom"></i> Data Cleanup – Study Sessions
                    </div>
                    <div class="card-body">
                        <p class="text-muted" style="font-size: 13px;">Remove old study session records to free up database space.</p>
                        <form method="POST" onsubmit="return StudifyConfirm.form(event, 'Clean Up Sessions', 'This will permanently delete old study session records. This cannot be undone.', 'danger');">
                            <?php echo getCSRFField(); ?>
                            <input type="hidden" name="action" value="cleanup_sessions">
                            <div class="mb-3">
                                <label for="cleanupSessionDays" class="form-label">Older than (days)</label>
                                <select class="form-select" id="cleanupSessionDays" name="days">
                                    <option value="30">30 days</option>
                                    <option value="60">60 days</option>
                                    <option value="90" selected>90 days</option>
                                    <option value="180">180 days</option>
                                    <option value="365">1 year</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-danger">
                                <i class="fas fa-trash-alt"></i> Clean Up Sessions
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> System Information
                    </div>
                    <div class="card-body">
                        <div class="row g-3" style="font-size: 13px;">
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid var(--border-color);">
                                    <span class="text-muted">PHP Version</span>
                                    <span class="fw-600"><?php echo phpversion(); ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid var(--border-color);">
                                    <span class="text-muted">MySQL Version</span>
                                    <span class="fw-600"><?php echo $conn->server_info; ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid var(--border-color);">
                                    <span class="text-muted">Server Software</span>
                                    <span class="fw-600"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'N/A'; ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid var(--border-color);">
                                    <span class="text-muted">Database</span>
                                    <span class="fw-600">studify</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid var(--border-color);">
                                    <span class="text-muted">Server Time</span>
                                    <span class="fw-600"><?php echo date('M d, Y H:i'); ?></span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex justify-content-between py-2" style="border-bottom: 1px solid var(--border-color);">
                                    <span class="text-muted">Total Records</span>
                                    <span class="fw-600"><?php echo number_format(array_sum($db_stats)); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<?php include '../includes/footer.php'; ?>
