<?php
/**
 * STUDIFY – Study Buddy / Accountability System
 * Pair with a classmate for mutual accountability
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

$page_title = 'Study Buddy';
$user_id = getCurrentUserId();
$user = getUserInfo($user_id, $conn);

// Safety: redirect if user data is invalid
if (!$user_id || !$user) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

$success = '';
$error = '';

// Handle AJAX actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json');
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }
    // Release session lock early — allows concurrent requests (e.g. chat polling + page loads)
    session_write_close();
    $action = $_POST['action'] ?? '';

    // Update user activity on every AJAX request
    updateUserActivity($user_id, $conn);

    $presets = [
        'wave' => "👋 Hey! Just checking in — how's studying going?",
        'motivate' => "💪 You've got this! Keep pushing through!",
        'reminder' => "⏰ Don't forget — you've got tasks due soon!",
        'celebrate' => "🎉 Great progress today! Keep it up!",
        'challenge' => "🔥 I just finished a task — your turn!"
    ];

    // ── Send chat message ──
    if ($action === 'send_message') {
        $buddy = getAcceptedBuddy($user_id, $conn);
        if (!$buddy) { echo json_encode(['success' => false, 'message' => 'No active buddy']); exit(); }
        if (!checkChatRateLimit($user_id, $conn)) {
            echo json_encode(['success' => false, 'message' => 'Slow down! You\'re sending messages too fast.']);
            exit();
        }
        $message = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'text';
        $reply_to = !empty($_POST['reply_to']) ? intval($_POST['reply_to']) : null;
        if (!in_array($type, ['text', 'nudge', 'emoji'])) $type = 'text';
        if ($type === 'nudge' && isset($presets[$message])) $message = $presets[$message];
        if (empty($message)) { echo json_encode(['success' => false, 'message' => 'Message cannot be empty']); exit(); }
        if (mb_strlen($message) > 1000) { echo json_encode(['success' => false, 'message' => 'Message too long (max 1000 chars)']); exit(); }

        $msg_id = sendChatMessage($user_id, $buddy['buddy_id'], $message, $conn, $type, $reply_to);
        $stmt = $conn->prepare("SELECT bm.*, u.name as sender_name, u.profile_photo as sender_photo,
                rm.message as reply_message, rm.sender_id as reply_sender_id, ru.name as reply_sender_name
                FROM buddy_messages bm
                JOIN users u ON u.id = bm.sender_id
                LEFT JOIN buddy_messages rm ON rm.id = bm.reply_to_id
                LEFT JOIN users ru ON ru.id = rm.sender_id
                WHERE bm.id = ?");
        $stmt->bind_param("i", $msg_id);
        $stmt->execute();
        $sent_msg = $stmt->get_result()->fetch_assoc();
        echo json_encode(['success' => true, 'message' => $sent_msg]);
        exit();
    }

    // ── Get messages (initial load / load more) ──
    if ($action === 'get_messages') {
        $buddy = getAcceptedBuddy($user_id, $conn);
        if (!$buddy) { echo json_encode(['success' => false, 'message' => 'No active buddy']); exit(); }
        $before_id = !empty($_POST['before_id']) ? intval($_POST['before_id']) : null;
        $limit = min(max(intval($_POST['limit'] ?? 50), 10), 100);
        $messages = getChatMessages($user_id, $buddy['buddy_id'], $conn, $limit, $before_id);
        markChatMessagesRead($user_id, $buddy['buddy_id'], $conn);
        echo json_encode(['success' => true, 'messages' => $messages]);
        exit();
    }

    // ── Poll for new messages ──
    if ($action === 'get_new_messages') {
        $buddy = getAcceptedBuddy($user_id, $conn);
        if (!$buddy) { echo json_encode(['success' => false, 'message' => 'No active buddy']); exit(); }
        $after_id = intval($_POST['after_id'] ?? 0);
        $messages = getNewChatMessages($user_id, $buddy['buddy_id'], $conn, $after_id);
        markChatMessagesRead($user_id, $buddy['buddy_id'], $conn);
        $buddy_typing = isBuddyTyping($buddy['buddy_id'], $conn);
        $buddy_online = isUserOnline($buddy['buddy_id'], $conn);
        $stmt = $conn->prepare("SELECT MAX(id) as last_read_id FROM buddy_messages WHERE sender_id = ? AND receiver_id = ? AND is_read = 1");
        $stmt->bind_param("ii", $user_id, $buddy['buddy_id']);
        $stmt->execute();
        $last_read = $stmt->get_result()->fetch_assoc()['last_read_id'] ?? 0;
        echo json_encode(['success' => true, 'messages' => $messages, 'buddy_typing' => $buddy_typing, 'buddy_online' => $buddy_online, 'last_read_id' => intval($last_read)]);
        exit();
    }

    // ── Mark messages as read ──
    if ($action === 'mark_read') {
        $buddy = getAcceptedBuddy($user_id, $conn);
        if ($buddy) markChatMessagesRead($user_id, $buddy['buddy_id'], $conn);
        $stmt = $conn->prepare("UPDATE buddy_nudges SET is_read = 1 WHERE receiver_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
        exit();
    }

    // ── Typing indicator ──
    if ($action === 'typing') {
        updateTypingStatus($user_id, $conn);
        echo json_encode(['success' => true]);
        exit();
    }

    // ── Stop typing indicator ──
    if ($action === 'stop_typing') {
        clearTypingStatus($user_id, $conn);
        echo json_encode(['success' => true]);
        exit();
    }

    // ── Heartbeat (online status) ──
    if ($action === 'heartbeat') {
        $buddy = getAcceptedBuddy($user_id, $conn);
        $buddy_online = false; $buddy_typing = false;
        if ($buddy) {
            $buddy_online = isUserOnline($buddy['buddy_id'], $conn);
            $buddy_typing = isBuddyTyping($buddy['buddy_id'], $conn);
        }
        echo json_encode(['success' => true, 'buddy_online' => $buddy_online, 'buddy_typing' => $buddy_typing]);
        exit();
    }

    // ── Delete message ──
    if ($action === 'delete_message') {
        $message_id = intval($_POST['message_id'] ?? 0);
        $deleted = deleteChatMessage($message_id, $user_id, $conn);
        echo json_encode(['success' => $deleted, 'message' => $deleted ? 'Message deleted' : 'Cannot delete this message']);
        exit();
    }

    // ── Set weekly goal ──
    if ($action === 'set_weekly_goal') {
        $target = intval($_POST['target_tasks'] ?? 5);
        $ok = setBuddyWeeklyGoal($user_id, $target, $conn);
        echo json_encode(['success' => $ok, 'target' => $target]);
        exit();
    }

    // ── Daily check-in ──
    if ($action === 'checkin') {
        $completed = ($_POST['completed'] ?? '0') === '1';
        $note = trim($_POST['note'] ?? '');
        $ok = setTodayCheckin($user_id, $completed, $note, $conn);
        // Update pair streak after check-in
        $buddy = getAcceptedBuddy($user_id, $conn);
        $pair_streak = 0;
        if ($buddy) {
            $pair_streak = updateBuddyPairStreak($user_id, $buddy['buddy_id'], $conn);
        }
        echo json_encode(['success' => $ok, 'pair_streak' => $pair_streak]);
        exit();
    }

    // ── Get comparison chart data ──
    if ($action === 'get_comparison_chart') {
        $buddy = getAcceptedBuddy($user_id, $conn);
        if (!$buddy) {
            echo json_encode(['success' => false, 'message' => 'No buddy']);
            exit();
        }
        $data = getWeeklyComparisonData($user_id, $buddy['buddy_id'], $conn);
        echo json_encode(['success' => true, 'data' => $data]);
        exit();
    }

    // ── Add scheduled nudge ──
    if ($action === 'add_scheduled_nudge') {
        $day = intval($_POST['day_of_week'] ?? 0);
        $time = $_POST['nudge_time'] ?? '09:00';
        $message = trim($_POST['message'] ?? '');
        $id = addScheduledNudge($user_id, $day, $time, $message, $conn);
        echo json_encode(['success' => $id !== false, 'id' => $id]);
        exit();
    }

    // ── Delete scheduled nudge ──
    if ($action === 'delete_scheduled_nudge') {
        $nudge_id = intval($_POST['nudge_id'] ?? 0);
        $ok = deleteScheduledNudge($nudge_id, $user_id, $conn);
        echo json_encode(['success' => $ok]);
        exit();
    }

    // ── Toggle scheduled nudge ──
    if ($action === 'toggle_scheduled_nudge') {
        $nudge_id = intval($_POST['nudge_id'] ?? 0);
        $ok = toggleScheduledNudge($nudge_id, $user_id, $conn);
        echo json_encode(['success' => $ok]);
        exit();
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCSRF();
    $action = $_POST['action'] ?? '';

    // Send buddy request by email
    if ($action === 'send_request') {
        $partner_email = sanitize($_POST['partner_email'] ?? '');
        if (empty($partner_email)) {
            $error = 'Please enter your buddy\'s email address.';
        } elseif ($partner_email === $user['email']) {
            $error = 'You cannot send a request to yourself.';
        } else {
            // Check if partner exists
            $stmt = $conn->prepare("SELECT id, name FROM users WHERE email = ? AND role = 'student'");
            $stmt->bind_param("s", $partner_email);
            $stmt->execute();
            $partner = $stmt->get_result()->fetch_assoc();
            if (!$partner) {
                $error = 'No student found with that email address.';
            } else {
                // Check if blocked
                if (isBuddyBlocked($user_id, $partner['id'], $conn)) {
                    $error = 'You cannot send a request to this user.';
                } else {
                    // Check existing relationships
                    $stmt = $conn->prepare("SELECT * FROM study_buddies 
                        WHERE ((requester_id = ? AND partner_id = ?) OR (requester_id = ? AND partner_id = ?))
                        AND status IN ('pending', 'accepted')");
                    $stmt->bind_param("iiii", $user_id, $partner['id'], $partner['id'], $user_id);
                    $stmt->execute();
                    $existing = $stmt->get_result()->fetch_assoc();
                    if ($existing) {
                        $error = $existing['status'] === 'accepted' 
                            ? 'You are already buddies with this person!' 
                            : 'A request is already pending with this person.';
                    } else {
                        // Check if either already has an accepted buddy
                        $my_buddy = getAcceptedBuddy($user_id, $conn);
                        $their_buddy = getAcceptedBuddy($partner['id'], $conn);
                        if ($my_buddy) {
                            $error = 'You already have an active buddy. Unpair first to send a new request.';
                        } elseif ($their_buddy) {
                            $error = 'That student already has an active study buddy.';
                        } else {
                            // Clean up old declined/unlinked records to allow re-pairing
                            $stmt = $conn->prepare("DELETE FROM study_buddies 
                                WHERE ((requester_id = ? AND partner_id = ?) OR (requester_id = ? AND partner_id = ?))
                                AND status IN ('declined', 'unlinked')");
                            $stmt->bind_param("iiii", $user_id, $partner['id'], $partner['id'], $user_id);
                            $stmt->execute();

                            $code = generateBuddyCode();
                            $stmt = $conn->prepare("INSERT INTO study_buddies (requester_id, partner_id, invite_code) VALUES (?, ?, ?)");
                            $stmt->bind_param("iis", $user_id, $partner['id'], $code);
                            $stmt->execute();
                            $success = "Buddy request sent to {$partner['name']}!";
                        }
                    }
                }
            }
        }
    }

    // Accept buddy request
    if ($action === 'accept_request') {
        $request_id = intval($_POST['request_id'] ?? 0);
        // Verify the request is for this user and is pending
        $stmt = $conn->prepare("SELECT * FROM study_buddies WHERE id = ? AND partner_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $request_id, $user_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        if ($request) {
            // Check if blocked
            if (isBuddyBlocked($user_id, $request['requester_id'], $conn)) {
                $error = 'You cannot accept a request from this user.';
            } else {
                // Check if user already has a buddy
                $my_buddy = getAcceptedBuddy($user_id, $conn);
                if ($my_buddy) {
                    $error = 'You already have an active buddy. Unpair first to accept a new request.';
                } else {
                    $stmt = $conn->prepare("UPDATE study_buddies SET status = 'accepted' WHERE id = ?");
                    $stmt->bind_param("i", $request_id);
                    $stmt->execute();
                    // Decline any other pending requests for both users
                    $stmt = $conn->prepare("UPDATE study_buddies SET status = 'declined' 
                        WHERE id != ? AND status = 'pending' 
                        AND (requester_id IN (?, ?) OR partner_id IN (?, ?))");
                    $stmt->bind_param("iiiii", $request_id, $user_id, $request['requester_id'], $user_id, $request['requester_id']);
                    $stmt->execute();
                    $success = 'Buddy request accepted! You\'re now study partners.';
                }
            }
        } else {
            $error = 'Request not found or already handled.';
        }
    }

    // Decline buddy request
    if ($action === 'decline_request') {
        $request_id = intval($_POST['request_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE study_buddies SET status = 'declined' WHERE id = ? AND partner_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $request_id, $user_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $success = 'Request declined.';
        }
    }

    // Unpair from buddy
    if ($action === 'unpair') {
        $stmt = $conn->prepare("UPDATE study_buddies SET status = 'unlinked' WHERE (requester_id = ? OR partner_id = ?) AND status = 'accepted'");
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $success = 'You\'ve been unpaired from your study buddy.';
        }
    }

    // Block buddy
    if ($action === 'block_buddy') {
        $block_id = intval($_POST['block_id'] ?? 0);
        if ($block_id && $block_id !== $user_id) {
            blockBuddy($user_id, $block_id, $conn);
            $success = 'User has been blocked. They can no longer send you buddy requests.';
        } else {
            $error = 'Invalid user to block.';
        }
    }

    // Unblock buddy
    if ($action === 'unblock_buddy') {
        $unblock_id = intval($_POST['unblock_id'] ?? 0);
        if ($unblock_id) {
            unblockBuddy($user_id, $unblock_id, $conn);
            $success = 'User has been unblocked.';
        }
    }

    // Report buddy
    if ($action === 'report_buddy') {
        $report_id = intval($_POST['report_id'] ?? 0);
        $reason = sanitize($_POST['reason'] ?? '');
        $details = sanitize($_POST['details'] ?? '');
        if ($report_id && $report_id !== $user_id && !empty($reason)) {
            reportBuddy($user_id, $report_id, $reason, $details, $conn);
            $success = 'Report submitted. An admin will review it.';
        } else {
            $error = 'Please provide a valid reason for the report.';
        }
    }

    // Cancel sent request
    if ($action === 'cancel_request') {
        $stmt = $conn->prepare("DELETE FROM study_buddies WHERE requester_id = ? AND status = 'pending'");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $success = 'Request cancelled.';
        }
    }
}

// Fetch current state
$buddy_pair = getAcceptedBuddy($user_id, $conn);
$pending_requests = getPendingBuddyRequests($user_id, $conn);
$sent_request = getSentBuddyRequest($user_id, $conn);

// Auto-mark buddy messages and nudges as read upon viewing the page
if ($buddy_pair) {
    markChatMessagesRead($user_id, $buddy_pair['buddy_id'], $conn);
}
$stmt = $conn->prepare("UPDATE buddy_nudges SET is_read = 1 WHERE receiver_id = ? AND is_read = 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->close();
$my_progress = getBuddyProgress($user_id, $conn);
$my_enhanced = null;
$blocked_users = getBlockedUsers($user_id, $conn);
$last_buddy = !$buddy_pair ? getLastBuddyPair($user_id, $conn) : null;

// If has buddy, get their progress and update online status
$buddy_progress = null;
$buddy_enhanced = null;
$my_weekly_goal = null;
$buddy_weekly_goal = null;
$my_checkin = null;
$buddy_checkin = null;
$scheduled_nudges = [];
$comparison_data = null;

if ($buddy_pair) {
    $buddy_progress = getBuddyProgress($buddy_pair['buddy_id'], $conn);
    $my_enhanced = getEnhancedBuddyProgress($user_id, $buddy_pair['buddy_id'], $conn);
    $buddy_enhanced = getEnhancedBuddyProgress($buddy_pair['buddy_id'], $user_id, $conn);
    $my_weekly_goal = getWeeklyGoalProgress($user_id, $conn);
    $buddy_weekly_goal = getWeeklyGoalProgress($buddy_pair['buddy_id'], $conn);
    $my_checkin = getTodayCheckin($user_id, $conn);
    $buddy_checkin = getTodayCheckin($buddy_pair['buddy_id'], $conn);
    $scheduled_nudges = getScheduledNudges($user_id, $conn);
    $comparison_data = getWeeklyComparisonData($user_id, $buddy_pair['buddy_id'], $conn);
    updateUserActivity($user_id, $conn);
    // Update pair streak
    updateBuddyPairStreak($user_id, $buddy_pair['buddy_id'], $conn);
}
?>
<?php include '../includes/header.php'; ?>

    <div class="page-header">
        <div>
            <h2><i class="fas fa-user-friends"></i> Study Buddy</h2>
            <p class="text-muted mb-0" style="font-size: 13px;">Pair up with a classmate for mutual accountability</p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if ($buddy_pair): ?>
    <!-- ===== PAIRED STATE: MESSENGER ===== -->
    <?php include __DIR__ . '/buddy_messenger.php'; ?>

    <?php else: ?>

    <?php if ($last_buddy): ?>
    <!-- ===== PAST CHAT HISTORY ===== -->
    <div class="card mb-4">
        <div class="card-body p-3">
            <div class="d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <div style="width:36px;height:36px;border-radius:50%;background:var(--bg-secondary);display:flex;align-items:center;justify-content:center;font-weight:600;font-size:14px;color:var(--text-muted);">
                        <?php echo strtoupper(substr($last_buddy['buddy']['name'], 0, 1)); ?>
                    </div>
                    <div>
                        <strong style="font-size:13px;"><?php echo htmlspecialchars($last_buddy['buddy']['name']); ?></strong>
                        <small class="d-block text-muted" style="font-size:11px;">Previously paired &middot; <?php echo $last_buddy['message_count']; ?> messages</small>
                    </div>
                </div>
                <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#pastChatHistory" style="font-size:12px;">
                    <i class="fas fa-history"></i> View Chat History
                </button>
            </div>
            <div class="collapse mt-3" id="pastChatHistory">
                <div style="max-height:400px;overflow-y:auto;border:1px solid var(--border-color);border-radius:var(--border-radius-sm);padding:12px;background:var(--bg-body);">
                    <?php
                    $past_messages = getChatMessages($user_id, $last_buddy['buddy_id'], $conn, 200);
                    if (empty($past_messages)): ?>
                        <p class="text-muted text-center mb-0" style="font-size:13px;">No messages found.</p>
                    <?php else:
                        $prev_date = '';
                        foreach ($past_messages as $msg):
                            $msg_date = date('M j, Y', strtotime($msg['created_at']));
                            $is_mine = intval($msg['sender_id']) === $user_id;
                            if ($msg_date !== $prev_date): $prev_date = $msg_date; ?>
                                <div class="text-center my-2"><small class="text-muted" style="font-size:10px;background:var(--bg-secondary);padding:2px 10px;border-radius:10px;"><?php echo $msg_date; ?></small></div>
                            <?php endif; ?>
                            <div class="d-flex mb-2 <?php echo $is_mine ? 'justify-content-end' : 'justify-content-start'; ?>">
                                <div style="max-width:75%;padding:8px 12px;border-radius:12px;font-size:13px;line-height:1.5;
                                    <?php echo $is_mine 
                                        ? 'background:var(--primary);color:white;border-bottom-right-radius:4px;' 
                                        : 'background:var(--bg-card);border:1px solid var(--border-color);color:var(--text-primary);border-bottom-left-radius:4px;'; ?>">
                                    <?php echo htmlspecialchars($msg['message']); ?>
                                    <div style="font-size:10px;margin-top:2px;opacity:0.7;"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></div>
                                </div>
                            </div>
                        <?php endforeach;
                    endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ===== UNPAIRED STATE ===== -->
    <div class="row g-4">
        <!-- Find a Buddy -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body py-4">
                    <div class="text-center mb-4">
                        <div class="buddy-hero-icon">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <h5 class="fw-700 mt-3">Find a Study Buddy</h5>
                        <p class="text-muted" style="font-size: 13px;">
                            Pair with a classmate to stay accountable. You'll see each other's progress and can send motivational nudges.
                        </p>
                    </div>

                    <?php if ($sent_request): ?>
                        <div class="alert alert-info py-2" style="font-size: 13px;">
                            <i class="fas fa-hourglass-half"></i> 
                            Request pending to <strong><?php echo htmlspecialchars($sent_request['partner_name']); ?></strong>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="action" value="cancel_request">
                                <?php echo csrfTokenField(); ?>
                                <button type="submit" class="btn btn-outline-danger btn-sm ms-2" style="font-size: 11px;">Cancel</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="buddy-invite-form">
                            <input type="hidden" name="action" value="send_request">
                            <?php echo csrfTokenField(); ?>
                            <label class="form-label fw-600" style="font-size: 13px;">Enter your buddy's email</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" name="partner_email" placeholder="classmate@email.com" required>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Send Request
                                </button>
                            </div>
                            <small class="text-muted mt-1 d-block">They must have a Studify student account to pair up.</small>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <!-- How It Works -->
            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="fw-700 mb-3"><i class="fas fa-question-circle text-info"></i> How It Works</h6>
                    <div class="buddy-steps">
                        <div class="buddy-step">
                            <div class="buddy-step-num">1</div>
                            <div>
                                <strong>Send a Request</strong>
                                <p class="text-muted mb-0" style="font-size: 12px;">Enter your classmate's email to invite them</p>
                            </div>
                        </div>
                        <div class="buddy-step">
                            <div class="buddy-step-num">2</div>
                            <div>
                                <strong>They Accept</strong>
                                <p class="text-muted mb-0" style="font-size: 12px;">Your buddy approves the pairing request</p>
                            </div>
                        </div>
                        <div class="buddy-step">
                            <div class="buddy-step-num">3</div>
                            <div>
                                <strong>Stay Accountable</strong>
                                <p class="text-muted mb-0" style="font-size: 12px;">See each other's progress & send nudges</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Incoming Requests -->
        <div class="col-lg-6">
            <?php if (!empty($pending_requests)): ?>
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="fw-700 mb-3">
                        <i class="fas fa-inbox text-primary"></i> Incoming Requests
                        <span class="badge bg-primary"><?php echo count($pending_requests); ?></span>
                    </h6>
                    <?php foreach ($pending_requests as $req): ?>
                        <div class="buddy-request-card">
                            <div class="d-flex align-items-center gap-3">
                                <div class="buddy-req-avatar">
                                    <?php if (!empty($req['requester_photo'])): ?>
                                        <img src="<?php echo BASE_URL . $req['requester_photo']; ?>" alt="">
                                    <?php else: ?>
                                        <span><?php echo strtoupper(substr($req['requester_name'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <strong><?php echo htmlspecialchars($req['requester_name']); ?></strong>
                                    <div class="text-muted" style="font-size: 12px;">
                                        <?php echo htmlspecialchars($req['requester_course'] ?? 'Student'); ?>
                                        <?php if ($req['requester_year']): ?> · Year <?php echo $req['requester_year']; ?><?php endif; ?>
                                    </div>
                                </div>
                                <div class="d-flex gap-2">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="accept_request">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <?php echo csrfTokenField(); ?>
                                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button>
                                    </form>
                                    <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Decline Request', 'Are you sure you want to decline this buddy request?', 'warning')">
                                        <input type="hidden" name="action" value="decline_request">
                                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                                        <?php echo csrfTokenField(); ?>
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-times"></i></button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Your Progress Preview -->
            <div class="card">
                <div class="card-body">
                    <h6 class="fw-700 mb-3"><i class="fas fa-chart-line text-success"></i> Your Progress</h6>
                    <p class="text-muted mb-3" style="font-size: 12.5px;">This is what your buddy will see when you pair up:</p>
                    <div class="row g-3">
                        <div class="col-6">
                            <div class="buddy-preview-stat">
                                <div class="value"><?php echo $my_progress['completion_pct']; ?>%</div>
                                <div class="label">Tasks Completed</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="buddy-preview-stat">
                                <div class="value"><?php echo $my_progress['streak']; ?> 🔥</div>
                                <div class="label">Day Streak</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="buddy-preview-stat">
                                <div class="value"><?php echo round($my_progress['week_minutes'] / 60, 1); ?>h</div>
                                <div class="label">Study This Week</div>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="buddy-preview-stat">
                                <div class="value"><?php echo $my_progress['week_sessions']; ?></div>
                                <div class="label">Sessions This Week</div>
                            </div>
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        <i class="fas fa-shield-alt"></i> Privacy: Only progress stats are shared — never task titles or details.
                    </small>
                </div>
            </div>

            <!-- Blocked Users -->
            <?php if (!empty($blocked_users)): ?>
            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="fw-700 mb-3"><i class="fas fa-ban text-danger"></i> Blocked Users</h6>
                    <?php foreach ($blocked_users as $bu): ?>
                        <div class="d-flex align-items-center justify-content-between mb-2 p-2 rounded" style="background: var(--bg-secondary);">
                            <div class="d-flex align-items-center gap-2">
                                <div class="buddy-req-avatar" style="width:32px;height:32px;font-size:12px;">
                                    <?php if (!empty($bu['blocked_photo'])): ?>
                                        <img src="<?php echo BASE_URL . $bu['blocked_photo']; ?>" alt="" style="width:32px;height:32px;">
                                    <?php else: ?>
                                        <span><?php echo strtoupper(substr($bu['blocked_name'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <strong style="font-size:13px;"><?php echo htmlspecialchars($bu['blocked_name']); ?></strong>
                                    <div class="text-muted" style="font-size:11px;"><?php echo htmlspecialchars($bu['blocked_email']); ?></div>
                                </div>
                            </div>
                            <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Unblock User', 'Are you sure you want to unblock this user? They will be able to send you buddy requests again.', 'warning')">
                                <input type="hidden" name="action" value="unblock_buddy">
                                <input type="hidden" name="unblock_id" value="<?php echo $bu['blocked_id']; ?>">
                                <?php echo csrfTokenField(); ?>
                                <button type="submit" class="btn btn-outline-secondary btn-sm" style="font-size:11px;"><i class="fas fa-unlock"></i> Unblock</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

<?php include '../includes/footer.php'; ?>
