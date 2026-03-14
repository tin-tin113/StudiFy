# Database Migration Instructions

## Task Templates Migration

To enable the Task Templates feature, you need to run the database migration.

### Option 1: Using phpMyAdmin (Recommended)

1. Open phpMyAdmin (usually at `http://localhost/phpmyadmin`)
2. Select the `studify` database
3. Click on the "SQL" tab
4. Copy and paste the contents of `migrations/add_task_templates.sql`
5. Click "Go" to execute

### Option 2: Using MySQL Command Line

```bash
mysql -u root -p studify < migrations/add_task_templates.sql
```

### Option 3: Using the PHP Migration Script

1. Navigate to your Studify directory in terminal/command prompt
2. Run: `php run_migration.php`
   - Note: Make sure PHP is in your PATH or use full path to PHP executable
   - For Laragon: `C:\laragon\bin\php\php-8.x.x\php.exe run_migration.php`

### What the Migration Does

1. Creates `task_templates` table with the following structure:
   - `id` - Primary key
   - `user_id` - Foreign key to users table
   - `name` - Template name
   - `title` - Task title (with placeholders)
   - `description` - Task description
   - `type` - Task type (Assignment, Quiz, etc.)
   - `priority` - Task priority (Low, Medium, High)
   - `is_recurring` - Whether task is recurring
   - `recurrence_type` - Recurrence pattern
   - `is_system` - System templates (available to all users)

2. Inserts 5 default system templates:
   - Weekly Lab Report
   - Quiz Preparation
   - Assignment Submission
   - Project Milestone
   - Exam Review

### Verification

After running the migration, you can verify it worked by:

1. Checking if the table exists:
   ```sql
   SHOW TABLES LIKE 'task_templates';
   ```

2. Checking if templates were inserted:
   ```sql
   SELECT * FROM task_templates WHERE is_system = 1;
   ```

3. In the application:
   - Go to Tasks page
   - Click "Add Task"
   - You should see a "Use Template" dropdown with 5 system templates

### Troubleshooting

**Error: Table already exists**
- The table might already exist. You can safely ignore this or drop it first:
  ```sql
  DROP TABLE IF EXISTS task_templates;
  ```
  Then run the migration again.

**Error: Foreign key constraint fails**
- Make sure the `users` table exists and has at least one user with `id = 1`
- If not, modify the INSERT statements to use an existing user ID

**Error: Access denied**
- Make sure you're using the correct database credentials
- Check `config/db.php` for the correct database name and user

---

**Migration File:** `migrations/add_task_templates.sql`  
**Created:** <?php echo date('Y-m-d'); ?>
