<?php
/**
 * STUDIFY – System Reports (Admin)
 * Detailed system analytics and reports
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
requireAdmin();

$page_title = 'System Reports';

// Overall stats
$total_users = getTotalUsers($conn);
$total_tasks = getTotalSystemTasks($conn);

$completed_tasks = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'Completed'");
if ($row = $result->fetch_assoc()) $completed_tasks = $row['count'];

$pending_tasks = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM tasks WHERE status = 'Pending'");
if ($row = $result->fetch_assoc()) $pending_tasks = $row['count'];

$in_progress_tasks = $total_tasks - $completed_tasks - $pending_tasks;
if ($in_progress_tasks < 0) $in_progress_tasks = 0;

// Tasks by priority
$priority_data = ['High' => 0, 'Medium' => 0, 'Low' => 0];
$result = $conn->query("SELECT priority, COUNT(*) as count FROM tasks GROUP BY priority");
while ($row = $result->fetch_assoc()) {
    $priority_data[$row['priority']] = $row['count'];
}

// Tasks by type
$type_data = [];
$result = $conn->query("SELECT type, COUNT(*) as count FROM tasks GROUP BY type ORDER BY count DESC");
while ($row = $result->fetch_assoc()) {
    $type_data[$row['type']] = $row['count'];
}

// Users registered per month (last 6 months)
$monthly_users = [];
$result = $conn->query("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count 
                         FROM users 
                         WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH) 
                         GROUP BY month ORDER BY month");
while ($row = $result->fetch_assoc()) {
    $monthly_users[] = $row;
}

// Study sessions data
$total_study_hours = 0;
$result = $conn->query("SELECT COALESCE(SUM(duration),0) as mins FROM study_sessions");
if ($row = $result->fetch_assoc()) $total_study_hours = round($row['mins'] / 60, 1);

$total_study_sessions = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM study_sessions");
if ($row = $result->fetch_assoc()) $total_study_sessions = $row['count'];

// Average tasks per user
$avg_tasks = $total_users > 0 ? round($total_tasks / $total_users, 1) : 0;

// Total subjects and semesters
$total_semesters = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM semesters");
if ($row = $result->fetch_assoc()) $total_semesters = $row['count'];

$total_subjects = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM subjects");
if ($row = $result->fetch_assoc()) $total_subjects = $row['count'];

$completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-chart-bar"></i> System Reports</h2>
        </div>

        <!-- Summary Cards -->
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
                <div class="stat-icon success"><i class="fas fa-percentage"></i></div>
                <div class="stat-number"><?php echo $completion_rate; ?>%</div>
                <div class="stat-label">Completion Rate</div>
            </div>
            <div class="card stat-card" style="border-left-color: var(--accent);">
                <div class="stat-icon primary"><i class="fas fa-clock"></i></div>
                <div class="stat-number"><?php echo $total_study_hours; ?>h</div>
                <div class="stat-label">Study Hours</div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Task Status Breakdown -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-chart-pie"></i> Task Status Breakdown
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="taskStatusChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tasks by Priority -->
            <div class="col-lg-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-flag"></i> Tasks by Priority
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="taskPriorityChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- User Registrations -->
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-user-plus"></i> User Registrations (Last 6 Months)
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="userRegistrationChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Stats -->
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <i class="fas fa-info-circle"></i> Detailed Statistics
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-3" style="font-size: 13px;">
                            <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid var(--border-color);">
                                <span class="text-muted"><i class="fas fa-calendar me-2"></i> Total Semesters</span>
                                <span class="fw-600"><?php echo $total_semesters; ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid var(--border-color);">
                                <span class="text-muted"><i class="fas fa-book me-2"></i> Total Subjects</span>
                                <span class="fw-600"><?php echo $total_subjects; ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid var(--border-color);">
                                <span class="text-muted"><i class="fas fa-chart-line me-2"></i> Avg Tasks/User</span>
                                <span class="fw-600"><?php echo $avg_tasks; ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid var(--border-color);">
                                <span class="text-muted"><i class="fas fa-stopwatch me-2"></i> Study Sessions</span>
                                <span class="fw-600"><?php echo $total_study_sessions; ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid var(--border-color);">
                                <span class="text-muted"><i class="fas fa-exclamation-triangle me-2"></i> High Priority</span>
                                <span class="fw-600 text-danger"><?php echo $priority_data['High']; ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2" style="border-bottom: 1px solid var(--border-color);">
                                <span class="text-muted"><i class="fas fa-check-circle me-2"></i> Completed Tasks</span>
                                <span class="fw-600 text-success"><?php echo $completed_tasks; ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2">
                                <span class="text-muted"><i class="fas fa-hourglass-half me-2"></i> Pending Tasks</span>
                                <span class="fw-600 text-warning"><?php echo $pending_tasks; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tasks by Type -->
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-th-list"></i> Tasks by Type
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <?php foreach ($type_data as $type => $count): 
                                $pct = $total_tasks > 0 ? round(($count / $total_tasks) * 100) : 0;
                                $color = '';
                                switch($type) {
                                    case 'Assignment': $color = 'primary'; break;
                                    case 'Quiz': $color = 'info'; break;
                                    case 'Exam': $color = 'danger'; break;
                                    case 'Project': $color = 'secondary'; break;
                                    case 'Report': $color = 'warning'; break;
                                    default: $color = 'secondary';
                                }
                            ?>
                            <div class="col-md-4 col-lg-2">
                                <div class="text-center p-3 rounded-md" style="background: var(--bg-card-hover); border: 1px solid var(--border-color);">
                                    <div class="fw-700" style="font-size: 22px; color: var(--<?php echo $color; ?>);"><?php echo $count; ?></div>
                                    <div class="text-muted" style="font-size: 12px;"><?php echo htmlspecialchars($type); ?></div>
                                    <div class="progress mt-2" style="height: 4px;">
                                        <div class="progress-bar bg-<?php echo $color; ?>" style="width: <?php echo $pct; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            <?php if (empty($type_data)): ?>
                            <div class="col-12">
                                <p class="text-muted text-center mb-0">No tasks created yet</p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Task Status Chart
    const statusCtx = document.getElementById('taskStatusChart');
    if (statusCtx) {
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'In Progress', 'Completed'],
                datasets: [{
                    data: [<?php echo $pending_tasks; ?>, <?php echo $in_progress_tasks; ?>, <?php echo $completed_tasks; ?>],
                    backgroundColor: ['#EAB308', '#2563EB', '#16A34A'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } }
                }
            }
        });
    }

    // Task Priority Chart
    const priorityCtx = document.getElementById('taskPriorityChart');
    if (priorityCtx) {
        new Chart(priorityCtx, {
            type: 'doughnut',
            data: {
                labels: ['High', 'Medium', 'Low'],
                datasets: [{
                    data: [<?php echo $priority_data['High']; ?>, <?php echo $priority_data['Medium']; ?>, <?php echo $priority_data['Low']; ?>],
                    backgroundColor: ['#DC2626', '#EAB308', '#16A34A'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 16, font: { size: 12 } } }
                }
            }
        });
    }

    // User Registration Chart
    const regCtx = document.getElementById('userRegistrationChart');
    if (regCtx) {
        new Chart(regCtx, {
            type: 'bar',
            data: {
                labels: [<?php echo implode(',', array_map(function($m) { return "'" . date('M Y', strtotime($m['month'] . '-01')) . "'"; }, $monthly_users)); ?>],
                datasets: [{
                    label: 'New Users',
                    data: [<?php echo implode(',', array_column($monthly_users, 'count')); ?>],
                    backgroundColor: 'rgba(22, 163, 74, 0.7)',
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, ticks: { stepSize: 1, font: { size: 11 } }, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { ticks: { font: { size: 11 } }, grid: { display: false } }
                }
            }
        });
    }
});
</script>

<?php include '../includes/footer.php'; ?>
