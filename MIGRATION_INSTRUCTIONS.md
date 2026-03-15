# Studify Migration Instructions

Last updated: March 15, 2026

This guide covers database upgrades for existing Studify installations and clarifies what each migration script does.

---

## 1. Fresh Install vs Existing Install

### Fresh install

If this is a new setup, import `database.sql` directly. It already includes schema coverage through v6.0.

### Existing install

If your database already has older Studify tables/data, follow the upgrade sequence below.

---

## 2. Recommended Upgrade Sequence (Existing Database)

1. Back up your database.
2. Run core compatibility fixes.
3. Run v5 migration.
4. Run task template migration.
5. Run study group migration.
6. Verify all required tables and columns.

---

## 3. Step-by-Step Commands

## 3.1 Backup first

Example (MySQL CLI):

```bash
mysqldump -u root -p studify > studify_backup_before_upgrade.sql
```

## 3.2 Run core compatibility fixes

This script handles important compatibility and integrity fixes.

```bash
php migrate_core_fixes.php
```

What it fixes:
- ensures `task_templates` exists
- ensures `tasks.user_id` exists and is backfilled if missing
- adds buddy unordered uniqueness index if needed
- applies attachment XOR check constraint when possible

## 3.3 Run v5 migration

```bash
php migrate_v5.php
```

Creates:
- `dashboard_widgets`
- `user_achievements`

## 3.4 Run task template migration

Choose one option:

Option A (script):

```bash
php run_migration.php
```

Option B (manual SQL):

```bash
mysql -u root -p studify < migrations/add_task_templates.sql
```

Important:
- `run_migration.php` currently runs the task template migration only.
- It does not execute the Study Groups migration.

## 3.5 Run Study Groups migration (v6.0)

```bash
mysql -u root -p studify < migrations/add_study_groups.sql
```

Creates:
- `study_groups`
- `group_members`
- `group_tasks`
- `group_messages`
- `group_message_reads`
- `group_join_requests`

---

## 4. Verification Checklist

Run these queries after migration:

```sql
SHOW TABLES LIKE 'task_templates';
SHOW TABLES LIKE 'dashboard_widgets';
SHOW TABLES LIKE 'user_achievements';
SHOW TABLES LIKE 'study_groups';
SHOW TABLES LIKE 'group_members';
SHOW TABLES LIKE 'group_tasks';
SHOW TABLES LIKE 'group_messages';
SHOW TABLES LIKE 'group_message_reads';
SHOW TABLES LIKE 'group_join_requests';
```

Optional schema checks:

```sql
SHOW COLUMNS FROM study_groups LIKE 'allow_member_invite';
SHOW COLUMNS FROM study_groups LIKE 'join_mode';
SHOW COLUMNS FROM tasks LIKE 'user_id';
```

---

## 5. Functional Smoke Validation

After schema migration, validate in the app:

1. Open Tasks page and confirm templates are available.
2. Open Dashboard and confirm widget preference saving works.
3. Open Study Groups page:
   - create a group
   - join by invite code
   - assign a group task
   - send a group message
4. Open Notifications page and confirm filters and preferences save.

---

## 6. Troubleshooting

### Error: table already exists

Safe in most migrations because scripts use `IF NOT EXISTS`.

### Error: foreign key fails

Ensure `users` table exists and referenced user IDs are valid.

### Error: duplicate buddy pair index

Run `php migrate_core_fixes.php` to clean duplicate/reverse buddy pairs and apply proper unique index.

### Error: access denied

Check credentials in `config/db.php` or your environment variables.

### PHP command not found on Windows

Use Laragon PHP executable explicitly, for example:

```bash
C:\laragon\bin\php\php-8.x.x\php.exe migrate_core_fixes.php
```

---

## 7. Rollback Strategy

If any step fails and data integrity is uncertain:

1. stop migration operations
2. restore backup
3. re-run steps one by one and inspect output after each step

---

For architecture and function-level details, see `SYSTEM_DOCUMENTATION.md`.
