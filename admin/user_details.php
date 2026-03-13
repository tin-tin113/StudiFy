<?php
/**
 * STUDIFY – User Details (Admin)
 * View detailed information about a specific user
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

$page_title = 'User Details';
$view_user_id = intval($_GET['id'] ?? 0);

if ($view_user_id <= 0) {
    header("Location: admin_dashboard.php");
    exit();
}

$user = getUserInfo($view_user_id, $conn);
if (!$user) {
    header("Location: admin_dashboard.php");
    exit();
}

$semesters = getUserSemesters($view_user_id, $conn);
$user_tasks = getUserTasks($view_user_id, $conn);
$user_task_count = count($user_tasks);
$completed_count = getCompletedTasksCount($view_user_id, $conn);
$progress = $user_task_count > 0 ? round(($completed_count / $user_task_count) * 100) : 0;

// Study stats
$study_query = "SELECT COUNT(*) as sessions, COALESCE(SUM(duration),0) as mins 
                FROM study_sessions WHERE user_id = ?";
$stmt = $conn->prepare($study_query);
$stmt->bind_param("i", $view_user_id);
$stmt->execute();
$study = $stmt->get_result()->fetch_assoc();

$initials = strtoupper(substr($user['name'], 0, 1) . (strpos($user['name'], ' ') !== false ? substr($user['name'], strpos($user['name'], ' ') + 1, 1) : ''));
?>
<?php include '../includes/header.php'; ?>

        <!-- Back Button -->
        <a href="manage_users.php" class="btn btn-secondary mb-3">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>

        <div class="row g-4">
            <!-- User Profile Card -->
            <div class="col-lg-4">
                <div class="card text-center">
                    <div class="card-body py-4">
                        <div style="width: 80px; height: 80px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: 700; margin: 0 auto 16px;">
                            <?php echo $initials; ?>
                        </div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h5>
                        <p class="text-muted mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                        <span class="badge bg-<?php echo $user['role'] === 'admin' ? 'danger' : 'info'; ?> mb-3">
                            <?php echo ucfirst($user['role']); ?>
                        </span>

                        <hr>
                        <div class="text-start" style="font-size: 13px;">
                            <?php if ($user['role'] !== 'admin'): ?>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted"><i class="fas fa-graduation-cap"></i> Course</span>
                                <span class="fw-medium"><?php echo htmlspecialchars($user['course'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted"><i class="fas fa-layer-group"></i> Year Level</span>
                                <span class="fw-medium"><?php echo $user['year_level'] ? $user['year_level'] . ' Year' : 'N/A'; ?></span>
                            </div>
                            <?php else: ?>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted"><i class="fas fa-user-shield"></i> Role</span>
                                <span class="fw-medium">System Administrator</span>
                            </div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-between py-1">
                                <span class="text-muted"><i class="fas fa-calendar-plus"></i> Joined</span>
                                <span class="fw-medium"><?php echo formatDate($user['created_at']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Stats + Data -->
            <div class="col-lg-8">
                <!-- Stat Cards -->
                <div class="row g-3 mb-4">
                    <div class="col-6 col-md-3">
                        <div class="card stat-card" style="border-left-color: var(--primary);">
                            <div class="stat-number"><?php echo count($semesters); ?></div>
                            <div class="stat-label">Semesters</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card" style="border-left-color: var(--info);">
                            <div class="stat-number"><?php echo $user_task_count; ?></div>
                            <div class="stat-label">Tasks</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card" style="border-left-color: var(--success);">
                            <div class="stat-number"><?php echo $completed_count; ?></div>
                            <div class="stat-label">Completed</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <div class="card stat-card" style="border-left-color: var(--accent);">
                            <div class="stat-number"><?php echo $progress; ?>%</div>
                            <div class="stat-label">Progress</div>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar -->
                <div class="card mb-4">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between mb-1">
                            <small class="fw-medium">Task Completion</small>
                            <small class="text-muted"><?php echo $completed_count; ?>/<?php echo $user_task_count; ?></small>
                        </div>
                        <div class="progress" style="height: 8px; border-radius: 4px;">
                            <div class="progress-bar bg-success" style="width: <?php echo $progress; ?>%;"></div>
                        </div>
                    </div>
                </div>

                <!-- Study Stats -->
                <div class="card mb-4">
                    <div class="card-body py-3">
                        <div class="d-flex align-items-center gap-4">
                            <div>
                                <div class="text-muted" style="font-size: 12px;">Study Sessions</div>
                                <div class="fw-bold" style="font-size: 18px;"><?php echo $study['sessions'] ?? 0; ?></div>
                            </div>
                            <div style="width: 1px; height: 32px; background: var(--border-color);"></div>
                            <div>
                                <div class="text-muted" style="font-size: 12px;">Total Study Hours</div>
                                <div class="fw-bold" style="font-size: 18px;"><?php echo round(($study['mins'] ?? 0) / 60, 1); ?>h</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Semesters List -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-calendar-alt"></i> Semesters (<?php echo count($semesters); ?>)</h6>
                        <?php if (count($semesters) > 0): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($semesters as $sem): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <span class="fw-medium"><?php echo htmlspecialchars($sem['name']); ?></span>
                                        <?php if ($sem['is_active']): ?>
                                            <span class="badge bg-success ms-1">Active</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-muted"><?php echo formatDate($sem['created_at']); ?></small>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No semesters created</p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Tasks -->
                <div class="card">
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-tasks"></i> Recent Tasks (Latest 5)</h6>
                        <?php if (count($user_tasks) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Deadline</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($user_tasks, 0, 5) as $task): ?>
                                    <tr>
                                        <td class="fw-medium"><?php echo htmlspecialchars($task['title']); ?></td>
                                        <td><small><?php echo htmlspecialchars($task['subject_name']); ?></small></td>
                                        <td>
                                            <span class="task-status-badge status-<?php echo strtolower(str_replace(' ', '-', $task['status'])); ?>">
                                                <i class="fas fa-<?php echo $task['status'] === 'Completed' ? 'check-circle' : ($task['status'] === 'In Progress' ? 'spinner fa-pulse' : 'hourglass-half'); ?>"></i>
                                                <?php echo $task['status']; ?>
                                            </span>
                                        </td>
                                        <td><small class="text-muted"><?php echo formatDateTime($task['deadline']); ?></small></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <p class="text-muted mb-0">No tasks yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php include '../includes/footer.php'; ?>
