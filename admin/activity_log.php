<?php
/**
 * STUDIFY – Activity Log (Admin)
 * View recent system activity across all users
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

$page_title = 'Activity Log';

// Recent tasks created (last 20)
$recent_tasks = [];
$result = $conn->query("SELECT t.*, s.name as subject_name, u.name as user_name, u.email as user_email
                         FROM tasks t 
                         JOIN subjects s ON t.subject_id = s.id 
                         JOIN semesters sem ON s.semester_id = sem.id 
                         JOIN users u ON sem.user_id = u.id 
                         ORDER BY t.created_at DESC LIMIT 20");
if ($result) {
    while ($row = $result->fetch_assoc()) $recent_tasks[] = $row;
}

// Recent study sessions (last 20)
$recent_sessions = [];
$result = $conn->query("SELECT ss.*, u.name as user_name, u.email as user_email
                         FROM study_sessions ss 
                         JOIN users u ON ss.user_id = u.id 
                         ORDER BY ss.created_at DESC LIMIT 20");
if ($result) {
    while ($row = $result->fetch_assoc()) $recent_sessions[] = $row;
}

// Recent user registrations (last 10)
$recent_users = [];
$result = $conn->query("SELECT * FROM users ORDER BY created_at DESC LIMIT 10");
if ($result) {
    while ($row = $result->fetch_assoc()) $recent_users[] = $row;
}

// Filter
$filter = $_GET['filter'] ?? 'all';
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-history"></i> Activity Log</h2>
        </div>

        <!-- Filter Tabs -->
        <div class="card mb-4">
            <div class="card-body" style="padding: 12px 20px;">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <a href="activity_log.php" class="btn btn-sm <?php echo $filter === 'all' ? 'btn-primary' : 'btn-secondary'; ?>">
                        <i class="fas fa-stream"></i> All Activity
                    </a>
                    <a href="activity_log.php?filter=tasks" class="btn btn-sm <?php echo $filter === 'tasks' ? 'btn-info' : 'btn-secondary'; ?>">
                        <i class="fas fa-tasks"></i> Tasks
                    </a>
                    <a href="activity_log.php?filter=study" class="btn btn-sm <?php echo $filter === 'study' ? 'btn-success' : 'btn-secondary'; ?>">
                        <i class="fas fa-clock"></i> Study Sessions
                    </a>
                    <a href="activity_log.php?filter=users" class="btn btn-sm <?php echo $filter === 'users' ? 'btn-warning' : 'btn-secondary'; ?>">
                        <i class="fas fa-user-plus"></i> Registrations
                    </a>
                </div>
            </div>
        </div>

        <?php if ($filter === 'all' || $filter === 'tasks'): ?>
        <!-- Recent Tasks Created -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-tasks"></i> Recent Tasks Created</span>
                <span class="badge bg-info"><?php echo count($recent_tasks); ?> shown</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (count($recent_tasks) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Task</th>
                                <th>Subject</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>Created</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_tasks as $t): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold" style="font-size: 13px;"><?php echo htmlspecialchars($t['user_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($t['user_email']); ?></small>
                                </td>
                                <td class="fw-medium" style="font-size: 13px;"><?php echo htmlspecialchars($t['title']); ?></td>
                                <td><small><?php echo htmlspecialchars($t['subject_name']); ?></small></td>
                                <td>
                                    <span class="badge bg-<?php echo getPriorityColor($t['priority']); ?>"><?php echo $t['priority']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo getStatusColor($t['status']); ?>"><?php echo $t['status']; ?></span>
                                </td>
                                <td><small class="text-muted"><?php echo formatDateTime($t['created_at']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-tasks"></i>
                    <h5>No Tasks Yet</h5>
                    <p>Tasks will appear here once students create them.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($filter === 'all' || $filter === 'study'): ?>
        <!-- Recent Study Sessions -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-clock"></i> Recent Study Sessions</span>
                <span class="badge bg-success"><?php echo count($recent_sessions); ?> shown</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (count($recent_sessions) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Duration</th>
                                <th>Type</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_sessions as $s): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold" style="font-size: 13px;"><?php echo htmlspecialchars($s['user_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($s['user_email']); ?></small>
                                </td>
                                <td><span class="fw-600"><?php echo $s['duration']; ?> min</span></td>
                                <td>
                                    <span class="badge bg-<?php echo $s['session_type'] === 'Focus' ? 'primary' : 'success'; ?>">
                                        <?php echo $s['session_type']; ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?php echo formatDateTime($s['created_at']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-clock"></i>
                    <h5>No Study Sessions</h5>
                    <p>Study sessions will appear here once students use the Pomodoro timer.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($filter === 'all' || $filter === 'users'): ?>
        <!-- Recent Registrations -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-user-plus"></i> Recent User Registrations</span>
                <span class="badge bg-warning"><?php echo count($recent_users); ?> shown</span>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (count($recent_users) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Course</th>
                                <th>Year</th>
                                <th>Role</th>
                                <th>Registered</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $u): 
                                $initials = strtoupper(substr($u['name'], 0, 1));
                            ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 12px;"><?php echo $initials; ?></div>
                                        <div>
                                            <div class="fw-semibold" style="font-size: 13px;"><?php echo htmlspecialchars($u['name']); ?></div>
                                            <small class="text-muted"><?php echo htmlspecialchars($u['email']); ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($u['course'] ?? '—'); ?></td>
                                <td><?php echo $u['year_level'] ? $u['year_level'] . ' Year' : '—'; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : 'info'; ?>">
                                        <?php echo ucfirst($u['role']); ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?php echo formatDateTime($u['created_at']); ?></small></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h5>No Users Yet</h5>
                    <p>Users will appear here once they register.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

<?php include '../includes/footer.php'; ?>
