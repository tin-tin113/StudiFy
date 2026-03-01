<?php
/**
 * STUDIFY – Study Analytics & Insights
 * Weekly/monthly charts, streaks, productivity insights from study_sessions
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdminRole()) { header("Location: " . BASE_URL . "admin/admin_dashboard.php"); exit(); }

$page_title = 'Study Analytics';
$user_id = getCurrentUserId();

// Total study stats
$total_q = $conn->prepare("SELECT COUNT(*) as sessions, COALESCE(SUM(duration),0) as minutes FROM study_sessions WHERE user_id = ? AND session_type = 'Focus'");
$total_q->bind_param("i", $user_id);
$total_q->execute();
$totals = $total_q->get_result()->fetch_assoc();

// This week
$week_q = $conn->prepare("SELECT COUNT(*) as sessions, COALESCE(SUM(duration),0) as minutes FROM study_sessions WHERE user_id = ? AND session_type = 'Focus' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$week_q->bind_param("i", $user_id);
$week_q->execute();
$week = $week_q->get_result()->fetch_assoc();

// This month
$month_q = $conn->prepare("SELECT COUNT(*) as sessions, COALESCE(SUM(duration),0) as minutes FROM study_sessions WHERE user_id = ? AND session_type = 'Focus' AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())");
$month_q->bind_param("i", $user_id);
$month_q->execute();
$month = $month_q->get_result()->fetch_assoc();

// Average session length
$avg_length = $totals['sessions'] > 0 ? round($totals['minutes'] / $totals['sessions'], 1) : 0;

// Most productive day of the week
$day_q = $conn->prepare("SELECT DAYNAME(created_at) as day_name, COALESCE(SUM(duration),0) as minutes FROM study_sessions WHERE user_id = ? AND session_type = 'Focus' GROUP BY DAYNAME(created_at), DAYOFWEEK(created_at) ORDER BY minutes DESC LIMIT 1");
$day_q->bind_param("i", $user_id);
$day_q->execute();
$best_day_row = $day_q->get_result()->fetch_assoc();
$best_day = $best_day_row['day_name'] ?? 'N/A';

// Study streak (consecutive days with ≥1 focus session)
$streak_q = $conn->prepare("SELECT DISTINCT DATE(created_at) as study_date FROM study_sessions WHERE user_id = ? AND session_type = 'Focus' ORDER BY study_date DESC");
$streak_q->bind_param("i", $user_id);
$streak_q->execute();
$dates = $streak_q->get_result()->fetch_all(MYSQLI_ASSOC);

$streak = 0;
$today = new DateTime();
$today->setTime(0, 0, 0);
foreach ($dates as $i => $d) {
    $study_date = new DateTime($d['study_date']);
    $study_date->setTime(0, 0, 0);
    $expected = clone $today;
    $expected->modify("-{$i} days");
    if ($study_date == $expected) {
        $streak++;
    } else {
        break;
    }
}

// Last 7 days hourly data for chart
$daily_q = $conn->prepare("SELECT DATE(created_at) as day, COALESCE(SUM(duration),0) as minutes FROM study_sessions WHERE user_id = ? AND session_type = 'Focus' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at) ORDER BY day ASC");
$daily_q->bind_param("i", $user_id);
$daily_q->execute();
$daily_raw = $daily_q->get_result()->fetch_all(MYSQLI_ASSOC);

$daily_labels = [];
$daily_data = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $daily_labels[] = date('M d', strtotime($d));
    $found = array_filter($daily_raw, fn($r) => $r['day'] === $d);
    $daily_data[] = !empty($found) ? round(array_values($found)[0]['minutes'] / 60, 2) : 0;
}

// Monthly trend (last 6 months)
$monthly_q = $conn->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COALESCE(SUM(duration),0) as minutes FROM study_sessions WHERE user_id = ? AND session_type = 'Focus' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY month ASC");
$monthly_q->bind_param("i", $user_id);
$monthly_q->execute();
$monthly_raw = $monthly_q->get_result()->fetch_all(MYSQLI_ASSOC);

$monthly_labels = [];
$monthly_data = [];
for ($i = 5; $i >= 0; $i--) {
    $m = date('Y-m', strtotime("-{$i} months"));
    $monthly_labels[] = date('M Y', strtotime($m . '-01'));
    $found = array_filter($monthly_raw, fn($r) => $r['month'] === $m);
    $monthly_data[] = !empty($found) ? round(array_values($found)[0]['minutes'] / 60, 1) : 0;
}

// Sessions by day of week for radar chart
$dow_q = $conn->prepare("SELECT DAYOFWEEK(created_at) as dow, COALESCE(SUM(duration),0) as minutes FROM study_sessions WHERE user_id = ? AND session_type = 'Focus' GROUP BY DAYOFWEEK(created_at) ORDER BY dow");
$dow_q->bind_param("i", $user_id);
$dow_q->execute();
$dow_raw = $dow_q->get_result()->fetch_all(MYSQLI_ASSOC);
$dow_names = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$dow_data = array_fill(0, 7, 0);
foreach ($dow_raw as $row) {
    $dow_data[$row['dow'] - 1] = round($row['minutes'] / 60, 1);
}
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-chart-area"></i> Study Analytics</h2>
        </div>

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3">
                <div class="card stat-card" style="border-left-color: var(--primary);">
                    <div class="stat-icon primary"><i class="fas fa-fire"></i></div>
                    <div class="stat-number"><?php echo $streak; ?></div>
                    <div class="stat-label">Day Streak 🔥</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card" style="border-left-color: var(--info);">
                    <div class="stat-icon info"><i class="fas fa-clock"></i></div>
                    <div class="stat-number"><?php echo round($totals['minutes'] / 60, 1); ?>h</div>
                    <div class="stat-label">Total Study Time</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card" style="border-left-color: var(--success);">
                    <div class="stat-icon success"><i class="fas fa-stopwatch"></i></div>
                    <div class="stat-number"><?php echo $avg_length; ?>m</div>
                    <div class="stat-label">Avg Session</div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card stat-card" style="border-left-color: var(--accent);">
                    <div class="stat-icon" style="background: var(--accent-light, #fef3c7); color: var(--accent, #d97706);"><i class="fas fa-star"></i></div>
                    <div class="stat-number"><?php echo $best_day; ?></div>
                    <div class="stat-label">Best Day</div>
                </div>
            </div>
        </div>

        <!-- Period summary -->
        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted" style="font-size: 12px;">This Week</div>
                                <div class="fw-bold" style="font-size: 20px;"><?php echo round($week['minutes'] / 60, 1); ?> hours</div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted" style="font-size: 12px;">Sessions</div>
                                <div class="fw-bold" style="font-size: 20px;"><?php echo $week['sessions']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="text-muted" style="font-size: 12px;">This Month</div>
                                <div class="fw-bold" style="font-size: 20px;"><?php echo round($month['minutes'] / 60, 1); ?> hours</div>
                            </div>
                            <div class="text-end">
                                <div class="text-muted" style="font-size: 12px;">Sessions</div>
                                <div class="fw-bold" style="font-size: 20px;"><?php echo $month['sessions']; ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts -->
        <div class="row g-4 mb-4">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-line"></i> Last 7 Days (Hours)</div>
                    <div class="card-body">
                        <canvas id="dailyChart" height="260"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header"><i class="fas fa-calendar-week"></i> Study by Day of Week</div>
                    <div class="card-body">
                        <canvas id="dowChart" height="260"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header"><i class="fas fa-chart-bar"></i> Monthly Trend (Hours)</div>
                    <div class="card-body">
                        <canvas id="monthlyChart" height="200"></canvas>
                    </div>
                </div>
            </div>
        </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chartFont = { family: 'Inter', size: 11, weight: '500' };

    // Daily chart
    new Chart(document.getElementById('dailyChart'), {
        type: 'line',
        data: {
            labels: <?php echo json_encode($daily_labels); ?>,
            datasets: [{
                label: 'Hours',
                data: <?php echo json_encode($daily_data); ?>,
                borderColor: '#16A34A',
                backgroundColor: 'rgba(22,163,74,0.1)',
                fill: true,
                tension: 0.4,
                pointRadius: 5,
                pointBackgroundColor: '#16A34A',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { font: chartFont }, grid: { color: 'rgba(0,0,0,0.04)' } },
                x: { ticks: { font: chartFont }, grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });

    // Day of week radar
    new Chart(document.getElementById('dowChart'), {
        type: 'radar',
        data: {
            labels: <?php echo json_encode($dow_names); ?>,
            datasets: [{
                label: 'Hours',
                data: <?php echo json_encode($dow_data); ?>,
                borderColor: '#2563EB',
                backgroundColor: 'rgba(37,99,235,0.15)',
                pointBackgroundColor: '#2563EB',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { r: { beginAtZero: true, ticks: { font: chartFont, display: false }, pointLabels: { font: chartFont } } },
            plugins: { legend: { display: false } }
        }
    });

    // Monthly chart
    new Chart(document.getElementById('monthlyChart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthly_labels); ?>,
            datasets: [{
                label: 'Hours',
                data: <?php echo json_encode($monthly_data); ?>,
                backgroundColor: '#16A34A',
                borderRadius: 6,
                borderSkipped: false,
                barThickness: 48
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, ticks: { font: chartFont }, grid: { color: 'rgba(0,0,0,0.04)' } },
                x: { ticks: { font: chartFont }, grid: { display: false } }
            },
            plugins: { legend: { display: false } }
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>
