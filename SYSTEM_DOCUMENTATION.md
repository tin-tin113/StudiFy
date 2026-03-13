# 📘 STUDIFY — Complete System Documentation

> **Student Task Management System**
> Generated: March 12, 2026 | Schema Version: v3.1

---

## Table of Contents

1. [System Overview](#1-system-overview)
2. [Database Schema](#2-database-schema)
3. [Configuration & Security](#3-configuration--security)
4. [Authentication System](#4-authentication-system)
5. [Core PHP Functions](#5-core-php-functions)
6. [Student Modules](#6-student-modules)
7. [Admin Modules](#7-admin-modules)
8. [Auth Pages](#8-auth-pages)
9. [Frontend JavaScript Systems](#9-frontend-javascript-systems)
10. [Layout & UI Architecture](#10-layout--ui-architecture)
11. [PWA Support](#11-pwa-support)
12. [Security Summary](#12-security-summary)
13. [Key Features at a Glance](#13-key-features-at-a-glance)

---

## 1. System Overview

| Property | Value |
|---|---|
| **Stack** | PHP 8+, MySQL/MariaDB, Bootstrap 5.3, Vanilla JS |
| **Server** | Laragon (Apache) at `localhost/Studify/` |
| **Database** | MySQL `studify`, charset `utf8mb4` |
| **Auth** | Session-based with bcrypt password hashing |
| **Theme** | Green primary (`#16A34A`), white sidebar, dark mode toggle |
| **PWA** | Standalone display, manifest + icons |
| **Architecture** | Server-rendered PHP with AJAX enhancements |
| **CSS** | Single `style.css` with CSS custom properties for theming |
| **JS** | Single `main.js` (1053 lines), modular object pattern, zero jQuery |

---

## 2. Database Schema

### 2.1 `users`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AUTO_INCREMENT | Primary key |
| `name` | VARCHAR(255) | NOT NULL |
| `email` | VARCHAR(255) UNIQUE | NOT NULL, indexed |
| `password` | VARCHAR(255) | bcrypt hash |
| `role` | ENUM('student','admin') | Default `student`, indexed |
| `course` | VARCHAR(255) | e.g. "BS Information Systems" |
| `year_level` | INT | 1–6 |
| `profile_photo` | VARCHAR(500) | Relative path to uploaded avatar |
| `onboarding_completed` | TINYINT(1) | Default 0 |
| `login_attempts` | INT | Brute-force counter, default 0 |
| `locked_until` | DATETIME | Account lockout expiry |
| `created_at` | TIMESTAMP | Auto-set |
| `updated_at` | TIMESTAMP | ON UPDATE auto-set |

### 2.2 `password_resets`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `email` | VARCHAR(255) | Indexed |
| `token` | VARCHAR(255) | SHA-256 hash of raw token, indexed |
| `expires_at` | DATETIME | 1-hour expiry |
| `used` | TINYINT(1) | Default 0 |
| `created_at` | TIMESTAMP | |

### 2.3 `semesters`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `user_id` | INT FK → users | ON DELETE CASCADE |
| `name` | VARCHAR(255) | e.g. "1st Semester 2025-2026" |
| `is_active` | BOOLEAN | Default 0, indexed |
| `created_at` / `updated_at` | TIMESTAMP | |

### 2.4 `subjects`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `semester_id` | INT FK → semesters | ON DELETE CASCADE |
| `name` | VARCHAR(255) | |
| `instructor_name` | VARCHAR(255) | |
| `created_at` / `updated_at` | TIMESTAMP | |

### 2.5 `tasks`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `user_id` | INT FK → users | ON DELETE CASCADE |
| `subject_id` | INT FK → subjects | ON DELETE SET NULL, nullable |
| `parent_id` | INT FK → tasks (self) | ON DELETE CASCADE — for subtasks |
| `title` | VARCHAR(255) | |
| `description` | TEXT | |
| `deadline` | DATETIME | Indexed |
| `priority` | ENUM('Low','Medium','High') | Default 'Medium' |
| `type` | ENUM('Assignment','Quiz','Project','Exam','Report','Other') | Default 'Assignment' |
| `status` | ENUM('Pending','In Progress','Completed') | Default 'Pending', indexed |
| `is_recurring` | TINYINT(1) | Default 0 |
| `recurrence_type` | ENUM('Daily','Weekly','Monthly') | |
| `recurrence_end` | DATE | |
| `position` | INT | Reserved for future task ordering |
| `created_at` / `updated_at` | TIMESTAMP | |

### 2.6 `study_sessions`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `user_id` | INT FK → users | ON DELETE CASCADE |
| `task_id` | INT FK → tasks | ON DELETE SET NULL, nullable |
| `subject_id` | INT FK → subjects | ON DELETE SET NULL, nullable |
| `duration` | INT | Minutes |
| `session_type` | ENUM('Focus','Break') | Default 'Focus' |
| `created_at` | TIMESTAMP | Indexed |

### 2.7 `notes`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `subject_id` | INT FK → subjects | ON DELETE SET NULL, nullable |
| `user_id` | INT FK → users | ON DELETE CASCADE |
| `title` | VARCHAR(255) | |
| `content` | LONGTEXT | Rich HTML (Quill.js) or plain text |
| `content_type` | ENUM('plain','markdown') | Default 'markdown' |
| `created_at` / `updated_at` | TIMESTAMP | |

### 2.8 `attachments`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `user_id` | INT FK → users | ON DELETE CASCADE |
| `task_id` | INT FK → tasks | ON DELETE CASCADE, nullable |
| `note_id` | INT FK → notes | ON DELETE CASCADE, nullable |
| `file_name` | VARCHAR(255) | Original filename |
| `file_path` | VARCHAR(500) | Relative storage path |
| `file_size` | INT | Bytes |
| `file_type` | VARCHAR(100) | MIME type |
| `created_at` | TIMESTAMP | |

### 2.9 `announcements`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `admin_id` | INT FK → users | ON DELETE CASCADE |
| `title` | VARCHAR(255) | |
| `content` | TEXT | |
| `priority` | ENUM('Low','Normal','Important','Urgent') | Default 'Normal' |
| `expires_at` | DATE | Nullable |
| `created_at` / `updated_at` | TIMESTAMP | |

### 2.10 `announcement_reads`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `announcement_id` | INT FK → announcements | ON DELETE CASCADE |
| `user_id` | INT FK → users | ON DELETE CASCADE |
| `read_at` | TIMESTAMP | |
| | UNIQUE KEY | `(announcement_id, user_id)` |

### 2.11 `study_buddies`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `requester_id` | INT FK → users | ON DELETE CASCADE |
| `partner_id` | INT FK → users | ON DELETE CASCADE |
| `status` | ENUM('pending','accepted','declined','unlinked') | Default 'pending' |
| `invite_code` | VARCHAR(32) | Unique 8-char hex code |
| `created_at` / `updated_at` | TIMESTAMP | |
| | UNIQUE KEY | `(requester_id, partner_id)` |

### 2.12 `buddy_nudges`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `sender_id` / `receiver_id` | INT FK → users | ON DELETE CASCADE |
| `message` | VARCHAR(500) | |
| `is_read` | TINYINT(1) | Default 0 |
| `created_at` | TIMESTAMP | |

### 2.13 `buddy_messages`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `sender_id` / `receiver_id` | INT FK → users | ON DELETE CASCADE |
| `message` | TEXT | |
| `message_type` | ENUM('text','nudge','emoji','system') | Default 'text' |
| `reply_to_id` | INT FK → buddy_messages | ON DELETE SET NULL — reply threading |
| `is_read` | TINYINT(1) | Default 0 |
| `created_at` | TIMESTAMP | Composite indexes for conversations |

### 2.14 `buddy_typing_status`

| Column | Type | Notes |
|---|---|---|
| `user_id` | INT PK FK → users | ON DELETE CASCADE |
| `typing_until` | TIMESTAMP | 3-second TTL |

### 2.15 `buddy_blocks`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `blocker_id` / `blocked_id` | INT FK → users | ON DELETE CASCADE |
| `created_at` | TIMESTAMP | |
| | UNIQUE KEY | `(blocker_id, blocked_id)` |

### 2.16 `buddy_reports`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `reporter_id` / `reported_id` | INT FK → users | ON DELETE CASCADE |
| `reason` | ENUM('harassment','spam','inappropriate','impersonation','other') | |
| `details` | TEXT | |
| `status` | ENUM('pending','reviewed','resolved','dismissed') | Default 'pending' |
| `reviewed_by` | INT FK → users | ON DELETE SET NULL |
| `reviewed_at` | TIMESTAMP | |
| `created_at` | TIMESTAMP | |

### 2.17 `activity_log`

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK AI | |
| `user_id` | INT FK → users | ON DELETE SET NULL |
| `action` | VARCHAR(255) | |
| `details` | TEXT | |
| `ip_address` | VARCHAR(45) | IPv4/IPv6 |
| `created_at` | TIMESTAMP | |

---

## 3. Configuration & Security

**File:** `config/db.php`

### Connection
- **Driver:** `mysqli` to `localhost`
- **Database:** `studify`
- **Charset:** `utf8mb4`
- **User:** `root` (no password — local dev)

### Session Settings
- `use_strict_mode` = 1
- `cookie_httponly` = 1
- `cookie_samesite` = "Lax"

### Constants
| Constant | Value | Purpose |
|---|---|---|
| `MAX_LOGIN_ATTEMPTS` | 5 | Before lockout |
| `LOCKOUT_DURATION` | 15 | Minutes |
| `MAX_FILE_SIZE` | 10,485,760 | 10 MB |
| `UPLOAD_DIR` | `uploads/` | Relative to project root |
| `ALLOWED_FILE_TYPES` | pdf, doc, docx, ppt, pptx, xls, xlsx, txt, jpg, jpeg, png, gif, zip, rar | Whitelist |

### Utility Functions in `db.php`
| Function | Purpose |
|---|---|
| `cleanInput($data)` | `trim()` + `stripslashes()` + `htmlspecialchars()` |
| `sanitize($data)` | Alias for `cleanInput()` |
| `generateCSRFToken()` | Creates `$_SESSION['csrf_token']` via `random_bytes(32)` |
| `csrfTokenField()` / `getCSRFField()` | Returns `<input type="hidden">` HTML |
| `validateCSRFToken($token)` | `hash_equals()` comparison |
| `requireCSRF()` | Validates token, **rotates** (prevents replay), returns 403 for AJAX |
| `redirect($location, $message, $type)` | Sets flash message in session, `header()` redirect |

---

## 4. Authentication System

**File:** `includes/auth.php`

| Function | Signature | Purpose |
|---|---|---|
| `isLoggedIn()` | `(): bool` | Checks `$_SESSION['user_id']` exists |
| `isStudent()` | `(): bool` | `$_SESSION['role'] === 'student'` |
| `isAdminRole()` | `(): bool` | `$_SESSION['role'] === 'admin'` |
| `requireLogin()` | `(): void` | Redirects to login with `?redirect=` if not logged in; 2-hour idle timeout via `$_SESSION['last_activity']` |
| `requireAdmin()` | `(): void` | Redirects to index if not admin |
| `getCurrentUserId()` | `(): ?int` | Returns session user ID |
| `getCurrentUserRole()` | `(): ?string` | Returns session role |
| `regenerateSession()` | `(): void` | `session_regenerate_id(true)` |
| `secureLogin($user)` | `(array): void` | Sets all session vars, regenerates session ID |
| `secureLogout()` | `(): void` | Full session destruction + cookie clearing |
| `isAccountLocked($email, $conn)` | `(string, mysqli): bool` | Checks `locked_until`; auto-resets if expired |
| `incrementLoginAttempts($email, $conn)` | `(string, mysqli): void` | Increments counter; locks at MAX_LOGIN_ATTEMPTS |
| `resetLoginAttempts($email, $conn)` | `(string, mysqli): void` | Resets on successful login |

---

## 5. Core PHP Functions

**File:** `includes/functions.php` (939 lines)

### 5.1 User & Semester

| Function | Parameters | Returns | Purpose |
|---|---|---|---|
| `getUserInfo` | `($user_id, $conn)` | `?array` | Full user row |
| `getActiveSemester` | `($user_id, $conn)` | `?array` | Active semester (`is_active=1`) |
| `getUserSemesters` | `($user_id, $conn)` | `array` | All semesters, newest first |
| `getSemesterSubjects` | `($semester_id, $conn)` | `array` | Subjects in a semester |
| `isAdmin` | `($user_id, $conn)` | `bool` | DB-level admin check |

### 5.2 Tasks

| Function | Parameters | Returns | Purpose |
|---|---|---|---|
| `getSubjectTasks` | `($subject_id, $conn)` | `array` | Parent tasks for a subject |
| `getUserTasks` | `($user_id, $conn, $limit, $offset)` | `array` | Paginated tasks with joins |
| `getUserTasksFiltered` | `($user_id, $conn, $subject_id, $status, $sort, $sort_dir)` | `array` | SQL-filtered/sorted tasks; whitelist-validated sort; `FIELD()` for priority |
| `getTaskStatusCounts` | `($user_id, $conn)` | `array` | Single-query: `total`, `pending`, `in_progress`, `completed` |
| `getSubjectTaskStats` | `($subject_id, $conn)` | `array` | `total` + `completed` for subject cards |
| `getPendingTasksCount` | `($user_id, $conn)` | `int` | Count of pending parent tasks |
| `getCompletedTasksCount` | `($user_id, $conn)` | `int` | Count of completed parent tasks |
| `getTotalTasksCount` | `($user_id, $conn)` | `int` | Count of all parent tasks |
| `getUpcomingTasks` | `($user_id, $conn, $days)` | `array` | Incomplete tasks within N days |
| `getCompletionPercentage` | `($user_id, $conn)` | `int` | `completed / total * 100` |
| `getTasksAsJSON` | `($user_id, $conn)` | `string` | Calendar-formatted JSON with priority colors |

### 5.3 Subtasks

| Function | Parameters | Returns | Purpose |
|---|---|---|---|
| `getSubtasks` | `($parent_id, $conn)` | `array` | Child tasks by position |
| `getSubtaskProgress` | `($parent_id, $conn)` | `array` | `total` + `completed` subtask counts |

### 5.4 Dashboard & Stats

| Function | Parameters | Returns | Purpose |
|---|---|---|---|
| `getDashboardStats` | `($user_id, $conn)` | `array` | Single-query: total/pending/completed/in-progress + weekly study minutes |

### 5.5 Formatting & Display

| Function | Parameters | Returns | Purpose |
|---|---|---|---|
| `formatDate` | `($date)` | `string` | Format to `M d, Y` |
| `formatDateTime` | `($datetime)` | `string` | Format to `M d, Y h:i A` |
| `getPriorityColor` | `($priority)` | `string` | Bootstrap color class |
| `getStatusColor` | `($status)` | `string` | Bootstrap color class |
| `getTypeColor` | `($type)` | `string` | Bootstrap color class |

### 5.6 Admin

| Function | Parameters | Returns | Purpose |
|---|---|---|---|
| `getAllUsers` | `($conn)` | `array` | All users, newest first |
| `getTotalSystemTasks` | `($conn)` | `int` | System-wide task count |
| `getTotalUsers` | `($conn)` | `int` | System-wide user count |

### 5.7 Pagination

| Function | Parameters | Returns | Purpose |
|---|---|---|---|
| `paginate` | `($total, $per_page, $current_page)` | `array` | Offset, has_prev, has_next metadata |
| `renderPagination` | `($pagination, $base_url)` | `string` | Bootstrap pagination HTML |

### 5.8 File Upload

| Function | Parameters | Returns | Purpose |
|---|---|---|---|
| `handleFileUpload` | `($file, $user_id, $conn, $task_id, $note_id)` | `array` | Validates, stores in `uploads/{user_id}/`, inserts record |
| `getAttachments` | `($conn, $task_id, $note_id)` | `array` | Fetch attachments for task or note |
| `formatFileSize` | `($bytes)` | `string` | Human-readable (KB, MB) |
| `handleProfilePhotoUpload` | `($file, $user_id, $conn)` | `array` | Avatar upload (JPG/PNG/GIF/WebP, max 2MB) |

### 5.9 Onboarding

| Function | Parameters | Returns | Purpose |
|---|---|---|---|
| `needsOnboarding` | `($user_id, $conn)` | `bool` | True if no semesters/subjects/tasks and not dismissed |
| `dismissOnboarding` | `($user_id, $conn)` | `bool` | Sets `onboarding_completed = 1` |

### 5.10 Global Search

| Function | Parameters | Returns | Purpose |
|---|---|---|---|
| `globalSearch` | `($user_id, $conn, $query, $limit)` | `array` | Searches tasks, notes, subjects via `LIKE` |

### 5.11 Study Buddy

| Function | Parameters | Returns | Purpose |
|---|---|---|---|
| `getAcceptedBuddy` | `($user_id, $conn)` | `?array` | Active buddy (max 1 allowed) |
| `getLastBuddyPair` | `($user_id, $conn)` | `?array` | Most recent unlinked buddy |
| `isBuddyBlocked` | `($user_id, $other_id, $conn)` | `bool` | Bidirectional block check |
| `blockBuddy` | `($blocker, $blocked, $conn)` | `bool` | Unpairs + declines + blocks |
| `unblockBuddy` | `($blocker, $blocked, $conn)` | `bool` | Removes block record |
| `reportBuddy` | `($reporter, $reported, $reason, $details, $conn)` | `bool` | Creates admin report |
| `getBlockedUsers` | `($user_id, $conn)` | `array` | List of blocked users |
| `checkChatRateLimit` | `($user_id, $conn, $max)` | `bool` | Max 15 messages/minute |
| `getPendingBuddyRequests` | `($user_id, $conn)` | `array` | Incoming pending requests |
| `getSentBuddyRequest` | `($user_id, $conn)` | `?array` | Outgoing pending request |
| `generateBuddyCode` | `()` | `string` | 8-char uppercase hex |
| `getBuddyProgress` | `($buddy_id, $conn)` | `array` | Privacy-safe stats for buddy card |
| `getUnreadNudgeCount` | `($user_id, $conn)` | `int` | Unread nudge count |
| `getBuddyNudges` | `($user_id, $conn, $limit)` | `array` | Recent nudges with sender info |

### 5.12 Buddy Chat (Instant Messaging)

| Function | Parameters | Returns | Purpose |
|---|---|---|---|
| `getChatMessages` | `($user_id, $buddy_id, $conn, $limit, $before_id)` | `array` | Paginated history with reply threading |
| `getNewChatMessages` | `($user_id, $buddy_id, $conn, $after_id)` | `array` | New messages since ID (for polling) |
| `sendChatMessage` | `($sender, $receiver, $message, $conn, $type, $reply_to)` | `int` | Inserts message, returns ID |
| `markChatMessagesRead` | `($user_id, $sender_id, $conn)` | `int` | Marks all from sender as read |
| `getUnreadBuddyMessageCount` | `($user_id, $conn)` | `int` | Total unread messages |
| `deleteChatMessage` | `($message_id, $user_id, $conn)` | `bool` | Sender-only deletion |
| `updateUserActivity` | `($user_id, $conn)` | `void` | Updates `last_active` timestamp |
| `isUserOnline` | `($user_id, $conn)` | `bool` | Active within 2 minutes |
| `updateTypingStatus` | `($user_id, $conn)` | `void` | 3-second typing TTL |
| `clearTypingStatus` | `($user_id, $conn)` | `void` | Clears typing indicator |
| `isBuddyTyping` | `($buddy_id, $conn)` | `bool` | Checks typing status |

---

## 6. Student Modules

### 6.1 Dashboard (`student/dashboard.php`)

- **Features:** Time-based greeting, 4 stat cards (total/pending/completed/in-progress), completion donut chart, priority distribution chart, weekly study hours bar chart, weekly summary, upcoming deadlines, unread announcements
- **Data:** `getDashboardStats()`, `getUpcomingTasks()`, priority queries, weekly charts

### 6.2 Tasks (`student/tasks.php`)

- **Features:** Card-based task list, add/edit/delete, status toggle, filter by subject & status, sort by deadline/priority/created/title, recurring task generation (Daily/Weekly/Monthly), subtask support
- **Separated views:** Active tasks (Pending/In Progress) shown in main list; Completed tasks in collapsible section below
- **AJAX Endpoints:**
  - `action=toggle_status` → toggles Pending ↔ Completed
  - `action=delete` → deletes task
- **Form Actions:** `add`, `edit`

### 6.3 Subjects (`student/subjects.php`)

- **Features:** Subject cards with task completion stats, add/edit/delete, auto-selects active semester, ownership verification
- **Form Actions:** `add`, `edit`, `delete`

### 6.4 Semesters (`student/semesters.php`)

- **Features:** Semester cards with activation toggle, add/edit/delete/rename, prevents deleting active semester, combo dropdown + custom name input for adding
- **Form Actions:** `add`, `edit`, `activate`, `delete`

### 6.5 Notes (`student/notes.php`)

- **Features:** Quill.js WYSIWYG rich text editor, notes optionally linked to subjects, grid card view with previews, search & filter, view in modal with formatted HTML rendering
- **Rich Text Toolbar:** Headings, bold/italic/underline/strike, text & background color, ordered/unordered lists, blockquote, code block, alignment, links, images, clear formatting
- **Form Actions:** `add`, `edit`, `delete`

### 6.6 Calendar (`student/calendar.php`)

- **Features:** FullCalendar 6.1.10, tasks as color-coded events (priority-based), drag-to-reschedule, add task from calendar, click event for details
- **AJAX:** `action=update_deadline` → updates task deadline on drag

### 6.7 Pomodoro Timer (`student/pomodoro.php`)

- **Features:** SVG circular timer, Focus/Short Break/Long Break modes, configurable durations, presets (Classic 25/5, Deep 50/10, Marathon 90/20), auto-start breaks, link to task/subject, mark task complete after session, session history with pagination, daily goal with celebration, persistent timer state across page loads, keyboard shortcuts
- **Focus Ambiance:** 6 procedural Web Audio sounds (Rain, Fire, Waves, Birds, Café, Wind), volume control, presets (Cozy, Nature, Storm, Study Café, Zen), auto-play/pause with timer
- **AJAX Endpoints:**
  - Default POST → saves study session
  - `action=complete_task` → marks task + subtasks as Completed
  - `action=get_history` → paginated session history

### 6.8 Study Analytics (`student/study_analytics.php`)

- **Features:** Total/weekly/monthly study stats, average session length, most productive day, study streak, 7-day bar chart, 6-month trend line chart, day-of-week radar chart
- **Charts:** Chart.js 4.4.0

### 6.9 Study Buddy (`student/study_buddy.php`)

- **Features:** 1-to-1 buddy pairing, send/accept/decline requests by email, invite codes, unpair, block/unblock/report, privacy-safe progress comparison, nudge presets (wave, motivate, reminder, celebrate, challenge), full instant messenger
- **AJAX Endpoints:**
  - `action=send_message` → sends chat (rate-limited: 15/min)
  - `action=load_messages` → initial paginated load
  - `action=poll_messages` → new messages + typing/online status
  - `action=mark_read` → marks messages + nudges as read
  - `action=typing_start` / `typing_stop` → 3-second TTL indicators
  - `action=heartbeat` → online status ping
  - `action=delete_message` → sender-only deletion
- **Form Actions:** `send_request`, `accept_request`, `decline_request`, `unpair`, `block_buddy`, `unblock_buddy`, `report_buddy`

### 6.10 Buddy Messenger (`student/buddy_messenger.php`)

- **Included from** `study_buddy.php` when buddies are paired
- **Features:** Messenger-style chat UI, conversation bubbles, online indicator, typing indicator, reply-to threading, dropdown menu (report, block, unpair), report modal with reason categories

### 6.11 Global Search (`student/global_search.php`)

- **AJAX GET:** `?q=searchterm`
- **Searches:** tasks (title/description), notes (title/content), subjects (name/instructor)
- **Returns:** JSON array with type tags

### 6.12 Utility Endpoints

| File | Method | Purpose |
|---|---|---|
| `student/dismiss_announcement.php` | AJAX POST | Inserts into `announcement_reads` |
| `student/dismiss_onboarding.php` | AJAX POST | Sets `onboarding_completed = 1` |

---

## 7. Admin Modules

### 7.1 Admin Dashboard (`admin/admin_dashboard.php`)

- **Stats:** Total users, total tasks, completion rate, total study hours, semesters, subjects, new users this month, pending tasks
- **Tables:** Recent 5 users, top 5 active users by task count

### 7.2 Manage Users (`admin/manage_users.php`)

- **Features:** User list with search, filter by role, delete users (prevents last admin), change role (prevents last admin demotion), cleans up avatar files on delete
- **Actions:** `delete_user`, `change_role`

### 7.3 Announcements (`admin/announcements.php`)

- **Features:** Create/edit/delete, priority levels (Low/Normal/Important/Urgent), expiry dates, read count tracking per announcement
- **Actions:** `add`, `edit`, `delete`

### 7.4 System Settings (`admin/system_settings.php`)

- **Features:** Database record counts, admin password reset for any user, cleanup old completed tasks (min 30 days), cleanup old study sessions
- **Actions:** `reset_password`, `cleanup_completed`, `cleanup_sessions`

### 7.5 System Reports (`admin/system_reports.php`)

- **Features:** Summary cards, tasks by status/priority/type charts, monthly user registration trend, average tasks per user

### 7.6 Activity Log (`admin/activity_log.php`)

- **Features:** Filterable feed (All/Tasks/Study/Users), recent tasks, study sessions, registrations

### 7.7 User Details (`admin/user_details.php`)

- **URL:** `?id=X`
- **Features:** Full user profile, task stats, study sessions, semesters/subjects

---

## 8. Auth Pages

| Page | File | Key Features |
|---|---|---|
| **Login** | `auth/login.php` | CSRF, bcrypt verify, brute-force lockout, redirect support, role-based redirect |
| **Register** | `auth/register.php` | Validation (min 8 chars, uppercase + number), duplicate check, auto-redirect to login |
| **Forgot Password** | `auth/forgot_password.php` | Token via `random_bytes(32)`, SHA-256 hash stored, 1-hour expiry, demo mode shows link |
| **Reset Password** | `auth/reset_password.php` | Token validation, same password policy, marks token as used |
| **Profile** | `auth/profile.php` | Update name/email/course/year, change password, avatar upload (2MB, MIME validated) |
| **Logout** | `auth/logout.php` | Full session destroy + cookie clear |

---

## 9. Frontend JavaScript Systems

**File:** `assets/js/main.js` (1053 lines)

### 9.1 `StudifyToast`
Custom toast notification system. Methods: `.success()`, `.error()`, `.info()`, `.warning()`, `.show()`. Auto-removes after 4 seconds with exit animation.

### 9.2 `DarkMode`
Toggles `data-theme="dark"` on `<html>`, persists via `localStorage`. Updates sun/moon icon on toggle buttons.

### 9.3 `Sidebar`
Mobile hamburger toggle with overlay. Desktop collapse toggle persists via `localStorage`. Adds `sidebar-collapsed` class.

### 9.4 `ScrollAnimations`
`IntersectionObserver`-based `.fade-in-up` animation trigger for elements entering the viewport.

### 9.5 `LandingNav`
Adds `.scrolled` class to `<nav>` on scroll > 50px (landing page only).

### 9.6 `GlobalSearch`
- Opens via **Ctrl+K** / **Cmd+K** / `/`
- 300ms debounced AJAX search to `global_search.php`
- Arrow key navigation, Enter to select, ESC to close
- Groups results by type (Tasks / Notes / Subjects) with icons
- XSS-safe via DOM `textContent` → `innerHTML`

### 9.7 `KeyboardShortcuts`
| Key | Action |
|---|---|
| **Ctrl+K** / **Cmd+K** | Open search |
| **/** | Open search |
| **N** | Navigate to tasks |
| **P** | Navigate to pomodoro |
| **D** | Navigate to dashboard |
| **ESC** | Close search |
- Skips shortcuts in `<input>`, `<textarea>`, `<select>`, and `contenteditable` elements (Quill editor safe)

### 9.8 `FocusAmbiance`
- **Web Audio API** procedural sound generation — no external audio files
- **6 sounds:** Rain, Fire/Crackling, Ocean Waves, Birds/Chirps, Coffee Shop, Wind
- Individual volume sliders, simultaneous playback
- **5 presets:** Cozy (Rain+Fire), Nature (Birds+Waves), Study Café (Coffee+Rain), Storm (Rain+Wind), Zen (Birds+Wind)
- Auto-pause during breaks, auto-resume on focus
- Hooks: `.onFocusStart()`, `.onBreakStart()`, `.onPause()`

### 9.9 `StudifyConfirm`
Promise-based confirmation dialog system. Methods: `.show()`, `.action()`, `.delete()`, `.form()`, `.logout()`. Types: danger, warning, info, success. ESC / click-outside to cancel.

### 9.10 Helper Functions

| Function | Purpose |
|---|---|
| `getCSRFToken()` | Reads from `<meta name="csrf-token">` |
| `toggleTaskStatus(id, base)` | AJAX POST to toggle task status |
| `deleteTask(id, base)` | AJAX DELETE with fade animation |
| `showConfirmDialog(...)` | Shorthand for `StudifyConfirm.show()` |

---

## 10. Layout & UI Architecture

### Header (`includes/header.php` — 406 lines)

- **Meta tags:** `base-url`, `csrf-token`, `theme-color`, manifest link
- **Dependencies loaded:** Bootstrap 5.3 CSS, Font Awesome 6.4, FullCalendar 6.1.10 CSS
- **Sidebar:** Logo, role-based nav (student: 9 links + badge counts; admin: 6 links), user avatar footer
- **Topbar:** Hamburger toggle, page title, search button (Ctrl+K), dark mode toggle, notification dropdown (tasks + announcements + buddy messages), user avatar dropdown
- **Global Search Modal:** Full-screen overlay, keyboard navigation
- **Confirmation Dialog:** Reusable overlay for destructive actions
- **Onboarding Checklist:** 3-step guide (semester → subjects → task), dismissable
- **Flash Messages:** Session-based → toast conversion

### Footer (`includes/footer.php`)

- Loads: Bootstrap 5.3 JS Bundle, FullCalendar 6.1.10 JS, Chart.js 4.4.0, `main.js` (cache-busted via `filemtime()`)

### CSS (`assets/css/style.css`)

- Single monolithic stylesheet with `filemtime()` cache-busting
- **CSS custom properties** (`:root` vars) for all colors, spacing, shadows
- **Dark mode** via `[data-theme="dark"]` selectors
- **Layout:** Fixed sidebar (240px) + topbar + scrollable main
- **Responsive:** Sidebar overlay on mobile, collapse on desktop
- **Component styles:** Auth cards, stat cards, task cards (with priority borders + status badges), calendar, pomodoro ring, toast notifications, search modal, confirmation dialog, onboarding checklist, completed tasks section, ambiance grid, WYSIWYG editor

---

## 11. PWA Support

**File:** `manifest.json`

```json
{
  "name": "Studify",
  "short_name": "Studify",
  "description": "Academic Task Manager for Students",
  "start_url": "/Studify/",
  "display": "standalone",
  "background_color": "#F9FAFB",
  "theme_color": "#16A34A",
  "icons": ["192x192", "512x512"]
}
```

---

## 12. Security Summary

| Feature | Implementation |
|---|---|
| **CSRF Protection** | Token per session via `random_bytes(32)`, validated on all POSTs, **rotated after use** (replay prevention) |
| **Password Hashing** | `password_hash(PASSWORD_BCRYPT)` / `password_verify()` |
| **SQL Injection** | Prepared statements (`bind_param`) throughout |
| **XSS Prevention** | `htmlspecialchars()` on all output; JS-side DOM `textContent` sanitization |
| **Session Security** | `session_regenerate_id(true)` on login, `use_strict_mode`, `cookie_httponly`, `cookie_samesite=Lax`, 2-hour idle timeout |
| **Brute Force** | Max 5 login attempts → 15-minute lockout |
| **Input Sanitization** | `trim` + `stripslashes` + `htmlspecialchars` via `cleanInput()` |
| **File Upload** | Extension whitelist, MIME validation, size limits (10MB/2MB), unique filenames |
| **Authorization** | `requireLogin()`, `requireAdmin()`, ownership checks on all data operations |
| **Password Policy** | Min 8 characters, requires uppercase + number |
| **Chat Rate Limiting** | Max 15 messages per minute |
| **Token-based Reset** | SHA-256 hashed, 1-hour expiry, single-use |
| **Admin Safety** | Prevents deleting/demoting last admin account |

---

## 13. Key Features at a Glance

| Feature | Technology |
|---|---|
| 🍅 **Pomodoro Timer** | SVG circular ring, vanilla JS, AJAX session save, task completion prompt |
| 🎵 **Focus Ambiance** | Web Audio API procedural sound (6 environments, 5 presets) |
| 📝 **Rich Text Notes** | Quill.js WYSIWYG editor with full toolbar, dark mode support |
| 📅 **Calendar** | FullCalendar 6.1.10 with drag-to-reschedule |
| 📊 **Study Analytics** | Chart.js 4.4 (bar, line, radar, doughnut charts) |
| 👥 **Study Buddy** | 1:1 pairing, real-time chat (polling), typing indicators, nudges, block/report |
| 🔍 **Global Search** | Ctrl+K modal, debounced AJAX, keyboard navigation |
| 🌙 **Dark Mode** | CSS custom properties, localStorage persistence |
| ✅ **Task Management** | CRUD, status filtering, priority sorting, recurring, subtasks, separated completed view |
| 📢 **Announcements** | Admin → student, priority levels, expiry, read tracking |
| 🎓 **Onboarding** | 3-step guided checklist for new users |
| 📱 **PWA** | manifest.json, standalone display mode |
| ⌨️ **Keyboard Shortcuts** | N/P/D/Ctrl+K navigation, Space for Pomodoro |
| 🔒 **Security** | CSRF rotation, bcrypt, brute-force lockout, prepared statements, rate limiting |

---

*This document covers all modules, functions, database tables, and features of the Studify system as of March 12, 2026.*
