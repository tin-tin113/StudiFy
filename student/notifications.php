<?php
/**
 * STUDIFY – Notification Center
 * Full-page view of all notifications with filters and preferences
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

requireLogin();
if (isAdminRole()) {
    header("Location: " . BASE_URL . "admin/admin_dashboard.php");
    exit();
}

$page_title = 'Notifications';
$user_id = getCurrentUserId();

$filter = $_GET['filter'] ?? 'all';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

$result = getAllNotifications($user_id, $conn, $page, $per_page, $filter);
$notifications = $result['data'];
$total = $result['total'];
$total_pages = max(1, ceil($total / $per_page));

$unread_count = getUnreadNotificationCount($user_id, $conn);
$prefs = getNotificationPreferences($user_id, $conn);

// Group notifications by date
$grouped = [];
foreach ($notifications as $notif) {
    $date = date('Y-m-d', strtotime($notif['created_at']));
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($date === $today) {
        $group = 'Today';
    } elseif ($date === $yesterday) {
        $group = 'Yesterday';
    } else {
        $group = date('M d, Y', strtotime($date));
    }

    $grouped[$group][] = $notif;
}
?>
<?php include '../includes/header.php'; ?>

        <div class="page-header">
            <h2><i class="fas fa-bell"></i> Notifications</h2>
            <div class="d-flex align-items-center gap-2">
                <?php if ($unread_count > 0): ?>
                <button class="btn btn-sm btn-primary" onclick="markAllRead()">
                    <i class="fas fa-check-double"></i> Mark All Read
                </button>
                <?php endif; ?>
                <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#prefsModal">
                    <i class="fas fa-cog"></i> Preferences
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type'] ?? 'info'; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($_SESSION['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <!-- Filter Tabs -->
        <div class="card mb-4">
            <div class="card-body" style="padding: 12px 20px;">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <?php
                    $filters = [
                        'all' => ['All', 'fas fa-inbox'],
                        'unread' => ['Unread', 'fas fa-circle'],
                        'deadlines' => ['Deadlines', 'fas fa-clock'],
                        'overdue' => ['Overdue', 'fas fa-exclamation-triangle'],
                        'study' => ['Study', 'fas fa-fire']
                    ];
                    foreach ($filters as $key => $val):
                    ?>
                    <a href="?filter=<?php echo $key; ?>" 
                       class="btn btn-sm <?php echo $filter === $key ? 'btn-primary' : 'btn-outline-secondary'; ?>">
                        <i class="<?php echo $val[1]; ?>"></i> <?php echo $val[0]; ?>
                        <?php if ($key === 'unread' && $unread_count > 0): ?>
                            <span class="badge bg-danger ms-1" style="font-size: 10px;"><?php echo $unread_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                    <span class="text-muted ms-auto" style="font-size: 12px;"><?php echo $total; ?> notification<?php echo $total !== 1 ? 's' : ''; ?></span>
                </div>
            </div>
        </div>

        <!-- Notifications List -->
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($grouped as $group_label => $group_items): ?>
            <div class="mb-3">
                <div class="fw-600 text-muted mb-2" style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; padding-left: 4px;">
                    <?php echo htmlspecialchars($group_label); ?>
                </div>
                <div class="d-flex flex-column gap-2">
                    <?php foreach ($group_items as $notif):
                        $icon = getNotificationIcon($notif['type']);
                        $is_unread = !$notif['is_read'];
                        $task_url = ($notif['reference_type'] === 'task' && $notif['reference_id']) 
                            ? 'tasks.php' : '#';
                    ?>
                    <div class="card notification-item <?php echo $is_unread ? 'notification-unread' : ''; ?>" 
                         id="notif-<?php echo $notif['id']; ?>"
                         data-id="<?php echo $notif['id']; ?>">
                        <div class="card-body py-3 px-4">
                            <div class="d-flex align-items-start gap-3">
                                <div class="notification-icon-wrap" style="color: <?php echo $icon[1]; ?>;">
                                    <i class="<?php echo $icon[0]; ?>"></i>
                                </div>
                                <div style="flex: 1; min-width: 0;">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-600" style="font-size: 13.5px;">
                                                <?php if ($is_unread): ?><span class="notification-dot"></span><?php endif; ?>
                                                <?php echo htmlspecialchars($notif['title']); ?>
                                            </div>
                                            <p class="text-muted mb-1" style="font-size: 12.5px;">
                                                <?php echo htmlspecialchars($notif['message']); ?>
                                            </p>
                                            <span class="text-muted" style="font-size: 11px;">
                                                <i class="fas fa-clock"></i> <?php echo notificationTimeAgo($notif['created_at']); ?>
                                            </span>
                                        </div>
                                        <div class="d-flex gap-1 flex-shrink-0 ms-2">
                                            <?php if ($notif['reference_type'] === 'task' && $notif['reference_id']): ?>
                                            <a href="tasks.php" class="btn btn-sm btn-outline-primary" title="View Task" 
                                               onclick="markNotifRead(<?php echo $notif['id']; ?>)">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($is_unread): ?>
                                            <button class="btn btn-sm btn-outline-secondary" title="Mark as read"
                                                    onclick="markNotifRead(<?php echo $notif['id']; ?>)">
                                                <i class="fas fa-check"></i>
                                            </button>
                                            <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-danger" title="Dismiss"
                                                    onclick="dismissNotif(<?php echo $notif['id']; ?>)">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Notifications pagination" class="mt-3">
                <ul class="pagination pagination-sm justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page - 1; ?>">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    </li>
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?filter=<?php echo $filter; ?>&page=<?php echo $page + 1; ?>">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>

        <?php else: ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-bell-slash" style="font-size: 48px; opacity: 0.15; color: var(--text-muted);"></i>
                    <p class="mt-3 text-muted mb-0" style="font-size: 14px;">
                        <?php if ($filter !== 'all'): ?>
                            No <?php echo $filter; ?> notifications found.
                            <br><a href="?filter=all" class="text-primary">View all notifications</a>
                        <?php else: ?>
                            You're all caught up! No notifications yet.
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Preferences Modal -->
        <div class="modal fade" id="prefsModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-cog"></i> Notification Preferences</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-3" style="font-size: 13px;">Choose which notifications you'd like to receive.</p>

                        <div class="d-flex flex-column gap-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-600" style="font-size: 13px;"><i class="fas fa-clock text-warning me-2"></i>Due in 24 Hours</div>
                                    <div class="text-muted" style="font-size: 11px;">Alert when tasks are due within 24 hours</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input pref-toggle" type="checkbox" id="pref_deadline_24h" 
                                           data-pref="deadline_24h" <?php echo $prefs['deadline_24h'] ? 'checked' : ''; ?>>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-600" style="font-size: 13px;"><i class="fas fa-exclamation-circle text-danger me-2"></i>Due in 1 Hour</div>
                                    <div class="text-muted" style="font-size: 11px;">Urgent alert when tasks are due within 1 hour</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input pref-toggle" type="checkbox" id="pref_deadline_1h" 
                                           data-pref="deadline_1h" <?php echo $prefs['deadline_1h'] ? 'checked' : ''; ?>>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-600" style="font-size: 13px;"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Overdue Alerts</div>
                                    <div class="text-muted" style="font-size: 11px;">Notify when tasks pass their deadline</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input pref-toggle" type="checkbox" id="pref_overdue" 
                                           data-pref="overdue_alerts" <?php echo $prefs['overdue_alerts'] ? 'checked' : ''; ?>>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-600" style="font-size: 13px;"><i class="fas fa-brain text-primary me-2"></i>Study Reminders</div>
                                    <div class="text-muted" style="font-size: 11px;">Reminders to study throughout the day</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input pref-toggle" type="checkbox" id="pref_study" 
                                           data-pref="study_reminders" <?php echo $prefs['study_reminders'] ? 'checked' : ''; ?>>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-600" style="font-size: 13px;"><i class="fas fa-fire" style="color: var(--accent, #d97706);" class="me-2"></i> Streak Alerts</div>
                                    <div class="text-muted" style="font-size: 11px;">Warn when your study streak is at risk</div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input pref-toggle" type="checkbox" id="pref_streak" 
                                           data-pref="streak_alerts" <?php echo $prefs['streak_alerts'] ? 'checked' : ''; ?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-primary" onclick="savePreferences()">
                            <i class="fas fa-save"></i> Save Preferences
                        </button>
                    </div>
                </div>
            </div>
        </div>

<script>
var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
var apiUrl = 'notification_api.php';

function markNotifRead(id) {
    fetch(apiUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mark_read&notification_id=' + id + '&csrf_token=' + csrfToken
    }).then(r => r.json()).then(data => {
        if (data.success) {
            var el = document.getElementById('notif-' + id);
            if (el) {
                el.classList.remove('notification-unread');
                var dot = el.querySelector('.notification-dot');
                if (dot) dot.remove();
                var readBtn = el.querySelector('[title="Mark as read"]');
                if (readBtn) readBtn.remove();
            }
        }
    });
}

function dismissNotif(id) {
    var el = document.getElementById('notif-' + id);
    if (el) {
        el.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateX(20px)';
        setTimeout(function() { el.remove(); }, 300);
    }
    fetch(apiUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=dismiss&notification_id=' + id + '&csrf_token=' + csrfToken
    });
}

function markAllRead() {
    fetch(apiUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=mark_all_read&csrf_token=' + csrfToken
    }).then(r => r.json()).then(data => {
        if (data.success) {
            document.querySelectorAll('.notification-unread').forEach(function(el) {
                el.classList.remove('notification-unread');
            });
            document.querySelectorAll('.notification-dot').forEach(function(el) {
                el.remove();
            });
            document.querySelectorAll('[title="Mark as read"]').forEach(function(el) {
                el.remove();
            });
        }
    });
}

function savePreferences() {
    var body = 'action=save_preferences&csrf_token=' + csrfToken;
    document.querySelectorAll('.pref-toggle').forEach(function(toggle) {
        body += '&' + toggle.dataset.pref + '=' + (toggle.checked ? 1 : 0);
    });

    fetch(apiUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: body
    }).then(r => r.json()).then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('prefsModal'))?.hide();
            // Show toast-like feedback
            var toast = document.createElement('div');
            toast.className = 'alert alert-success position-fixed';
            toast.style.cssText = 'top: 80px; right: 20px; z-index: 9999; font-size: 13px; padding: 10px 16px; box-shadow: var(--shadow-sm); animation: slideIn 0.3s ease;';
            toast.innerHTML = '<i class="fas fa-check-circle"></i> Preferences saved!';
            document.body.appendChild(toast);
            setTimeout(function() { toast.remove(); }, 2500);
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
