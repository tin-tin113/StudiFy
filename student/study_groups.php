<?php
/**
 * STUDIFY – Study Groups (v6.0)
 * Create / join study groups of 3-5 members.
 * Features: group progress dashboard, task assignment, group chat.
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

$page_title = 'Study Groups';
$user_id = getCurrentUserId();
$user = getUserInfo($user_id, $conn);

if (!$user_id || !$user) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$success = '';
$error = '';

// ─── AJAX handlers ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }
    // Release session lock early — allows concurrent requests (e.g. chat polling + page loads)
    session_write_close();
    $action = $_POST['action'] ?? '';
    updateUserActivity($user_id, $conn);

    $nudge_presets = [
        'wave' => "👋 Hey team! How's everyone doing?",
        'motivate' => "💪 Let's keep pushing — we've got this!",
        'reminder' => "⏰ Don't forget, we have deadlines coming up!",
        'celebrate' => "🎉 Great work everyone! Keep it up!",
        'challenge' => "🔥 I just finished my part — who's next?"
    ];

    // Send group message
    if ($action === 'send_message') {
        $gid = intval($_POST['group_id'] ?? 0);
        $group = getGroupInfo($gid, $user_id, $conn);
        if (!$group) { echo json_encode(['success' => false, 'message' => 'Not a member']); exit(); }
        if (!checkGroupChatRateLimit($user_id, $gid, $conn)) {
            echo json_encode(['success' => false, 'message' => 'Slow down! Too many messages.']);
            exit();
        }
        $message = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'text';
        $reply_to = !empty($_POST['reply_to']) ? intval($_POST['reply_to']) : null;
        if (!in_array($type, ['text', 'nudge', 'emoji'])) $type = 'text';
        if ($type === 'nudge' && isset($nudge_presets[$message])) $message = $nudge_presets[$message];
        if (empty($message)) { echo json_encode(['success' => false, 'message' => 'Empty message']); exit(); }
        if (mb_strlen($message) > 1000) { echo json_encode(['success' => false, 'message' => 'Too long (max 1000)']); exit(); }

        $msg_id = sendGroupMessage($gid, $user_id, $message, $conn, $type, $reply_to);
        $stmt = $conn->prepare("SELECT gm.*, u.name as sender_name, u.profile_photo as sender_photo,
                rm.message as reply_message, rm.sender_id as reply_sender_id, ru.name as reply_sender_name
                FROM group_messages gm
                JOIN users u ON u.id = gm.sender_id
                LEFT JOIN group_messages rm ON rm.id = gm.reply_to_id
                LEFT JOIN users ru ON ru.id = rm.sender_id
                WHERE gm.id = ?");
        $stmt->bind_param("i", $msg_id);
        $stmt->execute();
        $sent = $stmt->get_result()->fetch_assoc();
        echo json_encode(['success' => true, 'message' => $sent]);
        exit();
    }

    // Get messages
    if ($action === 'get_messages') {
        $gid = intval($_POST['group_id'] ?? 0);
        $group = getGroupInfo($gid, $user_id, $conn);
        if (!$group) { echo json_encode(['success' => false]); exit(); }
        $limit = min(intval($_POST['limit'] ?? 50), 100);
        $before = !empty($_POST['before_id']) ? intval($_POST['before_id']) : null;
        $msgs = getGroupMessages($gid, $conn, $limit, $before);
        echo json_encode(['success' => true, 'messages' => $msgs]);
        exit();
    }

    // Get new messages (polling)
    if ($action === 'get_new_messages') {
        $gid = intval($_POST['group_id'] ?? 0);
        $group = getGroupInfo($gid, $user_id, $conn);
        if (!$group) { echo json_encode(['success' => false]); exit(); }
        $after = intval($_POST['after_id'] ?? 0);
        $msgs = getNewGroupMessages($gid, $conn, $after);
        if (!empty($msgs)) {
            $last = end($msgs);
            markGroupMessagesRead($gid, $user_id, $last['id'], $conn);
        }
        echo json_encode(['success' => true, 'messages' => $msgs]);
        exit();
    }

    // Mark read
    if ($action === 'mark_read') {
        $gid = intval($_POST['group_id'] ?? 0);
        $last_id = intval($_POST['last_id'] ?? 0);
        markGroupMessagesRead($gid, $user_id, $last_id, $conn);
        echo json_encode(['success' => true]);
        exit();
    }

    // Toggle task status
    if ($action === 'toggle_task') {
        $gid = intval($_POST['group_id'] ?? 0);
        $tid = intval($_POST['task_id'] ?? 0);
        $result = toggleGroupTaskStatus($tid, $user_id, $gid, $conn);
        echo json_encode(['success' => $result !== false, 'new_status' => $result]);
        exit();
    }

    // Delete task
    if ($action === 'delete_task') {
        $gid = intval($_POST['group_id'] ?? 0);
        $tid = intval($_POST['task_id'] ?? 0);
        $ok = deleteGroupTask($tid, $user_id, $gid, $conn);
        echo json_encode(['success' => $ok]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
    exit();
}

// ─── Regular POST handlers ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // Create group
        if ($action === 'create_group') {
            $name = trim($_POST['group_name'] ?? '');
            $desc = trim($_POST['group_desc'] ?? '');
            if (empty($name) || mb_strlen($name) < 2 || mb_strlen($name) > 100) {
                $error = 'Group name must be 2-100 characters.';
            } else {
                $gid = createStudyGroup($user_id, $name, $desc, $conn);
                if ($gid) {
                    $success = 'Study group created! Share the invite code with your classmates.';
                } else {
                    $error = 'Failed to create group. Try again.';
                }
            }
        }

        // Join group
        if ($action === 'join_group') {
            $code = strtoupper(trim($_POST['invite_code'] ?? ''));
            if (empty($code)) {
                $error = 'Please enter an invite code.';
            } else {
                $result = joinGroupByCode($user_id, $code, $conn);
                if ($result['success']) {
                    if (!empty($result['pending'])) {
                        $success = $result['message'];
                    } else {
                        $success = 'You joined the group!';
                    }
                } else {
                    $error = $result['message'];
                }
            }
        }

        // Leave group
        if ($action === 'leave_group') {
            $gid = intval($_POST['group_id'] ?? 0);
            if (leaveGroup($user_id, $gid, $conn)) {
                $success = 'You left the group.';
            } else {
                $error = 'Failed to leave group.';
            }
        }

        // Remove member
        if ($action === 'remove_member') {
            $gid = intval($_POST['group_id'] ?? 0);
            $tid = intval($_POST['target_id'] ?? 0);
            if (removeGroupMember($user_id, $tid, $gid, $conn)) {
                $success = 'Member removed.';
            } else {
                $error = 'Only the group leader can remove members.';
            }
        }

        // Assign task
        if ($action === 'assign_task') {
            $gid = intval($_POST['group_id'] ?? 0);
            $group = getGroupInfo($gid, $user_id, $conn);
            if (!$group) {
                $error = 'You are not in this group.';
            } else {
                // Check assignment permission
                $can_assign = ($group['my_role'] === 'leader' || $group['allow_member_assign']);
                if (!$can_assign) {
                    $error = 'Only the group leader can assign tasks.';
                } else {
                    $to = intval($_POST['assign_to'] ?? 0);
                    $title = trim($_POST['task_title'] ?? '');
                    $desc = trim($_POST['task_desc'] ?? '');
                    $deadline = !empty($_POST['task_deadline']) ? $_POST['task_deadline'] : null;
                    $priority = $_POST['task_priority'] ?? 'Medium';
                    if (!in_array($priority, ['Low', 'Medium', 'High'])) $priority = 'Medium';
                    if (empty($title)) {
                        $error = 'Task title is required.';
                    } else {
                        $tid = assignGroupTask($gid, $user_id, $to, $title, $desc, $deadline, $priority, $conn);
                        if ($tid) {
                            $success = 'Task assigned!';
                        } else {
                            $error = 'Failed to assign task.';
                        }
                    }
                }
            }
        }

        // Update settings
        if ($action === 'update_settings') {
            $gid = intval($_POST['group_id'] ?? 0);
            $name = trim($_POST['group_name'] ?? '');
            $desc = trim($_POST['group_desc'] ?? '');
            $allow_assign = intval($_POST['allow_member_assign'] ?? 0);
            $allow_invite = intval($_POST['allow_member_invite'] ?? 0);
            $join_mode = $_POST['join_mode'] ?? 'open';
            if (empty($name)) {
                $error = 'Group name is required.';
            } else {
                if (updateGroupSettings($gid, $user_id, $name, $desc, $allow_assign, $conn, $allow_invite, $join_mode)) {
                    $success = 'Settings updated.';
                } else {
                    $error = 'Only the group leader can change settings.';
                }
            }
        }

        // Approve join request
        if ($action === 'approve_request') {
            $rid = intval($_POST['request_id'] ?? 0);
            if (approveJoinRequest($rid, $user_id, $conn)) {
                $success = 'Member approved!';
            } else {
                $error = 'Could not approve request. Group may be full or you are not the leader.';
            }
        }

        // Reject join request
        if ($action === 'reject_request') {
            $rid = intval($_POST['request_id'] ?? 0);
            if (rejectJoinRequest($rid, $user_id, $conn)) {
                $success = 'Request declined.';
            } else {
                $error = 'Could not decline request.';
            }
        }
    }
}

// ─── Fetch data ───
$my_groups = getUserStudyGroups($user_id, $conn);
$active_group_id = intval($_GET['group'] ?? 0);
$active_group = null;
$group_members = [];
$group_tasks = [];
$group_progress = [];

if ($active_group_id) {
    $active_group = getGroupInfo($active_group_id, $user_id, $conn);
    if ($active_group) {
        $group_members = getGroupMembers($active_group_id, $conn);
        $group_tasks = getGroupTasks($active_group_id, $conn);
        $group_progress = getGroupMemberProgress($active_group_id, $conn);
        $pending_requests = ($active_group['my_role'] === 'leader') ? getPendingJoinRequests($active_group_id, $conn) : [];
        updateUserActivity($user_id, $conn);

        // Auto-mark group messages as read when viewing the group
        $stmt_max = $conn->prepare("SELECT MAX(id) as max_id FROM group_messages WHERE group_id = ?");
        $stmt_max->bind_param("i", $active_group_id);
        $stmt_max->execute();
        $max_id = $stmt_max->get_result()->fetch_assoc()['max_id'] ?? 0;
        if ($max_id > 0) {
            markGroupMessagesRead($active_group_id, $user_id, $max_id, $conn);
        }
    }
}
?>
<?php include '../includes/header.php'; ?>

    <div class="page-header">
        <div>
            <h2><i class="fas fa-users"></i> Study Groups</h2>
            <p class="text-muted mb-0" style="font-size: 13px;">Collaborate with 3-5 classmates for group accountability</p>
        </div>
        <?php if (!$active_group): ?>
        <div>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                <i class="fas fa-plus"></i> Create Group
            </button>
        </div>
        <?php else: ?>
        <div>
            <a href="<?php echo BASE_URL; ?>student/study_groups.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> All Groups
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

<?php if ($active_group): ?>
    <!-- ===== GROUP DETAIL VIEW ===== -->
    <?php include __DIR__ . '/group_messenger.php'; ?>

<?php else: ?>
    <!-- ===== GROUP LIST VIEW ===== -->
    <div class="row g-3">
        <div class="col-lg-8">
            <?php if (empty($my_groups)): ?>
                <div class="card">
                    <div class="card-body text-center py-5">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">👥</div>
                        <h5 class="fw-700">No Study Groups Yet</h5>
                        <p class="text-muted mb-3">Create a group or join one with an invite code.</p>
                        <button class="btn btn-primary btn-sm me-2" data-bs-toggle="modal" data-bs-target="#createGroupModal">
                            <i class="fas fa-plus"></i> Create Group
                        </button>
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#joinGroupModal">
                            <i class="fas fa-sign-in-alt"></i> Join Group
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($my_groups as $g): ?>
                <a href="?group=<?php echo $g['id']; ?>" class="card mb-3 text-decoration-none group-card-link">
                    <div class="card-body d-flex align-items-center">
                        <div class="group-icon me-3">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 fw-700"><?php echo htmlspecialchars($g['name']); ?></h6>
                            <small class="text-muted">
                                <i class="fas fa-user-friends"></i> <?php echo $g['member_count']; ?>/<?php echo $g['max_members'] ?? 5; ?> members
                                · <?php echo $g['role'] === 'leader' ? '👑 Leader' : 'Member'; ?>
                            </small>
                        </div>
                        <div class="text-end">
                            <?php if ($g['unread_count'] > 0): ?>
                                <span class="badge bg-primary rounded-pill"><?php echo $g['unread_count']; ?></span>
                            <?php endif; ?>
                            <i class="fas fa-chevron-right text-muted ms-2"></i>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <!-- Join Group Card -->
            <div class="card mb-3">
                <div class="card-body">
                    <h6 class="fw-700 mb-3"><i class="fas fa-sign-in-alt text-primary"></i> Join a Group</h6>
                    <form method="POST">
                        <input type="hidden" name="action" value="join_group">
                        <?php echo csrfTokenField(); ?>
                        <div class="mb-3">
                            <input type="text" name="invite_code" class="form-control form-control-sm" placeholder="Enter invite code (e.g. A1B2C3D4)" maxlength="20" required style="text-transform: uppercase; letter-spacing: 2px; font-weight: 600;">
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm w-100"><i class="fas fa-sign-in-alt"></i> Join</button>
                    </form>
                </div>
            </div>

            <!-- How It Works -->
            <div class="card">
                <div class="card-body">
                    <h6 class="fw-700 mb-3"><i class="fas fa-info-circle text-info"></i> How It Works</h6>
                    <div class="d-flex align-items-start mb-2">
                        <span class="badge bg-primary rounded-circle me-2" style="width: 22px; height: 22px; font-size: 11px; display: flex; align-items: center; justify-content: center;">1</span>
                        <small>Create a group or join with a code</small>
                    </div>
                    <div class="d-flex align-items-start mb-2">
                        <span class="badge bg-primary rounded-circle me-2" style="width: 22px; height: 22px; font-size: 11px; display: flex; align-items: center; justify-content: center;">2</span>
                        <small>Assign tasks to group members</small>
                    </div>
                    <div class="d-flex align-items-start mb-2">
                        <span class="badge bg-primary rounded-circle me-2" style="width: 22px; height: 22px; font-size: 11px; display: flex; align-items: center; justify-content: center;">3</span>
                        <small>Track progress & chat together</small>
                    </div>
                    <div class="d-flex align-items-start">
                        <span class="badge bg-primary rounded-circle me-2" style="width: 22px; height: 22px; font-size: 11px; display: flex; align-items: center; justify-content: center;">4</span>
                        <small>Stay accountable as a team!</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<!-- Create Group Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h6 class="modal-title fw-700"><i class="fas fa-plus text-primary"></i> Create Study Group</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_group">
                    <?php echo csrfTokenField(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-600" style="font-size: 13px;">Group Name</label>
                        <input type="text" name="group_name" class="form-control" placeholder="e.g. Thesis Group A" maxlength="100" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600" style="font-size: 13px;">Description (optional)</label>
                        <textarea name="group_desc" class="form-control" rows="2" maxlength="500" placeholder="What's this group for?"></textarea>
                    </div>
                    <small class="text-muted"><i class="fas fa-info-circle"></i> You'll get an invite code to share with up to 4 classmates.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Create Group</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Join Group Modal -->
<div class="modal fade" id="joinGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h6 class="modal-title fw-700"><i class="fas fa-sign-in-alt text-primary"></i> Join Study Group</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="join_group">
                    <?php echo csrfTokenField(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-600" style="font-size: 13px;">Invite Code</label>
                        <input type="text" name="invite_code" class="form-control" placeholder="Enter the code from your groupmate" maxlength="20" required style="text-transform: uppercase; letter-spacing: 2px; font-weight: 600;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-sign-in-alt"></i> Join</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
