<?php
/**
 * STUDIFY - Help Center
 * System overview and usage guide for students and admins.
 */
define('BASE_URL', '');
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

$page_title = 'Help Center';
$user_role = $_SESSION['role'] ?? 'student';
?>
<?php include __DIR__ . '/includes/header.php'; ?>

<style>
.help-hero {
    background: linear-gradient(135deg, #0f766e 0%, #16a34a 55%, #65a30d 100%);
    color: #fff;
    border-radius: var(--border-radius);
    padding: 24px;
    margin-bottom: 18px;
}
.help-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 10px;
    font-size: 12px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.18);
    margin-right: 8px;
}
.help-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
    gap: 14px;
}
.help-card {
    border-radius: var(--border-radius);
    border: 1px solid var(--border-color);
    background: var(--card-bg);
    padding: 16px;
}
.help-card h5 {
    margin-bottom: 10px;
}
.help-list {
    margin: 0;
    padding-left: 18px;
}
.help-list li {
    margin-bottom: 8px;
    font-size: 14px;
}
.quick-links a {
    text-decoration: none;
}
</style>

<div class="help-hero">
    <h2 class="mb-2"><i class="fas fa-life-ring"></i> Studify Help Center</h2>
    <p class="mb-3" style="opacity:.95;">
        Learn how to use Studify step-by-step. This page explains the main features, common workflows,
        and quick fixes if something does not work as expected.
    </p>
    <span class="help-chip"><i class="fas fa-user-graduate"></i> Role: <?php echo ucfirst(htmlspecialchars($user_role)); ?></span>
    <span class="help-chip"><i class="fas fa-lightbulb"></i> Beginner Friendly Guide</span>
</div>

<div class="help-grid mb-4 quick-links">
    <div class="help-card">
        <h5><i class="fas fa-compass text-primary"></i> Quick Start</h5>
        <ol class="help-list">
            <li>Open <a href="<?php echo BASE_URL; ?>student/semesters.php">Semesters</a> and create your active semester.</li>
            <li>Add subjects in <a href="<?php echo BASE_URL; ?>student/subjects.php">Subjects</a>.</li>
            <li>Create tasks in <a href="<?php echo BASE_URL; ?>student/tasks.php">Tasks</a>.</li>
            <li>Plan your day in <a href="<?php echo BASE_URL; ?>student/daily_planning.php">Daily Planning</a>.</li>
            <li>Track progress in <a href="<?php echo BASE_URL; ?>student/dashboard.php">Dashboard</a>.</li>
        </ol>
    </div>

    <div class="help-card">
        <h5><i class="fas fa-book text-success"></i> Main Features</h5>
        <ul class="help-list">
            <li><strong>Tasks:</strong> Add deadlines, priority, and completion status.</li>
            <li><strong>Calendar:</strong> Visualize upcoming deadlines by date.</li>
            <li><strong>Notes:</strong> Write notes per subject.</li>
            <li><strong>Pomodoro:</strong> Run focus sessions and track study time.</li>
            <li><strong>Analytics:</strong> View completion and study trends.</li>
        </ul>
    </div>

    <div class="help-card">
        <h5><i class="fas fa-users text-warning"></i> Collaboration</h5>
        <ul class="help-list">
            <li><strong>Study Buddy:</strong> Pair with another student, nudge, and chat.</li>
            <li><strong>Study Groups:</strong> Join/create groups and share group tasks.</li>
            <li><strong>Notifications:</strong> Track reminders, overdue tasks, and updates.</li>
        </ul>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h4 class="mb-3"><i class="fas fa-route text-primary"></i> Recommended Student Workflow</h4>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="help-card h-100">
                    <h6 class="fw-700">Daily Routine</h6>
                    <ul class="help-list">
                        <li>Check Dashboard and Notifications first.</li>
                        <li>Open Daily Planning and pick today tasks.</li>
                        <li>Use Pomodoro while studying.</li>
                        <li>Mark tasks completed after finishing.</li>
                        <li>Review Analytics at end of day.</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="help-card h-100">
                    <h6 class="fw-700">Weekly Routine</h6>
                    <ul class="help-list">
                        <li>Create or update tasks for the week.</li>
                        <li>Check Calendar for deadline conflicts.</li>
                        <li>Update notes after each class.</li>
                        <li>Use Study Buddy or Group for accountability.</li>
                        <li>Review achievements and streak progress.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($user_role === 'admin'): ?>
<div class="card mb-4">
    <div class="card-body">
        <h4 class="mb-3"><i class="fas fa-user-shield text-danger"></i> Admin Guide</h4>
        <ul class="help-list">
            <li>Use <a href="<?php echo BASE_URL; ?>admin/manage_users.php">Manage Users</a> to view and maintain student accounts.</li>
            <li>Post updates in <a href="<?php echo BASE_URL; ?>admin/announcements.php">Announcements</a>.</li>
            <li>Monitor platform usage in <a href="<?php echo BASE_URL; ?>admin/system_reports.php">Reports</a>.</li>
            <li>Review security and user events in <a href="<?php echo BASE_URL; ?>admin/activity_log.php">Activity Log</a>.</li>
            <li>Use <a href="<?php echo BASE_URL; ?>admin/system_settings.php">System Settings</a> for maintenance actions.</li>
        </ul>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <h4 class="mb-3"><i class="fas fa-wrench text-info"></i> Troubleshooting</h4>
        <div class="row g-3">
            <div class="col-md-6">
                <div class="help-card h-100">
                    <h6 class="fw-700">I cannot log in</h6>
                    <ul class="help-list">
                        <li>Check your email and password spelling.</li>
                        <li>Use Forgot Password in login page.</li>
                        <li>If account is locked, wait and try again.</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="help-card h-100">
                    <h6 class="fw-700">Data is missing</h6>
                    <ul class="help-list">
                        <li>Check if the correct semester is active.</li>
                        <li>Verify task filters (status/date/search).</li>
                        <li>Refresh page after saving updates.</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="help-card h-100">
                    <h6 class="fw-700">Notifications not showing</h6>
                    <ul class="help-list">
                        <li>Open Notifications page and check dismissed items.</li>
                        <li>Make sure tasks have valid deadlines.</li>
                        <li>Review notification preferences if available.</li>
                    </ul>
                </div>
            </div>
            <div class="col-md-6">
                <div class="help-card h-100">
                    <h6 class="fw-700">Need support</h6>
                    <ul class="help-list">
                        <li>Capture the exact error message.</li>
                        <li>Note which page/action caused the issue.</li>
                        <li>Send logs and screenshot to your developer/admin.</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
