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
    $action = $_POST['action'] ?? '';

    // Send nudge
    if ($action === 'send_nudge') {
        $buddy = getAcceptedBuddy($user_id, $conn);
        if (!$buddy) {
            echo json_encode(['success' => false, 'message' => 'No active buddy']);
            exit();
        }
        $message = sanitize($_POST['message'] ?? '');
        $presets = [
            'wave' => "👋 Hey! Just checking in — how's studying going?",
            'motivate' => "💪 You've got this! Keep pushing through!",
            'reminder' => "⏰ Don't forget — you've got tasks due soon!",
            'celebrate' => "🎉 Great progress today! Keep it up!",
            'challenge' => "🔥 I just finished a task — your turn!"
        ];
        if (isset($presets[$message])) {
            $message = $presets[$message];
        }
        if (empty($message)) {
            echo json_encode(['success' => false, 'message' => 'Message cannot be empty']);
            exit();
        }
        // Rate limit: max 10 nudges per day to same person
        $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM buddy_nudges 
            WHERE sender_id = ? AND receiver_id = ? AND DATE(created_at) = CURDATE()");
        $stmt->bind_param("ii", $user_id, $buddy['buddy_id']);
        $stmt->execute();
        $count = $stmt->get_result()->fetch_assoc()['cnt'];
        if ($count >= 10) {
            echo json_encode(['success' => false, 'message' => 'Daily nudge limit reached (10/day)']);
            exit();
        }
        $stmt = $conn->prepare("INSERT INTO buddy_nudges (sender_id, receiver_id, message) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $user_id, $buddy['buddy_id'], $message);
        $stmt->execute();
        echo json_encode(['success' => true, 'message' => 'Nudge sent!']);
        exit();
    }

    // Mark nudges as read
    if ($action === 'mark_nudges_read') {
        $stmt = $conn->prepare("UPDATE buddy_nudges SET is_read = 1 WHERE receiver_id = ? AND is_read = 0");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        echo json_encode(['success' => true]);
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

    // Accept buddy request
    if ($action === 'accept_request') {
        $request_id = intval($_POST['request_id'] ?? 0);
        // Verify the request is for this user and is pending
        $stmt = $conn->prepare("SELECT * FROM study_buddies WHERE id = ? AND partner_id = ? AND status = 'pending'");
        $stmt->bind_param("ii", $request_id, $user_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        if ($request) {
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
        $stmt = $conn->prepare("DELETE FROM study_buddies WHERE (requester_id = ? OR partner_id = ?) AND status = 'accepted'");
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $success = 'You\'ve been unpaired from your study buddy.';
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
$nudges = getBuddyNudges($user_id, $conn, 15);
$unread_nudges = getUnreadNudgeCount($user_id, $conn);
$my_progress = getBuddyProgress($user_id, $conn);

// If has buddy, get their progress
$buddy_progress = null;
if ($buddy_pair) {
    $buddy_progress = getBuddyProgress($buddy_pair['buddy_id'], $conn);
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
        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> <?php echo $success; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <?php if ($buddy_pair): ?>
    <!-- ===== PAIRED STATE ===== -->
    <div class="row g-4">
        <!-- Buddy Card -->
        <div class="col-lg-4">
            <div class="card buddy-profile-card">
                <div class="card-body text-center py-4">
                    <div class="buddy-pair-badge"><i class="fas fa-link"></i> Paired</div>
                    <?php 
                        $b = $buddy_pair['buddy'];
                        $b_initials = strtoupper(substr($b['name'], 0, 1));
                    ?>
                    <div class="buddy-avatar mx-auto mb-3">
                        <?php if (!empty($b['profile_photo'])): ?>
                            <img src="<?php echo BASE_URL . $b['profile_photo']; ?>" alt="<?php echo htmlspecialchars($b['name']); ?>">
                        <?php else: ?>
                            <span><?php echo $b_initials; ?></span>
                        <?php endif; ?>
                    </div>
                    <h5 class="fw-700 mb-1"><?php echo htmlspecialchars($b['name']); ?></h5>
                    <p class="text-muted mb-1" style="font-size: 12.5px;"><?php echo htmlspecialchars($b['course'] ?? 'Student'); ?></p>
                    <?php if ($b['year_level']): ?>
                        <span class="badge bg-secondary" style="font-size: 11px;">Year <?php echo $b['year_level']; ?></span>
                    <?php endif; ?>
                    <div class="mt-3">
                        <small class="text-muted">Paired since <?php echo date('M d, Y', strtotime($buddy_pair['created_at'])); ?></small>
                    </div>
                    <hr>
                    <form method="POST" onsubmit="return StudifyConfirm.form(event, 'Unpair Study Buddy', 'Are you sure you want to unpair? You can pair with someone else afterwards.', 'warning')">
                        <input type="hidden" name="action" value="unpair">
                        <?php echo csrfTokenField(); ?>
                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                            <i class="fas fa-unlink"></i> Unpair
                        </button>
                    </form>
                </div>
            </div>

            <!-- Send Nudge -->
            <div class="card mt-4">
                <div class="card-body">
                    <h6 class="fw-700 mb-3"><i class="fas fa-paper-plane text-primary"></i> Send a Nudge</h6>
                    <div class="nudge-presets">
                        <button class="nudge-preset-btn" onclick="sendNudge('wave')">👋 Check In</button>
                        <button class="nudge-preset-btn" onclick="sendNudge('motivate')">💪 Motivate</button>
                        <button class="nudge-preset-btn" onclick="sendNudge('reminder')">⏰ Reminder</button>
                        <button class="nudge-preset-btn" onclick="sendNudge('celebrate')">🎉 Celebrate</button>
                        <button class="nudge-preset-btn" onclick="sendNudge('challenge')">🔥 Challenge</button>
                    </div>
                    <div class="input-group mt-3">
                        <input type="text" class="form-control form-control-sm" id="customNudge" placeholder="Or type a custom message..." maxlength="200">
                        <button class="btn btn-primary btn-sm" onclick="sendNudge(document.getElementById('customNudge').value)">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Comparison -->
        <div class="col-lg-8">
            <!-- Side by Side Stats -->
            <div class="card mb-4">
                <div class="card-body">
                    <h6 class="fw-700 mb-3"><i class="fas fa-chart-bar text-info"></i> Accountability Dashboard</h6>
                    <div class="buddy-compare">
                        <!-- You -->
                        <div class="buddy-compare-col">
                            <div class="buddy-compare-header you-header">
                                <i class="fas fa-user"></i> You
                            </div>
                            <div class="buddy-stat-grid">
                                <div class="buddy-stat-item">
                                    <div class="buddy-stat-value"><?php echo $my_progress['completion_pct']; ?>%</div>
                                    <div class="buddy-stat-label">Completed</div>
                                    <div class="buddy-progress-bar">
                                        <div class="buddy-progress-fill you-fill" style="width: <?php echo $my_progress['completion_pct']; ?>%"></div>
                                    </div>
                                </div>
                                <div class="buddy-stat-item">
                                    <div class="buddy-stat-value"><?php echo $my_progress['streak']; ?> 🔥</div>
                                    <div class="buddy-stat-label">Day Streak</div>
                                </div>
                                <div class="buddy-stat-item">
                                    <div class="buddy-stat-value"><?php echo round($my_progress['week_minutes'] / 60, 1); ?>h</div>
                                    <div class="buddy-stat-label">This Week</div>
                                </div>
                                <div class="buddy-stat-item">
                                    <div class="buddy-stat-value"><?php echo $my_progress['week_sessions']; ?></div>
                                    <div class="buddy-stat-label">Sessions</div>
                                </div>
                            </div>
                        </div>
                        <!-- VS -->
                        <div class="buddy-compare-vs">VS</div>
                        <!-- Buddy -->
                        <div class="buddy-compare-col">
                            <div class="buddy-compare-header buddy-header">
                                <i class="fas fa-user-friends"></i> <?php echo htmlspecialchars(explode(' ', $b['name'])[0]); ?>
                            </div>
                            <div class="buddy-stat-grid">
                                <div class="buddy-stat-item">
                                    <div class="buddy-stat-value"><?php echo $buddy_progress['completion_pct']; ?>%</div>
                                    <div class="buddy-stat-label">Completed</div>
                                    <div class="buddy-progress-bar">
                                        <div class="buddy-progress-fill buddy-fill" style="width: <?php echo $buddy_progress['completion_pct']; ?>%"></div>
                                    </div>
                                </div>
                                <div class="buddy-stat-item">
                                    <div class="buddy-stat-value"><?php echo $buddy_progress['streak']; ?> 🔥</div>
                                    <div class="buddy-stat-label">Day Streak</div>
                                </div>
                                <div class="buddy-stat-item">
                                    <div class="buddy-stat-value"><?php echo round($buddy_progress['week_minutes'] / 60, 1); ?>h</div>
                                    <div class="buddy-stat-label">This Week</div>
                                </div>
                                <div class="buddy-stat-item">
                                    <div class="buddy-stat-value"><?php echo $buddy_progress['week_sessions']; ?></div>
                                    <div class="buddy-stat-label">Sessions</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php if ($buddy_progress['due_soon'] > 0): ?>
                        <div class="alert alert-warning mt-3 mb-0 py-2" style="font-size: 12.5px;">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Your buddy has <strong><?php echo $buddy_progress['due_soon']; ?></strong> task<?php echo $buddy_progress['due_soon'] > 1 ? 's' : ''; ?> due within 3 days — send them a nudge!
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Nudge Inbox -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-700 mb-0">
                            <i class="fas fa-bell text-warning"></i> Nudge Inbox
                            <?php if ($unread_nudges > 0): ?>
                                <span class="badge bg-danger" style="font-size: 10px;"><?php echo $unread_nudges; ?> new</span>
                            <?php endif; ?>
                        </h6>
                        <?php if ($unread_nudges > 0): ?>
                            <button class="btn btn-sm btn-outline-secondary" onclick="markNudgesRead()">
                                <i class="fas fa-check-double"></i> Mark all read
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php if (empty($nudges)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox" style="font-size: 28px; opacity: 0.3;"></i>
                            <p class="mt-2 mb-0" style="font-size: 13px;">No nudges yet. Send one to your buddy!</p>
                        </div>
                    <?php else: ?>
                        <div class="nudge-list">
                            <?php foreach ($nudges as $nudge): ?>
                                <div class="nudge-item <?php echo !$nudge['is_read'] ? 'nudge-unread' : ''; ?>">
                                    <div class="nudge-sender-avatar">
                                        <?php if (!empty($nudge['sender_photo'])): ?>
                                            <img src="<?php echo BASE_URL . $nudge['sender_photo']; ?>" alt="">
                                        <?php else: ?>
                                            <span><?php echo strtoupper(substr($nudge['sender_name'], 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="nudge-content">
                                        <strong><?php echo htmlspecialchars($nudge['sender_name']); ?></strong>
                                        <p class="mb-0"><?php echo htmlspecialchars($nudge['message']); ?></p>
                                        <small class="text-muted"><?php echo date('M d, g:i A', strtotime($nudge['created_at'])); ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php else: ?>
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
        </div>
    </div>
    <?php endif; ?>

<script>
function sendNudge(message) {
    if (!message || !message.trim()) {
        showToast('Please enter a message or select a preset.', 'warning');
        return;
    }
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=send_nudge&message=${encodeURIComponent(message)}&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            document.getElementById('customNudge').value = '';
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(() => showToast('Network error', 'error'));
}

function markNudgesRead() {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch(window.location.href, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: `action=mark_nudges_read&csrf_token=${csrfToken}`
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.nudge-unread').forEach(el => el.classList.remove('nudge-unread'));
            document.querySelectorAll('.badge.bg-danger').forEach(el => el.remove());
            showToast('All nudges marked as read', 'success');
        }
    });
}
</script>

<?php include '../includes/footer.php'; ?>
