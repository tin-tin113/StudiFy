# Studify - Student Task and Collaboration System

Last updated: March 15, 2026

Studify is a server-rendered PHP and MySQL platform for academic planning, task management, productivity tracking, and peer collaboration.

## System Overview

Studify helps students:
- Manage semesters, subjects, and tasks in one place
- Plan daily work with calendar and daily planning views
- Track study sessions with a Pomodoro timer and analytics
- Use task templates and file attachments
- Stay accountable with Study Buddy and Study Groups
- Chat in one-on-one and group messenger interfaces
- Receive deadline and streak notifications
- Personalize the dashboard with saved widget layout

## Latest Release Coverage

This repository now includes features through v6.0:
- v4.0: Notification center and preferences
- v5.0: Dashboard widgets and achievements
- v6.0: Study Groups, group tasks, group chat, and join approvals

## Technology Stack

- Backend: Plain PHP 8+
- Database: MySQL or MariaDB
- Frontend: Bootstrap 5, Vanilla JavaScript
- Charts: Chart.js
- Calendar: FullCalendar
- Rich Text Notes: Quill.js
- Local development: Laragon

## Project Structure

```text
Studify/
  config/
    db.php

  includes/
    auth.php
    functions.php
    gamification.php
    notification_checker.php
    header.php
    footer.php
    sidebar.php

  auth/
    login.php
    register.php
    logout.php
    forgot_password.php
    reset_password.php
    profile.php

  student/
    dashboard.php
    semesters.php
    subjects.php
    tasks.php
    calendar.php
    daily_planning.php
    notes.php
    pomodoro.php
    study_analytics.php
    study_buddy.php
    buddy_messenger.php
    study_groups.php
    group_messenger.php
    attachments.php
    notifications.php
    notification_api.php
    global_search.php
    save_widgets.php
    dismiss_announcement.php
    dismiss_onboarding.php

  admin/
    admin_dashboard.php
    manage_users.php
    announcements.php
    system_reports.php
    system_settings.php
    activity_log.php
    user_details.php

  migrations/
    add_task_templates.sql
    add_study_groups.sql

  assets/
    css/style.css
    js/main.js

  uploads/
    attachments/
    avatars/
    photos/

  database.sql
  setup.php
  run_migration.php
  migrate_v5.php
  migrate_core_fixes.php
  README.md
  MIGRATION_INSTRUCTIONS.md
  SYSTEM_DOCUMENTATION.md
```

## Student Features

### Planning and tracking
- Semester and subject management
- Task CRUD with filters, sorting, recurrence, and subtasks
- Task templates
- Calendar deadline visualization and drag-to-reschedule
- Daily Planning page with top priorities and time blocks

### Productivity
- Pomodoro focus sessions with configurable settings
- Study session history and completion prompts
- Study analytics (weekly, monthly, and trend views)
- Streak and achievement tracking

### Collaboration
- Study Buddy pairing with privacy-safe progress comparison
- Buddy messaging, typing indicators, nudges, and safety tools
- Study Groups with:
  - Invite codes
  - Open join or approval mode
  - Member and role management
  - Group task assignment and completion tracking
  - Group chat with unread tracking and reply threading

### Files and notes
- Rich text notes
- Task and note attachments with upload/list/delete API support

### Notifications
- In-app notification center
- Filters, mark-read, dismiss, mark-all-read
- Per-user notification preference toggles

## Admin Features

- Dashboard and system summary
- User management and role control
- Announcement management with priority and expiry
- System reports and activity logs
- Password reset and cleanup tools

## Installation (Fresh Database)

1. Create a MySQL database named `studify`.
2. Import `database.sql`.
3. Update credentials in `config/db.php` or environment variables.
4. Start Apache and MySQL.
5. Open `http://localhost/Studify/`.

## Upgrading an Existing Database

Use `MIGRATION_INSTRUCTIONS.md` for exact upgrade flow. Summary:
1. Backup database.
2. Run `php migrate_core_fixes.php`.
3. Run `php migrate_v5.php`.
4. Run task template migration (`run_migration.php` or SQL file).
5. Run `migrations/add_study_groups.sql`.

## Default Accounts

If seeded by setup/import:
- Admin: admin@studify.com / password123
- Student: student@studify.com / password123

## Documentation Files

- `README.md`: quick project overview
- `SYSTEM_DOCUMENTATION.md`: complete architecture and function catalog
- `MIGRATION_INSTRUCTIONS.md`: migration and upgrade procedures

## Maintenance Utilities

- `setup.php`: initializes schema and seed data
- `run_php_lint.py`: lint helper
- `lint_check.bat`: local lint runner
- `smoke_check.php`: smoke checks
