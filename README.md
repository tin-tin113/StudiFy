# Studify - Student Task Management System

A comprehensive web-based academic task management system designed for students to organize, track, and manage their academic workload.

## 📋 System Overview

Studify is a semester-based task management application that helps students:
- Organize subjects and tasks by semester
- Track academic deadlines with calendar view
- Monitor task completion progress
- Use Pomodoro timer for productive study sessions
- Analyze academic performance with analytics

## 🛠️ Technology Stack

- **Backend:** Plain PHP (no framework)
- **Database:** MySQL
- **Frontend:** HTML5 + CSS3
- **UI Framework:** Bootstrap 5
- **JavaScript:** Vanilla JS (no dependencies)
- **Server:** Laragon (Local Development)

## 📁 Project Structure

```
/Studify/
│
├── config/
│   └── db.php                  # Database configuration & CSRF helpers
│
├── includes/
│   ├── header.php              # Navigation header with sidebar
│   ├── footer.php              # Footer with JS includes
│   ├── sidebar.php             # Sidebar (legacy, integrated into header)
│   ├── auth.php                # Authentication & session helpers
│   └── functions.php           # Utility functions (CRUD, search, buddy)
│
├── auth/
│   ├── login.php               # User login with brute-force protection
│   ├── register.php            # User registration
│   ├── logout.php              # Secure logout
│   ├── forgot_password.php     # Token-based password reset request
│   ├── reset_password.php      # Password reset via token
│   └── profile.php             # User profile with photo upload
│
├── student/
│   ├── dashboard.php           # Student dashboard with analytics
│   ├── semesters.php           # Semester management
│   ├── subjects.php            # Subject management
│   ├── tasks.php               # Task management (AJAX toggle/delete)
│   ├── calendar.php            # FullCalendar integration
│   ├── notes.php               # Notes per subject
│   ├── pomodoro.php            # Pomodoro timer with ambiance
│   ├── study_analytics.php     # Study analytics & insights
│   ├── study_buddy.php         # Study buddy accountability system
│   ├── global_search.php       # AJAX global search endpoint
│   ├── dismiss_announcement.php # AJAX dismiss announcement
│   └── dismiss_onboarding.php  # AJAX dismiss onboarding
│
├── admin/
│   ├── admin_dashboard.php     # Admin dashboard & system overview
│   ├── manage_users.php        # User management (CRUD, role change)
│   ├── announcements.php       # Announcement management
│   ├── system_reports.php      # System reports with charts
│   ├── activity_log.php        # Recent system activity viewer
│   ├── system_settings.php     # Settings, password reset, data cleanup
│   └── user_details.php        # Detailed user profile view
│
├── assets/
│   ├── css/
│   │   └── style.css           # Custom styling
│   └── js/
│       └── main.js             # Custom JavaScript & AJAX handlers
│
├── uploads/
│   └── avatars/                # User profile photos
│
├── database.sql                # Database schema (v2.0)
├── setup.php                   # Automated database setup script
├── manifest.json               # PWA manifest
└── index.php                   # Landing page
```

## 🚀 Installation & Setup

### 1. **Download Database Schema**

The database schema is provided in `database.sql` file in the root directory.

### 2. **Create Database**

1. Open phpMyAdmin (usually available at `localhost/phpmyadmin` in Laragon)
2. Create a new database named `studify`
3. Import the `database.sql` file to create all tables

**Or use MySQL command line:**
```bash
mysql -u root -p
CREATE DATABASE studify;
USE studify;
SOURCE /path/to/database.sql;
```

### 3. **Configure Database Connection**

Edit `config/db.php` and ensure the credentials match your setup:
```php
$db_host = 'localhost';
$db_user = 'root';
$db_password = '';  // Set your password if needed
$db_name = 'studify';
```

### 4. **Start Laragon**

1. Open Laragon application
2. Click "Start All" to start Apache and MySQL servers
3. Navigate to `http://localhost/Studify/` in your browser

## 🔐 Default Credentials

**Admin Account:**
- Email: `admin@studify.com`
- Password: `password123`

**Student Account:**
- Email: `student@studify.com`
- Password: `password123`

## 📚 Core Features

### 1. **Authentication System**
- User registration and login
- Password hashing with bcrypt
- Session-based authentication
- Role-based access control (Student/Admin)
- User profile management

### 2. **Semester Management**
- Create and manage semesters
- Activate/deactivate semesters
- View semester overview
- Delete semesters with cascading deletes

### 3. **Subject Management**
- Add subjects under semesters
- Edit subject information
- Add instructor names
- Track tasks per subject
- Delete subjects

### 4. **Task Management**
- Create tasks with detailed information
- Task fields: Title, Description, Deadline, Priority, Type, Status
- Priority levels: Low, Medium, High
- Task types: Assignment, Quiz, Project, Exam, Report, Other
- Status tracking: Pending, In Progress, Completed
- Subtask support via parent-child relationships
- Recurring tasks (Daily, Weekly, Monthly)
- Edit and delete tasks
- Filter and sort tasks
- AJAX-powered status toggle and deletion

### 5. **Dashboard**
- Overview of student progress
- Total tasks, pending, and completed count
- Completion percentage with progress bar
- Upcoming deadlines (next 7 days)
- Quick access to key features
- Academic analytics

### 6. **Calendar View**
- Interactive monthly calendar
- Task visualization by color (priority-based)
- Click events to view details
- Multiple calendar views (month, week, day, list)

### 7. **Pomodoro Timer**
- 25-minute focus sessions
- 5-minute break periods
- Customizable timer durations
- Session counter
- Study session tracking
- Today's and 30-day statistics

### 8. **Admin Panel**
- System overview dashboard with statistics
- User management (view, search, delete, role change)
- Announcement management (create, edit, delete with priority & expiry)
- System reports with interactive charts (Chart.js)
- Activity log (recent tasks, study sessions, registrations)
- System settings (password reset, data cleanup tools)
- View detailed user progress and study stats

### 9. **Notes**
- Create and manage notes per subject or general
- Search and filter notes
- Markdown content support

### 10. **Study Analytics**
- Study streaks and productivity insights
- Daily, weekly, and monthly study time charts
- Day-of-week activity radar chart
- Average session length tracking

### 11. **Study Buddy**
- Pair with classmates for mutual accountability
- Send/accept/decline buddy requests
- Send motivational nudges to your buddy
- View buddy's progress (privacy-safe stats only)
- Rate-limited messaging (10 nudges/day)

### 12. **Global Search**
- Search across tasks, notes, and subjects
- Real-time AJAX-powered results

## 🔒 Security Features

✅ **Prepared Statements** - Protection against SQL injection
✅ **Password Hashing** - Bcrypt hashing with `password_hash()`
✅ **Session Management** - Session-based authentication
✅ **Input Validation** - All inputs validated and sanitized
✅ **Output Escaping** - `htmlspecialchars()` for XSS protection
✅ **Role-Based Access** - Different access levels for students and admins
✅ **Cascading Deletes** - Foreign key constraints for data integrity

## 🎨 UI/UX Features

- **Responsive Design** - Works on desktop, tablet, and mobile
- **Bootstrap 5** - Modern and clean interface
- **Color-Coded System** - Priority and status indicators
- **User-Friendly** - Intuitive navigation and layout
- **Modal Forms** - Non-intrusive task creation/editing
- **Real-time Updates** - Live progress tracking
- **Sidebar Navigation** - Easy access to all features

## 📊 Database Schema

### Users Table
- id, name, email, password, role, course, year_level, profile_photo, onboarding_completed, login_attempts, locked_until, created_at, updated_at

### Password Resets Table
- id, email, token, expires_at, used, created_at

### Semesters Table
- id, user_id (FK → users), name, is_active, created_at, updated_at

### Subjects Table
- id, semester_id (FK → semesters), name, instructor_name, created_at, updated_at

### Tasks Table
- id, subject_id (FK → subjects), parent_id (FK → tasks, self-referencing), title, description, deadline, priority, type, status, is_recurring, recurrence_type, recurrence_end, position, created_at, updated_at

### Study Sessions Table
- id, user_id (FK → users), task_id (FK → tasks), subject_id (FK → subjects), duration, session_type, created_at

### Notes Table
- id, subject_id (FK → subjects), user_id (FK → users), title, content, content_type, created_at, updated_at

### Attachments Table
- id, user_id (FK → users), task_id (FK → tasks), note_id (FK → notes), file_name, file_path, file_size, file_type, created_at

### Announcements Table
- id, admin_id (FK → users), title, content, priority, expires_at, created_at, updated_at

### Announcement Reads Table
- id, announcement_id (FK → announcements), user_id (FK → users), read_at

### Study Buddies Table
- id, requester_id (FK → users), partner_id (FK → users), status, invite_code, created_at, updated_at

### Buddy Nudges Table
- id, sender_id (FK → users), receiver_id (FK → users), message, is_read, created_at

## 🔄 User Workflows

### Student Workflow:
1. Register/Login to Studify
2. Create a semester (e.g., "1st Semester 2024")
3. Activate the semester
4. Add subjects under the semester
5. Add tasks under subjects
6. Track progress on dashboard
7. View tasks in calendar
8. Use Pomodoro timer for study sessions
9. Update task status as you progress

### Admin Workflow:
1. Login with admin credentials
2. View system statistics on dashboard
3. Monitor user registrations
4. Manage users (view details, change roles, delete)
5. Create and manage announcements
6. Review system reports and activity log
7. Perform maintenance via system settings

## 🛠️ Troubleshooting

### Database Connection Error
- Ensure MySQL is running in Laragon
- Check database credentials in `config/db.php`
- Verify database name is `studify`

### Page Not Found
- Ensure Laragon has http://localhost/Studify/ configured
- Check if Apache is running

### Session Not Working
- Clear browser cookies
- Ensure PHP session save path has write permissions

## 📝 Notes for Development

- All database operations use prepared statements
- Functions are in `includes/functions.php` for reusability
- Each page checks authentication with `requireLogin()`
- Admin pages check role with `requireAdmin()`
- Custom CSS in `assets/css/style.css`
- Custom JS in `assets/js/main.js`

## 🎓 Educational Use

This system is designed as a learning project for 3rd year BS Information Systems students. It demonstrates:
- Database design and relationships
- CRUD operations with MySQL
- Session-based authentication
- Object-oriented and functional PHP
- Bootstrap responsive design
- Vanilla JavaScript interactions
- RESTful API concepts (AJAX)
- Security best practices

## 📄 License

This project is created for educational purposes.

## ✉️ Support

For issues or questions about the system, ensure:
1. All files are properly placed in `/www/Studify/` directory
2. MySQL database is imported correctly
3. PHP version supports the features (PHP 8.0+)
4. Laragon services are running

---

**Studify Version 2.0**
Developed as a comprehensive academic task management system for students.
