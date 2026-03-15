<?php
/**
 * STUDIFY – Admin Dashboard
 * System overview and management panel
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

$page_title = 'Admin Dashboard';

// Statistics
$total_users = getTotalUsers($conn);
$total_tasks = getTotalSystemTasks($conn);

$total_semesters = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM semesters");
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) $total_semesters = $row['count'];

$total_subjects = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM subjects");
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) $total_subjects = $row['count'];

// Study sessions count
$total_study = 0;
$stmt = $conn->prepare("SELECT COALESCE(SUM(duration),0) as mins FROM study_sessions");
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) $total_study = round($row['mins'] / 60, 1);

// Completed tasks count
$completed_tasks = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM tasks WHERE status = 'Completed'");
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) $completed_tasks = $row['count'];

// Pending tasks count
$pending_tasks = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM tasks WHERE status != 'Completed'");
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) $pending_tasks = $row['count'];

// New users this month
$new_users_month = 0;
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')");
$stmt->execute();
if ($row = $stmt->get_result()->fetch_assoc()) $new_users_month = $row['count'];

// Recent 5 users (exclude password hash)
$recent_users = [];
$stmt = $conn->prepare("SELECT id, name, email, role, course, year_level, profile_photo, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $recent_users[] = $row;

// Top active users (most tasks) — uses direct user_id FK on tasks
$top_users = [];
$stmt = $conn->prepare("SELECT u.name, u.email, COUNT(t.id) as task_count, 
                         SUM(CASE WHEN t.status = 'Completed' THEN 1 ELSE 0 END) as completed
                         FROM users u 
                         LEFT JOIN tasks t ON u.id = t.user_id AND t.parent_id IS NULL
                         WHERE u.role = 'student'
                         GROUP BY u.id ORDER BY task_count DESC LIMIT 5");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) $top_users[] = $row;

$completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-user-shield"></i> Admin Dashboard</h2>
        </div>

        <!-- Welcome -->
        <div class="welcome-card mb-4">
            <div class="welcome-accent"></div>
            <h4 class="mb-1"><i class="fas fa-chart-pie"></i> System Overview</h4>
            <p class="text-muted mb-0">Monitor platform health, user activity, and overall system performance</p>
        </div>

        <!-- Stats Row 1 -->
        <div class="dashboard-grid">
            <div class="card stat-card" style="border-left-color: var(--primary);">
                <div class="stat-icon primary"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $total_users; ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="card stat-card" style="border-left-color: var(--info);">
                <div class="stat-icon info"><i class="fas fa-tasks"></i></div>
                <div class="stat-number"><?php echo $total_tasks; ?></div>
                <div class="stat-label">Total Tasks</div>
            </div>
            <div class="card stat-card" style="border-left-color: var(--success);">
                <div class="stat-icon success"><i class="fas fa-check-double"></i></div>
                <div class="stat-number"><?php echo $completion_rate; ?>%</div>
                <div class="stat-label">Completion Rate</div>
            </div>
            <div class="card stat-card" style="border-left-color: var(--accent);">
                <div class="stat-icon primary"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $total_study; ?>h</div>
                <div class="stat-label">Total Study Hours</div>
            </div>
        </div>

        <!-- Stats Row 2 -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card stat-card" style="border-left-color: var(--warning);">
                    <div class="stat-number"><?php echo $pending_tasks; ?></div>
                    <div class="stat-label">Pending Tasks</div>
                    <small class="text-muted"><i class="fas fa-hourglass-half"></i> System-wide</small>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card" style="border-left-color: var(--success);">
                    <div class="stat-number"><?php echo $total_semesters; ?></div>
                    <div class="stat-label">Semesters</div>
                    <small class="text-muted"><i class="fas fa-calendar"></i> Created</small>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card" style="border-left-color: var(--info);">
                    <div class="stat-number"><?php echo $total_subjects; ?></div>
                    <div class="stat-label">Subjects</div>
                    <small class="text-muted"><i class="fas fa-book"></i> Registered</small>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card" style="border-left-color: var(--primary);">
                    <div class="stat-number"><?php echo $new_users_month; ?></div>
                    <div class="stat-label">New This Month</div>
                    <small class="text-muted"><i class="fas fa-user-plus"></i> Users</small>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- System Health -->
            <div class="col-lg-8">
                <!-- Task Completion Overview -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-line"></i> System Task Completion
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="fw-500" style="font-size: 13px;">Overall Task Completion</span>
                            <span class="fw-600" style="color: var(--primary); font-size: 13px;"><?php echo $completion_rate; ?>%</span>
                        </div>
                        <div class="progress progress-lg mb-4">
                            <div class="progress-bar bg-success" style="width: <?php echo $completion_rate; ?>%"></div>
                        </div>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="fw-700" style="font-size: 20px; color: var(--warning);"><?php echo $pending_tasks; ?></div>
                                <small class="text-muted">Pending</small>
                            </div>
                            <div class="col-6">
                                <div class="fw-700" style="font-size: 20px; color: var(--success);"><?php echo $completed_tasks; ?></div>
                                <small class="text-muted">Completed</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Top Active Users -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="fas fa-trophy"></i> Most Active Students</span>
                        <a href="manage_users.php" class="btn btn-sm btn-primary"><i class="fas fa-users-cog"></i> Manage All</a>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <?php if (count($top_users) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th class="text-center">Tasks</th>
                                        <th class="text-center">Completed</th>
                                        <th class="text-center">Progress</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($top_users as $u): 
                                        $pct = $u['task_count'] > 0 ? round(($u['completed'] / $u['task_count']) * 100) : 0;
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
                                        <td class="text-center"><span class="badge bg-info"><?php echo $u['task_count']; ?></span></td>
                                        <td class="text-center"><span class="badge bg-success"><?php echo $u['completed']; ?></span></td>
                                        <td class="text-center">
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 6px;">
                                                    <div class="progress-bar bg-success" style="width: <?php echo $pct; ?>%"></div>
                                                </div>
                                                <small class="text-muted" style="min-width: 30px;"><?php echo $pct; ?>%</small>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <h5>No Student Activity</h5>
                            <p>Student activity will appear here once they start creating tasks.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="manage_users.php" class="btn btn-primary">
                                <i class="fas fa-users-cog"></i> Manage Users
                            </a>
                            <a href="announcements.php" class="btn btn-warning">
                                <i class="fas fa-bullhorn"></i> Announcements
                            </a>
                            <a href="system_reports.php" class="btn btn-info text-white">
                                <i class="fas fa-chart-bar"></i> View Reports
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Recently Joined Users -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-user-plus"></i> Recently Joined
                    </div>
                    <div class="card-body" style="padding: 8px;">
                        <?php if (count($recent_users) > 0): ?>
                            <?php foreach ($recent_users as $u): 
                                $initials = strtoupper(substr($u['name'], 0, 1));
                                $days_ago = floor((time() - strtotime($u['created_at'])) / 86400);
                                $time_text = $days_ago == 0 ? 'Today' : ($days_ago == 1 ? 'Yesterday' : $days_ago . ' days ago');
                            ?>
                            <div class="d-flex align-items-center gap-3 p-3" style="border-bottom: 1px solid var(--border-color);">
                                <div style="width: 36px; height: 36px; border-radius: 50%; background: var(--primary-50); color: var(--primary); display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 13px; flex-shrink: 0;">
                                    <?php echo $initials; ?>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div class="fw-600" style="font-size: 13px;"><?php echo htmlspecialchars($u['name']); ?></div>
                                    <div class="text-muted" style="font-size: 11px;">
                                        <?php echo htmlspecialchars($u['email']); ?> · <?php echo $time_text; ?>
                                    </div>
                                </div>
                                <span class="badge bg-<?php echo $u['role'] === 'admin' ? 'danger' : 'info'; ?>" style="font-size: 10px;">
                                    <?php echo ucfirst($u['role']); ?>
                                </span>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-muted text-center py-3" style="font-size: 13px;">No users yet</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

<?php include '../includes/footer.php'; ?>
