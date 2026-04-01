<?php
/**
 * STUDIFY – Header
 * Clean white sidebar + top bar layout with global search
 */
if (!isset($conn)) {
    require_once __DIR__ . '/../config/db.php';
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_name = $_SESSION['name'] ?? 'User';
$user_role = $_SESSION['role'] ?? 'student';
$user_initials = strtoupper(substr($user_name, 0, 1));

// Get profile photo
$user_photo = null;
if (isset($_SESSION['user_id']) && function_exists('getUserInfo')) {
    $header_user = getUserInfo($_SESSION['user_id'], $conn);
    $user_photo = $header_user['profile_photo'] ?? null;
}

$pending_count = 0;
$unread_ann_count = 0;
$unread_nudge_count = 0;
$unread_notif_count = 0;
$recent_notifications = [];
if (isset($_SESSION['user_id']) && function_exists('getPendingTasksCount') && $user_role !== 'admin') {
    $pending_count = getPendingTasksCount($_SESSION['user_id'], $conn);
    // Count unread announcements
    $ann_count_q = $conn->prepare("SELECT COUNT(*) as c FROM announcements a 
                    WHERE (a.expires_at IS NULL OR a.expires_at >= CURDATE())
                    AND a.id NOT IN (SELECT announcement_id FROM announcement_reads WHERE user_id = ?)");
    $ann_count_q->bind_param("i", $_SESSION['user_id']);
    $ann_count_q->execute();
    $unread_ann_count = $ann_count_q->get_result()->fetch_assoc()['c'];
    // Count unread buddy messages (chat + nudges)
    if (function_exists('getUnreadBuddyMessageCount')) {
        $unread_nudge_count = getUnreadBuddyMessageCount($_SESSION['user_id'], $conn);
    } elseif (function_exists('getUnreadNudgeCount')) {
        $unread_nudge_count = getUnreadNudgeCount($_SESSION['user_id'], $conn);
    }
    if (function_exists('getUnreadGroupMessageCount')) {
        $unread_group_count = getUnreadGroupMessageCount($_SESSION['user_id'], $conn);
    }
    // Run notification checker only on dashboard (not every page load)
    if (function_exists('getUnreadNotificationCount')) {
        $unread_notif_count = getUnreadNotificationCount($_SESSION['user_id'], $conn);
        $recent_notifications = getRecentNotifications($_SESSION['user_id'], $conn, 8);
    }
}

// Check onboarding
$show_onboarding = false;
if (isset($_SESSION['user_id']) && $user_role === 'student' && function_exists('needsOnboarding')) {
    $show_onboarding = needsOnboarding($_SESSION['user_id'], $conn);
}

$csrf_token = generateCSRFToken();

// Security headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com; font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com; img-src 'self' data:;");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="base-url" content="<?php echo BASE_URL; ?>">
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrf_token); ?>">
    <title><?php echo htmlspecialchars($page_title ?? 'Studify'); ?> – Studify</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/style.css?v=<?php echo filemtime(dirname(__DIR__) . '/assets/css/style.css'); ?>">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg viewBox='0 0 40 40' xmlns='http://www.w3.org/2000/svg'%3E%3Crect width='40' height='40' rx='10' fill='%2316A34A'/%3E%3Cpath d='M10 13.5c0-.6.4-1 1-1 1.5 0 4.2.5 9 2.5v13.5c-4.8-2-7.5-2.5-9-2.5-.6 0-1-.4-1-1V13.5z' fill='%23fff' opacity='.9'/%3E%3Cpath d='M30 13.5c0-.6-.4-1-1-1-1.5 0-4.2.5-9 2.5v13.5c4.8-2 7.5-2.5 9-2.5.6 0 1-.4 1-1V13.5z' fill='%23fff' opacity='.7'/%3E%3C/svg%3E">
    <link rel="manifest" href="<?php echo BASE_URL; ?>manifest.json">
    <meta name="theme-color" content="#16A34A">
</head>
<body<?php echo ($current_page === 'dashboard.php') ? ' class="dashboard-page"' : ''; ?>>

<!-- Sidebar Overlay (Mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">
            <svg viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                <rect width="40" height="40" rx="10" fill="#16A34A"/>
                <path d="M20 12c-2.5 0-5 .8-5 .8v14.4s2.5-.8 5-.8 5 .8 5 .8V12.8s-2.5-.8-5-.8z" fill="#fff" opacity=".15"/>
                <path d="M10 13.5c0-.6.4-1 1-1 1.5 0 4.2.5 9 2.5v13.5c-4.8-2-7.5-2.5-9-2.5-.6 0-1-.4-1-1V13.5z" fill="#fff" opacity=".9"/>
                <path d="M30 13.5c0-.6-.4-1-1-1-1.5 0-4.2.5-9 2.5v13.5c4.8-2 7.5-2.5 9-2.5.6 0 1-.4 1-1V13.5z" fill="#fff" opacity=".7"/>
                <line x1="20" y1="14.5" x2="20" y2="28.5" stroke="#16A34A" stroke-width=".6" opacity=".5"/>
            </svg>
        </div>
        <div class="brand-text">Studi<span>fy</span></div>
        <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" title="Toggle Sidebar">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-nav-label">Menu</div>

        <?php if ($user_role === 'admin'): ?>
            <a href="<?php echo BASE_URL; ?>admin/admin_dashboard.php" title="Dashboard"
               class="nav-link-sidebar <?php echo $current_page === 'admin_dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> <span class="sidebar-link-text">Dashboard</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/manage_users.php" title="Manage Users"
               class="nav-link-sidebar <?php echo $current_page === 'manage_users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i> <span class="sidebar-link-text">Manage Users</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/announcements.php" title="Announcements"
               class="nav-link-sidebar <?php echo $current_page === 'announcements.php' ? 'active' : ''; ?>">
                <i class="fas fa-bullhorn"></i> <span class="sidebar-link-text">Announcements</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/system_reports.php" title="Reports"
               class="nav-link-sidebar <?php echo $current_page === 'system_reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> <span class="sidebar-link-text">Reports</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/activity_log.php" title="Activity Log"
               class="nav-link-sidebar <?php echo $current_page === 'activity_log.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> <span class="sidebar-link-text">Activity Log</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/system_settings.php" title="Settings"
               class="nav-link-sidebar <?php echo $current_page === 'system_settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cogs"></i> <span class="sidebar-link-text">Settings</span>
            </a>
            <a href="<?php echo BASE_URL; ?>admin/user_details.php" title="User Details"
               class="nav-link-sidebar <?php echo $current_page === 'user_details.php' ? 'active' : ''; ?>" style="display:none;">
                <i class="fas fa-eye"></i> <span class="sidebar-link-text">User Details</span>
            </a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>student/dashboard.php" title="Dashboard"
               class="nav-link-sidebar <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> <span class="sidebar-link-text">Dashboard</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/semesters.php" title="Semesters"
               class="nav-link-sidebar <?php echo $current_page === 'semesters.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i> <span class="sidebar-link-text">Semesters</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/subjects.php" title="Subjects"
               class="nav-link-sidebar <?php echo $current_page === 'subjects.php' ? 'active' : ''; ?>">
                <i class="fas fa-book"></i> <span class="sidebar-link-text">Subjects</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/tasks.php" title="Tasks"
               class="nav-link-sidebar <?php echo $current_page === 'tasks.php' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i> <span class="sidebar-link-text">Tasks</span>
                <?php if ($pending_count > 0): ?>
                    <span class="badge bg-warning ms-auto" style="font-size: 10px;"><?php echo $pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo BASE_URL; ?>student/daily_planning.php" title="Daily Planning"
               class="nav-link-sidebar <?php echo $current_page === 'daily_planning.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-day"></i> <span class="sidebar-link-text">Daily Planning</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/calendar.php" title="Calendar"
               class="nav-link-sidebar <?php echo $current_page === 'calendar.php' ? 'active' : ''; ?>">
                <i class="fas fa-calendar"></i> <span class="sidebar-link-text">Calendar</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/notes.php" title="Notes"
               class="nav-link-sidebar <?php echo $current_page === 'notes.php' ? 'active' : ''; ?>">
                <i class="fas fa-sticky-note"></i> <span class="sidebar-link-text">Notes</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/pomodoro.php" title="Pomodoro"
               class="nav-link-sidebar <?php echo $current_page === 'pomodoro.php' ? 'active' : ''; ?>">
                <i class="fas fa-clock"></i> <span class="sidebar-link-text">Pomodoro</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/study_analytics.php" title="Analytics"
               class="nav-link-sidebar <?php echo $current_page === 'study_analytics.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-area"></i> <span class="sidebar-link-text">Analytics</span>
            </a>
            <a href="<?php echo BASE_URL; ?>student/notifications.php" title="Notifications"
               class="nav-link-sidebar <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i> <span class="sidebar-link-text">Notifications</span>
                <?php if ($unread_notif_count > 0): ?>
                    <span id="sidebar-notif-badge" class="badge bg-danger ms-auto" style="font-size: 10px;"><?php echo $unread_notif_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo BASE_URL; ?>student/study_buddy.php" title="Study Buddy"
               class="nav-link-sidebar <?php echo $current_page === 'study_buddy.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-friends"></i> <span class="sidebar-link-text">Study Buddy</span>
                <?php if ($unread_nudge_count > 0): ?>
                    <span id="sidebar-buddy-badge" class="badge bg-danger ms-auto" style="font-size: 10px;"><?php echo $unread_nudge_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo BASE_URL; ?>student/study_groups.php" title="Study Groups"
               class="nav-link-sidebar <?php echo $current_page === 'study_groups.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> <span class="sidebar-link-text">Study Groups</span>
                <?php if (!empty($unread_group_count) && $unread_group_count > 0): ?>
                    <span id="sidebar-group-badge" class="badge bg-danger ms-auto" style="font-size: 10px;"><?php echo $unread_group_count; ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <div class="sidebar-nav-label">Other</div>

        <a href="<?php echo BASE_URL; ?>help.php" title="Help"
           class="nav-link-sidebar <?php echo $current_page === 'help.php' ? 'active' : ''; ?>">
            <i class="fas fa-circle-question"></i> <span class="sidebar-link-text">Help</span>
        </a>

        <a href="<?php echo BASE_URL; ?>auth/profile.php" title="Profile"
           class="nav-link-sidebar <?php echo $current_page === 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i> <span class="sidebar-link-text">Profile</span>
        </a>
        <a href="<?php echo BASE_URL; ?>auth/logout.php" class="nav-link-sidebar" onclick="return StudifyConfirm.logout(event, this.href);">
            <i class="fas fa-sign-out-alt"></i> <span class="sidebar-link-text">Log out</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="<?php echo BASE_URL; ?>auth/profile.php" class="sidebar-user text-decoration-none">
            <?php if ($user_photo): ?>
                <img src="<?php echo BASE_URL . htmlspecialchars($user_photo); ?>" alt="Profile" class="sidebar-user-avatar" style="object-fit: cover;">
            <?php else: ?>
                <div class="sidebar-user-avatar"><?php echo $user_initials; ?></div>
            <?php endif; ?>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($user_name); ?></div>
                <div class="sidebar-user-role"><?php echo ucfirst($user_role); ?></div>
            </div>
        </a>
    </div>
</aside>

<!-- TOPBAR -->
<header class="topbar">
    <div class="topbar-left">
        <button class="topbar-toggle" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <h1 class="topbar-title"><?php echo htmlspecialchars($page_title ?? 'Dashboard'); ?></h1>
    </div>
    <div class="topbar-right">
        <!-- Global Search -->
        <?php if ($user_role !== 'admin'): ?>
        <div class="global-search-wrapper">
            <button class="topbar-btn" onclick="GlobalSearch.open()" title="Search (Ctrl+K)" id="searchToggle">
                <i class="fas fa-search"></i>
            </button>
        </div>
        <?php endif; ?>

        <button class="topbar-btn dark-mode-toggle" onclick="DarkMode.toggle()" title="Toggle theme">
            <i class="fas fa-moon"></i>
        </button>
        
        <?php $notif_total = $unread_notif_count + $unread_ann_count + $unread_nudge_count; ?>
        <div class="dropdown">
            <button class="topbar-btn" data-bs-toggle="dropdown" aria-expanded="false" title="<?php echo $notif_total > 0 ? $notif_total . ' notification' . ($notif_total > 1 ? 's' : '') : 'No notifications'; ?>">
                <i class="fas fa-bell"></i>
                <?php if ($notif_total > 0): ?><span class="badge-dot"></span><?php endif; ?>
            </button>
            <ul class="dropdown-menu dropdown-menu-end notification-dropdown" style="min-width: 340px; max-height: 420px; overflow-y: auto;">
                <li class="px-3 py-2 d-flex justify-content-between align-items-center">
                    <span style="font-size: 12px; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Notifications</span>
                    <?php if ($unread_notif_count > 0): ?>
                    <span class="badge bg-primary" style="font-size: 10px;"><?php echo $unread_notif_count; ?> new</span>
                    <?php endif; ?>
                </li>
                <li><hr class="dropdown-divider"></li>
                <?php if ($unread_ann_count > 0): ?>
                <li>
                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>student/dashboard.php">
                        <i class="fas fa-bullhorn text-info"></i> <?php echo $unread_ann_count; ?> new announcement<?php echo $unread_ann_count > 1 ? 's' : ''; ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php if ($unread_nudge_count > 0): ?>
                <li>
                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>student/study_buddy.php">
                        <i class="fas fa-hand-peace text-primary"></i> <?php echo $unread_nudge_count; ?> buddy message<?php echo $unread_nudge_count > 1 ? 's' : ''; ?>
                    </a>
                </li>
                <?php endif; ?>
                <?php if (count($recent_notifications) > 0): ?>
                    <?php if ($unread_ann_count > 0 || $unread_nudge_count > 0): ?><li><hr class="dropdown-divider"></li><?php endif; ?>
                    <?php foreach ($recent_notifications as $rn):
                        $rn_icon = getNotificationIcon($rn['type']);
                        $rn_unread = !$rn['is_read'];
                    ?>
                    <li>
                        <a class="dropdown-item d-flex align-items-start gap-2 <?php echo $rn_unread ? 'fw-600' : ''; ?>" 
                           href="<?php echo BASE_URL; ?>student/notifications.php"
                           style="white-space: normal; padding: 10px 16px; <?php echo $rn_unread ? 'background: var(--primary-50, rgba(22,163,74,0.04));' : ''; ?>">
                            <i class="<?php echo $rn_icon[0]; ?> mt-1" style="color: <?php echo $rn_icon[1]; ?>; font-size: 13px; flex-shrink: 0;"></i>
                            <div style="min-width: 0;">
                                <div style="font-size: 12.5px; line-height: 1.4;">
                                    <?php if ($rn_unread): ?><span class="notification-dot"></span><?php endif; ?>
                                    <?php echo htmlspecialchars($rn['title']); ?>
                                </div>
                                <div class="text-muted" style="font-size: 11px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    <?php echo htmlspecialchars(mb_substr($rn['message'], 0, 60)); ?><?php echo mb_strlen($rn['message']) > 60 ? '...' : ''; ?>
                                </div>
                                <div class="text-muted" style="font-size: 10px; margin-top: 2px;">
                                    <?php echo notificationTimeAgo($rn['created_at']); ?>
                                </div>
                            </div>
                        </a>
                    </li>
                    <?php endforeach; ?>
                <?php elseif ($notif_total === 0): ?>
                <li class="px-3 py-3 text-center">
                    <i class="fas fa-check-circle text-success" style="font-size: 1.5rem; opacity: 0.5;"></i>
                    <p class="mb-0 mt-1" style="font-size: 13px; color: var(--text-muted);">You're all caught up!</p>
                </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li class="text-center py-2">
                    <a href="<?php echo BASE_URL; ?>student/notifications.php" class="text-primary fw-600" style="font-size: 12px; text-decoration: none;">
                        View All Notifications <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </li>
            </ul>
        </div>

        <div class="dropdown">
            <div class="topbar-user" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if ($user_photo): ?>
                    <img src="<?php echo BASE_URL . htmlspecialchars($user_photo); ?>" alt="Profile" class="topbar-user-avatar" style="object-fit: cover;">
                <?php else: ?>
                    <div class="topbar-user-avatar"><?php echo $user_initials; ?></div>
                <?php endif; ?>
                <span class="topbar-user-name"><?php echo htmlspecialchars($user_name); ?></span>
                <i class="fas fa-chevron-down" style="font-size:10px; color: var(--text-muted);"></i>
            </div>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="<?php echo BASE_URL; ?>auth/profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>auth/logout.php" onclick="return StudifyConfirm.logout(event, this.href);">
                        <i class="fas fa-sign-out-alt"></i> Log out
                    </a>
                </li>
            </ul>
        </div>
    </div>
</header>

<!-- Global Search Modal -->
<?php if ($user_role !== 'admin'): ?>
<div class="search-modal-overlay" id="searchModal" style="display:none;">
    <div class="search-modal">
        <div class="search-modal-input-wrap">
            <i class="fas fa-search"></i>
            <input type="text" id="globalSearchInput" placeholder="Search tasks, notes, subjects..." autocomplete="off">
            <kbd onclick="GlobalSearch.close()" style="cursor:pointer;" title="Close (Esc)">ESC</kbd>
        </div>
        <div class="search-modal-results" id="searchResults">
            <div class="search-empty">
                <i class="fas fa-search" style="font-size: 24px; opacity: 0.3;"></i>
                <p style="margin-top: 8px; font-size: 13px; color: var(--text-muted);">Type to search across your tasks, notes, and subjects</p>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Confirmation Dialog -->
<div class="confirm-overlay" id="confirmOverlay" style="display:none;">
    <div class="confirm-dialog">
        <div class="confirm-icon" id="confirmIcon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h5 class="confirm-title" id="confirmTitle">Are you sure?</h5>
        <p class="confirm-message" id="confirmMessage">This action cannot be undone.</p>
        <div class="confirm-actions">
            <button class="btn btn-sm btn-secondary" id="confirmCancel">Cancel</button>
            <button class="btn btn-sm btn-danger" id="confirmOk">Confirm</button>
        </div>
    </div>
</div>

<!-- Floating Quick Add Button (Student Only) -->
<?php if (isset($_SESSION['user_id']) && $user_role === 'student'): ?>
<button class="btn btn-primary floating-quick-add" onclick="QuickAdd.open()" title="Quick Add Task (Ctrl+Q)">
    <i class="fas fa-plus"></i>
</button>
<?php endif; ?>

<!-- MAIN CONTENT -->
<main class="main-content">
    <div class="content-wrapper">
        <?php if (isset($_SESSION['message'])): ?>
            <div data-flash-message="<?php echo htmlspecialchars($_SESSION['message']); ?>" 
                 data-flash-type="<?php echo $_SESSION['message_type'] ?? 'info'; ?>"></div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
        <?php endif; ?>

        <?php if ($show_onboarding): ?>
        <!-- Enhanced Onboarding Checklist -->
        <?php
        $onboarding_progress = getOnboardingProgress($_SESSION['user_id'], $conn);
        $steps = $onboarding_progress['steps'];
        $completed = $onboarding_progress['completed'];
        $total = $onboarding_progress['total'];
        $percentage = $onboarding_progress['percentage'];
        ?>
        <div class="card mb-4 onboarding-card" id="onboardingCard">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <h5 class="fw-700 mb-1"><i class="fas fa-rocket text-primary"></i> Welcome to Studify! Let's get you set up.</h5>
                        <p class="text-muted mb-2" style="font-size: 13px;">Complete these steps to start organizing your academic life.</p>
                        <!-- Progress Bar -->
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <div class="progress flex-grow-1" style="height: 8px; max-width: 300px;">
                                <div class="progress-bar bg-success" role="progressbar" style="width: <?php echo $percentage; ?>%" 
                                     aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <small class="text-muted fw-600"><?php echo $completed; ?>/<?php echo $total; ?> (<?php echo $percentage; ?>%)</small>
                        </div>
                    </div>
                    <button class="btn btn-sm btn-secondary" onclick="dismissOnboarding()" title="Dismiss">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="onboarding-steps">
                    <!-- Step 1: Create Semester -->
                    <a href="<?php echo BASE_URL; ?>student/semesters.php" class="onboarding-step <?php echo $steps['semester'] ? 'completed' : ''; ?>">
                        <div class="step-check"><i class="fas fa-<?php echo $steps['semester'] ? 'check' : 'plus'; ?>"></i></div>
                        <div>
                            <div class="fw-600" style="font-size: 13px;">Create a Semester</div>
                            <div class="text-muted" style="font-size: 11px;">e.g., "1st Semester 2025-2026"</div>
                        </div>
                    </a>
                    
                    <!-- Step 2: Add Subjects -->
                    <a href="<?php echo BASE_URL; ?>student/subjects.php" class="onboarding-step <?php echo $steps['subjects'] ? 'completed' : ''; ?> <?php echo !$steps['semester'] ? 'disabled' : ''; ?>">
                        <div class="step-check"><i class="fas fa-<?php echo $steps['subjects'] ? 'check' : 'plus'; ?>"></i></div>
                        <div>
                            <div class="fw-600" style="font-size: 13px;">Add Subjects (3+ recommended)</div>
                            <div class="text-muted" style="font-size: 11px;">Add your courses for this semester</div>
                        </div>
                    </a>
                    
                    <!-- Step 3: Create First Task -->
                    <a href="<?php echo BASE_URL; ?>student/tasks.php" class="onboarding-step <?php echo $steps['task'] ? 'completed' : ''; ?> <?php echo !$steps['subjects'] ? 'disabled' : ''; ?>">
                        <div class="step-check"><i class="fas fa-<?php echo $steps['task'] ? 'check' : 'plus'; ?>"></i></div>
                        <div>
                            <div class="fw-600" style="font-size: 13px;">Create Your First Task</div>
                            <div class="text-muted" style="font-size: 11px;">Add an assignment, quiz, or project</div>
                        </div>
                    </a>
                    
                    <!-- Step 4: Complete First Task -->
                    <a href="<?php echo BASE_URL; ?>student/tasks.php?status=Completed" class="onboarding-step <?php echo $steps['task_completed'] ? 'completed' : ''; ?> <?php echo !$steps['task'] ? 'disabled' : ''; ?>">
                        <div class="step-check"><i class="fas fa-<?php echo $steps['task_completed'] ? 'check' : 'circle'; ?>"></i></div>
                        <div>
                            <div class="fw-600" style="font-size: 13px;">Complete Your First Task</div>
                            <div class="text-muted" style="font-size: 11px;">Mark a task as completed</div>
                        </div>
                    </a>
                    
                    <!-- Step 5: Use Pomodoro Timer -->
                    <a href="<?php echo BASE_URL; ?>student/pomodoro.php" class="onboarding-step <?php echo $steps['pomodoro'] ? 'completed' : ''; ?>">
                        <div class="step-check"><i class="fas fa-<?php echo $steps['pomodoro'] ? 'check' : 'clock'; ?>"></i></div>
                        <div>
                            <div class="fw-600" style="font-size: 13px;">Use Pomodoro Timer</div>
                            <div class="text-muted" style="font-size: 11px;">Start a focused study session</div>
                        </div>
                    </a>
                    
                    <!-- Step 6: Set Up Study Buddy (Optional) -->
                    <a href="<?php echo BASE_URL; ?>student/study_buddy.php" class="onboarding-step <?php echo $steps['buddy'] ? 'completed' : ''; ?>">
                        <div class="step-check"><i class="fas fa-<?php echo $steps['buddy'] ? 'check' : 'user-friends'; ?>"></i></div>
                        <div>
                            <div class="fw-600" style="font-size: 13px;">Set Up Study Buddy <span class="badge bg-secondary" style="font-size: 9px;">Optional</span></div>
                            <div class="text-muted" style="font-size: 11px;">Pair with a classmate for accountability</div>
                        </div>
                    </a>
                    
                    <!-- Step 7: Customize Dashboard -->
                    <a href="<?php echo BASE_URL; ?>student/dashboard.php" class="onboarding-step <?php echo $steps['dashboard'] ? 'completed' : ''; ?>">
                        <div class="step-check"><i class="fas fa-<?php echo $steps['dashboard'] ? 'check' : 'sliders-h'; ?>"></i></div>
                        <div>
                            <div class="fw-600" style="font-size: 13px;">Customize Dashboard</div>
                            <div class="text-muted" style="font-size: 11px;">Arrange widgets to your preference</div>
                        </div>
                    </a>
                    
                    <!-- Step 8: Explore Calendar View -->
                    <a href="<?php echo BASE_URL; ?>student/calendar.php" class="onboarding-step <?php echo $steps['calendar'] ? 'completed' : ''; ?>">
                        <div class="step-check"><i class="fas fa-<?php echo $steps['calendar'] ? 'check' : 'calendar-alt'; ?>"></i></div>
                        <div>
                            <div class="fw-600" style="font-size: 13px;">Explore Calendar View</div>
                            <div class="text-muted" style="font-size: 11px;">View your tasks on the calendar</div>
                        </div>
                    </a>
                </div>
                <?php if ($completed >= 5): ?>
                <div class="mt-3 text-center">
                    <button class="btn btn-success btn-sm" onclick="dismissOnboarding()">
                        <i class="fas fa-check"></i> Great progress! Dismiss this guide
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <script>
        function dismissOnboarding() {
            var card = document.getElementById('onboardingCard');
            if (card) {
                card.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                card.style.opacity = '0';
                card.style.transform = 'translateY(-10px)';
                setTimeout(function() { card.remove(); }, 300);
            }
            var csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            fetch('<?php echo BASE_URL; ?>student/dismiss_onboarding.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'csrf_token=' + csrfToken
            });
        }
        </script>
        <?php endif; ?>
