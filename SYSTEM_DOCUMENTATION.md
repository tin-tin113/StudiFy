# STUDIFY - Complete System Documentation

Generated: March 15, 2026
Schema coverage: v6.0 + compatibility migrations

---

## Table of Contents

1. System Snapshot
2. Version Coverage
3. Database Schema
4. Configuration and Security Helpers
5. Authentication Helpers
6. Core Function Catalog (`includes/functions.php`)
7. Gamification and Notification Worker
8. Student Modules and Endpoints
9. Admin Modules
10. Frontend JavaScript Architecture
11. Security Summary
12. Migration and Maintenance Scripts

---

## 1. System Snapshot

| Property | Value |
|---|---|
| Stack | PHP 8+, MySQL/MariaDB, Bootstrap 5, Vanilla JS |
| Server target | Laragon (`http://localhost/Studify/`) |
| Architecture | Server-rendered PHP with AJAX endpoints |
| Database charset | `utf8mb4` |
| Auth model | Session-based, role-based (`student`, `admin`) |
| Primary domains | Tasks, Study Sessions, Notes, Notifications, Buddy, Study Groups |
| Current collaboration release | v6.0 (Study Groups + Group Chat) |

---

## 2. Version Coverage

- v2.x baseline: users, semesters, subjects, tasks, notes, study sessions, announcements
- v3.0: buddy instant messaging (`buddy_messages`, typing status)
- v3.1: buddy safety controls (`buddy_blocks`, `buddy_reports`)
- v4.0: notifications (`notifications`, `notification_preferences`)
- v5.0: widgets and achievements (`dashboard_widgets`, `user_achievements`)
- v6.0: study groups (`study_groups`, members, group tasks, group messages, join requests)

---

## 3. Database Schema

### 3.1 Core Academic and Identity Tables

- `users`
  - identity, role, profile photo, onboarding flag, lockout fields, last activity
- `password_resets`
  - hashed token workflow for password reset
- `semesters`
  - per-user semester containers
- `subjects`
  - semester-linked subject records
- `tasks`
  - user task records, subtasks (`parent_id`), recurrence fields
  - status is currently `Pending` or `Completed`
  - compatibility note: legacy UI values of `In Progress` are normalized to `Pending` in controller logic
- `study_sessions`
  - focus and break sessions with optional task/subject links
- `notes`
  - user notes with optional subject link and content type
- `attachments`
  - file metadata linked to exactly one parent (`task_id` xor `note_id`)
- `announcements`
  - admin announcements with priority and expiry
- `announcement_reads`
  - per-user announcement dismiss/read tracking
- `activity_log`
  - system activity auditing

### 3.2 Buddy Collaboration Tables

- `study_buddies`
  - buddy pair requests, acceptance lifecycle, invite codes
- `buddy_nudges`
  - quick motivational nudges
- `buddy_messages`
  - one-to-one chat with message types and reply threading
- `buddy_typing_status`
  - typing indicator TTL state
- `buddy_blocks`
  - user block relationships
- `buddy_reports`
  - moderation report queue

### 3.3 Notification and Gamification Tables

- `notifications`
  - in-app alerts (`deadline_24h`, `deadline_1h`, `overdue`, `study_reminder`, `streak_risk`)
- `notification_preferences`
  - per-user notification toggles
- `dashboard_widgets`
  - saved widget order and visibility
- `user_achievements`
  - unlocked achievement keys and timestamps

### 3.4 Study Group Tables (v6.0)

- `study_groups`
  - name, description, leader, invite code, max members
  - group controls: `allow_member_assign`, `allow_member_invite`, `join_mode` (`open` or `approval`)
- `group_members`
  - group membership and role (`leader`, `member`)
- `group_tasks`
  - group-assigned tasks and completion status
- `group_messages`
  - group chat messages (`text`, `nudge`, `emoji`, `system`) with replies
- `group_message_reads`
  - per-group unread pointer (`last_read_id`) per user
- `group_join_requests`
  - pending/approved/rejected requests for approval mode groups

---

## 4. Configuration and Security Helpers

Source: `config/db.php`

### 4.1 Environment and session setup

- Reads DB connection from env vars with local defaults:
  - `DB_HOST`, `DB_USER`, `DB_PASS`, `DB_NAME`
- Session hardening:
  - strict mode
  - HttpOnly cookie
  - SameSite=Lax
  - secure cookie when HTTPS is detected

### 4.2 Constants

- `MAX_LOGIN_ATTEMPTS = 5`
- `LOCKOUT_DURATION = 15` minutes
- `MAX_FILE_SIZE = 10MB`
- `UPLOAD_DIR = uploads/`
- `ALLOWED_FILE_TYPES` whitelist for attachments

### 4.3 Utility functions

- `cleanInput($data)`
- `sanitize($data)`
- `e($str)`
- `generateCSRFToken()`
- `csrfTokenField()`
- `getCSRFField()`
- `validateCSRFToken($token = null)`
- `requireCSRF()`
- `redirect($location, $message = '', $type = '')`

Important note: `cleanInput()` and `sanitize()` are trim helpers, not full sanitizers; SQL safety is handled by prepared statements and output safety by escaping (`htmlspecialchars`/`e`).

---

## 5. Authentication Helpers

Source: `includes/auth.php`

- `isLoggedIn()`
- `isStudent()`
- `isAdminRole()`
- `requireLogin()`
- `requireAdmin()`
- `getCurrentUserId()`
- `getCurrentUserRole()`
- `regenerateSession()`
- `secureLogin($user)`
- `secureLogout()`
- `isAccountLocked($email, $conn)`
- `incrementLoginAttempts($email, $conn)`
- `resetLoginAttempts($email, $conn)`

Behavior highlights:
- idle timeout enforcement in `requireLogin()`
- session regeneration on login
- brute-force lockout counters in users table

---

## 6. Core Function Catalog (`includes/functions.php`)

This section lists the primary function surface grouped by domain.

### 6.1 User, semester, and subjects

- `getUserInfo($user_id, $conn)`
- `getActiveSemester($user_id, $conn)`
- `getUserSemesters($user_id, $conn)`
- `getSemesterSubjects($semester_id, $conn)`
- `isAdmin($user_id, $conn)`

### 6.2 Tasks, dashboard metrics, and calendar data

- `getSubjectTasks($subject_id, $conn)`
- `getUserTasks($user_id, $conn, $limit = 0, $offset = 0)`
- `getUserTasksFiltered($user_id, $conn, $subject_id = 0, $status = '', $sort = 'deadline', $sort_dir = 'ASC')`
- `getTaskStatusCounts($user_id, $conn)`
- `getSubjectTaskStats($subject_id, $conn)`
- `getPendingTasksCount($user_id, $conn)`
- `getCompletedTasksCount($user_id, $conn)`
- `getTotalTasksCount($user_id, $conn)`
- `getUpcomingTasks($user_id, $conn, $days = 7)`
- `getCompletionPercentage($user_id, $conn)`
- `getDashboardStats($user_id, $conn)`
- `getTasksAsJSON($user_id, $conn)`
- `getSubtasks($parent_id, $conn)`
- `getSubtaskProgress($parent_id, $conn)`
- `getOverdueTasksCount($user_id, $conn)`

### 6.3 Formatting, admin helpers, and pagination

- `formatDate($date)`
- `formatDateTime($datetime)`
- `getPriorityColor($priority)`
- `getStatusColor($status)`
- `getTypeColor($type)`
- `getAllUsers($conn)`
- `getTotalSystemTasks($conn)`
- `getTotalUsers($conn)`
- `paginate($total, $per_page, $current_page)`
- `renderPagination($pagination, $base_url)`

### 6.4 Files, onboarding, and search

- `handleFileUpload($file, $user_id, $conn, $task_id = null, $note_id = null)`
- `getAttachments($conn, $task_id = null, $note_id = null)`
- `formatFileSize($bytes)`
- `handleProfilePhotoUpload($file, $user_id, $conn)`
- `needsOnboarding($user_id, $conn)`
- `getOnboardingProgress($user_id, $conn)`
- `dismissOnboarding($user_id, $conn)`
- `globalSearch($user_id, $conn, $query, $limit = 20)`

### 6.5 Study Buddy, safety, and request flow

- `getAcceptedBuddy($user_id, $conn)`
- `getLastBuddyPair($user_id, $conn)`
- `isBuddyBlocked($user_id, $other_id, $conn)`
- `blockBuddy($blocker_id, $blocked_id, $conn)`
- `unblockBuddy($blocker_id, $blocked_id, $conn)`
- `reportBuddy($reporter_id, $reported_id, $reason, $details, $conn)`
- `getBlockedUsers($user_id, $conn)`
- `checkChatRateLimit($user_id, $conn, $max_per_minute = 15)`
- `getPendingBuddyRequests($user_id, $conn)`
- `getSentBuddyRequest($user_id, $conn)`
- `generateBuddyCode()`
- `getBuddyProgress($buddy_id, $conn)`
- `getUnreadNudgeCount($user_id, $conn)`
- `getBuddyNudges($user_id, $conn, $limit = 10)`

### 6.6 Buddy chat transport and online presence

- `getChatMessages($user_id, $buddy_id, $conn, $limit = 50, $before_id = null)`
- `getNewChatMessages($user_id, $buddy_id, $conn, $after_id)`
- `sendChatMessage($sender_id, $receiver_id, $message, $conn, $type = 'text', $reply_to = null)`
- `markChatMessagesRead($user_id, $sender_id, $conn)`
- `getUnreadBuddyMessageCount($user_id, $conn)`
- `deleteChatMessage($message_id, $user_id, $conn)`
- `updateUserActivity($user_id, $conn)`
- `isUserOnline($user_id, $conn)`
- `updateTypingStatus($user_id, $conn)`
- `clearTypingStatus($user_id, $conn)`
- `isBuddyTyping($buddy_id, $conn)`

### 6.7 Notification utilities

- `createNotification($user_id, $type, $title, $message, $conn, $ref_id = null, $ref_type = 'general')`
- `getUnreadNotificationCount($user_id, $conn)`
- `getRecentNotifications($user_id, $conn, $limit = 8)`
- `getAllNotifications($user_id, $conn, $page = 1, $per_page = 20, $filter = 'all')`
- `markNotificationRead($notification_id, $user_id, $conn)`
- `markAllNotificationsRead($user_id, $conn)`
- `dismissNotification($notification_id, $user_id, $conn)`
- `getNotificationPreferences($user_id, $conn)`
- `updateNotificationPreferences($user_id, $prefs, $conn)`
- `notificationTimeAgo($datetime)`
- `getNotificationIcon($type)`

### 6.8 Task templates

- `getTaskTemplates($user_id, $conn)`
- `getTaskTemplate($template_id, $user_id, $conn)`
- `createTaskTemplate($user_id, $name, $title, $description, $type, $priority, $is_recurring, $recurrence_type, $conn)`
- `updateTaskTemplate($template_id, $user_id, $name, $title, $description, $type, $priority, $is_recurring, $recurrence_type, $conn)`
- `deleteTaskTemplate($template_id, $user_id, $conn)`
- `createTaskFromTemplate($template_id, $user_id, $subject_id, $deadline, $conn)`

### 6.9 Study Groups and Group Chat (v6.0)

- Group creation and discovery:
  - `generateGroupInviteCode($conn)`
  - `createStudyGroup($user_id, $name, $description, $conn)`
  - `getUserStudyGroups($user_id, $conn)`
  - `getGroupInfo($group_id, $user_id, $conn)`
  - `getGroupMembers($group_id, $conn)`
  - `getGroupMemberProgress($group_id, $conn)`
- Membership lifecycle:
  - `joinGroupByCode($user_id, $code, $conn)`
  - `leaveGroup($user_id, $group_id, $conn)`
  - `removeGroupMember($leader_id, $target_id, $group_id, $conn)`
  - `updateGroupSettings($group_id, $leader_id, $name, $description, $allow_member_assign, $conn, $allow_member_invite = 0, $join_mode = 'open')`
  - `getPendingJoinRequests($group_id, $conn)`
  - `approveJoinRequest($request_id, $leader_id, $conn)`
  - `rejectJoinRequest($request_id, $leader_id, $conn)`
- Group tasks:
  - `assignGroupTask($group_id, $assigned_by, $assigned_to, $title, $description, $deadline, $priority, $conn)`
  - `getGroupTasks($group_id, $conn, $filter_user = null)`
  - `toggleGroupTaskStatus($task_id, $user_id, $group_id, $conn)`
  - `deleteGroupTask($task_id, $user_id, $group_id, $conn)`
- Group chat:
  - `sendGroupMessage($group_id, $sender_id, $message, $conn, $type = 'text', $reply_to = null)`
  - `getGroupMessages($group_id, $conn, $limit = 50, $before_id = null)`
  - `getNewGroupMessages($group_id, $conn, $after_id)`
  - `markGroupMessagesRead($group_id, $user_id, $last_id, $conn)`
  - `getUnreadGroupMessageCount($user_id, $conn)`
  - `checkGroupChatRateLimit($user_id, $group_id, $conn, $max = 15)`

---

## 7. Gamification and Notification Worker

### 7.1 Gamification helper (`includes/gamification.php`)

- `getStudyStreak(int $user_id, $conn): array`
  - computes current streak, longest streak, and last-7-day activity map
- `checkAndAwardAchievements(int $user_id, $conn): array`
  - calculates eligibility and unlocks achievements into `user_achievements`
- `getDashboardWidgets(int $user_id, $conn): array`
  - returns widget defaults merged with persisted layout from `dashboard_widgets`

### 7.2 Notification checker (`includes/notification_checker.php`)

- `runNotificationChecker($user_id, $conn)`
  - throttled to once per minute per session
  - checks overdue tasks, due-in-1h, due-in-24h, streak risk
  - deduplicates notifications by type/reference
  - applies preference toggles
  - cleans notifications older than 30 days

---

## 8. Student Modules and Endpoints

### 8.1 `student/dashboard.php`

- Personal overview cards, charts, upcoming tasks, announcements
- Uses widget preferences and achievements/streak data

### 8.2 `student/semesters.php`

- Actions: create, edit, activate, delete semesters

### 8.3 `student/subjects.php`

- Actions:
  - `add`
  - `edit`
  - `delete`
  - `add_task` (inline subject task add)

### 8.4 `student/tasks.php`

- Features:
  - CRUD task management
  - recurrence support
  - subtask handling
  - template support
  - attachment integration
- AJAX actions:
  - `toggle_status`
  - `delete`
- Form actions:
  - `add`
  - `edit`

### 8.5 `student/calendar.php`

- FullCalendar integration
- AJAX action:
  - `reschedule` (deadline update)

### 8.6 `student/daily_planning.php`

- Date-based planning surface
- top-3 priorities, time blocks, daily study summaries
- writes optional time blocks into task records (`type='Other'`)

### 8.7 `student/notes.php`

- Quill-based rich text notes with search/filtering
- Actions:
  - `add`
  - `edit`
  - `delete`

### 8.8 `student/pomodoro.php`

- Focus/break timer, study session persistence, history retrieval
- AJAX actions:
  - `complete_task`
  - `get_history`
- default AJAX POST: insert study session
- fallback form POST: `save_session`

### 8.9 `student/study_analytics.php`

- weekly/monthly aggregates, trends, and chart views

### 8.10 `student/study_buddy.php` and `student/buddy_messenger.php`

- Buddy relationship lifecycle and messenger UI
- Buddy actions:
  - `send_request`
  - `accept_request`
  - `decline_request`
  - `cancel_request`
  - `unpair`
  - `block_buddy`
  - `unblock_buddy`
  - `report_buddy`
- Buddy chat AJAX actions:
  - `send_message`
  - `get_messages`
  - `get_new_messages`
  - `mark_read`
  - `typing`
  - `stop_typing`
  - `heartbeat`
  - `delete_message`

### 8.11 `student/study_groups.php` and `student/group_messenger.php`

- Group lifecycle, tasks, and group chat UI
- Group actions:
  - `create_group`
  - `join_group`
  - `leave_group`
  - `remove_member`
  - `assign_task`
  - `update_settings`
  - `approve_request`
  - `reject_request`
- Group AJAX actions:
  - `send_message`
  - `get_messages`
  - `get_new_messages`
  - `mark_read`
  - `toggle_task`
  - `delete_task`

### 8.12 `student/attachments.php`

- Attachment API actions:
  - `upload`
  - `list`
  - `delete`

### 8.13 `student/notifications.php` and `student/notification_api.php`

- Notification center page with filters and preference modal
- API actions:
  - `mark_read`
  - `mark_all_read`
  - `dismiss`
  - `dismiss_overdue`
  - `get_count`
  - `save_preferences`

### 8.14 Other student endpoints

- `student/global_search.php` (GET search endpoint)
- `student/save_widgets.php`
  - actions: `save_layout`, `toggle_widget`
- `student/dismiss_announcement.php`
- `student/dismiss_onboarding.php`

---

## 9. Admin Modules

- `admin/admin_dashboard.php`
  - aggregate system KPIs and high-level trends
- `admin/manage_users.php`
  - user search/filter, role change, delete safeguards
- `admin/announcements.php`
  - announcement CRUD with priority/expiry
- `admin/system_reports.php`
  - reporting views for tasks, users, and activity
- `admin/system_settings.php`
  - admin password reset + cleanup operations
- `admin/activity_log.php`
  - system activity visibility
- `admin/user_details.php`
  - per-user drill-down metrics and profile details

---

## 10. Frontend JavaScript Architecture

Source: `assets/js/main.js`

Primary modules and helpers:
- `StudifyToast`
- `DarkMode`
- `Sidebar`
- `ScrollAnimations`
- `LandingNav`
- `PomodoroTimer`
- `GlobalSearch`
- `KeyboardShortcuts`
- `FocusAmbiance`
- `StudifyConfirm`
- utility helpers:
  - `getCSRFToken()`
  - `toggleTaskStatus(...)`
  - `deleteTask(...)`

---

## 11. Security Summary

- Prepared statements for data access
- CSRF token generation and validation across state-changing operations
- Session hardening and login-session regeneration
- Idle timeout enforcement in authenticated pages
- Brute-force lockout controls
- Role checks (`requireLogin`, `requireAdmin`, and ownership checks)
- Upload MIME/size validation and controlled storage paths
- Buddy safety controls (block/report) and message rate limiting
- Group and buddy chat rate limits

---

## 12. Migration and Maintenance Scripts

### 12.1 Migration files

- `migrations/add_task_templates.sql`
- `migrations/add_study_groups.sql`

### 12.2 PHP migration scripts

- `run_migration.php`
  - runs the task template migration SQL
- `migrate_v5.php`
  - creates v5 tables (`dashboard_widgets`, `user_achievements`)
- `migrate_core_fixes.php`
  - compatibility/schema fixes (task ownership, buddy uniqueness, attachment integrity)

### 12.3 Operational helpers

- `setup.php` (fresh setup/bootstrap)
- `smoke_check.php` (basic smoke checks)
- `run_php_lint.py`, `lint_check.bat`, `lint_requested.bat` (lint workflow)

---

This documentation reflects the current repository state and includes Study Groups, group chat, notification APIs, and the full helper-function surface that was missing from earlier markdown versions.
