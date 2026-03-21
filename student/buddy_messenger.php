<?php
/**
 * STUDIFY – Study Buddy Messenger
 * Messenger-style: conversation list → click → full chat with bubbles
 * Included from study_buddy.php when buddies are paired
 *
 * Available vars: $buddy_pair, $my_progress, $buddy_progress, $user_id, $user, $conn
 */
$b = $buddy_pair['buddy'];
$b_initials = strtoupper(substr($b['name'], 0, 1));
$is_online  = isUserOnline($buddy_pair['buddy_id'], $conn);
$buddy_first = htmlspecialchars(explode(' ', $b['name'])[0]);
$buddy_first_js = htmlspecialchars(addslashes(explode(' ', $b['name'])[0]), ENT_QUOTES);
$unread_count = getUnreadBuddyMessageCount($user_id, $conn);

// Last message for conversation preview
$last_msgs = getChatMessages($user_id, $buddy_pair['buddy_id'], $conn, 1);
$last_msg  = !empty($last_msgs) ? $last_msgs[count($last_msgs) - 1] : null;
$last_preview = '';
$last_time    = '';
if ($last_msg) {
    $is_mine = intval($last_msg['sender_id']) === $user_id;
    $prefix  = $is_mine ? 'You: ' : '';
    $last_preview = $prefix . mb_strimwidth($last_msg['message'], 0, 80, '…');
    $last_time = date('g:i A', strtotime($last_msg['created_at']));
}
?>

<!-- ===== BUDDY INFO BAR ===== -->
<div class="buddy-bar">
    <div class="buddy-bar-left">
        <div class="buddy-bar-avatar">
            <?php if (!empty($b['profile_photo'])): ?>
                <img src="<?php echo BASE_URL . $b['profile_photo']; ?>" alt="">
            <?php else: ?>
                <span><?php echo $b_initials; ?></span>
            <?php endif; ?>
            <span class="buddy-bar-dot <?php echo $is_online ? 'is-online' : ''; ?>" id="buddyDotBar"></span>
        </div>
        <div class="buddy-bar-info">
            <strong><?php echo htmlspecialchars($b['name']); ?></strong>
            <small id="buddyStatusBar"><?php echo $is_online ? 'Active now' : 'Offline'; ?></small>
        </div>
        <span class="buddy-pair-badge"><i class="fas fa-link"></i> Paired</span>
    </div>
    <div class="buddy-bar-right">
        <div class="dropdown d-inline">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-ellipsis-v"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <a class="dropdown-item" href="#" data-bs-toggle="modal" data-bs-target="#reportBuddyModal">
                        <i class="fas fa-flag text-warning"></i> Report <?php echo $buddy_first; ?>
                    </a>
                </li>
                <li>
                    <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Block User', 'Are you sure you want to block <?php echo $buddy_first_js; ?>? This will unpair you and prevent future requests.', 'danger')">
                        <input type="hidden" name="action" value="block_buddy">
                        <input type="hidden" name="block_id" value="<?php echo $buddy_pair['buddy_id']; ?>">
                        <?php echo csrfTokenField(); ?>
                        <button type="submit" class="dropdown-item text-danger"><i class="fas fa-ban"></i> Block <?php echo $buddy_first; ?></button>
                    </form>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Unpair Study Buddy', 'Are you sure you want to unpair from <?php echo $buddy_first_js; ?>? Your chat history will be preserved.', 'warning')">
                        <input type="hidden" name="action" value="unpair">
                        <?php echo csrfTokenField(); ?>
                        <button type="submit" class="dropdown-item text-danger"><i class="fas fa-unlink"></i> Unpair</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- Report Buddy Modal -->
<div class="modal fade" id="reportBuddyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h6 class="modal-title fw-700"><i class="fas fa-flag text-warning"></i> Report <?php echo htmlspecialchars($b['name']); ?></h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="report_buddy">
                    <input type="hidden" name="report_id" value="<?php echo $buddy_pair['buddy_id']; ?>">
                    <?php echo csrfTokenField(); ?>
                    <div class="mb-3">
                        <label class="form-label fw-600" style="font-size: 13px;">Reason for report</label>
                        <select name="reason" class="form-select" required>
                            <option value="">Select a reason…</option>
                            <option value="harassment">Harassment or bullying</option>
                            <option value="spam">Spam or unwanted messages</option>
                            <option value="inappropriate">Inappropriate content</option>
                            <option value="impersonation">Impersonation</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600" style="font-size: 13px;">Additional details (optional)</label>
                        <textarea name="details" class="form-control" rows="3" maxlength="500" placeholder="Describe what happened…"></textarea>
                    </div>
                    <small class="text-muted"><i class="fas fa-shield-alt"></i> Reports are reviewed by admins. Your identity is kept confidential.</small>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm"><i class="fas fa-flag"></i> Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ===== CONVERSATION LIST VIEW (default) ===== -->
<div id="convoListView">

    <!-- Buddy Pair Streak Banner -->
    <?php if ($my_enhanced && $my_enhanced['pair_streak'] > 0): ?>
    <div class="buddy-streak-banner mb-3">
        <div class="streak-fire">🔥</div>
        <div class="streak-info">
            <strong><?php echo $my_enhanced['pair_streak']; ?>-Day Buddy Streak!</strong>
            <small>You and <?php echo $buddy_first; ?> have been active together</small>
        </div>
    </div>
    <?php endif; ?>

    <!-- Daily Check-in Card -->
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="fw-700 mb-3"><i class="fas fa-calendar-check text-success"></i> Daily Check-in</h6>
            <div class="row g-3">
                <div class="col-6">
                    <div class="checkin-box <?php echo $my_checkin && $my_checkin['completed'] ? 'checked-in' : ''; ?>" id="myCheckinBox">
                        <div class="checkin-user"><i class="fas fa-user"></i> You</div>
                        <?php if ($my_checkin): ?>
                            <div class="checkin-status <?php echo $my_checkin['completed'] ? 'done' : 'skipped'; ?>">
                                <?php echo $my_checkin['completed'] ? '<i class="fas fa-check-circle"></i> Done' : '<i class="fas fa-times-circle"></i> Skipped'; ?>
                            </div>
                            <?php if ($my_checkin['note']): ?>
                                <small class="checkin-note">"<?php echo htmlspecialchars($my_checkin['note']); ?>"</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="checkin-pending">
                                <button class="btn btn-success btn-sm" onclick="BuddyEnhanced.checkin(true)"><i class="fas fa-check"></i> Done</button>
                                <button class="btn btn-outline-secondary btn-sm" onclick="BuddyEnhanced.checkin(false)"><i class="fas fa-times"></i> Skip</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-6">
                    <div class="checkin-box <?php echo $buddy_checkin && $buddy_checkin['completed'] ? 'checked-in' : ''; ?>">
                        <div class="checkin-user"><i class="fas fa-user-friends"></i> <?php echo $buddy_first; ?></div>
                        <?php if ($buddy_checkin): ?>
                            <div class="checkin-status <?php echo $buddy_checkin['completed'] ? 'done' : 'skipped'; ?>">
                                <?php echo $buddy_checkin['completed'] ? '<i class="fas fa-check-circle"></i> Done' : '<i class="fas fa-times-circle"></i> Skipped'; ?>
                            </div>
                            <?php if ($buddy_checkin['note']): ?>
                                <small class="checkin-note">"<?php echo htmlspecialchars($buddy_checkin['note']); ?>"</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="checkin-status pending"><i class="fas fa-hourglass-half"></i> Waiting</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Weekly Goals Comparison -->
    <div class="card mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-700 mb-0"><i class="fas fa-bullseye text-primary"></i> Weekly Goals</h6>
                <button class="btn btn-outline-primary btn-sm" onclick="BuddyEnhanced.showGoalModal()" style="font-size:11px;">
                    <i class="fas fa-edit"></i> Set Goal
                </button>
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <div class="goal-box you-goal">
                        <div class="goal-header"><i class="fas fa-user"></i> You</div>
                        <div class="goal-progress">
                            <div class="goal-numbers"><?php echo $my_weekly_goal['completed']; ?> / <?php echo $my_weekly_goal['target']; ?> tasks</div>
                            <div class="goal-bar"><div class="goal-fill" style="width:<?php echo $my_weekly_goal['progress_pct']; ?>%"></div></div>
                            <div class="goal-pct"><?php echo $my_weekly_goal['progress_pct']; ?>%</div>
                        </div>
                    </div>
                </div>
                <div class="col-6">
                    <div class="goal-box buddy-goal">
                        <div class="goal-header"><i class="fas fa-user-friends"></i> <?php echo $buddy_first; ?></div>
                        <div class="goal-progress">
                            <div class="goal-numbers"><?php echo $buddy_weekly_goal['completed']; ?> / <?php echo $buddy_weekly_goal['target']; ?> tasks</div>
                            <div class="goal-bar"><div class="goal-fill" style="width:<?php echo $buddy_weekly_goal['progress_pct']; ?>%"></div></div>
                            <div class="goal-pct"><?php echo $buddy_weekly_goal['progress_pct']; ?>%</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Weekly Stats Comparison -->
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="fw-700 mb-3"><i class="fas fa-chart-line text-info"></i> This Week's Activity</h6>
            <div class="buddy-compare">
                <div class="buddy-compare-col">
                    <div class="buddy-compare-header you-header"><i class="fas fa-user"></i> You</div>
                    <div class="buddy-stat-grid">
                        <div class="buddy-stat-item">
                            <div class="buddy-stat-value"><?php echo $my_enhanced['week_tasks']; ?></div>
                            <div class="buddy-stat-label">Tasks Done</div>
                        </div>
                        <div class="buddy-stat-item">
                            <div class="buddy-stat-value"><?php echo $my_enhanced['streak']; ?> 🔥</div>
                            <div class="buddy-stat-label">Streak</div>
                        </div>
                        <div class="buddy-stat-item">
                            <div class="buddy-stat-value"><?php echo $my_enhanced['week_hours']; ?>h</div>
                            <div class="buddy-stat-label">Study Time</div>
                        </div>
                    </div>
                </div>
                <div class="buddy-compare-vs"><span>VS</span></div>
                <div class="buddy-compare-col">
                    <div class="buddy-compare-header buddy-header"><i class="fas fa-user-friends"></i> <?php echo $buddy_first; ?></div>
                    <div class="buddy-stat-grid">
                        <div class="buddy-stat-item">
                            <div class="buddy-stat-value"><?php echo $buddy_enhanced['week_tasks']; ?></div>
                            <div class="buddy-stat-label">Tasks Done</div>
                        </div>
                        <div class="buddy-stat-item">
                            <div class="buddy-stat-value"><?php echo $buddy_enhanced['streak']; ?> 🔥</div>
                            <div class="buddy-stat-label">Streak</div>
                        </div>
                        <div class="buddy-stat-item">
                            <div class="buddy-stat-value"><?php echo $buddy_enhanced['week_hours']; ?>h</div>
                            <div class="buddy-stat-label">Study Time</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Progress Comparison Chart -->
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="fw-700 mb-3"><i class="fas fa-chart-bar text-warning"></i> 4-Week Progress</h6>
            <div style="height: 160px;">
                <canvas id="buddyComparisonChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Conversation Entry -->
    <div class="card mb-3">
        <div class="card-body p-0">
            <div class="convo-card-hdr">
                <span><i class="fas fa-comments text-primary"></i> Messages</span>
                <?php if ($unread_count > 0): ?>
                    <span class="badge bg-primary rounded-pill" id="convoHeaderBadge"><?php echo $unread_count; ?></span>
                <?php else: ?>
                    <span class="badge bg-primary rounded-pill" id="convoHeaderBadge" style="display:none;">0</span>
                <?php endif; ?>
            </div>
            <div class="convo-item" id="convoItem" onclick="BuddyMessenger.openChat()">
                <div class="convo-avatar">
                    <?php if (!empty($b['profile_photo'])): ?>
                        <img src="<?php echo BASE_URL . $b['profile_photo']; ?>" alt="">
                    <?php else: ?>
                        <span><?php echo $b_initials; ?></span>
                    <?php endif; ?>
                    <span class="convo-dot <?php echo $is_online ? 'is-online' : ''; ?>" id="convoDot"></span>
                </div>
                <div class="convo-body">
                    <div class="convo-head">
                        <span class="convo-name"><?php echo htmlspecialchars($b['name']); ?></span>
                        <span class="convo-time" id="convoTime"><?php echo $last_time; ?></span>
                    </div>
                    <div class="convo-preview" id="convoPreview"><?php echo $last_preview ?: '<em>Start a conversation with ' . $buddy_first . ' 👋</em>'; ?></div>
                </div>
                <?php if ($unread_count > 0): ?>
                    <span class="convo-unread" id="convoUnread"><?php echo $unread_count; ?></span>
                <?php else: ?>
                    <span class="convo-unread" id="convoUnread" style="display:none;">0</span>
                <?php endif; ?>
                <span class="convo-arrow"><i class="fas fa-chevron-right"></i></span>
            </div>
        </div>
    </div>

    <!-- Quick Nudge -->
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="fw-700 mb-3"><i class="fas fa-hand-peace text-primary"></i> Quick Nudge</h6>
            <p class="text-muted mb-3" style="font-size: 12px;">Send a quick nudge to motivate your buddy!</p>
            <div class="nudge-presets">
                <button class="nudge-preset-btn" onclick="BuddyMessenger.sendNudge('wave')">👋 Check In</button>
                <button class="nudge-preset-btn" onclick="BuddyMessenger.sendNudge('motivate')">💪 Motivate</button>
                <button class="nudge-preset-btn" onclick="BuddyMessenger.sendNudge('reminder')">⏰ Remind</button>
                <button class="nudge-preset-btn" onclick="BuddyMessenger.sendNudge('celebrate')">🎉 Celebrate</button>
                <button class="nudge-preset-btn" onclick="BuddyMessenger.sendNudge('challenge')">🔥 Challenge</button>
            </div>
        </div>
    </div>

    <!-- Scheduled Nudges -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="fw-700 mb-0"><i class="fas fa-clock text-secondary"></i> Scheduled Nudges</h6>
                <button class="btn btn-outline-secondary btn-sm" onclick="BuddyEnhanced.showNudgeModal()" style="font-size:11px;">
                    <i class="fas fa-plus"></i> Add
                </button>
            </div>
            <div id="scheduledNudgesList">
                <?php if (empty($scheduled_nudges)): ?>
                    <p class="text-muted text-center mb-0" style="font-size:12px;">No scheduled nudges yet.</p>
                <?php else: ?>
                    <?php
                    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                    foreach ($scheduled_nudges as $nudge): ?>
                    <div class="scheduled-nudge-item" data-id="<?php echo $nudge['id']; ?>">
                        <div class="nudge-time">
                            <span class="nudge-day"><?php echo $days[$nudge['day_of_week']]; ?></span>
                            <span class="nudge-hour"><?php echo date('g:i A', strtotime($nudge['nudge_time'])); ?></span>
                        </div>
                        <div class="nudge-message"><?php echo htmlspecialchars($nudge['message']); ?></div>
                        <div class="nudge-actions">
                            <button class="btn btn-sm <?php echo $nudge['is_active'] ? 'btn-success' : 'btn-outline-secondary'; ?>"
                                    onclick="BuddyEnhanced.toggleNudge(<?php echo $nudge['id']; ?>)" title="Toggle">
                                <i class="fas fa-<?php echo $nudge['is_active'] ? 'check' : 'pause'; ?>"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="BuddyEnhanced.deleteNudge(<?php echo $nudge['id']; ?>)" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div><!-- /convoListView -->


<!-- ===== CHAT VIEW (hidden until conversation clicked) ===== -->
<div id="chatView" style="display:none;">
    <div class="chat-panel">
        <!-- Chat Header -->
        <div class="chat-header">
            <button class="chat-back-btn" onclick="BuddyMessenger.closeChat()" title="Back to conversations">
                <i class="fas fa-arrow-left"></i>
            </button>
            <div class="chat-header-avatar">
                <?php if (!empty($b['profile_photo'])): ?>
                    <img src="<?php echo BASE_URL . $b['profile_photo']; ?>" alt="">
                <?php else: ?>
                    <span><?php echo $b_initials; ?></span>
                <?php endif; ?>
                <span class="chat-header-dot <?php echo $is_online ? 'is-online' : ''; ?>" id="chatDot"></span>
            </div>
            <div class="chat-header-info">
                <strong><?php echo htmlspecialchars($b['name']); ?></strong>
                <small id="chatStatusText"><?php echo $is_online ? 'Active now' : 'Offline'; ?></small>
            </div>
        </div>

        <!-- Load More -->
        <div class="chat-load-more" id="chatLoadMore" style="display:none;">
            <button onclick="BuddyMessenger.loadMore()" id="chatLoadMoreBtn">
                <i class="fas fa-angle-up"></i> Load older messages
            </button>
        </div>

        <!-- Messages -->
        <div class="chat-messages" id="chatMessages">
            <div class="chat-loader" id="chatLoader">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                <span>Loading messages…</span>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div class="chat-typing" id="chatTyping" style="display:none;">
            <div class="chat-typing-bubble">
                <span class="chat-typing-dots"><span></span><span></span><span></span></span>
                <?php echo $buddy_first; ?> is typing…
            </div>
        </div>

        <!-- Reply Context -->
        <div class="chat-reply-bar" id="chatReplyBar" style="display:none;">
            <i class="fas fa-reply text-primary"></i>
            <span id="chatReplyText"></span>
            <button onclick="BuddyMessenger.cancelReply()"><i class="fas fa-times"></i></button>
        </div>

        <!-- Input Area -->
        <div class="chat-input-area">
            <div class="chat-nudge-row">
                <button class="chat-nudge-btn" onclick="BuddyMessenger.sendNudge('wave')" title="Check In">👋</button>
                <button class="chat-nudge-btn" onclick="BuddyMessenger.sendNudge('motivate')" title="Motivate">💪</button>
                <button class="chat-nudge-btn" onclick="BuddyMessenger.sendNudge('reminder')" title="Remind">⏰</button>
                <button class="chat-nudge-btn" onclick="BuddyMessenger.sendNudge('celebrate')" title="Celebrate">🎉</button>
                <button class="chat-nudge-btn" onclick="BuddyMessenger.sendNudge('challenge')" title="Challenge">🔥</button>
            </div>
            <div class="chat-input-row">
                <textarea id="chatInput" placeholder="Type a message…" rows="1" maxlength="1000"></textarea>
                <button id="chatSendBtn" title="Send"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
    </div>

    <!-- Chat Empty State -->
    <div class="chat-empty" id="chatEmpty" style="display:none;">
        <div class="chat-empty-icon"><i class="fas fa-paper-plane"></i></div>
        <p>No messages yet</p>
        <span>Say hi to <?php echo $buddy_first; ?>! 👋</span>
    </div>
</div><!-- /chatView -->


<script>
const BuddyMessenger = {
    buddyId:      <?php echo $buddy_pair['buddy_id']; ?>,
    buddyName:    <?php echo json_encode($buddy_first); ?>,
    userId:       <?php echo $user_id; ?>,
    userName:     <?php echo json_encode($user['name']); ?>,
    buddyPhoto:   <?php echo json_encode(!empty($b['profile_photo']) ? BASE_URL . $b['profile_photo'] : ''); ?>,
    buddyInitial: <?php echo json_encode($b_initials); ?>,

    lastMessageId: 0,
    oldestMessageId: null,
    pollTimer: null,
    heartbeatTimer: null,
    replyToId: null,
    hasMore: true,
    chatOpen: false,
    lastDateLabel: '',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',

    /* ── Init ── */
    init() {
        this.bindEvents();
        this.startHeartbeat();
        this.heartbeat();
    },

    bindEvents() {
        const input = document.getElementById('chatInput');
        if (input) {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.send(); }
            });
            let typingTimeout;
            input.addEventListener('input', () => {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 120) + 'px';
                clearTimeout(typingTimeout);
                this.ajax('typing');
                typingTimeout = setTimeout(() => { this.ajax('stop_typing'); }, 3000);
            });
        }
        document.getElementById('chatSendBtn')?.addEventListener('click', () => this.send());
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.chatOpen) this.closeChat();
        });
    },

    /* ── View Switching ── */
    openChat() {
        document.getElementById('convoListView').style.display = 'none';
        document.getElementById('chatView').style.display = 'block';
        this.chatOpen = true;
        this.loadMessages();
        this.startPolling();
    },

    closeChat() {
        document.getElementById('chatView').style.display = 'none';
        document.getElementById('convoListView').style.display = '';
        this.chatOpen = false;
        this.stopPolling();
        // Clear chat messages for fresh load next time
        const container = document.getElementById('chatMessages');
        container.innerHTML = '<div class="chat-loader" id="chatLoader"><div class="spinner-border spinner-border-sm text-primary" role="status"></div><span>Loading messages…</span></div>';
        document.getElementById('chatEmpty').style.display = 'none';
        document.getElementById('chatLoadMore').style.display = 'none';
        this.oldestMessageId = null;
        this.hasMore = true;
        this.lastDateLabel = '';
        this.refreshPreview();
    },

    /* ── Load Messages ── */
    async loadMessages() {
        const container = document.getElementById('chatMessages');
        const loader = document.getElementById('chatLoader');
        const empty = document.getElementById('chatEmpty');
        try {
            const data = await this.ajax('get_messages', { limit: 50 });
            if (!data.success) return;
            loader.style.display = 'none';

            if (data.messages.length === 0) {
                empty.style.display = 'flex';
                return;
            }
            empty.style.display = 'none';

            this.lastDateLabel = '';
            data.messages.forEach(msg => {
                this.appendDateSep(msg, container);
                container.appendChild(this.createBubble(msg));
            });

            const last = data.messages[data.messages.length - 1];
            const first = data.messages[0];
            this.lastMessageId = parseInt(last.id);
            this.oldestMessageId = parseInt(first.id);
            this.hasMore = data.messages.length >= 50;
            if (this.hasMore) document.getElementById('chatLoadMore').style.display = 'block';

            this.scrollToBottom();
        } catch (e) {
            console.error('Load failed:', e);
            loader.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> Failed to load</span>';
        }
    },

    /* ── Load More ── */
    async loadMore() {
        if (!this.hasMore) return;
        const btn = document.getElementById('chatLoadMoreBtn');
        if (btn) btn.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Loading…';
        try {
            const data = await this.ajax('get_messages', { limit: 30, before_id: this.oldestMessageId });
            if (!data.success || data.messages.length === 0) {
                this.hasMore = false;
                document.getElementById('chatLoadMore').style.display = 'none';
                return;
            }
            const container = document.getElementById('chatMessages');
            const prevH = container.scrollHeight;

            const tempLast = this.lastDateLabel;
            this.lastDateLabel = '';
            const frag = document.createDocumentFragment();
            data.messages.forEach(msg => {
                this.appendDateSep(msg, frag);
                frag.appendChild(this.createBubble(msg));
            });
            container.insertBefore(frag, container.firstChild);
            this.lastDateLabel = tempLast;
            this.deduplicateDateSeps(container);
            container.scrollTop = container.scrollHeight - prevH;

            this.oldestMessageId = parseInt(data.messages[0].id);
            if (data.messages.length < 30) {
                this.hasMore = false;
                document.getElementById('chatLoadMore').style.display = 'none';
            }
        } catch (e) { console.error(e); }
        finally { if (btn) btn.innerHTML = '<i class="fas fa-angle-up"></i> Load older messages'; }
    },

    /* ── Polling ── */
    startPolling() { this.pollTimer = setInterval(() => this.poll(), 3000); },
    stopPolling() { clearInterval(this.pollTimer); this.pollTimer = null; },
    startHeartbeat() { this.heartbeatTimer = setInterval(() => this.heartbeat(), 30000); },
    async heartbeat() { try { await this.ajax('heartbeat'); } catch(e) {} },

    async poll() {
        try {
            const data = await this.ajax('get_new_messages', { after_id: this.lastMessageId });
            if (!data.success) return;

            const typing = document.getElementById('chatTyping');
            if (typing) typing.style.display = data.buddy_typing ? 'block' : 'none';
            this.updateOnlineUI(data.buddy_online);
            if (data.last_read_id) this.updateReadReceipts(data.last_read_id);

            if (data.messages && data.messages.length > 0) {
                document.getElementById('chatEmpty').style.display = 'none';
                const container = document.getElementById('chatMessages');
                const wasBottom = this.isAtBottom();
                data.messages.forEach(msg => {
                    this.appendDateSep(msg, container);
                    container.appendChild(this.createBubble(msg, true));
                    this.lastMessageId = Math.max(this.lastMessageId, parseInt(msg.id));
                });
                if (wasBottom) this.scrollToBottom();
                if (data.messages.some(m => parseInt(m.sender_id) !== this.userId)) this.playSound();
            }
        } catch (e) { console.error('Poll error:', e); }
    },

    /* ── Refresh Preview ── */
    async refreshPreview() {
        try {
            const data = await this.ajax('get_messages', { limit: 1 });
            if (data.success && data.messages.length > 0) {
                const msg = data.messages[data.messages.length - 1];
                const isMine = parseInt(msg.sender_id) === this.userId;
                const preview = (isMine ? 'You: ' : '') + this.truncate(msg.message, 70);
                const d = new Date(msg.created_at);
                document.getElementById('convoPreview').textContent = preview;
                document.getElementById('convoTime').textContent = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                this.lastMessageId = Math.max(this.lastMessageId, parseInt(msg.id));
            }
            const ub = document.getElementById('convoUnread');
            const hb = document.getElementById('convoHeaderBadge');
            if (ub) { ub.style.display = 'none'; ub.textContent = '0'; }
            if (hb) { hb.style.display = 'none'; hb.textContent = '0'; }
        } catch (e) {}
    },

    /* ── Create Bubble ── */
    createBubble(msg, isNew = false) {
        const isMine = parseInt(msg.sender_id) === this.userId;
        const isNudge = msg.message_type === 'nudge';
        const d = new Date(msg.created_at);
        const timeStr = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

        const wrap = document.createElement('div');
        wrap.className = 'msg-wrap' + (isMine ? ' mine' : ' theirs') + (isNudge ? ' nudge-msg' : '') + (isNew ? ' msg-new' : '');
        wrap.dataset.msgId = msg.id;

        let avatarHtml = '';
        if (!isMine) {
            avatarHtml = this.buddyPhoto
                ? '<div class="msg-avatar"><img src="' + this.esc(this.buddyPhoto) + '" alt=""></div>'
                : '<div class="msg-avatar"><span>' + this.esc(this.buddyInitial) + '</span></div>';
        }

        let replyHtml = '';
        if (msg.reply_message) {
            const rSender = parseInt(msg.reply_sender_id) === this.userId ? 'You' : (msg.reply_sender_name || this.buddyName);
            replyHtml = '<div class="msg-reply-ctx"><i class="fas fa-reply"></i> <strong>' + this.esc(rSender) + '</strong> ' + this.esc(this.truncate(msg.reply_message, 50)) + '</div>';
        }

        let readHtml = '';
        if (isMine) {
            readHtml = '<span class="msg-read' + (parseInt(msg.is_read) ? ' seen' : '') + '" data-msg-id="' + msg.id + '">' +
                (parseInt(msg.is_read) ? '<i class="fas fa-check-double"></i>' : '<i class="fas fa-check"></i>') + '</span>';
        }

        const nudgeTag = isNudge ? '<span class="msg-nudge-tag"><i class="fas fa-bolt"></i></span> ' : '';

        const safeMsg = this.esc(this.truncate(msg.message, 60)).replace(/'/g, '&#39;');
        const safeSender = isMine ? 'You' : this.esc(msg.sender_name || this.buddyName).replace(/'/g, '&#39;');
        const actions = '<div class="msg-actions">' +
            '<button onclick="event.stopPropagation();BuddyMessenger.replyTo(' + msg.id + ',\'' + safeMsg + '\',\'' + safeSender + '\')" title="Reply"><i class="fas fa-reply"></i></button>' +
            (isMine ? '<button onclick="event.stopPropagation();BuddyMessenger.deleteMessage(' + msg.id + ')" title="Delete"><i class="fas fa-trash-alt"></i></button>' : '') +
            '</div>';

        wrap.innerHTML =
            avatarHtml +
            '<div class="msg-bubble">' +
                replyHtml +
                '<div class="msg-text">' + nudgeTag + this.esc(msg.message) + '</div>' +
                '<div class="msg-meta">' + '<span class="msg-time">' + timeStr + '</span>' + readHtml + '</div>' +
                actions +
            '</div>';

        return wrap;
    },

    /* ── Date Separators ── */
    appendDateSep(msg, container) {
        const d = new Date(msg.created_at);
        const label = this.formatDateLabel(d);
        if (label !== this.lastDateLabel) {
            this.lastDateLabel = label;
            const sep = document.createElement('div');
            sep.className = 'chat-date-sep';
            sep.dataset.date = label;
            sep.innerHTML = '<span>' + label + '</span>';
            container.appendChild(sep);
        }
    },

    deduplicateDateSeps(container) {
        let lastLabel = '';
        container.querySelectorAll('.chat-date-sep').forEach(sep => {
            if (sep.dataset.date === lastLabel) sep.remove();
            else lastLabel = sep.dataset.date;
        });
    },

    formatDateLabel(d) {
        const now = new Date();
        if (d.toDateString() === now.toDateString()) return 'Today';
        const y = new Date(now); y.setDate(now.getDate() - 1);
        if (d.toDateString() === y.toDateString()) return 'Yesterday';
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    },

    /* ── Send ── */
    async send() {
        const input = document.getElementById('chatInput');
        const message = input.value.trim();
        if (!message) return;
        const isEmoji = /^[\p{Emoji}\u200d\ufe0f\s]{1,12}$/u.test(message);
        input.value = '';
        input.style.height = 'auto';

        try {
            const data = await this.ajax('send_message', { message, type: isEmoji ? 'emoji' : 'text', reply_to: this.replyToId || '' });
            if (data.success && data.message) {
                const container = document.getElementById('chatMessages');
                this.appendDateSep(data.message, container);
                container.appendChild(this.createBubble(data.message, true));
                this.lastMessageId = Math.max(this.lastMessageId, parseInt(data.message.id));
                this.cancelReply();
                document.getElementById('chatEmpty').style.display = 'none';
                this.scrollToBottom();
            } else {
                showToast(data.message || 'Failed to send', 'error');
            }
        } catch (e) { showToast('Network error', 'error'); }
    },

    async sendNudge(type) {
        try {
            const data = await this.ajax('send_message', { message: type, type: 'nudge' });
            if (data.success && data.message) {
                if (this.chatOpen) {
                    const container = document.getElementById('chatMessages');
                    this.appendDateSep(data.message, container);
                    container.appendChild(this.createBubble(data.message, true));
                    this.lastMessageId = Math.max(this.lastMessageId, parseInt(data.message.id));
                    document.getElementById('chatEmpty').style.display = 'none';
                    this.scrollToBottom();
                }
                showToast('Nudge sent! 🎉', 'success');
                this.refreshPreview();
            }
        } catch (e) { showToast('Failed to send nudge', 'error'); }
    },

    /* ── Reply / Delete ── */
    replyTo(msgId, text, sender) {
        this.replyToId = msgId;
        document.getElementById('chatReplyText').textContent = sender + ': ' + text;
        document.getElementById('chatReplyBar').style.display = 'flex';
        document.getElementById('chatInput').focus();
    },

    cancelReply() {
        this.replyToId = null;
        const bar = document.getElementById('chatReplyBar');
        if (bar) bar.style.display = 'none';
    },

    async deleteMessage(msgId) {
        const confirmed = await StudifyConfirm.show('Delete Message', 'Delete this message? This cannot be undone.', 'danger', 'Delete');
        if (!confirmed) return;
        try {
            const data = await this.ajax('delete_message', { message_id: msgId });
            if (data.success) {
                const el = document.querySelector('.msg-wrap[data-msg-id="' + msgId + '"]');
                if (el) { el.style.transition = 'opacity .25s, max-height .3s'; el.style.opacity = '0'; el.style.maxHeight = '0'; el.style.overflow = 'hidden'; setTimeout(() => el.remove(), 300); }
            }
        } catch (e) { showToast('Failed to delete', 'error'); }
    },

    /* ── UI Helpers ── */
    updateOnlineUI(isOnline) {
        ['buddyDotBar','convoDot','chatDot'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('is-online', isOnline);
        });
        ['buddyStatusBar','chatStatusText'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = isOnline ? 'Active now' : 'Offline';
        });
    },

    updateReadReceipts(lastReadId) {
        document.querySelectorAll('.msg-read').forEach(el => {
            if (parseInt(el.dataset.msgId) <= lastReadId) {
                el.innerHTML = '<i class="fas fa-check-double"></i>';
                el.classList.add('seen');
            }
        });
    },

    scrollToBottom() {
        const c = document.getElementById('chatMessages');
        if (c) setTimeout(() => { c.scrollTop = c.scrollHeight; }, 50);
    },

    isAtBottom() {
        const c = document.getElementById('chatMessages');
        return !c || (c.scrollHeight - c.scrollTop - c.clientHeight < 80);
    },

    playSound() {
        try {
            const ctx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = ctx.createOscillator(); const gain = ctx.createGain();
            osc.connect(gain); gain.connect(ctx.destination);
            osc.frequency.value = 800; osc.type = 'sine'; gain.gain.value = 0.08;
            osc.start(ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);
            osc.stop(ctx.currentTime + 0.25);
        } catch (e) {}
    },

    truncate(t, n) { return t && t.length > n ? t.substring(0, n) + '…' : (t || ''); },
    esc(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; },

    async ajax(action, data = {}) {
        const body = new URLSearchParams({ action, csrf_token: this.csrfToken, ...data });
        const r = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString()
        });
        return r.json();
    },

    destroy() { clearInterval(this.pollTimer); clearInterval(this.heartbeatTimer); }
};

document.addEventListener('DOMContentLoaded', () => BuddyMessenger.init());
window.addEventListener('beforeunload', () => BuddyMessenger.destroy());
</script>

<!-- Weekly Goal Modal -->
<div class="modal fade" id="weeklyGoalModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-700"><i class="fas fa-bullseye text-primary"></i> Set Weekly Goal</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-600" style="font-size:13px;">Tasks to complete this week</label>
                <input type="number" id="weeklyGoalInput" class="form-control" min="1" max="50" value="<?php echo $my_weekly_goal['target'] ?? 5; ?>">
                <small class="text-muted">Set a realistic goal based on your schedule.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="BuddyEnhanced.saveGoal()"><i class="fas fa-save"></i> Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Scheduled Nudge Modal -->
<div class="modal fade" id="scheduleNudgeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-700"><i class="fas fa-clock text-secondary"></i> Schedule a Nudge</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-600" style="font-size:13px;">Day of Week</label>
                    <select id="nudgeDaySelect" class="form-select">
                        <option value="1">Monday</option>
                        <option value="2">Tuesday</option>
                        <option value="3">Wednesday</option>
                        <option value="4">Thursday</option>
                        <option value="5">Friday</option>
                        <option value="6">Saturday</option>
                        <option value="0">Sunday</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600" style="font-size:13px;">Time</label>
                    <input type="time" id="nudgeTimeInput" class="form-control" value="09:00">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-600" style="font-size:13px;">Message</label>
                    <textarea id="nudgeMessageInput" class="form-control" rows="2" maxlength="500" placeholder="e.g., Time to check your tasks!"></textarea>
                </div>
                <small class="text-muted">This nudge will be automatically sent to your buddy at the scheduled time.</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="BuddyEnhanced.saveNudge()"><i class="fas fa-save"></i> Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Check-in Note Modal -->
<div class="modal fade" id="checkinNoteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-700"><i class="fas fa-calendar-check text-success"></i> Daily Check-in</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="checkinCompleted" value="1">
                <label class="form-label fw-600" style="font-size:13px;">Add a note (optional)</label>
                <input type="text" id="checkinNoteInput" class="form-control" maxlength="255" placeholder="e.g., Finished 3 chapters!">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Skip Note</button>
                <button type="button" class="btn btn-success btn-sm" onclick="BuddyEnhanced.submitCheckin()"><i class="fas fa-check"></i> Check In</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Buddy Streak Banner */
.buddy-streak-banner {
    background: linear-gradient(135deg, #ff6b35 0%, #f7931e 100%);
    color: white;
    border-radius: var(--border-radius);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}
.buddy-streak-banner .streak-fire { font-size: 28px; }
.buddy-streak-banner .streak-info strong { display: block; font-size: 14px; }
.buddy-streak-banner .streak-info small { opacity: 0.9; font-size: 12px; }

/* Check-in Box */
.checkin-box {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 12px;
    text-align: center;
    transition: all 0.2s;
}
.checkin-box.checked-in { border-color: var(--success); background: rgba(var(--success-rgb), 0.05); }
.checkin-user { font-weight: 600; font-size: 12px; margin-bottom: 8px; color: var(--text-muted); }
.checkin-status { font-size: 13px; font-weight: 600; }
.checkin-status.done { color: var(--success); }
.checkin-status.skipped { color: var(--text-muted); }
.checkin-status.pending { color: var(--warning); }
.checkin-note { display: block; margin-top: 6px; font-style: italic; color: var(--text-muted); }
.checkin-pending { display: flex; gap: 6px; justify-content: center; }
.checkin-pending .btn { font-size: 11px; padding: 4px 10px; }

/* Goal Box */
.goal-box {
    border: 1px solid var(--border-color);
    border-radius: var(--border-radius);
    padding: 12px;
}
.goal-header { font-weight: 600; font-size: 12px; margin-bottom: 8px; color: var(--text-muted); }
.goal-numbers { font-size: 13px; font-weight: 600; margin-bottom: 6px; }
.goal-bar { height: 8px; background: var(--bg-secondary); border-radius: 4px; overflow: hidden; }
.goal-fill { height: 100%; background: var(--primary); border-radius: 4px; transition: width 0.3s; }
.goal-pct { font-size: 11px; color: var(--text-muted); margin-top: 4px; text-align: right; }
.you-goal .goal-fill { background: var(--primary); }
.buddy-goal .goal-fill { background: var(--info); }

/* Scheduled Nudge Item */
.scheduled-nudge-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}
.scheduled-nudge-item:last-child { border-bottom: none; }
.nudge-time { text-align: center; min-width: 50px; }
.nudge-day { display: block; font-weight: 600; font-size: 12px; color: var(--primary); }
.nudge-hour { display: block; font-size: 11px; color: var(--text-muted); }
.nudge-message { flex: 1; font-size: 13px; color: var(--text-primary); }
.nudge-actions { display: flex; gap: 4px; }
.nudge-actions .btn { padding: 2px 6px; font-size: 10px; }
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const BuddyEnhanced = {
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',

    init() {
        this.initComparisonChart();
    },

    async ajax(action, data = {}) {
        const body = new URLSearchParams({ action, csrf_token: this.csrfToken, ...data });
        const r = await fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString()
        });
        return r.json();
    },

    // Weekly Goal
    showGoalModal() {
        new bootstrap.Modal(document.getElementById('weeklyGoalModal')).show();
    },

    async saveGoal() {
        const target = parseInt(document.getElementById('weeklyGoalInput').value) || 5;
        const data = await this.ajax('set_weekly_goal', { target_tasks: target });
        if (data.success) {
            showToast('Weekly goal updated!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('weeklyGoalModal')).hide();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('Failed to save goal', 'error');
        }
    },

    // Check-in
    checkin(completed) {
        document.getElementById('checkinCompleted').value = completed ? '1' : '0';
        if (completed) {
            new bootstrap.Modal(document.getElementById('checkinNoteModal')).show();
        } else {
            this.submitCheckin();
        }
    },

    async submitCheckin() {
        const completed = document.getElementById('checkinCompleted').value;
        const note = document.getElementById('checkinNoteInput')?.value || '';
        const data = await this.ajax('checkin', { completed, note });
        if (data.success) {
            showToast('Checked in!', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('checkinNoteModal'));
            if (modal) modal.hide();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('Failed to check in', 'error');
        }
    },

    // Scheduled Nudges
    showNudgeModal() {
        document.getElementById('nudgeMessageInput').value = '';
        new bootstrap.Modal(document.getElementById('scheduleNudgeModal')).show();
    },

    async saveNudge() {
        const day = document.getElementById('nudgeDaySelect').value;
        const time = document.getElementById('nudgeTimeInput').value;
        const message = document.getElementById('nudgeMessageInput').value.trim();
        if (!message) { showToast('Please enter a message', 'warning'); return; }

        const data = await this.ajax('add_scheduled_nudge', { day_of_week: day, nudge_time: time, message });
        if (data.success) {
            showToast('Nudge scheduled!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('scheduleNudgeModal')).hide();
            setTimeout(() => location.reload(), 500);
        } else {
            showToast('Failed to schedule nudge', 'error');
        }
    },

    async toggleNudge(id) {
        const data = await this.ajax('toggle_scheduled_nudge', { nudge_id: id });
        if (data.success) {
            location.reload();
        }
    },

    async deleteNudge(id) {
        const confirmed = await StudifyConfirm.show('Delete Nudge', 'Remove this scheduled nudge?', 'danger', 'Delete');
        if (!confirmed) return;
        const data = await this.ajax('delete_scheduled_nudge', { nudge_id: id });
        if (data.success) {
            const el = document.querySelector(`.scheduled-nudge-item[data-id="${id}"]`);
            if (el) el.remove();
            showToast('Nudge deleted', 'success');
        }
    },

    // Comparison Chart
    initComparisonChart() {
        const ctx = document.getElementById('buddyComparisonChart');
        if (!ctx) return;

        const chartData = <?php echo json_encode($comparison_data ?? ['labels' => [], 'user' => [], 'buddy' => []]); ?>;

        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [
                    {
                        label: 'You',
                        data: chartData.user,
                        backgroundColor: 'rgba(99, 102, 241, 0.7)',
                        borderColor: 'rgba(99, 102, 241, 1)',
                        borderWidth: 1,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8
                    },
                    {
                        label: '<?php echo $buddy_first; ?>',
                        data: chartData.buddy,
                        backgroundColor: 'rgba(14, 165, 233, 0.7)',
                        borderColor: 'rgba(14, 165, 233, 1)',
                        borderWidth: 1,
                        barPercentage: 0.7,
                        categoryPercentage: 0.8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 }, padding: 8 } }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, font: { size: 10 } },
                        grid: { display: false }
                    },
                    x: {
                        ticks: { font: { size: 10 } },
                        grid: { display: false }
                    }
                }
            }
        });
    }
};

document.addEventListener('DOMContentLoaded', () => BuddyEnhanced.init());
</script>
