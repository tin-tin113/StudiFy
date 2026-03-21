<?php
/**
 * STUDIFY – Group Messenger & Dashboard
 * Included from study_groups.php when viewing a specific group.
 *
 * Available vars: $active_group, $group_members, $group_tasks, $group_progress, $user_id, $user, $conn
 */
$g = $active_group;
$is_leader = ($g['my_role'] === 'leader');
$can_assign = ($is_leader || $g['allow_member_assign']);
$can_invite = ($is_leader || !empty($g['allow_member_invite']));
$member_count = count($group_members);
?>

<!-- ===== GROUP HEADER BAR ===== -->
<div class="buddy-bar">
    <div class="buddy-bar-left">
        <div class="group-icon-bar">
            <i class="fas fa-users"></i>
        </div>
        <div class="buddy-bar-info">
            <strong><?php echo htmlspecialchars($g['name']); ?></strong>
            <small><?php echo $member_count; ?>/<?php echo $g['max_members']; ?> members · <?php echo $is_leader ? '👑 Leader' : 'Member'; ?></small>
        </div>
    </div>
    <div class="buddy-bar-right">
        <div class="dropdown d-inline">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-ellipsis-v"></i>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#groupSettingsModal">
                        <i class="fas fa-cog text-muted"></i> Settings & Members
                    </button>
                </li>
                <?php if ($can_invite): ?>
                <li>
                    <button class="dropdown-item" onclick="copyInviteCode()">
                        <i class="fas fa-copy text-primary"></i> Copy Invite Code
                    </button>
                </li>
                <?php endif; ?>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Leave Group', 'Are you sure you want to leave this group?', 'warning')">
                        <input type="hidden" name="action" value="leave_group">
                        <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                        <?php echo csrfTokenField(); ?>
                        <button type="submit" class="dropdown-item text-danger"><i class="fas fa-sign-out-alt"></i> Leave Group</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</div>

<!-- ===== TAB NAVIGATION ===== -->
<ul class="nav nav-pills nav-fill mb-3 group-tabs" id="groupTabs">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="pill" href="#tabProgress"><i class="fas fa-chart-bar"></i> <span class="d-none d-sm-inline">Progress</span></a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#tabTasks"><i class="fas fa-tasks"></i> <span class="d-none d-sm-inline">Tasks</span>
            <?php
            $pending_count = count(array_filter($group_tasks, fn($t) => $t['status'] === 'Pending'));
            if ($pending_count > 0): ?>
                <span class="badge bg-warning ms-1" style="font-size: 10px;"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="pill" href="#tabChat"><i class="fas fa-comments"></i> <span class="d-none d-sm-inline">Chat</span></a>
    </li>
</ul>

<div class="tab-content">

<!-- ===== PROGRESS TAB ===== -->
<div class="tab-pane fade show active" id="tabProgress">
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="fw-700 mb-3"><i class="fas fa-trophy text-warning"></i> Group Progress</h6>
            <?php if (empty($group_progress)): ?>
                <p class="text-muted">No members yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" style="font-size: 13px;">
                        <thead>
                            <tr>
                                <th>Member</th>
                                <th class="text-center">Group Tasks</th>
                                <th class="text-center">Completion</th>
                                <th class="text-center">Study/Week</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $best_gpct = max(array_column($group_progress, 'group_completion_pct'));
                            $best_hours = max(array_map(fn($p) => $p['week_minutes'], $group_progress));
                            ?>
                            <?php foreach ($group_progress as $p): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="group-member-avatar me-2">
                                            <?php if (!empty($p['profile_photo'])): ?>
                                                <img src="<?php echo BASE_URL . $p['profile_photo']; ?>" alt="">
                                            <?php else: ?>
                                                <span><?php echo strtoupper(substr($p['name'], 0, 1)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <span class="fw-600"><?php echo htmlspecialchars($p['name']); ?></span>
                                            <?php if ($p['role'] === 'leader'): ?><small class="text-warning">👑</small><?php endif; ?>
                                            <?php if ($p['user_id'] == $user_id): ?><small class="text-muted">(you)</small><?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-700">
                                        <?php echo $p['group_tasks_done']; ?>/<?php echo $p['group_tasks_total']; ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="fw-700 <?php echo ($p['group_completion_pct'] == $best_gpct && $best_gpct > 0) ? 'text-success' : ''; ?>">
                                        <?php echo $p['group_completion_pct']; ?>%
                                    </span>
                                    <div class="buddy-progress-bar mt-1" style="height: 5px;">
                                        <div class="buddy-progress-fill you-fill" style="width:<?php echo $p['group_completion_pct']; ?>%"></div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="fw-700 <?php echo ($p['week_minutes'] == $best_hours && $best_hours > 0) ? 'text-success' : ''; ?>">
                                        <?php echo round($p['week_minutes'] / 60, 1); ?>h
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($can_invite): ?>
    <!-- Invite Code Card -->
    <div class="card mb-3">
        <div class="card-body text-center">
            <small class="text-muted">Invite Code<?php echo ($g['join_mode'] ?? 'open') === 'approval' ? ' (Requires Approval)' : ''; ?></small>
            <div class="fw-700" id="inviteCodeDisplay" style="font-size: 1.5rem; letter-spacing: 4px; font-family: monospace; user-select: all;">
                <?php echo htmlspecialchars($g['invite_code']); ?>
            </div>
            <button class="btn btn-outline-primary btn-sm mt-2" onclick="copyInviteCode()">
                <i class="fas fa-copy"></i> Copy
            </button>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($is_leader && !empty($pending_requests)): ?>
    <!-- Pending Join Requests -->
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="fw-700 mb-3"><i class="fas fa-user-clock text-warning"></i> Pending Join Requests (<?php echo count($pending_requests); ?>)</h6>
            <div class="list-group list-group-flush">
                <?php foreach ($pending_requests as $req): ?>
                <div class="list-group-item px-0 d-flex align-items-center">
                    <div class="group-member-avatar me-2">
                        <?php if (!empty($req['profile_photo'])): ?>
                            <img src="<?php echo BASE_URL . $req['profile_photo']; ?>" alt="">
                        <?php else: ?>
                            <span><?php echo strtoupper(substr($req['name'], 0, 1)); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="flex-grow-1">
                        <span class="fw-600" style="font-size: 13px;"><?php echo htmlspecialchars($req['name']); ?></span>
                        <br><small class="text-muted"><?php echo htmlspecialchars($req['course'] ?? ''); ?> · Year <?php echo $req['year_level'] ?? '?'; ?></small>
                    </div>
                    <form method="POST" class="d-inline me-1" onsubmit="return StudifyConfirm.form(event, 'Accept Member', 'Accept this member into the group?', 'success')">
                        <input type="hidden" name="action" value="approve_request">
                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                        <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                        <?php echo csrfTokenField(); ?>
                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i></button>
                    </form>
                    <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Decline Request', 'Decline this join request?', 'warning')">
                        <input type="hidden" name="action" value="reject_request">
                        <input type="hidden" name="request_id" value="<?php echo $req['id']; ?>">
                        <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                        <?php echo csrfTokenField(); ?>
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-times"></i></button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <script>
    function copyInviteCode() {
        var code = <?php echo json_encode($g['invite_code']); ?>;
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(code).then(function() {
                showToast('Copied!', 'success');
            });
        } else {
            var ta = document.createElement('textarea');
            ta.value = code;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
            showToast('Copied!', 'success');
        }
    }
    </script>
</div>

<!-- ===== TASKS TAB ===== -->
<div class="tab-pane fade" id="tabTasks">
    <?php if ($can_assign): ?>
    <div class="mb-3">
        <button class="btn btn-outline-primary btn-sm" type="button" id="btnAddTask" onclick="document.getElementById('addTaskForm').classList.toggle('d-none'); this.classList.toggle('d-none');">
            <i class="fas fa-plus"></i> Add Task
        </button>
        <div class="card mt-2 d-none" id="addTaskForm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h6 class="fw-700 mb-0"><i class="fas fa-plus text-primary"></i> Assign Task</h6>
                    <button type="button" class="btn-close btn-sm" onclick="document.getElementById('addTaskForm').classList.add('d-none'); document.getElementById('btnAddTask').classList.remove('d-none');"></button>
                </div>
                <form method="POST" action="?group=<?php echo $g['id']; ?>#tabTasks" onsubmit="return StudifyConfirm.form(event, 'Assign Task', 'Assign this task to the selected member?', 'info')">
                    <input type="hidden" name="action" value="assign_task">
                    <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                    <?php echo csrfTokenField(); ?>
                    <div class="row g-2">
                        <div class="col-md-6">
                            <input type="text" name="task_title" class="form-control form-control-sm" placeholder="Task title" maxlength="255" required>
                        </div>
                        <div class="col-md-6">
                            <select name="assign_to" class="form-select form-select-sm" required>
                                <option value="">Assign to…</option>
                                <?php foreach ($group_members as $m): ?>
                                <option value="<?php echo $m['user_id']; ?>">
                                    <?php echo htmlspecialchars($m['name']); ?><?php echo $m['user_id'] == $user_id ? ' (me)' : ''; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="datetime-local" name="task_deadline" class="form-control form-control-sm" id="groupTaskDeadline">
                            <div class="form-check mt-1">
                                <input class="form-check-input" type="checkbox" id="groupNoDueDate" onchange="document.getElementById('groupTaskDeadline').disabled = this.checked; if(this.checked) document.getElementById('groupTaskDeadline').value = '';">
                                <label class="form-check-label" for="groupNoDueDate" style="font-size: 11px;">No due date</label>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <select name="task_priority" class="form-select form-select-sm">
                                <option value="Low">Low</option>
                                <option value="Medium" selected>Medium</option>
                                <option value="High">High</option>
                            </select>
                        </div>
                        <div class="col-md-5">
                            <input type="text" name="task_desc" class="form-control form-control-sm" placeholder="Description (optional)" maxlength="500">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus"></i> Assign</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
        $pending_tasks = array_filter($group_tasks, fn($t) => $t['status'] === 'Pending');
        $completed_tasks = array_filter($group_tasks, fn($t) => $t['status'] === 'Completed');
        $overdue_check = function($t) { return $t['deadline'] && strtotime($t['deadline']) < time(); };
    ?>
    <!-- Pending Tasks -->
    <div class="card mb-3">
        <div class="card-body">
            <h6 class="fw-700 mb-3"><i class="fas fa-tasks text-info"></i> Pending Tasks <span class="badge bg-primary rounded-pill"><?php echo count($pending_tasks); ?></span></h6>
            <?php if (empty($pending_tasks)): ?>
                <p class="text-muted text-center py-3"><i class="fas fa-check-circle text-success"></i> All tasks completed!</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;"></th>
                                <th>Task</th>
                                <th>Assigned To</th>
                                <th>Deadline</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pending_tasks as $t):
                            $is_overdue = $overdue_check($t);
                        ?>
                            <tr class="group-task-item" data-task-id="<?php echo $t['id']; ?>">
                                <td>
                                    <button class="btn btn-sm p-0 text-success" onclick="GroupPage.toggleTask(<?php echo $t['id']; ?>)" title="Mark as completed">
                                        <i class="far fa-circle fa-lg"></i>
                                    </button>
                                </td>
                                <td>
                                    <div class="fw-600"><?php echo htmlspecialchars($t['title']); ?></div>
                                    <?php if (!empty($t['description'])): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($t['description']); ?></small>
                                    <?php endif; ?>
                                    <!-- Attachments for this group task -->
                                    <div class="group-task-attachments" id="gatt-task-<?php echo $t['id']; ?>" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px;"></div>
                                    <div style="margin-top:4px;">
                                        <label title="Attach a file" style="cursor:pointer;font-size:11px;color:var(--primary);">
                                            <i class="fas fa-paperclip"></i> Attach
                                            <input type="file" style="display:none;" onchange="GroupPage.uploadAttachment(<?php echo $t['id']; ?>, this)">
                                        </label>
                                    </div>
                                </td>
                                <td><i class="fas fa-user-circle text-muted me-1"></i><?php echo htmlspecialchars($t['assigned_to_name']); ?></td>
                                <td>
                                    <?php if ($t['deadline']): ?>
                                        <span class="<?php echo $is_overdue ? 'text-danger fw-600' : ''; ?>">
                                            <i class="fas fa-calendar-alt me-1"></i><?php echo date('M j, Y', strtotime($t['deadline'])); ?>
                                            <br><small><?php echo date('g:i A', strtotime($t['deadline'])); ?></small>
                                        </span>
                                        <?php if ($is_overdue): ?>
                                            <br><span class="badge bg-danger" style="font-size:10px;">OVERDUE</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($t['priority'] === 'High'): ?>
                                        <span class="badge bg-danger">High</span>
                                    <?php elseif ($t['priority'] === 'Medium'): ?>
                                        <span class="badge bg-warning text-dark">Medium</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Low</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_overdue): ?>
                                        <span class="badge bg-danger"><i class="fas fa-exclamation-triangle"></i> Overdue</span>
                                    <?php else: ?>
                                        <span class="badge bg-info"><i class="fas fa-hourglass-half"></i> Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_leader || $t['assigned_by'] == $user_id): ?>
                                    <button class="btn btn-sm text-danger p-0" onclick="GroupPage.deleteTask(<?php echo $t['id']; ?>)" title="Delete task">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Completed Tasks (collapsible) -->
    <?php if (!empty($completed_tasks)): ?>
    <div class="card">
        <div class="card-body">
            <h6 class="fw-700 mb-0 d-flex align-items-center justify-content-between" style="cursor:pointer;" onclick="let el=document.getElementById('completedTasksBody'); el.style.display = el.style.display==='none'?'block':'none'; this.querySelector('.fa-chevron-down,.fa-chevron-up').classList.toggle('fa-chevron-down'); this.querySelector('.fa-chevron-down,.fa-chevron-up').classList.toggle('fa-chevron-up');">
                <span><i class="fas fa-check-double text-success"></i> Completed Tasks <span class="badge bg-success rounded-pill"><?php echo count($completed_tasks); ?></span></span>
                <i class="fas fa-chevron-down text-muted"></i>
            </h6>
            <div id="completedTasksBody" style="display:none;" class="mt-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 13px;">
                        <thead class="table-light">
                            <tr>
                                <th style="width:40px;"></th>
                                <th>Task</th>
                                <th>Assigned To</th>
                                <th>Completed</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th style="width:60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($completed_tasks as $t): ?>
                            <tr class="group-task-item" data-task-id="<?php echo $t['id']; ?>" style="opacity: 0.75;">
                                <td>
                                    <button class="btn btn-sm p-0 text-warning" onclick="GroupPage.toggleTask(<?php echo $t['id']; ?>)" title="Reopen task">
                                        <i class="fas fa-check-circle fa-lg text-success"></i>
                                    </button>
                                </td>
                                <td>
                                    <div class="fw-600 text-decoration-line-through text-muted"><?php echo htmlspecialchars($t['title']); ?></div>
                                    <!-- Attachments for completed group task (view only) -->
                                    <div class="group-task-attachments" id="gatt-task-<?php echo $t['id']; ?>" style="margin-top:6px;display:flex;flex-wrap:wrap;gap:4px;"></div>
                                </td>
                                <td><i class="fas fa-user-circle text-muted me-1"></i><?php echo htmlspecialchars($t['assigned_to_name']); ?></td>
                                <td>
                                    <?php if ($t['completed_at']): ?>
                                        <i class="fas fa-calendar-check text-success me-1"></i><?php echo date('M j, Y', strtotime($t['completed_at'])); ?>
                                        <br><small class="text-muted"><?php echo date('g:i A', strtotime($t['completed_at'])); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($t['priority'] === 'High'): ?>
                                        <span class="badge bg-danger">High</span>
                                    <?php elseif ($t['priority'] === 'Medium'): ?>
                                        <span class="badge bg-warning text-dark">Medium</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Low</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-success"><i class="fas fa-check"></i> Completed</span></td>
                                <td>
                                    <?php if ($is_leader || $t['assigned_by'] == $user_id): ?>
                                    <button class="btn btn-sm text-danger p-0" onclick="GroupPage.deleteTask(<?php echo $t['id']; ?>)" title="Delete task">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ===== CHAT TAB ===== -->
<div class="tab-pane fade" id="tabChat">
    <div class="card">
        <div class="card-body p-0">
            <div class="chat-panel group-chat-panel">
                <div class="chat-load-more" id="gcLoadMore" style="display:none;">
                    <button onclick="GroupChat.loadMore()" id="gcLoadMoreBtn">
                        <i class="fas fa-angle-up"></i> Load older messages
                    </button>
                </div>
                <div class="chat-messages" id="gcMessages" style="min-height: 300px; max-height: 450px;">
                    <div class="chat-loader" id="gcLoader">
                        <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                        <span>Loading messages…</span>
                    </div>
                </div>
                <div class="chat-reply-bar" id="gcReplyBar" style="display:none;">
                    <i class="fas fa-reply text-primary"></i>
                    <span id="gcReplyText"></span>
                    <button onclick="GroupChat.cancelReply()"><i class="fas fa-times"></i></button>
                </div>
                <div class="chat-input-area">
                    <div class="chat-nudge-row">
                        <button class="chat-nudge-btn" onclick="GroupChat.sendNudge('wave')" title="Check In">👋</button>
                        <button class="chat-nudge-btn" onclick="GroupChat.sendNudge('motivate')" title="Motivate">💪</button>
                        <button class="chat-nudge-btn" onclick="GroupChat.sendNudge('reminder')" title="Remind">⏰</button>
                        <button class="chat-nudge-btn" onclick="GroupChat.sendNudge('celebrate')" title="Celebrate">🎉</button>
                        <button class="chat-nudge-btn" onclick="GroupChat.sendNudge('challenge')" title="Challenge">🔥</button>
                    </div>
                    <div class="chat-input-row">
                        <textarea id="gcInput" placeholder="Message the group…" rows="1" maxlength="1000"></textarea>
                        <button id="gcSendBtn" title="Send"><i class="fas fa-paper-plane"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div><!-- /tab-content -->

<!-- Settings & Members Modal -->
<div class="modal fade" id="groupSettingsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-700"><i class="fas fa-cog text-primary"></i> Group Settings</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <?php if ($is_leader): ?>
                <form method="POST" class="mb-4" onsubmit="return StudifyConfirm.form(event, 'Save Settings', 'Save these group settings?', 'info')">
                    <input type="hidden" name="action" value="update_settings">
                    <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                    <?php echo csrfTokenField(); ?>
                    <div class="mb-2">
                        <label class="form-label fw-600" style="font-size: 13px;">Group Name</label>
                        <input type="text" name="group_name" class="form-control form-control-sm" value="<?php echo htmlspecialchars($g['name']); ?>" maxlength="100" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-600" style="font-size: 13px;">Description</label>
                        <textarea name="group_desc" class="form-control form-control-sm" rows="2" maxlength="500"><?php echo htmlspecialchars($g['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="allow_member_assign" value="1" id="allowMemberAssign" <?php echo $g['allow_member_assign'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allowMemberAssign" style="font-size: 13px;">
                            Allow all members to assign tasks
                        </label>
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="allow_member_invite" value="1" id="allowMemberInvite" <?php echo !empty($g['allow_member_invite']) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="allowMemberInvite" style="font-size: 13px;">
                            Allow all members to share invite code
                        </label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-600" style="font-size: 13px;">Join Mode</label>
                        <select name="join_mode" class="form-select form-select-sm">
                            <option value="open" <?php echo ($g['join_mode'] ?? 'open') === 'open' ? 'selected' : ''; ?>>Open — anyone with code joins instantly</option>
                            <option value="approval" <?php echo ($g['join_mode'] ?? 'open') === 'approval' ? 'selected' : ''; ?>>Approval — leader must accept requests</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-save"></i> Save</button>
                </form>
                <hr>
                <?php endif; ?>

                <h6 class="fw-700 mb-3"><i class="fas fa-user-friends text-info"></i> Members (<?php echo $member_count; ?>/<?php echo $g['max_members']; ?>)</h6>
                <div class="list-group list-group-flush">
                    <?php foreach ($group_members as $m): ?>
                    <div class="list-group-item px-0 d-flex align-items-center">
                        <div class="group-member-avatar me-2">
                            <?php if (!empty($m['profile_photo'])): ?>
                                <img src="<?php echo BASE_URL . $m['profile_photo']; ?>" alt="">
                            <?php else: ?>
                                <span><?php echo strtoupper(substr($m['name'], 0, 1)); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1">
                            <span class="fw-600" style="font-size: 13px;"><?php echo htmlspecialchars($m['name']); ?></span>
                            <?php if ($m['role'] === 'leader'): ?><span class="badge bg-warning text-dark ms-1" style="font-size: 9px;">👑 Leader</span><?php endif; ?>
                            <?php if ($m['user_id'] == $user_id): ?><small class="text-muted ms-1">(you)</small><?php endif; ?>
                            <br><small class="text-muted"><?php echo htmlspecialchars($m['course'] ?? ''); ?> · Year <?php echo $m['year_level'] ?? '?'; ?></small>
                        </div>
                        <?php if ($is_leader && $m['user_id'] != $user_id): ?>
                        <form method="POST" class="d-inline" onsubmit="return StudifyConfirm.form(event, 'Remove Member', 'Remove <?php echo htmlspecialchars(addslashes($m['name'])); ?> from the group?', 'danger')">
                            <input type="hidden" name="action" value="remove_member">
                            <input type="hidden" name="group_id" value="<?php echo $g['id']; ?>">
                            <input type="hidden" name="target_id" value="<?php echo $m['user_id']; ?>">
                            <?php echo csrfTokenField(); ?>
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-user-minus"></i></button>
                        </form>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const GroupPage = {
    groupId: <?php echo $g['id']; ?>,
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',

    toggleTask(taskId) {
        const el = document.querySelector(`.group-task-item[data-task-id="${taskId}"]`);
        const currentStatus = el?.closest('#completedTasksBody') ? 'Completed' : 'Pending';
        const title = currentStatus === 'Pending' ? 'Complete Task' : 'Reopen Task';
        const msg = currentStatus === 'Pending' ? 'Mark this task as completed?' : 'Mark this task as pending again?';
        const type = currentStatus === 'Pending' ? 'success' : 'warning';
        StudifyConfirm.action(title, msg, type, async () => {
            try {
                const data = await this.ajax('toggle_task', { task_id: taskId });
                if (data.success) {
                    const url = new URL(window.location);
                    url.hash = 'tabTasks';
                    window.location.href = url.toString();
                    window.location.reload();
                } else {
                    showToast('Cannot toggle this task', 'error');
                }
            } catch(e) { showToast('Network error', 'error'); }
        });
    },

    deleteTask(taskId) {
        StudifyConfirm.action('Delete Task', 'Are you sure you want to delete this task? This cannot be undone.', 'danger', async () => {
            try {
                const data = await this.ajax('delete_task', { task_id: taskId });
                if (data.success) {
                    const el = document.querySelector(`.group-task-item[data-task-id="${taskId}"]`);
                    if (el) el.remove();
                    showToast('Task deleted', 'success');
                }
            } catch(e) { showToast('Network error', 'error'); }
        });
    },

    async ajax(action, data = {}) {
        const body = new URLSearchParams({ action, csrf_token: this.csrfToken, group_id: this.groupId, ...data });
        const r = await fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString()
        });
        return r.json();
    },

    // Attachment methods
    uploadAttachment(taskId, input) {
        if (!input.files.length) return;
        const file = input.files[0];
        const fd = new FormData();
        fd.append('action', 'upload');
        fd.append('group_task_id', taskId);
        fd.append('file', file);
        fd.append('csrf_token', this.csrfToken);

        StudifyToast.info('Uploading...', file.name);
        fetch('<?php echo BASE_URL; ?>student/attachments.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    StudifyToast.success('Uploaded', data.attachment.file_name);
                    this.loadTaskAttachments(taskId);
                } else {
                    StudifyToast.error('Upload Failed', data.message);
                }
                input.value = '';
            })
            .catch(() => { StudifyToast.error('Error', 'Network error'); input.value = ''; });
    },

    loadTaskAttachments(taskId) {
        const container = document.getElementById('gatt-task-' + taskId);
        if (!container) return;
        fetch('<?php echo BASE_URL; ?>student/attachments.php?action=list&group_task_id=' + taskId, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.success) this.renderAttachments(container, data.attachments);
            });
    },

    renderAttachments(container, attachments) {
        container.innerHTML = '';
        attachments.forEach(att => {
            const chip = document.createElement('div');
            chip.style.cssText = 'display:inline-flex;align-items:center;gap:4px;background:var(--bg-secondary);border:1px solid var(--border-color);border-radius:4px;padding:2px 6px;font-size:10px;max-width:150px;';
            const icon = this.getFiletypeIcon(att.file_type);
            chip.innerHTML = `
                <i class="${icon}" style="color:var(--primary);"></i>
                <a href="<?php echo BASE_URL; ?>${att.file_path}" target="_blank" class="text-truncate" style="max-width:80px;color:var(--text-primary);" title="${this.escapeHtml(att.file_name)}">${this.escapeHtml(att.file_name)}</a>
                <button type="button" onclick="GroupPage.deleteAttachment(${att.id})" class="btn btn-sm p-0" title="Delete" style="font-size:10px;color:var(--danger);"><i class="fas fa-times"></i></button>
            `;
            container.appendChild(chip);
        });
    },

    deleteAttachment(attId) {
        StudifyConfirm.action('Delete File', 'Remove this attachment?', 'danger', () => {
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('attachment_id', attId);
            fd.append('csrf_token', this.csrfToken);
            fetch('<?php echo BASE_URL; ?>student/attachments.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        StudifyToast.success('Deleted', 'Attachment removed');
                        this.loadAllTaskAttachments();
                    } else {
                        StudifyToast.error('Error', data.message);
                    }
                });
        });
    },

    loadAllTaskAttachments() {
        document.querySelectorAll('.group-task-attachments').forEach(el => {
            const taskId = el.id.replace('gatt-task-', '');
            this.loadTaskAttachments(parseInt(taskId));
        });
    },

    getFiletypeIcon(mime) {
        if (mime.startsWith('image/')) return 'fas fa-image';
        if (mime.includes('pdf')) return 'fas fa-file-pdf';
        if (mime.includes('word') || mime.includes('document')) return 'fas fa-file-word';
        if (mime.includes('excel') || mime.includes('spreadsheet')) return 'fas fa-file-excel';
        if (mime.includes('powerpoint') || mime.includes('presentation')) return 'fas fa-file-powerpoint';
        if (mime.includes('zip') || mime.includes('compressed')) return 'fas fa-file-archive';
        if (mime.startsWith('text/')) return 'fas fa-file-alt';
        return 'fas fa-file';
    },

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
};

const GroupChat = {
    groupId: <?php echo $g['id']; ?>,
    userId: <?php echo $user_id; ?>,
    csrfToken: GroupPage.csrfToken,
    lastMessageId: 0,
    oldestMessageId: null,
    pollTimer: null,
    replyToId: null,
    hasMore: true,
    lastDateLabel: '',

    init() {
        const input = document.getElementById('gcInput');
        if (input) {
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); this.send(); }
            });
            input.addEventListener('input', () => {
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 120) + 'px';
            });
        }
        document.getElementById('gcSendBtn')?.addEventListener('click', () => this.send());

        // Load when chat tab is shown
        document.querySelector('[href="#tabChat"]')?.addEventListener('shown.bs.tab', () => {
            if (this.lastMessageId === 0) this.loadMessages();
            this.startPolling();
        });
        document.querySelector('[href="#tabChat"]')?.addEventListener('hidden.bs.tab', () => {
            this.stopPolling();
        });
    },

    async loadMessages() {
        const container = document.getElementById('gcMessages');
        const loader = document.getElementById('gcLoader');
        try {
            const data = await this.ajax('get_messages', { limit: 50 });
            if (!data.success) return;
            loader.style.display = 'none';
            if (data.messages.length === 0) {
                container.innerHTML = '<div class="chat-empty" style="display:flex;"><div class="chat-empty-icon"><i class="fas fa-comments"></i></div><p>No messages yet</p><span>Start the conversation! 💬</span></div>';
                return;
            }
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
            if (this.hasMore) document.getElementById('gcLoadMore').style.display = 'block';
            this.scrollToBottom();
            this.ajax('mark_read', { last_id: this.lastMessageId });
        } catch(e) {
            console.error('Load failed:', e);
            loader.innerHTML = '<span class="text-danger"><i class="fas fa-exclamation-circle"></i> Failed to load</span>';
        }
    },

    async loadMore() {
        if (!this.hasMore) return;
        const btn = document.getElementById('gcLoadMoreBtn');
        if (btn) btn.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Loading…';
        try {
            const data = await this.ajax('get_messages', { limit: 30, before_id: this.oldestMessageId });
            if (!data.success || data.messages.length === 0) {
                this.hasMore = false;
                document.getElementById('gcLoadMore').style.display = 'none';
                return;
            }
            const container = document.getElementById('gcMessages');
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
            container.scrollTop = container.scrollHeight - prevH;
            this.oldestMessageId = parseInt(data.messages[0].id);
            if (data.messages.length < 30) {
                this.hasMore = false;
                document.getElementById('gcLoadMore').style.display = 'none';
            }
        } catch(e) { console.error(e); }
        finally { if (btn) btn.innerHTML = '<i class="fas fa-angle-up"></i> Load older messages'; }
    },

    startPolling() { if (!this.pollTimer) this.pollTimer = setInterval(() => this.poll(), 4000); },
    stopPolling() { clearInterval(this.pollTimer); this.pollTimer = null; },

    async poll() {
        try {
            const data = await this.ajax('get_new_messages', { after_id: this.lastMessageId });
            if (!data.success) return;
            if (data.messages && data.messages.length > 0) {
                const container = document.getElementById('gcMessages');
                const emptyEl = container.querySelector('.chat-empty');
                if (emptyEl) emptyEl.remove();
                const wasBottom = this.isAtBottom();
                data.messages.forEach(msg => {
                    this.appendDateSep(msg, container);
                    container.appendChild(this.createBubble(msg, true));
                    this.lastMessageId = Math.max(this.lastMessageId, parseInt(msg.id));
                });
                if (wasBottom) this.scrollToBottom();
                this.ajax('mark_read', { last_id: this.lastMessageId });
            }
        } catch(e) { console.error('Poll error:', e); }
    },

    createBubble(msg, isNew = false) {
        const isMine = parseInt(msg.sender_id) === this.userId;
        const isSystem = msg.message_type === 'system';
        const isNudge = msg.message_type === 'nudge';
        const d = new Date(msg.created_at);
        const timeStr = d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });

        if (isSystem) {
            const sys = document.createElement('div');
            sys.className = 'chat-system-msg';
            sys.innerHTML = '<span>' + this.esc(msg.message) + '</span>';
            return sys;
        }

        const wrap = document.createElement('div');
        wrap.className = 'msg-wrap' + (isMine ? ' mine' : ' theirs') + (isNudge ? ' nudge-msg' : '') + (isNew ? ' msg-new' : '');
        wrap.dataset.msgId = msg.id;

        let avatarHtml = '';
        if (!isMine) {
            const photo = msg.sender_photo;
            const initial = (msg.sender_name || '?')[0].toUpperCase();
            avatarHtml = photo
                ? '<div class="msg-avatar"><img src="' + this.esc('../' + photo) + '" alt="" title="' + this.esc(msg.sender_name) + '"></div>'
                : '<div class="msg-avatar"><span title="' + this.esc(msg.sender_name) + '">' + initial + '</span></div>';
        }

        let senderHtml = '';
        if (!isMine) {
            senderHtml = '<div class="msg-sender-name">' + this.esc(msg.sender_name) + '</div>';
        }

        let replyHtml = '';
        if (msg.reply_message) {
            const rSender = parseInt(msg.reply_sender_id) === this.userId ? 'You' : (msg.reply_sender_name || '');
            replyHtml = '<div class="msg-reply-ctx"><i class="fas fa-reply"></i> <strong>' + this.esc(rSender) + '</strong> ' + this.esc(this.truncate(msg.reply_message, 50)) + '</div>';
        }

        const nudgeTag = isNudge ? '<span class="msg-nudge-tag"><i class="fas fa-bolt"></i></span> ' : '';

        const safeMsg = this.esc(this.truncate(msg.message, 60)).replace(/'/g, '&#39;');
        const safeSender = isMine ? 'You' : this.esc(msg.sender_name || '').replace(/'/g, '&#39;');
        const replyBtn = '<button onclick="event.stopPropagation();GroupChat.replyTo(' + msg.id + ',\'' + safeMsg + '\',\'' + safeSender + '\')" title="Reply"><i class="fas fa-reply"></i></button>';

        wrap.innerHTML =
            avatarHtml +
            '<div class="msg-bubble">' +
                senderHtml +
                replyHtml +
                '<div class="msg-text">' + nudgeTag + this.esc(msg.message) + '</div>' +
                '<div class="msg-meta"><span class="msg-time">' + timeStr + '</span></div>' +
                '<div class="msg-actions">' + replyBtn + '</div>' +
            '</div>';

        return wrap;
    },

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

    formatDateLabel(d) {
        const now = new Date();
        if (d.toDateString() === now.toDateString()) return 'Today';
        const y = new Date(now); y.setDate(now.getDate() - 1);
        if (d.toDateString() === y.toDateString()) return 'Yesterday';
        return d.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
    },

    async send() {
        const input = document.getElementById('gcInput');
        const message = input.value.trim();
        if (!message) return;
        const isEmoji = /^[\p{Emoji}\u200d\ufe0f\s]{1,12}$/u.test(message);
        input.value = '';
        input.style.height = 'auto';
        try {
            const data = await this.ajax('send_message', { message, type: isEmoji ? 'emoji' : 'text', reply_to: this.replyToId || '' });
            if (data.success && data.message) {
                const container = document.getElementById('gcMessages');
                const emptyEl = container.querySelector('.chat-empty');
                if (emptyEl) emptyEl.remove();
                this.appendDateSep(data.message, container);
                container.appendChild(this.createBubble(data.message, true));
                this.lastMessageId = Math.max(this.lastMessageId, parseInt(data.message.id));
                this.cancelReply();
                this.scrollToBottom();
            } else {
                showToast(data.message || 'Failed to send', 'error');
            }
        } catch(e) { showToast('Network error', 'error'); }
    },

    async sendNudge(type) {
        try {
            const data = await this.ajax('send_message', { message: type, type: 'nudge' });
            if (data.success && data.message) {
                const container = document.getElementById('gcMessages');
                const emptyEl = container.querySelector('.chat-empty');
                if (emptyEl) emptyEl.remove();
                this.appendDateSep(data.message, container);
                container.appendChild(this.createBubble(data.message, true));
                this.lastMessageId = Math.max(this.lastMessageId, parseInt(data.message.id));
                this.scrollToBottom();
                showToast('Nudge sent! 🎉', 'success');
            }
        } catch(e) { showToast('Failed to send nudge', 'error'); }
    },

    replyTo(msgId, text, sender) {
        this.replyToId = msgId;
        document.getElementById('gcReplyText').textContent = sender + ': ' + text;
        document.getElementById('gcReplyBar').style.display = 'flex';
        document.getElementById('gcInput').focus();
    },

    cancelReply() {
        this.replyToId = null;
        const bar = document.getElementById('gcReplyBar');
        if (bar) bar.style.display = 'none';
    },

    scrollToBottom() {
        const c = document.getElementById('gcMessages');
        if (c) setTimeout(() => { c.scrollTop = c.scrollHeight; }, 50);
    },
    isAtBottom() {
        const c = document.getElementById('gcMessages');
        return !c || (c.scrollHeight - c.scrollTop - c.clientHeight < 80);
    },
    truncate(t, n) { return t && t.length > n ? t.substring(0, n) + '…' : (t || ''); },
    esc(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; },

    async ajax(action, data = {}) {
        const body = new URLSearchParams({ action, csrf_token: this.csrfToken, group_id: this.groupId, ...data });
        const r = await fetch(window.location.pathname, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
            body: body.toString()
        });
        return r.json();
    }
};

document.addEventListener('DOMContentLoaded', () => {
    GroupChat.init();
    GroupPage.loadAllTaskAttachments();

    // Restore active tab from URL hash (e.g. #tabTasks after task toggle)
    const hash = window.location.hash.replace('#', '');
    if (hash) {
        const tabLink = document.querySelector(`[href="#${hash}"]`);
        if (tabLink) {
            const tab = new bootstrap.Tab(tabLink);
            tab.show();
        }
    }
});
</script>
