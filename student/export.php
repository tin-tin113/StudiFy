<?php
/**
 * STUDIFY – Export / Print Report
 * Generates a print-friendly report page for tasks, grades, or semester summary
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdminRole()) { header("Location: " . BASE_URL . "admin/admin_dashboard.php"); exit(); }

$user_id = getCurrentUserId();
$user = getUserInfo($user_id, $conn);

if (!$user_id || !$user) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$type = $_GET['type'] ?? 'tasks'; // tasks, semester
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Studify – Export Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: 'Segoe UI', sans-serif; padding: 20px; background: #fff; color: #333; font-size: 13px; }
        .report-header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #16A34A; padding-bottom: 15px; }
        .report-header h1 { color: #16A34A; font-size: 24px; margin: 0; }
        .report-header p { color: #666; margin: 4px 0 0; font-size: 12px; }
        .report-meta { display: flex; justify-content: space-between; margin-bottom: 20px; font-size: 12px; color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f1f5f9; font-weight: 600; text-align: left; padding: 8px 10px; border: 1px solid #e2e8f0; font-size: 12px; }
        td { padding: 7px 10px; border: 1px solid #e2e8f0; font-size: 12px; }
        tr:nth-child(even) { background: #f8fafc; }
        .section-title { font-size: 16px; font-weight: 700; margin: 25px 0 10px; color: #16A34A; border-left: 4px solid #16A34A; padding-left: 10px; }
        .summary-box { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 15px; margin-bottom: 20px; display: flex; gap: 30px; flex-wrap: wrap; }
        .summary-item { text-align: center; }
        .summary-item .num { font-size: 22px; font-weight: 700; color: #16A34A; }
        .summary-item .lbl { font-size: 11px; color: #666; }
        .no-print { margin-bottom: 20px; }
        .badge-print { padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef9c3; color: #854d0e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .badge-info { background: #dbeafe; color: #1e40af; }
        @media print {
            .no-print { display: none !important; }
            body { padding: 0; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex gap-2">
            <a href="dashboard.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Back</a>
            <a href="export.php?type=tasks" class="btn btn-sm <?php echo $type === 'tasks' ? 'btn-primary' : 'btn-outline-primary'; ?>">Tasks Report</a>
            <a href="export.php?type=semester" class="btn btn-sm <?php echo $type === 'semester' ? 'btn-primary' : 'btn-outline-primary'; ?>">Semester Summary</a>
        </div>
        <button onclick="window.print();" class="btn btn-sm btn-success"><i class="fas fa-print"></i> Print / Save as PDF</button>
    </div>
    <hr>
</div>

<div class="report-header">
    <h1><i class="fas fa-book-open"></i> Studify</h1>
    <p>Student Task Management System</p>
</div>

<div class="report-meta">
    <div>
        <strong>Student:</strong> <?php echo htmlspecialchars($user['name']); ?> &nbsp;|&nbsp;
        <strong>Course:</strong> <?php echo htmlspecialchars($user['course'] ?? 'N/A'); ?> &nbsp;|&nbsp;
        <strong>Year:</strong> <?php echo $user['year_level'] ? $user['year_level'] . ' Year' : 'N/A'; ?>
    </div>
    <div><strong>Date:</strong> <?php echo date('F d, Y'); ?></div>
</div>

<?php if ($type === 'tasks'): ?>
    <?php
    $tasks = getUserTasks($user_id, $conn);
    $total = count($tasks);
    $done = count(array_filter($tasks, fn($t) => $t['status'] === 'Completed'));
    $pending = count(array_filter($tasks, fn($t) => $t['status'] === 'Pending'));
    $pct = $total > 0 ? round(($done / $total) * 100) : 0;
    ?>

    <div class="summary-box">
        <div class="summary-item"><div class="num"><?php echo $total; ?></div><div class="lbl">Total Tasks</div></div>
        <div class="summary-item"><div class="num"><?php echo $done; ?></div><div class="lbl">Completed</div></div>
        <div class="summary-item"><div class="num"><?php echo $pending; ?></div><div class="lbl">Pending</div></div>
        <div class="summary-item"><div class="num"><?php echo $pct; ?>%</div><div class="lbl">Completion</div></div>
    </div>

    <div class="section-title">All Tasks</div>
    <table>
        <thead>
            <tr><th>#</th><th>Title</th><th>Subject</th><th>Type</th><th>Priority</th><th>Status</th><th>Deadline</th></tr>
        </thead>
        <tbody>
            <?php foreach ($tasks as $i => $t): ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo htmlspecialchars($t['title']); ?></td>
                <td><?php echo htmlspecialchars($t['subject_name']); ?></td>
                <td><?php echo $t['type']; ?></td>
                <td><span class="badge-print badge-<?php echo $t['priority'] === 'High' ? 'danger' : ($t['priority'] === 'Medium' ? 'warning' : 'success'); ?>"><?php echo $t['priority']; ?></span></td>
                <td><span class="badge-print badge-<?php echo $t['status'] === 'Completed' ? 'success' : ($t['status'] === 'Pending' ? 'warning' : 'info'); ?>"><?php echo $t['status']; ?></span></td>
                <td><?php echo date('M d, Y h:i A', strtotime($t['deadline'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php elseif ($type === 'semester'): ?>
    <?php
    $active = getActiveSemester($user_id, $conn);
    if ($active):
        $subjects = getSemesterSubjects($active['id'], $conn);
        $total_tasks = 0; $done_tasks = 0;
        foreach ($subjects as &$sub) {
            $tasks = getSubjectTasks($sub['id'], $conn);
            $sub['task_count'] = count($tasks);
            $sub['done_count'] = count(array_filter($tasks, fn($t) => $t['status'] === 'Completed'));
            $total_tasks += $sub['task_count'];
            $done_tasks += $sub['done_count'];
        }
        unset($sub);
    ?>

    <div class="summary-box">
        <div class="summary-item"><div class="num"><?php echo htmlspecialchars($active['name']); ?></div><div class="lbl">Active Semester</div></div>
        <div class="summary-item"><div class="num"><?php echo count($subjects); ?></div><div class="lbl">Subjects</div></div>
        <div class="summary-item"><div class="num"><?php echo $total_tasks; ?></div><div class="lbl">Total Tasks</div></div>
        <div class="summary-item"><div class="num"><?php echo $total_tasks > 0 ? round(($done_tasks / $total_tasks) * 100) : 0; ?>%</div><div class="lbl">Completion</div></div>
    </div>

    <div class="section-title">Subjects Overview</div>
    <table>
        <thead><tr><th>#</th><th>Subject</th><th>Instructor</th><th>Tasks</th><th>Completed</th><th>Progress</th></tr></thead>
        <tbody>
            <?php foreach ($subjects as $i => $sub): 
                $sub_pct = $sub['task_count'] > 0 ? round(($sub['done_count'] / $sub['task_count']) * 100) : 0;
            ?>
            <tr>
                <td><?php echo $i + 1; ?></td>
                <td><?php echo htmlspecialchars($sub['name']); ?></td>
                <td><?php echo htmlspecialchars($sub['instructor_name'] ?? 'N/A'); ?></td>
                <td><?php echo $sub['task_count']; ?></td>
                <td><?php echo $sub['done_count']; ?></td>
                <td><?php echo $sub_pct; ?>%</td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php else: ?>
        <p class="text-muted">No active semester found.</p>
    <?php endif; ?>

<?php endif; ?>

<div style="text-align: center; margin-top: 40px; padding-top: 15px; border-top: 1px solid #e2e8f0; font-size: 11px; color: #999;">
    Generated by Studify &bull; <?php echo date('F d, Y h:i A'); ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
