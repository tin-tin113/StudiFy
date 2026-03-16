#!/usr/bin/env python3
"""
Studify System Documentation PDF Generator
Generates a comprehensive PDF explaining all system functions,
their connections, importance, and benefits.
"""

from fpdf import FPDF
from fpdf.enums import XPos, YPos

# --- Colour palette ------------------------------------------------------------
PRIMARY   = (37,  99, 235)   # blue-600
DARK      = (17,  24,  39)   # gray-900
MEDIUM    = (55,  65,  81)   # gray-700
LIGHT     = (107, 114, 128)  # gray-500
BG_LIGHT  = (241, 245, 249)  # slate-100
BG_WHITE  = (255, 255, 255)
ACCENT    = (16, 185, 129)   # emerald-500
WARNING   = (245, 158,  11)  # amber-500
DANGER    = (239,  68,  68)  # red-500
PURPLE    = (139,  92, 246)  # violet-500
TEAL      = (20,  184, 166)  # teal-500

# --- Document class ------------------------------------------------------------
class StudifyDocs(FPDF):

    def header(self):
        if self.page_no() == 1:
            return
        self.set_fill_color(*PRIMARY)
        self.rect(0, 0, 210, 10, 'F')
        self.set_font('Helvetica', 'B', 8)
        self.set_text_color(*BG_WHITE)
        self.set_xy(10, 2)
        self.cell(0, 6, 'Studify - System Functions & Architecture Reference  v6.0', new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        self.set_text_color(*DARK)
        self.ln(4)

    def footer(self):
        if self.page_no() == 1:
            return
        self.set_y(-12)
        self.set_draw_color(*PRIMARY)
        self.set_line_width(0.4)
        self.line(10, self.get_y(), 200, self.get_y())
        self.ln(1)
        self.set_font('Helvetica', '', 7)
        self.set_text_color(*LIGHT)
        self.cell(0, 5, f'Page {self.page_no()} | © 2026 Studify - Academic Planning Platform', align='C')

    # -- helpers --------------------------------------------------------------

    def h1(self, text, color=PRIMARY):
        self.ln(4)
        self.set_fill_color(*color)
        self.rect(10, self.get_y(), 190, 10, 'F')
        self.set_font('Helvetica', 'B', 13)
        self.set_text_color(*BG_WHITE)
        self.set_x(12)
        self.cell(0, 10, text, new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        self.set_text_color(*DARK)
        self.ln(2)

    def h2(self, text, color=DARK):
        self.ln(3)
        self.set_font('Helvetica', 'B', 11)
        self.set_text_color(*color)
        self.set_x(10)
        self.cell(0, 7, text, new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        self.set_draw_color(*color)
        self.set_line_width(0.5)
        self.line(10, self.get_y(), 200, self.get_y())
        self.set_text_color(*DARK)
        self.ln(2)

    def h3(self, text, color=MEDIUM):
        self.ln(2)
        self.set_font('Helvetica', 'B', 9.5)
        self.set_text_color(*color)
        self.set_x(12)
        self.cell(0, 6, '>  ' + text, new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        self.set_text_color(*DARK)

    def body(self, text, indent=14):
        self.set_font('Helvetica', '', 9)
        self.set_text_color(*MEDIUM)
        self.set_x(indent)
        self.multi_cell(196 - indent, 5, text)
        self.set_text_color(*DARK)

    def bullet(self, text, indent=16, color=PRIMARY):
        self.set_font('Helvetica', '', 9)
        self.set_text_color(*color)
        self.set_x(indent)
        self.cell(4, 5, '*')
        self.set_text_color(*MEDIUM)
        self.multi_cell(192 - indent, 5, text)
        self.set_text_color(*DARK)

    def func_row(self, name, desc, bg=False):
        y = self.get_y()
        if bg:
            self.set_fill_color(*BG_LIGHT)
            self.rect(12, y, 186, 7, 'F')
        self.set_font('Courier', 'B', 8)
        self.set_text_color(*PRIMARY)
        self.set_x(13)
        self.cell(62, 7, name[:40])
        self.set_font('Helvetica', '', 8)
        self.set_text_color(*MEDIUM)
        self.multi_cell(120, 7, desc)
        self.set_text_color(*DARK)

    def tag(self, text, color):
        self.set_font('Helvetica', 'B', 7.5)
        self.set_fill_color(*color)
        self.set_text_color(*BG_WHITE)
        self.cell(len(text)*2.4 + 4, 5, text, fill=True, new_x=XPos.RIGHT, new_y=YPos.LAST)
        self.set_text_color(*DARK)
        self.cell(2, 5, '')

    def info_box(self, title, text, color=ACCENT):
        self.ln(2)
        self.set_fill_color(*color)
        self.rect(10, self.get_y(), 3, 20, 'F')
        self.set_fill_color(240, 253, 250)
        self.rect(13, self.get_y(), 187, 18, 'F')
        y = self.get_y() + 2
        self.set_xy(15, y)
        self.set_font('Helvetica', 'B', 9)
        self.set_text_color(*color)
        self.cell(0, 5, title, new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        self.set_x(15)
        self.set_font('Helvetica', '', 8.5)
        self.set_text_color(*MEDIUM)
        self.multi_cell(183, 5, text)
        self.set_text_color(*DARK)
        self.ln(2)

    def conn_row(self, source, arrow, target, desc):
        self.set_font('Courier', 'B', 8)
        self.set_text_color(*PRIMARY)
        self.set_x(13)
        self.cell(48, 6, source)
        self.set_font('Helvetica', 'B', 9)
        self.set_text_color(*ACCENT)
        self.cell(12, 6, arrow, align='C')
        self.set_font('Courier', 'B', 8)
        self.set_text_color(*PURPLE)
        self.cell(48, 6, target)
        self.set_font('Helvetica', '', 8)
        self.set_text_color(*MEDIUM)
        self.multi_cell(0, 6, desc)
        self.set_text_color(*DARK)


# ==============================================================================
def build_pdf():
    pdf = StudifyDocs('P', 'mm', 'A4')
    pdf.set_auto_page_break(auto=True, margin=16)
    pdf.set_margins(10, 14, 10)
    pdf.set_title('Studify - System Functions Reference')
    pdf.set_author('Studify Documentation Generator')

    # =============================================================
    # COVER PAGE
    # =============================================================
    pdf.add_page()
    # Background gradient band
    pdf.set_fill_color(*PRIMARY)
    pdf.rect(0, 0, 210, 90, 'F')
    pdf.set_fill_color(30, 64, 175)   # slightly darker
    pdf.rect(0, 62, 210, 28, 'F')

    # Title
    pdf.set_xy(0, 22)
    pdf.set_font('Helvetica', 'B', 34)
    pdf.set_text_color(*BG_WHITE)
    pdf.cell(210, 14, 'STUDIFY', align='C', new_x=XPos.LMARGIN, new_y=YPos.NEXT)
    pdf.set_font('Helvetica', '', 14)
    pdf.cell(210, 8, 'Academic Planning & Collaboration Platform', align='C', new_x=XPos.LMARGIN, new_y=YPos.NEXT)
    pdf.set_font('Helvetica', 'I', 10)
    pdf.set_text_color(186, 230, 253)
    pdf.cell(210, 6, 'System Functions, Architecture & Developer Reference -- v6.0', align='C', new_x=XPos.LMARGIN, new_y=YPos.NEXT)

    # White card area
    pdf.set_fill_color(*BG_WHITE)
    pdf.rect(20, 98, 170, 160, 'F')

    pdf.set_xy(25, 106)
    pdf.set_font('Helvetica', 'B', 11)
    pdf.set_text_color(*DARK)
    pdf.cell(160, 7, 'What this document covers', new_x=XPos.LMARGIN, new_y=YPos.NEXT)
    pdf.set_draw_color(*PRIMARY)
    pdf.set_line_width(0.5)
    pdf.line(25, pdf.get_y(), 185, pdf.get_y())
    pdf.ln(2)

    topics = [
        ('1', 'System Overview & Technology Stack'),
        ('2', 'Authentication & Security Module'),
        ('3', 'Core Library -- All PHP Functions Explained'),
        ('4', 'Student Modules: Tasks, Notes, Calendar, Pomodoro'),
        ('5', 'Collaboration: Study Buddy & Study Groups (v6.0)'),
        ('6', 'Notifications & Gamification Engine'),
        ('7', 'Admin Panel & System Management'),
        ('8', 'Database Schema & Table Relationships'),
        ('9', 'Module Interconnections & Data Flow'),
        ('10', 'Importance & Benefits of Each Feature'),
    ]
    for num, title in topics:
        pdf.set_x(26)
        pdf.set_fill_color(*PRIMARY)
        pdf.set_text_color(*BG_WHITE)
        pdf.set_font('Helvetica', 'B', 8)
        pdf.cell(7, 6, num, fill=True, align='C')
        pdf.set_text_color(*MEDIUM)
        pdf.set_font('Helvetica', '', 9)
        pdf.cell(0, 6, '  ' + title, new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        pdf.ln(1)

    pdf.set_xy(25, 245)
    pdf.set_font('Helvetica', '', 8)
    pdf.set_text_color(*LIGHT)
    pdf.cell(0, 5, 'Generated: March 2026  |  PHP 8+  /  MySQL  /  Bootstrap 5  |  localhost/Studify')

    # =============================================================
    # 1. SYSTEM OVERVIEW
    # =============================================================
    pdf.add_page()
    pdf.h1('1.  SYSTEM OVERVIEW')

    pdf.h2('What is Studify?')
    pdf.body(
        'Studify is a full-stack, server-rendered web application built on PHP 8+ and MySQL. '
        'It serves as an all-in-one academic productivity platform, helping university '
        'students organise semesters, manage tasks with deadlines, take rich-text notes, '
        'track study sessions with a Pomodoro timer, collaborate with peers via Study Buddy '
        'pairing or Study Groups, and stay motivated through a gamification engine (streaks '
        'and achievements). An admin panel provides user oversight, system announcements, and '
        'usage reports.'
    )
    pdf.ln(3)

    pdf.h2('Technology Stack')
    stack = [
        ('Backend', 'PHP 8+ -- server-side rendering, session auth, prepared statements'),
        ('Database', 'MySQL -- relational schema, foreign keys, unique constraints'),
        ('Frontend', 'Bootstrap 5.3 + custom CSS -- responsive, mobile-first layout'),
        ('Charts', 'Chart.js -- bar/line visualisations in analytics & dashboard'),
        ('Calendar', 'FullCalendar 6.1 -- interactive deadline calendar with drag-to-reschedule'),
        ('Rich Text', 'Quill.js -- note editor with formatting toolbar'),
        ('Drag & Drop', 'SortableJS 1.15 -- dashboard widget reordering'),
        ('Icons', 'Font Awesome 6.4 -- icon library throughout the UI'),
        ('Security', 'CSRF tokens, bcrypt passwords, session hardening, brute-force lockout'),
    ]
    for label, desc in stack:
        pdf.set_x(14)
        pdf.set_font('Helvetica', 'B', 9)
        pdf.set_text_color(*PRIMARY)
        pdf.cell(28, 6, label + ':')
        pdf.set_font('Helvetica', '', 9)
        pdf.set_text_color(*MEDIUM)
        pdf.multi_cell(160, 6, desc)

    pdf.h2('Directory Structure at a Glance')
    dirs = [
        ('config/',    'Database connection, constants, CSRF helpers, utility functions'),
        ('includes/',  'Shared auth, core library (functions.php), gamification, notification worker, layout partials'),
        ('auth/',      'Login, register, logout, password reset, profile management'),
        ('student/',   'All student-facing pages + JSON API endpoints'),
        ('admin/',     'Admin dashboard, user management, announcements, reports, settings'),
        ('assets/',    'CSS (style.css) and JavaScript (main.js) -- front-end logic'),
        ('uploads/',   'User-uploaded files: attachments/, avatars/, photos/'),
        ('migrations/','Incremental SQL migrations for schema upgrades (v5, v6)'),
    ]
    for d, desc in dirs:
        pdf.set_x(14)
        pdf.set_font('Courier', 'B', 8.5)
        pdf.set_text_color(*PRIMARY)
        pdf.cell(30, 6, d)
        pdf.set_font('Helvetica', '', 8.5)
        pdf.set_text_color(*MEDIUM)
        pdf.multi_cell(160, 6, desc)

    pdf.h2('User Roles')
    pdf.bullet('Student -- The primary user. Has access to all academic planning, collaboration, and gamification features.')
    pdf.bullet('Admin -- Elevated role. Can manage all users, post announcements, view system-wide reports, and run maintenance tasks.')

    # =============================================================
    # 2. AUTHENTICATION & SECURITY
    # =============================================================
    pdf.add_page()
    pdf.h1('2.  AUTHENTICATION & SECURITY MODULE')

    pdf.h2('Files Involved')
    pdf.bullet('auth/login.php  -- Login form handler')
    pdf.bullet('auth/register.php  -- New user registration')
    pdf.bullet('auth/logout.php  -- Session destruction')
    pdf.bullet('auth/forgot_password.php  -- Token-based password reset initiation')
    pdf.bullet('auth/reset_password.php  -- Password reset form')
    pdf.bullet('auth/profile.php  -- Profile editing, photo upload')
    pdf.bullet('includes/auth.php  -- Helper function library for session/role checks')
    pdf.bullet('config/db.php  -- CSRF helpers, input sanitisation, session hardening')

    pdf.h2('Function Reference -- includes/auth.php')
    funcs = [
        ('isLoggedIn()', 'Returns true if $_SESSION["user_id"] is set. Used as a guard on every protected page.'),
        ('isStudent()', 'Checks that the current session role equals "student". Used to protect student-only routes.'),
        ('isAdminRole()', 'Checks session role equals "admin". Guards all pages under admin/.'),
        ('requireLogin()', 'Redirects to login if not authenticated. Also enforces a 2-hour idle timeout to expire stale sessions.'),
        ('requireAdmin()', 'Calls requireLogin() then additionally checks the admin role. Double-layer protection.'),
        ('getCurrentUserId()', 'Returns $_SESSION["user_id"]. Central getter used across all modules to identify the current user.'),
        ('getCurrentUserRole()', 'Returns $_SESSION["role"]. Enables role-conditional UI rendering.'),
        ('regenerateSession()', 'Calls session_regenerate_id(true) to rotate the session ID and prevent session fixation attacks.'),
        ('secureLogin($user)', 'Post-authentication hook: regenerates session ID, clears CSRF token, and writes user keys into $_SESSION.'),
        ('secureLogout()', 'Clears session array, expires the session cookie, and destroys the session. Used by auth/logout.php.'),
        ('isAccountLocked($email, $conn)', 'Queries login_attempts table. Auto-resets expired lockouts. Returns true if account is currently locked.'),
        ('incrementLoginAttempts($email, $conn)', 'Atomically increments failed login counter. When MAX_LOGIN_ATTEMPTS (5) is reached, sets locked_until for 15 min.'),
        ('resetLoginAttempts($email, $conn)', 'Called after successful login. Clears attempt counter and lock timestamp.'),
    ]
    for i, (name, desc) in enumerate(funcs):
        pdf.func_row(name, desc, bg=(i % 2 == 0))

    pdf.h2('Function Reference -- config/db.php')
    cfg_funcs = [
        ('cleanInput($data)', 'Trims whitespace from input strings. Not a security sanitiser -- SQL injection is prevented exclusively by prepared statements.'),
        ('sanitize($data)', 'Alias of cleanInput(). Provided for semantic clarity in form handling code.'),
        ('e($str)', 'Shorthand for htmlspecialchars(). Used inline in every template to safely echo user data into HTML.'),
        ('generateCSRFToken()', 'Creates (or re-uses) a 32-byte hex token stored in $_SESSION["csrf_token"]. Called in every form via csrfTokenField().'),
        ('csrfTokenField()', 'Emits a hidden <input> element containing the current CSRF token. Used in every POST form.'),
        ('getCSRFField()', 'Alias of csrfTokenField(). Identical output -- two names for the same purpose.'),
        ('validateCSRFToken($token)', 'Compares submitted token against session token using hash_equals() (timing-safe). Returns bool.'),
        ('requireCSRF()', 'Calls validateCSRFToken() and immediately exits with HTTP 403 (JSON or redirect) if the check fails. Called at the top of every POST handler.'),
        ('redirect($location, $msg, $type)', 'Sets a flash session message (success/error/info) then performs header() redirect. Centralises navigation after actions.'),
    ]
    for i, (name, desc) in enumerate(cfg_funcs):
        pdf.func_row(name, desc, bg=(i % 2 == 0))

    pdf.info_box('Security Model',
        'Every POST endpoint calls requireCSRF() before processing. All DB queries use PDO prepared statements. '
        'Passwords are stored as bcrypt hashes (cost 12). Sessions use HttpOnly + SameSite=Lax cookies. '
        'Security response headers (X-Frame-Options, CSP, Referrer-Policy) are emitted by header.php on every page.',
        PRIMARY)

    # =============================================================
    # 3. CORE LIBRARY -- functions.php
    # =============================================================
    pdf.add_page()
    pdf.h1('3.  CORE LIBRARY  (includes/functions.php)')

    pdf.body(
        'functions.php is the single largest file (~1 740 lines). It houses every domain-logic function '
        'used across all student pages. Functions are organised into the following logical groups:'
    )

    # -- 3a User / Semester / Subject --------------------------------------
    pdf.h3('Group A -- Users, Semesters & Subjects', DARK)
    funcs_a = [
        ('getUserInfo($uid, $conn)', 'Fetches the full users row for a given ID. Used on profile, dashboard, buddy pages.'),
        ('getActiveSemester($uid, $conn)', 'Returns the one semester with is_active=1 for the user. Central to all task/subject lookups.'),
        ('getUserSemesters($uid, $conn)', 'All semesters for a user ordered newest first. Powers the Semesters management page.'),
        ('getSemesterSubjects($sem_id, $conn)', 'Returns all subjects for a semester. Used in task filters, subject lists, and calendar.'),
        ('isAdmin($uid, $conn)', 'DB-level check (reads users.role). Used as a secondary guard inside functions that behave differently for admins.'),
    ]
    for i, (n, d) in enumerate(funcs_a):
        pdf.func_row(n, d, bg=(i % 2 == 0))

    # -- 3b Tasks ----------------------------------------------------------
    pdf.h3('Group B -- Task Management', DARK)
    funcs_b = [
        ('getSubjectTasks($sub_id, $conn)', 'All tasks belonging to a specific subject. Used in the Subjects page task list.'),
        ('getUserTasks($uid, $conn, $lim, $off)', 'All tasks for a user with optional limit/offset for pagination.'),
        ('getUserTasksFiltered(...)', 'Full-featured filtered query: accepts subject_id, status, sort column, sort direction. Powers the Tasks page filter bar.'),
        ('getTaskStatusCounts($uid, $conn)', 'Returns array [pending, completed]. Used in dashboard stat cards and progress bars.'),
        ('getSubjectTaskStats($sub_id, $conn)', 'Returns [total, completed] for one subject. Drives the per-subject progress indicator.'),
        ('getPendingTasksCount($uid, $conn)', 'Quick count of tasks not yet completed. Used in topbar badge and dashboard.'),
        ('getCompletedTasksCount($uid, $conn)', 'Count of completed tasks. Used in analytics and achievement checks.'),
        ('getTotalTasksCount($uid, $conn)', 'All tasks regardless of status. Used for completion percentage calculation.'),
        ('getUpcomingTasks($uid, $conn, $days)', 'Returns tasks due within N days (default 7). Shown in the dashboard upcoming-tasks widget.'),
        ('getCompletionPercentage($uid, $conn)', 'Returns integer 0-100. Used in the circular progress widget on the dashboard.'),
        ('getDashboardStats($uid, $conn)', 'Aggregate array: pending, completed, overdue, upcoming, total_semesters, subjects, study_hours. One call powers all dashboard cards.'),
        ('getTasksAsJSON($uid, $conn)', 'Serialises tasks as JSON events for FullCalendar. Called by calendar.php AJAX init request.'),
        ('getSubtasks($parent_id, $conn)', 'Child tasks of a parent task. Rendered as an indent list under the parent in tasks.php.'),
        ('getSubtaskProgress($parent_id, $conn)', 'Returns [done, total] for a parent\'s subtasks. Powers the subtask progress bar.'),
        ('getOverdueTasksCount($uid, $conn)', 'Count of past-deadline, non-completed tasks. Used in notification checker and dashboard.'),
    ]
    for i, (n, d) in enumerate(funcs_b):
        pdf.func_row(n, d, bg=(i % 2 == 0))

    # -- 3c Formatting / Admin helpers --------------------------------------
    pdf.add_page()
    pdf.h3('Group C -- Formatting & Admin Helpers', DARK)
    funcs_c = [
        ('formatDate($date)', 'Converts MySQL date string to human-readable format (e.g. "Mar 15, 2026"). Used in task lists and notes.'),
        ('formatDateTime($datetime)', 'Formatted date+time string. Used in activity logs and notification timestamps.'),
        ('getPriorityColor($priority)', 'Maps Low/Medium/High to Bootstrap colour classes (success/warning/danger). Used in task badges.'),
        ('getStatusColor($status)', 'Maps Pending/Completed to colour classes. Used in task status badges.'),
        ('getTypeColor($type)', 'Maps task types (Assignment, Quiz, etc.) to Bootstrap colours for visual distinction.'),
        ('getAllUsers($conn)', 'Returns all user rows. Used in admin/manage_users.php table.'),
        ('getTotalSystemTasks($conn)', 'System-wide task count. Used in admin dashboard stat card.'),
        ('getTotalUsers($conn)', 'System-wide user count. Admin dashboard.'),
        ('paginate($total, $per, $page)', 'Builds a pagination metadata array: total_pages, has_prev, has_next, etc.'),
        ('renderPagination($pag, $url)', 'Renders Bootstrap 5 pagination HTML from the paginate() result. Used in tasks.php and notifications.php.'),
    ]
    for i, (n, d) in enumerate(funcs_c):
        pdf.func_row(n, d, bg=(i % 2 == 0))

    # -- 3d Files / Onboarding / Search -------------------------------------
    pdf.h3('Group D -- Files, Onboarding & Search', DARK)
    funcs_d = [
        ('handleFileUpload($file, $uid, $conn, $task_id, $note_id)', 'Validates MIME type and file size (<=10 MB). Moves file to uploads/attachments/ and inserts an attachments row.'),
        ('getAttachments($conn, $task_id, $note_id)', 'Retrieves attachment records for a task or note. Used in task detail panels and note view.'),
        ('formatFileSize($bytes)', 'Converts byte count to KB/MB/GB string. Displayed next to each attachment in the UI.'),
        ('handleProfilePhotoUpload($file, $uid, $conn)', 'Validates avatar image format, moves to uploads/avatars/, and updates users.profile_photo.'),
        ('needsOnboarding($uid, $conn)', 'Returns true if the user has not dismissed onboarding and has not yet created a semester or task. Gates the onboarding checklist overlay.'),
        ('getOnboardingProgress($uid, $conn)', 'Returns step-completion map, completed count, and percentage. Drives the checklist progress bar.'),
        ('dismissOnboarding($uid, $conn)', 'Sets users.onboarding_completed = 1. Called by student/dismiss_onboarding.php endpoint.'),
        ('globalSearch($uid, $conn, $query, $lim)', 'Searches tasks.title, notes.title, subjects.name for the query string. Returns typed result array for the search modal.'),
    ]
    for i, (n, d) in enumerate(funcs_d):
        pdf.func_row(n, d, bg=(i % 2 == 0))

    # -- 3e Task Templates --------------------------------------------------
    pdf.h3('Group E -- Task Templates', DARK)
    funcs_e = [
        ('getTaskTemplates($uid, $conn)', 'Returns user-created templates plus system templates (is_system=1). Shown in the "Use Template" dropdown on the task form.'),
        ('getTaskTemplate($tmpl_id, $uid, $conn)', 'Fetches a single template row. Used to pre-fill the add-task form.'),
        ('createTaskTemplate(...)', 'Inserts a new template with title, description, type, priority, and optional recurrence settings.'),
        ('updateTaskTemplate(...)', 'Updates an existing user template. System templates cannot be updated by regular users.'),
        ('deleteTaskTemplate($tmpl_id, $uid, $conn)', 'Removes a user-owned template. Blocked if template is_system=1.'),
        ('createTaskFromTemplate($tmpl_id, $uid, $sub_id, $deadline, $conn)', 'Copies template fields into a new tasks row, allowing the student to add a deadline and choose a subject.'),
    ]
    for i, (n, d) in enumerate(funcs_e):
        pdf.func_row(n, d, bg=(i % 2 == 0))

    # -------------------------------------------------------------
    # 4. STUDENT MODULES
    # -------------------------------------------------------------
    pdf.add_page()
    pdf.h1('4.  STUDENT MODULES')

    # Dashboard
    pdf.h2('4.1  Dashboard  (student/dashboard.php)')
    pdf.body(
        'The student dashboard is the first page seen after login. It aggregates data from multiple '
        'functions into a single at-a-glance view. It calls getDashboardStats(), getStudyStreak(), '
        'checkAndAwardAchievements(), getDashboardWidgets(), getUpcomingTasks(), getUnreadNotificationCount(), '
        'and getOnboardingProgress(). Widgets are drag-and-drop reorderable via SortableJS and saved '
        'server-side through save_widgets.php.'
    )
    pdf.h3('Key Widgets', ACCENT)
    widgets = [
        'Completion Ring -- circular chart showing overall task completion %',
        'Study Streak -- consecutive daily study days, with longest-streak record',
        'Stats Cards -- pending tasks, study hours, achievements unlocked, overdue count',
        'Weekly Activity Chart -- bar chart of study session durations per day (Chart.js)',
        'Upcoming Tasks -- next 7 days of deadlines',
        'Active Announcements -- unread admin announcements with priority badges',
        'Achievements -- gamification unlock badges',
        'Onboarding Checklist -- step-by-step new-user guide (auto-hides when dismissed)',
    ]
    for w in widgets:
        pdf.bullet(w, color=MEDIUM)

    # Semesters & Subjects
    pdf.h2('4.2  Semesters & Subjects  (student/semesters.php, subjects.php)')
    pdf.body(
        'Semesters provide the top-level academic structure. A student may have multiple semesters but only '
        'ONE can be active at a time -- activating a semester auto-deactivates all others. Subjects belong '
        'to a semester and group related tasks. Tasks can also exist without a subject (standalone). '
        'Deleting a semester cascades to its subjects and tasks.'
    )

    # Tasks
    pdf.h2('4.3  Task Management  (student/tasks.php)')
    pdf.body(
        'The most feature-rich student module. Tasks support five dimensions of organisation: '
        'priority (Low/Medium/High), type (Assignment/Quiz/Project/Exam/Report/Other), status, '
        'deadline, and subject association. Additional power features include:'
    )
    features_tasks = [
        'Subtasks -- child tasks nested under a parent with aggregate progress bar',
        'Recurring tasks -- daily, weekly, or monthly automatic recurrence with end date',
        'Attachments -- multiple file uploads per task (PDF, Office, images, archives)',
        'Templates -- save and reuse common task structures to save setup time',
        'Inline status toggle -- one-click Pending <-> Completed via AJAX, no page reload',
        'Filters & sort -- filter by subject, status, type, priority; sort by deadline or priority',
    ]
    for f in features_tasks:
        pdf.bullet(f, color=MEDIUM)

    # Calendar
    pdf.h2('4.4  Calendar  (student/calendar.php)')
    pdf.body(
        'Renders all task deadlines on a FullCalendar month/week/day view. Colour-coded by priority '
        '(green/amber/red). Drag a task to a new date to reschedule it -- the move is saved immediately '
        'via AJAX to the action=reschedule endpoint. Integrates getTasksAsJSON() to load events on init.'
    )

    # Daily Planning
    pdf.h2('4.5  Daily Planning  (student/daily_planning.php)')
    pdf.body(
        'A day-picker view that lets students set up to three top-priority tasks for each date, '
        'define time blocks (e.g. 09:00-11:00: "Study Calculus"), and log study session summaries. '
        'Helps convert a deadline-focused task list into an actionable daily schedule.'
    )

    # Notes
    pdf.h2('4.6  Notes  (student/notes.php)')
    pdf.body(
        'A Quill.js-powered rich-text note editor. Notes can be associated with a subject or kept '
        'standalone. Supports bold, italic, bullet lists, headings, and code blocks. Notes are '
        'searchable via globalSearch(). Attachments can be added to notes using the same '
        'handleFileUpload() pipeline as tasks. Actions: add, edit, delete.'
    )

    # Pomodoro
    pdf.h2('4.7  Pomodoro Timer  (student/pomodoro.php)')
    pdf.body(
        'A client-side circular SVG countdown timer implementing the Pomodoro Technique '
        '(default: 25 min focus / 5 min break). Completed focus sessions are saved to the '
        'study_sessions table via an AJAX call to the complete_task endpoint -- this feeds the '
        'Study Analytics module and the streak calculation. History of recent sessions is '
        'displayed below the timer via get_history AJAX.'
    )

    # Study Analytics
    pdf.h2('4.8  Study Analytics  (student/study_analytics.php)')
    pdf.body(
        'Aggregates study_sessions rows into weekly and monthly totals displayed as Chart.js '
        'bar/line charts. Shows: total hours this week, average daily duration, best study day, '
        'session count, and per-subject breakdown. Helps students identify productive patterns '
        'and time distribution across subjects.'
    )

    # -------------------------------------------------------------
    # 5. COLLABORATION
    # -------------------------------------------------------------
    pdf.add_page()
    pdf.h1('5.  COLLABORATION -- STUDY BUDDY & STUDY GROUPS')

    pdf.h2('5.1  Study Buddy System  (student/study_buddy.php, buddy_messenger.php)')
    pdf.body(
        'The Study Buddy feature pairs two students for peer accountability. One student sends a request '
        '(or generates an 8-character invite code); the other accepts. Once paired, both can view each '
        '\'s task completion progress (privacy-safe summary), send context-aware nudges, and chat '
        'in real time via long-polling AJAX.'
    )

    pdf.h3('Buddy Lifecycle Functions', PRIMARY)
    buddy_funcs = [
        ('getAcceptedBuddy($uid, $conn)', 'Returns the active buddy pair record. Used to show/hide buddy-specific UI elements.'),
        ('getPendingBuddyRequests($uid, $conn)', 'Incoming requests awaiting acceptance. Displayed in the buddy request inbox.'),
        ('getSentBuddyRequest($uid, $conn)', 'Outgoing pending request. Prevents sending duplicate requests.'),
        ('generateBuddyCode()', 'Creates a random 8-char alphanumeric invite code for out-of-band pairing.'),
        ('getBuddyProgress($buddy_id, $conn)', 'Returns task completion % and weekly study hours for the buddy. Shown on the buddy dashboard panel.'),
    ]
    for i, (n, d) in enumerate(buddy_funcs):
        pdf.func_row(n, d, bg=(i % 2 == 0))

    pdf.h3('Chat Transport Functions', PRIMARY)
    chat_funcs = [
        ('getChatMessages($uid, $bid, $conn, $lim, $before_id)', 'Paged chat history. before_id enables infinite-scroll-up pagination.'),
        ('getNewChatMessages($uid, $bid, $conn, $after_id)', 'Polls for messages newer than after_id. The core of the real-time chat polling loop.'),
        ('sendChatMessage($sender, $recv, $msg, $conn, $type, $reply_to)', 'Inserts a message. Supports message types: text, nudge, emoji, system. reply_to enables threaded replies.'),
        ('markChatMessagesRead($uid, $sender_id, $conn)', 'Sets is_read=1 for all unread messages from one sender. Clears the unread badge.'),
        ('getUnreadBuddyMessageCount($uid, $conn)', 'Total unread across all buddy conversations. Shown in the topbar notification badge.'),
        ('deleteChatMessage($msg_id, $uid, $conn)', 'Removes own message. Performs a hard delete from buddy_messages.'),
        ('checkChatRateLimit($uid, $conn, $max)', 'Counts messages sent in the last 60 seconds. Returns true if under the limit (default 15/min).'),
    ]
    for i, (n, d) in enumerate(chat_funcs):
        pdf.func_row(n, d, bg=(i % 2 == 0))

    pdf.h3('Safety Controls', DANGER)
    safety = [
        ('isBuddyBlocked($uid, $other_id, $conn)', 'Checks buddy_blocks in both directions. Prevents message delivery if true.'),
        ('blockBuddy($blocker, $blocked, $conn)', 'Inserts block record and unlinks any active buddy pair between the two users.'),
        ('unblockBuddy($blocker, $blocked, $conn)', 'Removes the block. Does not restore the previous buddy pair.'),
        ('reportBuddy($reporter, $reported, $reason, $details, $conn)', 'Inserts a moderation report into buddy_reports for admin review.'),
        ('updateUserActivity($uid, $conn)', 'Updates users.last_active. Called on every page load and message send.'),
        ('isUserOnline($uid, $conn)', 'Returns true if last_active was within 5 minutes. Powers the green online dot.'),
        ('updateTypingStatus / clearTypingStatus / isBuddyTyping', 'Three-function typing-indicator protocol using a TTL column in buddy_typing_status.'),
    ]
    for i, (n, d) in enumerate(safety):
        pdf.func_row(n, d, bg=(i % 2 == 0))

    # Study Groups
    pdf.h2('5.2  Study Groups v6.0  (student/study_groups.php, group_messenger.php)')
    pdf.body(
        'Study Groups expand on buddy pairing to support teams of up to 5 students. Each group has one '
        'leader who controls membership, tasks, and settings. Groups can operate in open-join or '
        'approval-required mode. A dedicated group chat with reply threading is included.'
    )

    pdf.h3('Group Lifecycle Functions', PRIMARY)
    group_funcs = [
        ('createStudyGroup($uid, $name, $desc, $conn)', 'Creates the study_groups row and inserts the creator as leader into group_members.'),
        ('getUserStudyGroups($uid, $conn)', 'Returns all groups the user belongs to with member count and unread message count.'),
        ('getGroupInfo($grp_id, $uid, $conn)', 'Fetches group details -- only returns a result if the user is a member (access control built in).'),
        ('getGroupMembers($grp_id, $conn)', 'Member list with names, roles, and avatar paths. Shown in the group "People" tab.'),
        ('getGroupMemberProgress($grp_id, $conn)', 'Task completion % for each member. Drives the group leaderboard widget.'),
        ('joinGroupByCode($uid, $code, $conn)', 'Parses invite code. If group is open, joins immediately. If approval mode, creates a join request.'),
        ('leaveGroup($uid, $grp_id, $conn)', 'Removes member. If the leaving user was the only leader, the group is dissolved.'),
        ('removeGroupMember($leader, $target, $grp_id, $conn)', 'Leader-only action. Removes a specific member.'),
        ('updateGroupSettings(...)', 'Updates group name, description, join mode, and permission flags.'),
        ('getPendingJoinRequests($grp_id, $conn)', 'Returns pending approval-mode requests for the leader to review.'),
        ('approveJoinRequest / rejectJoinRequest', 'Leader approval actions. Approve inserts the user into group_members; reject sets status=rejected.'),
    ]
    for i, (n, d) in enumerate(group_funcs):
        pdf.func_row(n, d, bg=(i % 2 == 0))

    pdf.h3('Group Tasks & Chat', PRIMARY)
    grp_task_funcs = [
        ('assignGroupTask(...)', 'Creates a group_tasks record assigned by a leader (or member if allow_member_assign=1) to a specific member.'),
        ('getGroupTasks($grp_id, $conn, $filter)', 'Returns all group tasks, optionally filtered to one assignee. Shown in the group Tasks tab.'),
        ('toggleGroupTaskStatus($task_id, $uid, $grp_id, $conn)', 'Assignee marks their own group task Pending <-> Completed.'),
        ('sendGroupMessage($grp_id, $sender, $msg, $conn, $type, $reply_to)', 'Inserts group_messages row. Rate-limited. Supports threaded replies.'),
        ('getGroupMessages / getNewGroupMessages', 'Full history (paged) and polling-only-new variants. Mirror of the buddy chat transport pattern.'),
        ('markGroupMessagesRead($grp_id, $uid, $last_id, $conn)', 'Updates the last_read_id pointer in group_message_reads. Used to compute unread counts.'),
        ('getUnreadGroupMessageCount($uid, $conn)', 'Sums unread messages across all user\'s groups. Adds to topbar badge alongside buddy unread count.'),
        ('checkGroupChatRateLimit($uid, $grp_id, $conn, $max)', 'Per-user, per-group rate limit: max 15 messages/minute.'),
    ]
    for i, (n, d) in enumerate(grp_task_funcs):
        pdf.func_row(n, d, bg=(i % 2 == 0))

    # -------------------------------------------------------------
    # 6. NOTIFICATIONS & GAMIFICATION
    # -------------------------------------------------------------
    pdf.add_page()
    pdf.h1('6.  NOTIFICATIONS & GAMIFICATION ENGINE')

    pdf.h2('6.1  Notification System')
    pdf.body(
        'The notification system has two layers: an automatic background checker '
        '(notification_checker.php) that generates alerts, and a user-facing API '
        '(notification_api.php) plus page (notifications.php) for reading and managing them.'
    )

    pdf.h3('Notification Creation & Retrieval', PRIMARY)
    notif_funcs = [
        ('createNotification($uid, $type, $title, $msg, $conn, $ref_id, $ref_type)', 'Inserts a notifications row. Called by the notification checker and manually by other modules.'),
        ('getUnreadNotificationCount($uid, $conn)', 'Count of unread+undismissed notifications. Shown as red badge in the topbar bell icon.'),
        ('getRecentNotifications($uid, $conn, $lim)', 'Most recent notifications for the topbar dropdown (last 10). Polled every 60 seconds via AJAX.'),
        ('getAllNotifications($uid, $conn, $page, $per, $filter)', 'Full paginated list for the Notifications page. Supports filter: all, unread, deadline, streak.'),
        ('markNotificationRead($notif_id, $uid, $conn)', 'Sets is_read=1 for one notification. Called when user clicks it.'),
        ('markAllNotificationsRead($uid, $conn)', 'Bulk mark-all-read. Triggered by the "Mark All Read" button.'),
        ('dismissNotification($notif_id, $uid, $conn)', 'Sets is_dismissed=1. Hides alert permanently from the list.'),
        ('getNotificationPreferences($uid, $conn)', 'Returns per-type on/off settings. Checked by the notification checker before generating each alert.'),
        ('updateNotificationPreferences($uid, $prefs, $conn)', 'Upserts preference row. Called by the Preferences modal in notifications.php.'),
        ('notificationTimeAgo($datetime)', 'Converts a datetime to a human relative string ("2 minutes ago", "Yesterday").'),
        ('getNotificationIcon($type)', 'Returns [icon_class, color] tuple. Used by every notification renderer to display the correct icon.'),
    ]
    for i, (n, d) in enumerate(notif_funcs):
        pdf.func_row(n, d, bg=(i % 2 == 0))

    pdf.h3('Automatic Notification Worker  (includes/notification_checker.php)', PRIMARY)
    pdf.body(
        'runNotificationChecker($uid, $conn) is called silently on every student page load '
        'but is throttled to execute at most once per minute per session. It performs four checks:'
    )
    checks = [
        'OVERDUE -- Tasks past their deadline that are still Pending -> generates an overdue alert',
        '1-HOUR -- Tasks with deadline within the next 60 minutes -> generates a 1h warning',
        '24-HOUR -- Tasks due within the next 24 hours -> generates a 24h reminder',
        'STREAK RISK -- If the user has a study streak but has not studied today -> sends a streak-risk nudge',
    ]
    for c in checks:
        pdf.bullet(c, color=WARNING)
    pdf.body('Old notifications (>30 days) are auto-deleted during the same run to keep the table lean.')

    pdf.h2('6.2  Gamification Engine  (includes/gamification.php)')
    pdf.h3('Study Streak -- getStudyStreak($uid, $conn)', ACCENT)
    pdf.body(
        'Queries study_sessions and computes: (1) current_streak -- consecutive days with at least one '
        'session, (2) longest_streak ever recorded, (3) studied_today boolean, '
        '(4) last_7_days -- an array mapping each day to true/false for the activity heatmap on the dashboard.'
    )

    pdf.h3('Achievement Engine -- checkAndAwardAchievements($uid, $conn)', ACCENT)
    achv = [
        ('first_task', 'Complete your first task'),
        ('task_master', 'Complete 10 tasks'),
        ('century', 'Complete 100 tasks'),
        ('note_taker', 'Create your first note'),
        ('study_buddy', 'Pair with a study buddy'),
        ('group_joiner', 'Join a study group'),
        ('streak_3', 'Maintain a 3-day study streak'),
        ('streak_7', 'Reach a 7-day streak'),
        ('streak_30', 'Reach a 30-day streak'),
        ('pomodoro_first', 'Complete your first Pomodoro session'),
        ('pomodoro_10', 'Complete 10 Pomodoro sessions'),
        ('night_owl', 'Study after midnight'),
        ('early_bird', 'Study before 6 am'),
        ('semester_start', 'Create your first semester'),
        ('subject_collector', 'Add 5 or more subjects'),
    ]
    for i, (key, desc) in enumerate(achv):
        pdf.func_row(key, desc, bg=(i % 2 == 0))

    pdf.h3('Dashboard Widgets -- getDashboardWidgets($uid, $conn)', ACCENT)
    pdf.body(
        'Returns an ordered array of 8 widget definitions merged with the user\'s saved preferences '
        'from dashboard_widgets. Each widget has: key, label, icon, position, and is_visible. '
        'Used by dashboard.php to render only the widgets the student has enabled, in their chosen order.'
    )

    # -------------------------------------------------------------
    # 7. ADMIN PANEL
    # -------------------------------------------------------------
    pdf.add_page()
    pdf.h1('7.  ADMIN PANEL')

    pdf.body(
        'All admin pages are gated by requireAdmin() which verifies both authentication and the admin '
        'role. The admin panel provides full visibility and control over the platform without accessing '
        'individual student content beyond necessary moderation.'
    )

    admin_pages = [
        ('admin/admin_dashboard.php', 'Overview',
         'Stat cards: total users, tasks, semesters, subjects, study hours. '
         'Recent sign-ups table. Completion rate across the platform. No individual data exposed.'),
        ('admin/manage_users.php', 'User Management',
         'Searchable/filterable user table. Actions: change role (student<->admin) with last-admin safeguard, '
         'delete user (with avatar cleanup). Clicking a user links to user_details.php.'),
        ('admin/announcements.php', 'Announcements',
         'CRUD for platform-wide announcements. Priority levels: Low/Normal/Important/Urgent. '
         'Optional expiry date. Active announcements shown on every student dashboard. '
         'Students can dismiss an announcement -- tracked via announcement_reads.'),
        ('admin/system_reports.php', 'System Reports',
         'Aggregate visualisations: task distribution by priority and type (Chart.js doughnut), '
         'monthly new user registrations, weekly study session totals, top 10 most-active students.'),
        ('admin/system_settings.php', 'Settings & Maintenance',
         'Three maintenance actions: reset a user\'s password, bulk-delete completed tasks older than 30 days, '
         'and clean up expired PHP sessions from the sessions directory.'),
        ('admin/activity_log.php', 'Activity Log',
         'Recent system events: tasks created, study sessions logged. Filterable by type. '
         'Each row shows user, action, timestamp, and IP address.'),
        ('admin/user_details.php', 'User Details',
         'Drill-down page: user profile info, task breakdown by subject, list of study sessions, '
         'semester/subject tree. Linked from manage_users row actions.'),
    ]
    for path, title, desc in admin_pages:
        pdf.h3(title + '  (' + path + ')', DARK)
        pdf.body(desc)

    # -------------------------------------------------------------
    # 8. DATABASE SCHEMA
    # -------------------------------------------------------------
    pdf.add_page()
    pdf.h1('8.  DATABASE SCHEMA & TABLE RELATIONSHIPS')

    pdf.h2('Table Groups by Domain')

    groups = [
        ('Identity & Auth', ['users', 'password_resets'], PRIMARY),
        ('Academic Structure', ['semesters', 'subjects', 'tasks', 'study_sessions', 'notes', 'attachments', 'task_templates'], ACCENT),
        ('Collaboration', ['study_buddies', 'buddy_messages', 'buddy_nudges', 'buddy_typing_status', 'buddy_blocks', 'buddy_reports'], PURPLE),
        ('Study Groups v6.0', ['study_groups', 'group_members', 'group_tasks', 'group_messages', 'group_message_reads', 'group_join_requests'], TEAL),
        ('Admin & Announcements', ['announcements', 'announcement_reads', 'activity_log'], WARNING),
        ('Gamification & Notifications', ['notifications', 'notification_preferences', 'dashboard_widgets', 'user_achievements'], DANGER),
    ]
    for group_name, tables, color in groups:
        pdf.set_fill_color(*color)
        pdf.set_text_color(*BG_WHITE)
        pdf.set_font('Helvetica', 'B', 9)
        pdf.set_x(10)
        pdf.cell(60, 6, group_name, fill=True)
        pdf.set_font('Helvetica', '', 8.5)
        pdf.set_fill_color(245, 245, 250)
        pdf.set_text_color(*MEDIUM)
        pdf.cell(130, 6, ', '.join(tables), fill=True, new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        pdf.set_text_color(*DARK)
        pdf.ln(1)

    pdf.h2('Critical Foreign Key Relationships')
    rels = [
        ('tasks.user_id', '->', 'users.id', 'Every task belongs to one student'),
        ('tasks.subject_id (nullable)', '->', 'subjects.id', 'Task optionally attached to a subject'),
        ('tasks.parent_id (nullable)', '->', 'tasks.id', 'Self-reference for subtask hierarchy'),
        ('semesters.user_id', '->', 'users.id', 'Each semester owned by one student'),
        ('subjects.semester_id', '->', 'semesters.id', 'Subjects scoped to a semester'),
        ('study_sessions.user_id', '->', 'users.id', 'Study sessions linked to student'),
        ('notes.user_id', '->', 'users.id', 'Notes owned by student'),
        ('attachments.task_id OR note_id', '->', 'tasks/notes', 'Mutual-exclusivity via CHECK constraint'),
        ('study_buddies.requester_id/partner_id', '->', 'users.id (×2)', 'A buddy pair links two students'),
        ('buddy_messages.sender_id/receiver_id', '->', 'users.id (×2)', 'Both ends of each message are users'),
        ('study_groups.leader_id', '->', 'users.id', 'Group leader must be a registered user'),
        ('group_members.group_id, user_id', '->', 'study_groups, users', 'Many-to-many membership junction'),
        ('group_tasks.assigned_by/to', '->', 'users.id (×2)', 'Task sides reference real users in the group'),
        ('notifications.user_id', '->', 'users.id', 'Each notification targeted at one user'),
        ('user_achievements.user_id', '->', 'users.id', 'Achievements awarded per user'),
        ('dashboard_widgets.user_id', '->', 'users.id', 'Widget preferences per user'),
    ]
    for src, arr, tgt, desc in rels:
        pdf.conn_row(src, arr, tgt, '  ' + desc)
        pdf.ln(0.5)

    pdf.h2('Important Unique Constraints')
    uniques = [
        'users.email -- prevents duplicate accounts',
        'study_buddies UNIQUE(LEAST(a,b), GREATEST(a,b)) -- prevents duplicate pairs regardless of who requested',
        'buddy_blocks UNIQUE(blocker_id, blocked_id) -- prevents duplicate block records',
        'group_members UNIQUE(group_id, user_id) -- a user can only be a member once per group',
        'group_join_requests UNIQUE(group_id, user_id) -- one active request per user per group',
        'group_message_reads PK(group_id, user_id) -- one read-pointer per user per group',
        'dashboard_widgets UNIQUE(user_id, widget_key) -- one preference row per widget per user',
        'user_achievements UNIQUE(user_id, achievement_key) -- achievments are awarded exactly once',
        'announcement_reads UNIQUE(announcement_id, user_id) -- dismissal tracked once per user',
        'notification_preferences PK(user_id) -- one preference row per user',
    ]
    for u in uniques:
        pdf.bullet(u, color=MEDIUM)

    # -------------------------------------------------------------
    # 9. MODULE INTERCONNECTIONS
    # -------------------------------------------------------------
    pdf.add_page()
    pdf.h1('9.  MODULE INTERCONNECTIONS & DATA FLOW')

    pdf.h2('Login -> Dashboard Flow')
    flow_login = [
        'auth/login.php  -- validates credentials, calls secureLogin(), redirects to dashboard',
        'student/dashboard.php  -- calls requireLogin() -> getDashboardStats() -> getStudyStreak() -> checkAndAwardAchievements() -> getDashboardWidgets()',
        'includes/notification_checker.php  -- included by header.php, runs runNotificationChecker() (throttled)',
        'includes/header.php  -- renders topbar, calls getUnreadNotificationCount(), getUnreadBuddyMessageCount(), getUnreadGroupMessageCount()',
        'getOnboardingProgress()  -- gates the onboarding checklist overlay on first use',
    ]
    for i, f in enumerate(flow_login):
        pdf.set_x(13)
        pdf.set_fill_color(*PRIMARY)
        pdf.set_text_color(*BG_WHITE)
        pdf.set_font('Helvetica', 'B', 8)
        pdf.cell(6, 5, str(i+1), fill=True, align='C')
        pdf.set_text_color(*MEDIUM)
        pdf.set_font('Helvetica', '', 8.5)
        pdf.multi_cell(0, 5, '  ' + f)
        pdf.set_text_color(*DARK)
        pdf.ln(0.5)

    pdf.h2('Task Completion Chain')
    pdf.body(
        'When a student toggles a task to Completed (AJAX in tasks.php or pomodoro.php):'
    )
    chain = [
        'tasks.status is updated to "Completed"',
        'checkAndAwardAchievements() is re-evaluated -> may unlock a new achievement',
        'If via Pomodoro, a study_sessions row is also inserted -> feeds getStudyStreak() and analytics',
        'getDashboardStats() and getCompletionPercentage() reflect the new state on next dashboard load',
        'getOverdueTasksCount() decrements if the task was past its deadline',
        'Notification checker will no longer generate alerts for this task',
    ]
    for i, step in enumerate(chain):
        pdf.set_x(13)
        pdf.set_fill_color(*ACCENT)
        pdf.set_text_color(*BG_WHITE)
        pdf.set_font('Helvetica', 'B', 8)
        pdf.cell(6, 5, str(i+1), fill=True, align='C')
        pdf.set_text_color(*MEDIUM)
        pdf.set_font('Helvetica', '', 8.5)
        pdf.multi_cell(0, 5, '  ' + step)
        pdf.set_text_color(*DARK)
        pdf.ln(0.5)

    pdf.h2('Notification Generation Chain')
    pdf.body(
        'Each page load (student side) -> header.php includes notification_checker.php -> '
        'runNotificationChecker() checks session throttle -> queries tasks for overdue/1h/24h '
        'deadlines -> respects getNotificationPreferences() -> calls createNotification() -> '
        'getUnreadNotificationCount() increments -> topbar bell badge updates on next poll.'
    )

    pdf.h2('Study Streak Chain')
    pdf.body(
        'User completes a Pomodoro session -> AJAX saves study_sessions row -> next dashboard load '
        'calls getStudyStreak() -> queries session dates -> computes consecutive-day run -> '
        'if streak broken, notification_checker fires a streak_risk alert the next day -> '
        'streak-based achievements (streak_3, streak_7, streak_30) checked by checkAndAwardAchievements().'
    )

    pdf.h2('Study Buddy Chat Real-Time Loop')
    pdf.body(
        'Client JS polls getNewChatMessages($uid, $bid, $conn, $after_id) every ~2 seconds -> '
        'only returns rows newer than the last known message ID (efficient) -> '
        'isBuddyBlocked() is checked on every send -> checkChatRateLimit() enforces 15 msg/min -> '
        'markChatMessagesRead() is called when the chat window is in focus -> '
        'getUnreadBuddyMessageCount() feeds the topbar badge poll.'
    )

    pdf.h2('File Upload Flow  (attachments.php)')
    pdf.body(
        'POST to attachments.php with action=upload and either task_id or note_id -> '
        'requireCSRF() -> requireLogin() -> handleFileUpload() validates MIME + size -> '
        'file moved to uploads/attachments/ -> attachments row inserted -> '
        'getAttachments() retrieves the list -> formatFileSize() formats display.'
    )

    pdf.h2('Global Search Flow')
    pdf.body(
        'Ctrl+K or search icon -> GlobalSearch JS module opens modal -> debounced AJAX GET '
        'to global_search.php -> globalSearch($uid, $conn, $query, 20) -> queries tasks.title, '
        'notes.title, subjects.name with LIKE -> returns typed JSON array -> rendered in modal as '
        'clickable results that navigate to the appropriate page.'
    )

    # -------------------------------------------------------------
    # 10. IMPORTANCE & BENEFITS
    # -------------------------------------------------------------
    pdf.add_page()
    pdf.h1('10.  IMPORTANCE & BENEFITS OF EACH FEATURE')

    features = [
        {
            'title': 'Semester & Subject Structure',
            'color': PRIMARY,
            'importance': 'Provides the organisational backbone. Without this hierarchy, tasks would be an unstructured list. '
                'The active-semester model ensures students always work in the context of their current academic period.',
            'benefits': [
                'Mirrors real academic calendars -- intuitive for students',
                'Cascading deletes keep data consistent when a semester ends',
                'Subject-level progress bars motivate balanced study across courses',
            ]
        },
        {
            'title': 'Task Management + Subtasks + Recurrence',
            'color': ACCENT,
            'importance': 'The core value proposition of the platform. Students rarely have to re-enter repetitive tasks '
                '(weekly labs, daily reading) thanks to recurrence. Subtasks break overwhelming projects into manageable steps.',
            'benefits': [
                'Reduces cognitive load -- student sees one list, not scattered notes',
                'Priority and type tags enable quick visual triage',
                'Recurrence automates repetitive scheduling, saving time',
                'Subtask progress bar gives a real sense of advancement on large projects',
            ]
        },
        {
            'title': 'FullCalendar Deadline View',
            'color': TEAL,
            'importance': 'Transforms abstract deadlines into a spatial, temporal view. Students can spot deadline clusters '
                '(exam weeks) immediately and spread workload before crunch time.',
            'benefits': [
                'Visual deadline density reveals workload spikes at a glance',
                'Drag-to-reschedule is faster than editing a form',
                'Month/week/day views serve different planning horizons',
            ]
        },
        {
            'title': 'Pomodoro Timer + Study Sessions',
            'color': PURPLE,
            'importance': 'Translates intention into measurable action. Every completed Pomodoro session is persisted '
                'and feeds the streak engine, analytics, and achievements -- creating a positive feedback loop.',
            'benefits': [
                'Pomodoro technique proven to improve focus and reduce procrastination',
                'Session data drives analytics, enabling evidence-based study improvements',
                'Completing a session can trigger achievement unlocks, providing instant gratification',
                'Focus ambiance sounds reduce distraction without leaving the app',
            ]
        },
        {
            'title': 'Rich-Text Notes + Attachments',
            'color': WARNING,
            'importance': 'Keeps all study materials in one place. Notes linked to subjects mean students do not lose '
                'context when revisiting material. Attachments allow lecture slides and reference PDFs to live alongside tasks.',
            'benefits': [
                'Quill.js offers Word-like formatting without a separate app',
                'Subject-linked notes make revision faster',
                'Entire study workflow (tasks + notes + files) stays in one system',
                'Global search covers notes as well as tasks, reducing time spent hunting for information',
            ]
        },
        {
            'title': 'Study Buddy Collaboration',
            'color': ACCENT,
            'importance': 'Social accountability is one of the strongest motivators for consistent study. '
                'The buddy system formalises this into a structured, safe pairing with privacy controls.',
            'benefits': [
                'Peer accountability increases follow-through on study goals',
                'Nudge feature is a lightweight motivational tool without the overhead of a full chat',
                'Block/report system ensures student safety and appropriate use',
                'Progress sharing (task %, study hours) creates healthy comparison without exposing private data',
            ]
        },
        {
            'title': 'Study Groups v6.0',
            'color': TEAL,
            'importance': 'Extends collaboration beyond pairs to full project teams. Group tasks with assignees enable '
                'realistic project management workflows within the platform, matching how group assignments actually work.',
            'benefits': [
                'Group task assignment with completion tracking replaces informal WhatsApp coordination',
                'Approval-required join mode protects group integrity',
                'Reply-threaded group chat reduces message-context loss in busy groups',
                'Leader role provides clear accountability without a separate team management tool',
            ]
        },
        {
            'title': 'Gamification -- Streaks & Achievements',
            'color': DANGER,
            'importance': 'Extrinsic motivation scaffolding that helps students build habits. The streak mechanic '
                'turns daily study into a mini-game, and achievements celebrate milestones that might otherwise go unnoticed.',
            'benefits': [
                'Streak risk notifications prevent accidental streak breaks',
                'Achievements cover diverse behaviours (social, time-of-day, volume), rewarding different student types',
                'Dashboard heatmap provides a satisfying visual record of study consistency',
                'Streak leaderboard potential (future) leverages social motivation',
            ]
        },
        {
            'title': 'Smart Notification System',
            'color': PRIMARY,
            'importance': 'Passive alerting means students do not have to actively check for approaching deadlines. '
                'The preference system prevents notification fatigue by letting students choose which alert types matter.',
            'benefits': [
                'Throttled checker (once/min) prevents DB overload while keeping alerts timely',
                'Five distinct types (overdue, 1h, 24h, study reminder, streak risk) cover the full time horizon',
                'Auto-cleanup after 30 days keeps the notification table performant',
                'Mark-all-read and dismiss keep the inbox manageable',
            ]
        },
        {
            'title': 'Admin Panel',
            'color': MEDIUM,
            'importance': 'Essential for platform governance. Without admin tools, problematic accounts cannot be managed, '
                'important announcements cannot be broadcast, and technical maintenance cannot be performed without direct DB access.',
            'benefits': [
                'User management with last-admin safeguard prevents accidental lock-out',
                'Priority-level announcements ensure critical notices (exam format changes) stand out',
                'System reports surface platform health and most-engaged students',
                'Maintenance actions (cleanup, session purge) can be triggered without server access',
            ]
        },
        {
            'title': 'Security Architecture',
            'color': DANGER,
            'importance': 'A student platform contains sensitive academic data. The layered security model '
                '(CSRF, bcrypt, session hardening, brute-force lockout, XSS escaping, CSP) provides defence in depth.',
            'benefits': [
                'CSRF tokens on every form protect against cross-site request forgery',
                'Brute-force lockout (5 attempts -> 15 min) blocks password spraying attacks',
                'Prepared statements throughout eliminate SQL injection vectors',
                'HttpOnly session cookies prevent XSS-based session hijacking',
                'Content Security Policy restricts script execution to trusted CDNs only',
            ]
        },
    ]

    for feat in features:
        if pdf.get_y() > 220:
            pdf.add_page()
        pdf.ln(3)
        pdf.set_fill_color(*feat['color'])
        pdf.rect(10, pdf.get_y(), 190, 7, 'F')
        pdf.set_font('Helvetica', 'B', 10)
        pdf.set_text_color(*BG_WHITE)
        pdf.set_x(13)
        pdf.cell(0, 7, feat['title'], new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        pdf.set_text_color(*DARK)

        pdf.set_font('Helvetica', 'B', 8.5)
        pdf.set_text_color(*MEDIUM)
        pdf.set_x(13)
        pdf.cell(0, 5, 'Why it matters:', new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        pdf.body(feat['importance'], indent=13)

        pdf.set_font('Helvetica', 'B', 8.5)
        pdf.set_text_color(*MEDIUM)
        pdf.set_x(13)
        pdf.cell(0, 5, 'Key benefits:', new_x=XPos.LMARGIN, new_y=YPos.NEXT)
        for b in feat['benefits']:
            pdf.bullet(b, indent=15, color=feat['color'])
        pdf.set_text_color(*DARK)

    # -------------------------------------------------------------
    # FINAL PAGE -- QUICK REFERENCE
    # -------------------------------------------------------------
    pdf.add_page()
    pdf.h1('QUICK REFERENCE -- KEY FILES & ENTRY POINTS')

    quick = [
        ('Landing Page',        'index.php',                              'Redirects logged-in users; shows marketing landing for guests'),
        ('One-time Setup',      'setup.php',                              'Creates all DB tables & seeds demo accounts (localhost only)'),
        ('DB Config',           'config/db.php',                          'DB connection, CSRF helpers, constants, utility functions'),
        ('Auth Library',        'includes/auth.php',                      'Login state, role checks, session management functions'),
        ('Core Functions',      'includes/functions.php',                 'ALL domain logic: tasks, notes, buddy, groups, notifications'),
        ('Gamification',        'includes/gamification.php',              'Streak, achievement engine, widget config'),
        ('Login',               'auth/login.php',                         'Credential validation, brute-force lockout, session setup'),
        ('Register',            'auth/register.php',                      'Email uniqueness, bcrypt, validation'),
        ('Profile',             'auth/profile.php',                       'Name, email, password change, photo upload'),
        ('Student Dashboard',   'student/dashboard.php',                  'Aggregate view: stats, streak, widgets, announcements'),
        ('Tasks',               'student/tasks.php',                      'Full task CRUD, subtasks, recurrence, templates, attachments'),
        ('Calendar',            'student/calendar.php',                   'FullCalendar deadline view + drag-reschedule'),
        ('Notes',               'student/notes.php',                      'Quill rich-text notes with attachment support'),
        ('Pomodoro',            'student/pomodoro.php',                   'Focus timer + session persistence + history'),
        ('Analytics',           'student/study_analytics.php',            'Weekly/monthly Chart.js study charts'),
        ('Study Buddy',         'student/study_buddy.php',                'Pairing, chat, nudges, block/report'),
        ('Study Groups',        'student/study_groups.php',               'Group lifecycle, tasks, group chat (v6.0)'),
        ('Notifications Page',  'student/notifications.php',              'Paginated notification list with preference modal'),
        ('Notification API',    'student/notification_api.php',           'JSON API: mark read, dismiss, count, preferences'),
        ('Attachment API',      'student/attachments.php',                'File upload/list/delete JSON API'),
        ('Global Search API',   'student/global_search.php',              'Cross-entity search endpoint'),
        ('Admin Dashboard',     'admin/admin_dashboard.php',              'Platform-wide stats for admins'),
        ('User Management',     'admin/manage_users.php',                 'Search, role change, delete users'),
        ('Announcements',       'admin/announcements.php',                'Create/edit/delete announcements with priority'),
        ('Reports',             'admin/system_reports.php',               'Usage charts and top-user tables'),
        ('Settings',            'admin/system_settings.php',              'Maintenance: cleanup tasks, sessions, reset passwords'),
    ]
    for label, path, desc in quick:
        y = pdf.get_y()
        if y > 265:
            pdf.add_page()
        pdf.set_fill_color(*BG_LIGHT)
        pdf.rect(10, pdf.get_y(), 190, 6.5, 'F')
        pdf.set_x(11)
        pdf.set_font('Helvetica', 'B', 7.5)
        pdf.set_text_color(*DARK)
        pdf.cell(36, 6.5, label)
        pdf.set_font('Courier', '', 7.5)
        pdf.set_text_color(*PRIMARY)
        pdf.cell(52, 6.5, path)
        pdf.set_font('Helvetica', '', 7.5)
        pdf.set_text_color(*MEDIUM)
        pdf.multi_cell(0, 6.5, desc)
        pdf.set_text_color(*DARK)
        pdf.ln(0.5)

    pdf.ln(8)
    pdf.set_fill_color(*PRIMARY)
    pdf.rect(10, pdf.get_y(), 190, 14, 'F')
    pdf.set_font('Helvetica', 'B', 9)
    pdf.set_text_color(*BG_WHITE)
    pdf.set_x(12)
    pdf.cell(0, 7, 'Studify v6.0  --  Academic Planning & Collaboration Platform', new_x=XPos.LMARGIN, new_y=YPos.NEXT)
    pdf.set_x(12)
    pdf.set_font('Helvetica', '', 8)
    pdf.set_text_color(186, 230, 253)
    pdf.cell(0, 7, 'PHP 8+  /  MySQL  /  Bootstrap 5  /  localhost/Studify   |   Generated March 2026')

    return pdf


# ===============================================================================
if __name__ == '__main__':
    out = 'c:/laragon/www/Studify/Studify_System_Reference.pdf'
    pdf = build_pdf()
    pdf.output(out)
    print(f'PDF saved -> {out}')
    print(f'Pages: {pdf.page}')
