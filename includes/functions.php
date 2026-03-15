<?php
// Utility Functions for Studify
// Modular PHP functions for reusable logic

// Function to get user information
function getUserInfo($user_id, $conn) {
    $query = "SELECT id, name, email, role, course, year_level, profile_photo, onboarding_completed, login_attempts, locked_until, created_at, updated_at FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to get active semester for user
function getActiveSemester($user_id, $conn) {
    $query = "SELECT * FROM semesters WHERE user_id = ? AND is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Function to get all semesters for user
function getUserSemesters($user_id, $conn) {
    $query = "SELECT * FROM semesters WHERE user_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get all subjects for a semester
function getSemesterSubjects($semester_id, $conn) {
    $query = "SELECT * FROM subjects WHERE semester_id = ? ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $semester_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get all tasks for a subject
function getSubjectTasks($subject_id, $conn) {
    $query = "SELECT * FROM tasks WHERE subject_id = ? AND parent_id IS NULL ORDER BY deadline ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get all tasks for user (across all subjects)
function getUserTasks($user_id, $conn, $limit = 0, $offset = 0) {
    $query = "SELECT t.*, COALESCE(s.name, 'General') as subject_name, COALESCE(se.name, '') as semester_name 
              FROM tasks t
              LEFT JOIN subjects s ON t.subject_id = s.id
              LEFT JOIN semesters se ON s.semester_id = se.id
              WHERE t.user_id = ? AND t.parent_id IS NULL
              ORDER BY t.deadline ASC";
    if ($limit > 0) {
        $query .= " LIMIT ? OFFSET ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iii", $user_id, $limit, $offset);
    } else {
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $user_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Get filtered tasks with SQL-level filtering (avoids double-fetch)
function getUserTasksFiltered($user_id, $conn, $subject_id = 0, $status = '', $sort = 'deadline', $sort_dir = 'ASC') {
    $allowed_sorts = ['deadline', 'priority', 'created_at', 'title'];
    $allowed_dirs = ['ASC', 'DESC'];
    if (!in_array($sort, $allowed_sorts)) $sort = 'deadline';
    if (!in_array(strtoupper($sort_dir), $allowed_dirs)) $sort_dir = 'ASC';
    
    // For priority sorting, use FIELD for proper order
    // For deadline sorting, put NULL deadlines last
    if ($sort === 'priority') {
        $order_clause = "FIELD(t.priority, 'High', 'Medium', 'Low') $sort_dir";
    } elseif ($sort === 'deadline') {
        $order_clause = "t.deadline IS NULL, t.deadline $sort_dir";
    } else {
        $order_clause = "t.$sort $sort_dir";
    }
    
    $query = "SELECT t.*, COALESCE(s.name, 'General') as subject_name, COALESCE(se.name, '') as semester_name 
              FROM tasks t
              LEFT JOIN subjects s ON t.subject_id = s.id
              LEFT JOIN semesters se ON s.semester_id = se.id
              WHERE t.user_id = ? AND t.parent_id IS NULL";
    $params = [$user_id];
    $types = "i";
    
    if ($subject_id > 0) {
        $query .= " AND t.subject_id = ?";
        $params[] = $subject_id;
        $types .= "i";
    }
    if (!empty($status) && in_array($status, ['Pending', 'Completed'])) {
        if ($status === 'Pending') {
            // Treat legacy "In Progress" rows as pending to keep behavior consistent
            $query .= " AND t.status != 'Completed'";
        } else {
            $query .= " AND t.status = ?";
            $params[] = $status;
            $types .= "s";
        }
    }
    $query .= " ORDER BY $order_clause";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get task status counts in a single query (avoids multiple COUNT queries)
function getTaskStatusCounts($user_id, $conn) {
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status != 'Completed' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
        FROM tasks WHERE user_id = ? AND parent_id IS NULL");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get subject task stats (completion count for subject cards)
function getSubjectTaskStats($subject_id, $conn) {
    $stmt = $conn->prepare("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
        FROM tasks WHERE subject_id = ? AND parent_id IS NULL");
    $stmt->bind_param("i", $subject_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Function to get pending tasks count
function getPendingTasksCount($user_id, $conn) {
    $query = "SELECT COUNT(*) as count FROM tasks t
              WHERE t.user_id = ? AND t.status != 'Completed' AND t.parent_id IS NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Function to get completed tasks count
function getCompletedTasksCount($user_id, $conn) {
    $query = "SELECT COUNT(*) as count FROM tasks t
              WHERE t.user_id = ? AND t.status = 'Completed' AND t.parent_id IS NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Function to get total tasks count
function getTotalTasksCount($user_id, $conn) {
    $query = "SELECT COUNT(*) as count FROM tasks t
              WHERE t.user_id = ? AND t.parent_id IS NULL";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Function to get upcoming tasks (next N days)
function getUpcomingTasks($user_id, $conn, $days = 7) {
    $query = "SELECT t.*, COALESCE(s.name, 'General') as subject_name
              FROM tasks t
              LEFT JOIN subjects s ON t.subject_id = s.id
              WHERE t.user_id = ? 
              AND t.status != 'Completed'
              AND t.parent_id IS NULL
              AND DATE(t.deadline) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
              ORDER BY t.deadline ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $days);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to calculate task completion percentage
function getCompletionPercentage($user_id, $conn) {
    $counts = getTaskStatusCounts($user_id, $conn);
    $total = intval($counts['total'] ?? 0);
    if ($total == 0) return 0;
    $completed = intval($counts['completed'] ?? 0);
    return round(($completed / $total) * 100);
}

// ─── Optimized Dashboard Stats (single query) ───
function getDashboardStats($user_id, $conn) {
    $query = "SELECT 
                COUNT(CASE WHEN t.parent_id IS NULL THEN 1 END) as total_tasks,
                                COUNT(CASE WHEN t.status != 'Completed' AND t.parent_id IS NULL THEN 1 END) as pending_tasks,
                                COUNT(CASE WHEN t.status = 'Completed' AND t.parent_id IS NULL THEN 1 END) as completed_tasks
              FROM tasks t
              WHERE t.user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stats = $stmt->get_result()->fetch_assoc();
    
    $stats['completed'] = $stats['completed_tasks'];
    $stats['pending'] = $stats['pending_tasks'];
    $stats['in_progress_tasks'] = 0;
    $stats['completion_pct'] = $stats['total_tasks'] > 0 
        ? round(($stats['completed_tasks'] / $stats['total_tasks']) * 100) 
        : 0;
    
    // Weekly study minutes
    $week_start = date('Y-m-d', strtotime('monday this week'));
    $stmt = $conn->prepare("SELECT COALESCE(SUM(ss.duration), 0) as study_minutes
        FROM study_sessions ss WHERE ss.user_id = ? AND ss.created_at >= ?");
    $stmt->bind_param("is", $user_id, $week_start);
    $stmt->execute();
    $weekly = $stmt->get_result()->fetch_assoc();
    $stats['study_minutes'] = $weekly['study_minutes'];
    
    return $stats;
}

// Function to get all tasks in JSON format for calendar
function getTasksAsJSON($user_id, $conn) {
    $tasks = getUserTasks($user_id, $conn);
    $calendarEvents = [];
    
    foreach ($tasks as $task) {
        $isCompleted = $task['status'] === 'Completed';
        
        // Completed tasks get muted gray; active tasks get priority colors
        if ($isCompleted) {
            $color = '#9ca3af'; // gray for completed
        } else {
            switch ($task['priority']) {
                case 'High':   $color = '#dc3545'; break;
                case 'Medium': $color = '#ffc107'; break;
                case 'Low':    $color = '#28a745'; break;
                default:       $color = '#007bff'; break;
            }
        }
        
        $calendarEvents[] = [
            'id' => $task['id'],
            'title' => ($isCompleted ? '✓ ' : '') . $task['title'],
            'start' => $task['deadline'],
            'backgroundColor' => $color,
            'borderColor' => $color,
            'textColor' => ($task['priority'] === 'Medium' && !$isCompleted) ? '#000' : '#fff',
            'classNames' => $isCompleted ? ['fc-event-completed'] : [],
            'extendedProps' => [
                'description' => $task['description'],
                'priority' => $task['priority'],
                'type' => $task['type'],
                'status' => $task['status'],
                'subject' => $task['subject_name']
            ]
        ];
    }
    
    return json_encode($calendarEvents);
}

// Function to check if user is admin
function isAdmin($user_id, $conn) {
    $user = getUserInfo($user_id, $conn);
    return $user && $user['role'] === 'admin';
}

// Function to format date for display
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Function to format datetime for display
function formatDateTime($datetime) {
    return date('M d, Y H:i', strtotime($datetime));
}

// Function to get priority badge color
function getPriorityColor($priority) {
    switch ($priority) {
        case 'High':
            return 'danger';
        case 'Medium':
            return 'warning';
        case 'Low':
            return 'success';
        default:
            return 'info';
    }
}

// Function to get status badge color
function getStatusColor($status) {
    switch ($status) {
        case 'Completed':
            return 'success';
        case 'Pending':
            return 'warning';
        default:
            return 'secondary';
    }
}

// Function to get type badge color
function getTypeColor($type) {
    switch ($type) {
        case 'Assignment':
            return 'primary';
        case 'Quiz':
            return 'info';
        case 'Project':
            return 'secondary';
        case 'Exam':
            return 'danger';
        default:
            return 'light';
    }
}

// Function to get all users (for admin) — excludes password hash
function getAllUsers($conn) {
    $query = "SELECT id, name, email, role, course, year_level, profile_photo, onboarding_completed, login_attempts, locked_until, created_at, updated_at FROM users ORDER BY created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

// Function to get total system tasks count (for admin)
function getTotalSystemTasks($conn) {
    $query = "SELECT COUNT(*) as count FROM tasks";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Function to get total registered users (for admin)
function getTotalUsers($conn) {
    $query = "SELECT COUNT(*) as count FROM users";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['count'];
}

// ─── Subtasks ───
function getSubtasks($parent_id, $conn) {
    $query = "SELECT * FROM tasks WHERE parent_id = ? ORDER BY position ASC, created_at ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getSubtaskProgress($parent_id, $conn) {
    $query = "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
              FROM tasks WHERE parent_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $parent_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// ─── Pagination Helper ───
function paginate($total, $per_page, $current_page) {
    $total_pages = max(1, ceil($total / $per_page));
    $current_page = max(1, min($current_page, $total_pages));
    $offset = ($current_page - 1) * $per_page;
    
    return [
        'total' => $total,
        'per_page' => $per_page,
        'current_page' => $current_page,
        'total_pages' => $total_pages,
        'offset' => $offset,
        'has_prev' => $current_page > 1,
        'has_next' => $current_page < $total_pages
    ];
}

function renderPagination($pagination, $base_url) {
    if ($pagination['total_pages'] <= 1) return '';
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm justify-content-center mt-3">';
    
    // Previous
    $html .= '<li class="page-item ' . (!$pagination['has_prev'] ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . $base_url . '&page=' . ($pagination['current_page'] - 1) . '"><i class="fas fa-chevron-left"></i></a></li>';
    
    // Pages
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $start + 4);
    $start = max(1, $end - 4);
    
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item ' . ($i == $pagination['current_page'] ? 'active' : '') . '">';
        $html .= '<a class="page-link" href="' . $base_url . '&page=' . $i . '">' . $i . '</a></li>';
    }
    
    // Next
    $html .= '<li class="page-item ' . (!$pagination['has_next'] ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . $base_url . '&page=' . ($pagination['current_page'] + 1) . '"><i class="fas fa-chevron-right"></i></a></li>';
    
    $html .= '</ul></nav>';
    return $html;
}

// ─── File Upload Helper ───
function handleFileUpload($file, $user_id, $conn, $task_id = null, $note_id = null) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File too large (max 10MB)'];
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_FILE_TYPES)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Create upload directory
    $user_dir = UPLOAD_DIR . $user_id . '/';
    if (!is_dir($user_dir)) {
        mkdir($user_dir, 0755, true);
    }
    
    // Generate unique filename
    $filename = bin2hex(random_bytes(8)) . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $filepath = $user_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Use server-detected MIME type instead of client-supplied $_FILES type
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $real_type = $finfo->file($filepath);
        $stmt = $conn->prepare("INSERT INTO attachments (user_id, task_id, note_id, file_name, file_path, file_size, file_type) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $relative_path = 'uploads/' . $user_id . '/' . $filename;
        $stmt->bind_param("iiissis", $user_id, $task_id, $note_id, $file['name'], $relative_path, $file['size'], $real_type);
        $stmt->execute();
        
        return ['success' => true, 'id' => $conn->insert_id, 'path' => $relative_path, 'name' => $file['name']];
    }
    
    return ['success' => false, 'message' => 'Failed to save file'];
}

// Get attachments for a task or note
function getAttachments($conn, $task_id = null, $note_id = null) {
    if ($task_id) {
        $stmt = $conn->prepare("SELECT * FROM attachments WHERE task_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $task_id);
    } elseif ($note_id) {
        $stmt = $conn->prepare("SELECT * FROM attachments WHERE note_id = ? ORDER BY created_at DESC");
        $stmt->bind_param("i", $note_id);
    } else {
        return [];
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Format file size
function formatFileSize($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

// ─── Profile Photo Upload ───
function handleProfilePhotoUpload($file, $user_id, $conn) {
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed)) {
        return ['success' => false, 'message' => 'Only JPG, PNG, GIF, WEBP allowed'];
    }
    
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Photo must be under 5MB'];
    }
    
    $photo_dir = UPLOAD_DIR . 'avatars/';
    if (!is_dir($photo_dir)) mkdir($photo_dir, 0755, true);
    
    $filename = 'avatar_' . $user_id . '_' . time() . '.' . $ext;
    $filepath = $photo_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        $relative = 'uploads/avatars/' . $filename;
        $stmt = $conn->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
        $stmt->bind_param("si", $relative, $user_id);
        $stmt->execute();
        return ['success' => true, 'path' => $relative];
    }
    
    return ['success' => false, 'message' => 'Upload failed'];
}

// Function to check if user needs onboarding
function needsOnboarding($user_id, $conn) {
    $stmt = $conn->prepare("SELECT onboarding_completed FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    
    if ($result && $result['onboarding_completed']) {
        return false;
    }
    
    // Single query to check if user has semesters → subjects → tasks
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM tasks t
        JOIN subjects s ON t.subject_id = s.id
        JOIN semesters sem ON s.semester_id = sem.id
        WHERE sem.user_id = ? AND t.parent_id IS NULL LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return ($row['cnt'] ?? 0) == 0;
}

// Enhanced onboarding checklist - Get completion status for all 8 steps
function getOnboardingProgress($user_id, $conn) {
    $progress = [
        'semester' => false,
        'subjects' => false,
        'task' => false,
        'task_completed' => false,
        'pomodoro' => false,
        'buddy' => false,
        'dashboard' => false,
        'calendar' => false
    ];
    
    // Steps 1-4: single query to get semester, subject, task, and completed task counts
    $stmt = $conn->prepare("SELECT
        (SELECT COUNT(*) FROM semesters WHERE user_id = ?) as semester_count,
        (SELECT COUNT(*) FROM subjects s JOIN semesters sem ON s.semester_id = sem.id WHERE sem.user_id = ?) as subject_count,
        (SELECT COUNT(*) FROM tasks t WHERE t.user_id = ? AND t.parent_id IS NULL) as task_count,
        (SELECT COUNT(*) FROM tasks t WHERE t.user_id = ? AND t.status = 'Completed' AND t.parent_id IS NULL) as completed_count");
    $stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $counts = $stmt->get_result()->fetch_assoc();
    
    $progress['semester'] = $counts['semester_count'] > 0;
    $progress['subjects'] = $counts['subject_count'] >= 3;
    $progress['task'] = $counts['task_count'] > 0;
    $progress['task_completed'] = $counts['completed_count'] > 0;
    
    // Step 5: Use Pomodoro Timer
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM study_sessions WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $progress['pomodoro'] = $result['cnt'] > 0;
    
    // Step 6: Set Up Study Buddy (optional, but checked)
    if (function_exists('getAcceptedBuddy')) {
        $buddy = getAcceptedBuddy($user_id, $conn);
        $progress['buddy'] = !empty($buddy);
    }
    
    // Step 7: Customize Dashboard (check if widgets are customized)
    if (function_exists('getDashboardWidgets')) {
        $widgets = getDashboardWidgets($user_id, $conn);
        $progress['dashboard'] = !empty($widgets) && count($widgets) > 0;
    } else {
        // If widget system doesn't exist, consider it done if they've visited dashboard
        $progress['dashboard'] = true; // Default to true for now
    }
    
    // Step 8: Explore Calendar View (check if they've viewed calendar)
    // This would require tracking page visits, for now default to true if they have tasks
    $progress['calendar'] = $progress['task'];
    
    // Calculate completion percentage
    $completed = array_sum($progress);
    $total = count($progress);
    $percentage = round(($completed / $total) * 100);
    
    return [
        'steps' => $progress,
        'completed' => $completed,
        'total' => $total,
        'percentage' => $percentage
    ];
}

// Function to dismiss onboarding
function dismissOnboarding($user_id, $conn) {
    $stmt = $conn->prepare("UPDATE users SET onboarding_completed = 1 WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// Global search across tasks, notes, subjects
function globalSearch($user_id, $conn, $query, $limit = 20) {
    $results = [];
    $search_term = '%' . $query . '%';
    
    // Search tasks
    $stmt = $conn->prepare("SELECT t.id, t.title, t.status, t.priority, COALESCE(s.name, 'General') as subject_name 
        FROM tasks t 
        LEFT JOIN subjects s ON t.subject_id = s.id 
        WHERE t.user_id = ? AND (t.title LIKE ? OR t.description LIKE ?) 
        ORDER BY t.deadline ASC LIMIT ?");
    $stmt->bind_param("issi", $user_id, $search_term, $search_term, $limit);
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($tasks as $task) {
        $results[] = ['type' => 'task', 'data' => $task];
    }
    
    // Search notes (include notes without subjects)
    $stmt = $conn->prepare("SELECT n.id, n.title, COALESCE(s.name, 'General') as subject_name 
        FROM notes n 
        LEFT JOIN subjects s ON n.subject_id = s.id 
        WHERE n.user_id = ? AND (n.title LIKE ? OR n.content LIKE ?) 
        ORDER BY n.updated_at DESC LIMIT ?");
    $stmt->bind_param("issi", $user_id, $search_term, $search_term, $limit);
    $stmt->execute();
    $notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($notes as $note) {
        $results[] = ['type' => 'note', 'data' => $note];
    }
    
    // Search subjects
    $stmt = $conn->prepare("SELECT s.id, s.name, sem.name as semester_name 
        FROM subjects s 
        JOIN semesters sem ON s.semester_id = sem.id 
        WHERE sem.user_id = ? AND (s.name LIKE ? OR s.instructor_name LIKE ?) 
        ORDER BY s.name ASC LIMIT ?");
    $stmt->bind_param("issi", $user_id, $search_term, $search_term, $limit);
    $stmt->execute();
    $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($subjects as $sub) {
        $results[] = ['type' => 'subject', 'data' => $sub];
    }
    
    return $results;
}

// ============================================
// STUDY BUDDY FUNCTIONS
// ============================================

// Get accepted buddy for a user (only one active buddy at a time)
function getAcceptedBuddy($user_id, $conn) {
    $stmt = $conn->prepare("SELECT sb.*, 
        CASE WHEN sb.requester_id = ? THEN sb.partner_id ELSE sb.requester_id END as buddy_id
        FROM study_buddies sb 
        WHERE (sb.requester_id = ? OR sb.partner_id = ?) AND sb.status = 'accepted'
        LIMIT 1");
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        $buddy = getUserInfo($result['buddy_id'], $conn);
        $result['buddy'] = $buddy;
    }
    return $result;
}

// Get the most recent unlinked buddy (for showing past chat history)
function getLastBuddyPair($user_id, $conn) {
    $stmt = $conn->prepare("SELECT sb.*, 
        CASE WHEN sb.requester_id = ? THEN sb.partner_id ELSE sb.requester_id END as buddy_id
        FROM study_buddies sb 
        WHERE (sb.requester_id = ? OR sb.partner_id = ?) AND sb.status = 'unlinked'
        ORDER BY sb.updated_at DESC LIMIT 1");
    $stmt->bind_param("iii", $user_id, $user_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if ($result) {
        // Check if there are any messages between them
        $check = $conn->prepare("SELECT COUNT(*) as cnt FROM buddy_messages 
            WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)");
        $check->bind_param("iiii", $user_id, $result['buddy_id'], $result['buddy_id'], $user_id);
        $check->execute();
        $msg_count = $check->get_result()->fetch_assoc()['cnt'];
        if ($msg_count == 0) return null;
        $result['message_count'] = $msg_count;
        $result['buddy'] = getUserInfo($result['buddy_id'], $conn);
    }
    return $result;
}

// Check if a user is blocked by or has blocked another user
function isBuddyBlocked($user_id, $other_id, $conn) {
    $stmt = $conn->prepare("SELECT id FROM buddy_blocks 
        WHERE (blocker_id = ? AND blocked_id = ?) OR (blocker_id = ? AND blocked_id = ?)");
    $stmt->bind_param("iiii", $user_id, $other_id, $other_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->num_rows > 0;
}

// Block a buddy
function blockBuddy($blocker_id, $blocked_id, $conn) {
    $conn->begin_transaction();
    try {
        // First unpair if currently paired
        $stmt = $conn->prepare("UPDATE study_buddies SET status = 'unlinked'
            WHERE ((requester_id = ? AND partner_id = ?)
                OR (requester_id = ? AND partner_id = ?))
            AND status = 'accepted'");
        $stmt->bind_param("iiii", $blocker_id, $blocked_id, $blocked_id, $blocker_id);
        $stmt->execute();

        // Decline any pending requests between them
        $stmt = $conn->prepare("UPDATE study_buddies SET status = 'declined'
            WHERE ((requester_id = ? AND partner_id = ?)
                OR (requester_id = ? AND partner_id = ?))
            AND status = 'pending'");
        $stmt->bind_param("iiii", $blocker_id, $blocked_id, $blocked_id, $blocker_id);
        $stmt->execute();

        // Insert block record
        $stmt = $conn->prepare("INSERT IGNORE INTO buddy_blocks (blocker_id, blocked_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $blocker_id, $blocked_id);
        $ok = $stmt->execute();

        $conn->commit();
        return $ok;
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('blockBuddy failed: ' . $e->getMessage());
        return false;
    }
}

// Unblock a buddy
function unblockBuddy($blocker_id, $blocked_id, $conn) {
    $stmt = $conn->prepare("DELETE FROM buddy_blocks WHERE blocker_id = ? AND blocked_id = ?");
    $stmt->bind_param("ii", $blocker_id, $blocked_id);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

// Report a buddy
function reportBuddy($reporter_id, $reported_id, $reason, $details, $conn) {
    $stmt = $conn->prepare("INSERT INTO buddy_reports (reporter_id, reported_id, reason, details) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $reporter_id, $reported_id, $reason, $details);
    return $stmt->execute();
}

// Get users blocked by this user
function getBlockedUsers($user_id, $conn) {
    $stmt = $conn->prepare("SELECT bb.*, u.name as blocked_name, u.email as blocked_email, u.profile_photo as blocked_photo
        FROM buddy_blocks bb
        JOIN users u ON u.id = bb.blocked_id
        WHERE bb.blocker_id = ?
        ORDER BY bb.created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Chat rate limiting: max messages per minute
function checkChatRateLimit($user_id, $conn, $max_per_minute = 15) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM buddy_messages 
        WHERE sender_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $count = $stmt->get_result()->fetch_assoc()['cnt'];
    return $count < $max_per_minute;
}

// Get pending buddy requests received
function getPendingBuddyRequests($user_id, $conn) {
    $stmt = $conn->prepare("SELECT sb.*, u.name as requester_name, u.email as requester_email, 
        u.course as requester_course, u.year_level as requester_year, u.profile_photo as requester_photo
        FROM study_buddies sb
        JOIN users u ON u.id = sb.requester_id
        WHERE sb.partner_id = ? AND sb.status = 'pending'
        ORDER BY sb.created_at DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get pending buddy request sent by user
function getSentBuddyRequest($user_id, $conn) {
    $stmt = $conn->prepare("SELECT sb.*, u.name as partner_name, u.email as partner_email
        FROM study_buddies sb
        JOIN users u ON u.id = sb.partner_id
        WHERE sb.requester_id = ? AND sb.status = 'pending'
        LIMIT 1");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Generate unique invite code
function generateBuddyCode() {
    return strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

// Get buddy progress stats (privacy-safe: only completion %)
function getBuddyProgress($buddy_id, $conn) {
    $stats = getDashboardStats($buddy_id, $conn);
    $total = $stats['total_tasks'] ?? 0;
    $completed = $stats['completed_tasks'] ?? 0;
    $pct = $total > 0 ? round(($completed / $total) * 100) : 0;

    // Study time this week
    $stmt = $conn->prepare("SELECT COALESCE(SUM(duration),0) as week_minutes, COUNT(*) as week_sessions
        FROM study_sessions WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stmt->bind_param("i", $buddy_id);
    $stmt->execute();
    $study = $stmt->get_result()->fetch_assoc();

    // Streak: consecutive days with completed tasks (optimized – only check last 365 days)
    $stmt = $conn->prepare("SELECT DISTINCT DATE(t.updated_at) as d FROM tasks t
        WHERE t.user_id = ? AND t.status = 'Completed'
        AND t.updated_at >= DATE_SUB(CURDATE(), INTERVAL 365 DAY)
        ORDER BY d DESC
        LIMIT 365");
    $stmt->bind_param("i", $buddy_id);
    $stmt->execute();
    $days = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $streak = 0;
    $check = new DateTime('today');
    foreach ($days as $row) {
        $day = new DateTime($row['d']);
        if ($day->format('Y-m-d') === $check->format('Y-m-d')) {
            $streak++;
            $check->modify('-1 day');
        } else {
            break;
        }
    }

    // Pending tasks due soon (count only, no details)
    $stmt = $conn->prepare("SELECT COUNT(*) as due_soon FROM tasks t
        WHERE t.user_id = ? AND t.status != 'Completed' AND t.deadline <= DATE_ADD(NOW(), INTERVAL 3 DAY)");
    $stmt->bind_param("i", $buddy_id);
    $stmt->execute();
    $due = $stmt->get_result()->fetch_assoc();

    return [
        'total_tasks' => $total,
        'completed_tasks' => $completed,
        'completion_pct' => $pct,
        'week_minutes' => $study['week_minutes'],
        'week_sessions' => $study['week_sessions'],
        'streak' => $streak,
        'due_soon' => $due['due_soon']
    ];
}

// Get unread nudge count for a user
function getUnreadNudgeCount($user_id, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM buddy_nudges WHERE receiver_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['cnt'];
}

// Get recent nudges for a user
function getBuddyNudges($user_id, $conn, $limit = 10) {
    $stmt = $conn->prepare("SELECT bn.*, u.name as sender_name, u.profile_photo as sender_photo
        FROM buddy_nudges bn
        JOIN users u ON u.id = bn.sender_id
        WHERE bn.receiver_id = ?
        ORDER BY bn.created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// ============================================
// BUDDY CHAT FUNCTIONS (Instant Messaging)
// ============================================

// Get chat messages between two users (paginated, chronological)
function getChatMessages($user_id, $buddy_id, $conn, $limit = 50, $before_id = null) {
    $sql = "SELECT bm.*, u.name as sender_name, u.profile_photo as sender_photo,
            rm.message as reply_message, rm.sender_id as reply_sender_id, ru.name as reply_sender_name
            FROM buddy_messages bm
            JOIN users u ON u.id = bm.sender_id
            LEFT JOIN buddy_messages rm ON rm.id = bm.reply_to_id
            LEFT JOIN users ru ON ru.id = rm.sender_id
            WHERE ((bm.sender_id = ? AND bm.receiver_id = ?) OR (bm.sender_id = ? AND bm.receiver_id = ?))";
    if ($before_id) {
        $sql .= " AND bm.id < ? ORDER BY bm.created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiiii", $user_id, $buddy_id, $buddy_id, $user_id, $before_id, $limit);
    } else {
        $sql .= " ORDER BY bm.created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iiiii", $user_id, $buddy_id, $buddy_id, $user_id, $limit);
    }
    $stmt->execute();
    $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    return array_reverse($messages);
}

// Get new chat messages since a given message ID (for polling)
function getNewChatMessages($user_id, $buddy_id, $conn, $after_id) {
    $stmt = $conn->prepare("SELECT bm.*, u.name as sender_name, u.profile_photo as sender_photo,
            rm.message as reply_message, rm.sender_id as reply_sender_id, ru.name as reply_sender_name
            FROM buddy_messages bm
            JOIN users u ON u.id = bm.sender_id
            LEFT JOIN buddy_messages rm ON rm.id = bm.reply_to_id
            LEFT JOIN users ru ON ru.id = rm.sender_id
            WHERE ((bm.sender_id = ? AND bm.receiver_id = ?) OR (bm.sender_id = ? AND bm.receiver_id = ?))
            AND bm.id > ?
            ORDER BY bm.created_at ASC");
    $stmt->bind_param("iiiii", $user_id, $buddy_id, $buddy_id, $user_id, $after_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Send a chat message
function sendChatMessage($sender_id, $receiver_id, $message, $conn, $type = 'text', $reply_to = null) {
    $stmt = $conn->prepare("INSERT INTO buddy_messages (sender_id, receiver_id, message, message_type, reply_to_id) VALUES (?, ?, ?, ?, ?)");
    $reply_to_val = $reply_to ? intval($reply_to) : null;
    $stmt->bind_param("iissi", $sender_id, $receiver_id, $message, $type, $reply_to_val);
    $stmt->execute();
    return $conn->insert_id;
}

// Mark chat messages as read
function markChatMessagesRead($user_id, $sender_id, $conn) {
    $stmt = $conn->prepare("UPDATE buddy_messages SET is_read = 1 WHERE receiver_id = ? AND sender_id = ? AND is_read = 0");
    $stmt->bind_param("ii", $user_id, $sender_id);
    $stmt->execute();
    return $stmt->affected_rows;
}

// Get unread buddy message count (for header notifications)
function getUnreadBuddyMessageCount($user_id, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM buddy_messages WHERE receiver_id = ? AND is_read = 0");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['cnt'];
}

// Delete a chat message (only sender can delete)
function deleteChatMessage($message_id, $user_id, $conn) {
    $stmt = $conn->prepare("DELETE FROM buddy_messages WHERE id = ? AND sender_id = ?");
    $stmt->bind_param("ii", $message_id, $user_id);
    $stmt->execute();
    return $stmt->affected_rows > 0;
}

// Update user online status (heartbeat)
function updateUserActivity($user_id, $conn) {
    $stmt = $conn->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
}

// Check if a user is online (active in last 2 minutes)
function isUserOnline($user_id, $conn) {
    $stmt = $conn->prepare("SELECT last_active FROM users WHERE id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    if (!$result || !$result['last_active']) return false;
    $last = new DateTime($result['last_active']);
    $now = new DateTime();
    return ($now->getTimestamp() - $last->getTimestamp()) < 120;
}

// Update typing status
function updateTypingStatus($user_id, $conn) {
    $stmt = $conn->prepare("INSERT INTO buddy_typing_status (user_id, typing_until) 
        VALUES (?, DATE_ADD(NOW(), INTERVAL 3 SECOND)) 
        ON DUPLICATE KEY UPDATE typing_until = DATE_ADD(NOW(), INTERVAL 3 SECOND)");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
}

// Clear typing status (user stopped typing)
function clearTypingStatus($user_id, $conn) {
    $stmt = $conn->prepare("UPDATE buddy_typing_status SET typing_until = NOW() WHERE user_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
    }
}

// Check if buddy is currently typing
function isBuddyTyping($buddy_id, $conn) {
    $stmt = $conn->prepare("SELECT typing_until FROM buddy_typing_status WHERE user_id = ? AND typing_until > NOW()");
    if (!$stmt) return false;
    $stmt->bind_param("i", $buddy_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ? true : false;
}

// ============================================
// NOTIFICATION FUNCTIONS
// ============================================

// Create a notification
function createNotification($user_id, $type, $title, $message, $conn, $ref_id = null, $ref_type = 'general') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, reference_id, reference_type) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssis", $user_id, $type, $title, $message, $ref_id, $ref_type);
    return $stmt->execute();
}

// Get unread notification count
function getUnreadNotificationCount($user_id, $conn) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0 AND is_dismissed = 0");
    if (!$stmt) return 0;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['cnt'];
}

// Get recent notifications (for dropdown)
function getRecentNotifications($user_id, $conn, $limit = 8) {
    $stmt = $conn->prepare(
        "SELECT n.*, t.title as task_title FROM notifications n
         LEFT JOIN tasks t ON n.reference_type = 'task' AND n.reference_id = t.id
         WHERE n.user_id = ? AND n.is_dismissed = 0
         ORDER BY n.is_read ASC, n.created_at DESC LIMIT ?"
    );
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get all notifications (paginated, for full page)
function getAllNotifications($user_id, $conn, $page = 1, $per_page = 20, $filter = 'all') {
    $offset = ($page - 1) * $per_page;

    $where = "n.user_id = ? AND n.is_dismissed = 0";
    $params = [$user_id];
    $types = "i";

    if ($filter === 'unread') {
        $where .= " AND n.is_read = 0";
    } elseif ($filter === 'deadlines') {
        $where .= " AND n.type IN ('deadline_24h', 'deadline_1h')";
    } elseif ($filter === 'overdue') {
        $where .= " AND n.type = 'overdue'";
    } elseif ($filter === 'study') {
        $where .= " AND n.type IN ('study_reminder', 'streak_risk')";
    }

    // Count query
    $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM notifications n WHERE $where");
    $count_stmt->bind_param($types, ...$params);
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];

    // Data query
    $stmt = $conn->prepare(
        "SELECT n.*, t.title as task_title FROM notifications n
         LEFT JOIN tasks t ON n.reference_type = 'task' AND n.reference_id = t.id
         WHERE $where
         ORDER BY n.created_at DESC LIMIT ? OFFSET ?"
    );
    $params[] = $per_page;
    $params[] = $offset;
    $types .= "ii";
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    return ['data' => $notifications, 'total' => $total];
}

// Mark a single notification as read
function markNotificationRead($notification_id, $user_id, $conn) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

// Mark all notifications as read
function markAllNotificationsRead($user_id, $conn) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

// Dismiss a notification (soft delete)
function dismissNotification($notification_id, $user_id, $conn) {
    $stmt = $conn->prepare("UPDATE notifications SET is_dismissed = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

// Get notification preferences (returns defaults if no row exists)
function getNotificationPreferences($user_id, $conn) {
    $defaults = [
        'deadline_24h' => 1,
        'deadline_1h' => 1,
        'overdue_alerts' => 1,
        'study_reminders' => 1,
        'streak_alerts' => 1
    ];

    $stmt = $conn->prepare("SELECT * FROM notification_preferences WHERE user_id = ?");
    if (!$stmt) return $defaults;
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();

    if (!$result) return $defaults;

    return [
        'deadline_24h' => (int)$result['deadline_24h'],
        'deadline_1h' => (int)$result['deadline_1h'],
        'overdue_alerts' => (int)$result['overdue_alerts'],
        'study_reminders' => (int)$result['study_reminders'],
        'streak_alerts' => (int)$result['streak_alerts']
    ];
}

// Update notification preferences
function updateNotificationPreferences($user_id, $prefs, $conn) {
    $stmt = $conn->prepare(
        "INSERT INTO notification_preferences (user_id, deadline_24h, deadline_1h, overdue_alerts, study_reminders, streak_alerts)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE deadline_24h = VALUES(deadline_24h), deadline_1h = VALUES(deadline_1h),
         overdue_alerts = VALUES(overdue_alerts), study_reminders = VALUES(study_reminders), streak_alerts = VALUES(streak_alerts)"
    );
    $d24 = (int)($prefs['deadline_24h'] ?? 1);
    $d1 = (int)($prefs['deadline_1h'] ?? 1);
    $overdue = (int)($prefs['overdue_alerts'] ?? 1);
    $study = (int)($prefs['study_reminders'] ?? 1);
    $streak = (int)($prefs['streak_alerts'] ?? 1);
    $stmt->bind_param("iiiiii", $user_id, $d24, $d1, $overdue, $study, $streak);
    return $stmt->execute();
}

// Format notification time as "time ago"
function notificationTimeAgo($datetime) {
    $now = new DateTime();
    $time = new DateTime($datetime);
    $diff = $now->diff($time);

    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' min' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

// Get notification icon based on type
function getNotificationIcon($type) {
    switch ($type) {
        case 'deadline_1h':  return ['fas fa-exclamation-circle', 'var(--danger)'];
        case 'deadline_24h': return ['fas fa-clock', 'var(--warning)'];
        case 'overdue':      return ['fas fa-exclamation-triangle', 'var(--danger)'];
        case 'streak_risk':  return ['fas fa-fire', 'var(--accent, #d97706)'];
        case 'study_reminder': return ['fas fa-brain', 'var(--primary)'];
        default:             return ['fas fa-bell', 'var(--primary)'];
    }
}

// Get overdue tasks count (for dashboard banner)
function getOverdueTasksCount($user_id, $conn) {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) as cnt FROM tasks WHERE user_id = ? AND status != 'Completed' AND deadline < NOW() AND parent_id IS NULL"
    );
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['cnt'];
}

// ============================================
// TASK TEMPLATE FUNCTIONS
// ============================================

// Get all task templates (user templates + system templates)
function getTaskTemplates($user_id, $conn) {
    $stmt = $conn->prepare("SELECT * FROM task_templates 
        WHERE (user_id = ? OR is_system = 1) 
        ORDER BY is_system DESC, name ASC");
    if (!$stmt) return [];
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get a single task template
function getTaskTemplate($template_id, $user_id, $conn) {
    $stmt = $conn->prepare("SELECT * FROM task_templates 
        WHERE id = ? AND (user_id = ? OR is_system = 1)");
    if (!$stmt) return null;
    $stmt->bind_param("ii", $template_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Create a task template
function createTaskTemplate($user_id, $name, $title, $description, $type, $priority, $is_recurring, $recurrence_type, $conn) {
    $stmt = $conn->prepare("INSERT INTO task_templates 
        (user_id, name, title, description, type, priority, is_recurring, recurrence_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("isssssis", $user_id, $name, $title, $description, $type, $priority, $is_recurring, $recurrence_type);
    return $stmt->execute() ? $conn->insert_id : false;
}

// Update a task template
function updateTaskTemplate($template_id, $user_id, $name, $title, $description, $type, $priority, $is_recurring, $recurrence_type, $conn) {
    $stmt = $conn->prepare("UPDATE task_templates 
        SET name = ?, title = ?, description = ?, type = ?, priority = ?, is_recurring = ?, recurrence_type = ?
        WHERE id = ? AND user_id = ?");
    if (!$stmt) return false;
    $stmt->bind_param("sssssisii", $name, $title, $description, $type, $priority, $is_recurring, $recurrence_type, $template_id, $user_id);
    return $stmt->execute();
}

// Delete a task template
function deleteTaskTemplate($template_id, $user_id, $conn) {
    $stmt = $conn->prepare("DELETE FROM task_templates WHERE id = ? AND user_id = ? AND is_system = 0");
    if (!$stmt) return false;
    $stmt->bind_param("ii", $template_id, $user_id);
    return $stmt->execute();
}

// Create task from template
function createTaskFromTemplate($template_id, $user_id, $subject_id, $deadline, $conn) {
    $template = getTaskTemplate($template_id, $user_id, $conn);
    if (!$template) return false;
    
    // Replace placeholders in title
    $title = $template['title'];
    $title = str_replace('{week}', date('W'), $title);
    $title = str_replace('{subject}', 'Subject', $title);
    $title = str_replace('{title}', 'Task', $title);
    $title = str_replace('{milestone}', 'Milestone', $title);
    
    $subject_id_val = $subject_id > 0 ? $subject_id : null;
    $stmt = $conn->prepare("INSERT INTO tasks 
        (user_id, subject_id, title, description, type, priority, deadline, is_recurring, recurrence_type) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) return false;
    $stmt->bind_param("iisssssis", $user_id, $subject_id_val, $title, $template['description'], 
        $template['type'], $template['priority'], $deadline, $template['is_recurring'], $template['recurrence_type']);
    
    return $stmt->execute() ? $conn->insert_id : false;
}

// ============================================
// STUDY GROUP FUNCTIONS (v6.0)
// ============================================

// Generate a unique invite code
function generateGroupInviteCode($conn) {
    do {
        $code = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
        $stmt = $conn->prepare("SELECT id FROM study_groups WHERE invite_code = ?");
        $stmt->bind_param("s", $code);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
    } while ($exists);
    return $code;
}

// Create a study group (creator becomes leader)
function createStudyGroup($user_id, $name, $description, $conn) {
    $code = generateGroupInviteCode($conn);
    $stmt = $conn->prepare("INSERT INTO study_groups (name, description, leader_id, invite_code) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $name, $description, $user_id, $code);
    if (!$stmt->execute()) return false;
    $group_id = $conn->insert_id;
    // Add creator as leader member
    $role = 'leader';
    $stmt2 = $conn->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, ?)");
    $stmt2->bind_param("iis", $group_id, $user_id, $role);
    $stmt2->execute();
    return $group_id;
}

// Get all study groups for a user
function getUserStudyGroups($user_id, $conn) {
    $stmt = $conn->prepare("SELECT sg.*, gm.role,
        (SELECT COUNT(*) FROM group_members WHERE group_id = sg.id) as member_count,
        (SELECT COUNT(*) FROM group_messages WHERE group_id = sg.id AND id > COALESCE(
            (SELECT last_read_id FROM group_message_reads WHERE group_id = sg.id AND user_id = ?), 0
        )) as unread_count
        FROM study_groups sg
        JOIN group_members gm ON gm.group_id = sg.id AND gm.user_id = ?
        ORDER BY sg.updated_at DESC");
    $stmt->bind_param("ii", $user_id, $user_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get full group info by ID (with membership check)
function getGroupInfo($group_id, $user_id, $conn) {
    $stmt = $conn->prepare("SELECT sg.*, gm.role as my_role
        FROM study_groups sg
        JOIN group_members gm ON gm.group_id = sg.id AND gm.user_id = ?
        WHERE sg.id = ?");
    $stmt->bind_param("ii", $user_id, $group_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc();
}

// Get group members with progress stats
function getGroupMembers($group_id, $conn) {
    $stmt = $conn->prepare("SELECT gm.*, u.name, u.email, u.profile_photo, u.course, u.year_level
        FROM group_members gm
        JOIN users u ON u.id = gm.user_id
        WHERE gm.group_id = ?
        ORDER BY gm.role = 'leader' DESC, gm.joined_at ASC");
    $stmt->bind_param("i", $group_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get group member progress (group tasks only — no personal tasks)
function getGroupMemberProgress($group_id, $conn) {
    $members = getGroupMembers($group_id, $conn);
    $progress = [];
    foreach ($members as $m) {
        // Group-specific task stats only
        $stmt = $conn->prepare("SELECT
            COUNT(*) as group_tasks_total,
            SUM(status = 'Completed') as group_tasks_done
            FROM group_tasks WHERE group_id = ? AND assigned_to = ?");
        $stmt->bind_param("ii", $group_id, $m['user_id']);
        $stmt->execute();
        $gt = $stmt->get_result()->fetch_assoc();

        $total = (int)$gt['group_tasks_total'];
        $done = (int)$gt['group_tasks_done'];

        // Weekly study sessions (general — shows study effort)
        $stmt = $conn->prepare("SELECT COALESCE(SUM(duration),0) as week_minutes, COUNT(*) as week_sessions
            FROM study_sessions WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->bind_param("i", $m['user_id']);
        $stmt->execute();
        $study = $stmt->get_result()->fetch_assoc();

        $progress[] = [
            'user_id' => $m['user_id'],
            'name' => $m['name'],
            'profile_photo' => $m['profile_photo'],
            'role' => $m['role'],
            'group_tasks_total' => $total,
            'group_tasks_done' => $done,
            'group_completion_pct' => $total > 0 ? round(($done / $total) * 100) : 0,
            'week_minutes' => (int)$study['week_minutes'],
            'week_sessions' => (int)$study['week_sessions'],
        ];
    }
    return $progress;
}

// Join a group by invite code
function joinGroupByCode($user_id, $code, $conn) {
    $stmt = $conn->prepare("SELECT id, max_members, join_mode FROM study_groups WHERE invite_code = ?");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $group = $stmt->get_result()->fetch_assoc();
    if (!$group) return ['success' => false, 'message' => 'Invalid invite code.'];

    // Check if already a member
    $stmt = $conn->prepare("SELECT id FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $group['id'], $user_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) return ['success' => false, 'message' => 'You are already in this group.'];

    // Check member cap
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM group_members WHERE group_id = ?");
    $stmt->bind_param("i", $group['id']);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
    if ($cnt >= $group['max_members']) return ['success' => false, 'message' => 'This group is full (max ' . $group['max_members'] . ' members).'];

    // Approval mode — create a join request instead of joining directly
    if ($group['join_mode'] === 'approval') {
        // Check for existing pending request
        $stmt = $conn->prepare("SELECT id, status FROM group_join_requests WHERE group_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $group['id'], $user_id);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        if ($existing) {
            if ($existing['status'] === 'pending') return ['success' => false, 'message' => 'You already have a pending request for this group.'];
            if ($existing['status'] === 'rejected') {
                // Allow re-request: update to pending
                $stmt = $conn->prepare("UPDATE group_join_requests SET status = 'pending', created_at = NOW() WHERE id = ?");
                $stmt->bind_param("i", $existing['id']);
                $stmt->execute();
                return ['success' => true, 'pending' => true, 'message' => 'Join request sent! The group leader will review it.'];
            }
        }
        $pending = 'pending';
        $stmt = $conn->prepare("INSERT INTO group_join_requests (group_id, user_id, status) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $group['id'], $user_id, $pending);
        $stmt->execute();
        return ['success' => true, 'pending' => true, 'message' => 'Join request sent! The group leader will review it.'];
    }

    // Open mode — join directly
    $role = 'member';
    $stmt = $conn->prepare("INSERT INTO group_members (group_id, user_id, role) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $group['id'], $user_id, $role);
    $stmt->execute();

    // System message
    $user = getUserInfo($user_id, $conn);
    $sys_msg = htmlspecialchars($user['name']) . ' joined the group.';
    $type = 'system';
    $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message, message_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $group['id'], $user_id, $sys_msg, $type);
    $stmt->execute();

    return ['success' => true, 'group_id' => $group['id']];
}

// Leave a group
function leaveGroup($user_id, $group_id, $conn) {
    $group = getGroupInfo($group_id, $user_id, $conn);
    if (!$group) return false;

    // If leader, transfer leadership or disband
    if ($group['my_role'] === 'leader') {
        $stmt = $conn->prepare("SELECT user_id FROM group_members WHERE group_id = ? AND user_id != ? ORDER BY joined_at ASC LIMIT 1");
        $stmt->bind_param("ii", $group_id, $user_id);
        $stmt->execute();
        $next = $stmt->get_result()->fetch_assoc();
        if ($next) {
            // Transfer leadership
            $stmt = $conn->prepare("UPDATE study_groups SET leader_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $next['user_id'], $group_id);
            $stmt->execute();
            $stmt = $conn->prepare("UPDATE group_members SET role = 'leader' WHERE group_id = ? AND user_id = ?");
            $stmt->bind_param("ii", $group_id, $next['user_id']);
            $stmt->execute();
        } else {
            // Last member — disband
            $stmt = $conn->prepare("DELETE FROM study_groups WHERE id = ?");
            $stmt->bind_param("i", $group_id);
            $stmt->execute();
            return true;
        }
    }

    $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $group_id, $user_id);
    $stmt->execute();

    // System message
    $user = getUserInfo($user_id, $conn);
    $sys_msg = htmlspecialchars($user['name']) . ' left the group.';
    $type = 'system';
    $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message, message_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $group_id, $user_id, $sys_msg, $type);
    $stmt->execute();
    return true;
}

// Remove a member (leader only)
function removeGroupMember($leader_id, $target_id, $group_id, $conn) {
    $group = getGroupInfo($group_id, $leader_id, $conn);
    if (!$group || $group['my_role'] !== 'leader') return false;
    if ($leader_id === $target_id) return false;

    $stmt = $conn->prepare("DELETE FROM group_members WHERE group_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $group_id, $target_id);
    $stmt->execute();

    $target = getUserInfo($target_id, $conn);
    $sys_msg = htmlspecialchars($target['name']) . ' was removed from the group.';
    $type = 'system';
    $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message, message_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $group_id, $leader_id, $sys_msg, $type);
    $stmt->execute();
    return true;
}

// Assign a task within a group
function assignGroupTask($group_id, $assigned_by, $assigned_to, $title, $description, $deadline, $priority, $conn) {
    $stmt = $conn->prepare("INSERT INTO group_tasks (group_id, assigned_by, assigned_to, title, description, deadline, priority) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iiissss", $group_id, $assigned_by, $assigned_to, $title, $description, $deadline, $priority);
    if (!$stmt->execute()) return false;
    $task_id = $conn->insert_id;

    // System message
    $by = getUserInfo($assigned_by, $conn);
    $to = getUserInfo($assigned_to, $conn);
    $sys_msg = htmlspecialchars($by['name']) . ' assigned "' . htmlspecialchars($title) . '" to ' . htmlspecialchars($to['name']) . '.';
    $type = 'system';
    $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message, message_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $group_id, $assigned_by, $sys_msg, $type);
    $stmt->execute();
    return $task_id;
}

// Get group tasks with assignee info
function getGroupTasks($group_id, $conn, $filter_user = null) {
    $sql = "SELECT gt.*, 
        ab.name as assigned_by_name, ab.profile_photo as assigned_by_photo,
        at2.name as assigned_to_name, at2.profile_photo as assigned_to_photo
        FROM group_tasks gt
        JOIN users ab ON ab.id = gt.assigned_by
        JOIN users at2 ON at2.id = gt.assigned_to
        WHERE gt.group_id = ?";
    if ($filter_user) {
        $sql .= " AND gt.assigned_to = ?";
        $sql .= " ORDER BY gt.status ASC, gt.deadline ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $group_id, $filter_user);
    } else {
        $sql .= " ORDER BY gt.status ASC, gt.deadline ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $group_id);
    }
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Toggle group task status
function toggleGroupTaskStatus($task_id, $user_id, $group_id, $conn) {
    // Verify membership
    $stmt = $conn->prepare("SELECT gt.*, gm.role FROM group_tasks gt
        JOIN group_members gm ON gm.group_id = gt.group_id AND gm.user_id = ?
        WHERE gt.id = ? AND gt.group_id = ?");
    $stmt->bind_param("iii", $user_id, $task_id, $group_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    if (!$task) return false;

    // Only assignee or leader can toggle
    if ($task['assigned_to'] != $user_id && $task['role'] !== 'leader') return false;

    $new_status = $task['status'] === 'Completed' ? 'Pending' : 'Completed';
    $completed_at = $new_status === 'Completed' ? date('Y-m-d H:i:s') : null;
    $stmt = $conn->prepare("UPDATE group_tasks SET status = ?, completed_at = ? WHERE id = ?");
    $stmt->bind_param("ssi", $new_status, $completed_at, $task_id);
    $stmt->execute();

    // System message on completion
    if ($new_status === 'Completed') {
        $u = getUserInfo($user_id, $conn);
        $sys_msg = '✅ ' . htmlspecialchars($u['name']) . ' completed "' . htmlspecialchars($task['title']) . '"!';
        $type = 'system';
        $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message, message_type) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("iiss", $group_id, $user_id, $sys_msg, $type);
        $stmt->execute();
    }
    return $new_status;
}

// Delete a group task (leader or assigner)
function deleteGroupTask($task_id, $user_id, $group_id, $conn) {
    $stmt = $conn->prepare("SELECT gt.*, gm.role FROM group_tasks gt
        JOIN group_members gm ON gm.group_id = gt.group_id AND gm.user_id = ?
        WHERE gt.id = ? AND gt.group_id = ?");
    $stmt->bind_param("iii", $user_id, $task_id, $group_id);
    $stmt->execute();
    $task = $stmt->get_result()->fetch_assoc();
    if (!$task) return false;
    if ($task['assigned_by'] != $user_id && $task['role'] !== 'leader') return false;

    $stmt = $conn->prepare("DELETE FROM group_tasks WHERE id = ?");
    $stmt->bind_param("i", $task_id);
    return $stmt->execute();
}

// ── Group Chat Functions ──

function sendGroupMessage($group_id, $sender_id, $message, $conn, $type = 'text', $reply_to = null) {
    $reply_val = $reply_to ? intval($reply_to) : null;
    $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message, message_type, reply_to_id) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("iissi", $group_id, $sender_id, $message, $type, $reply_val);
    $stmt->execute();
    $msg_id = $conn->insert_id;
    $stmt2 = $conn->prepare("UPDATE study_groups SET updated_at = NOW() WHERE id = ?");
    $stmt2->bind_param("i", $group_id);
    $stmt2->execute();
    return $msg_id;
}

function getGroupMessages($group_id, $conn, $limit = 50, $before_id = null) {
    $sql = "SELECT gm.*, u.name as sender_name, u.profile_photo as sender_photo,
            rm.message as reply_message, rm.sender_id as reply_sender_id, ru.name as reply_sender_name
            FROM group_messages gm
            JOIN users u ON u.id = gm.sender_id
            LEFT JOIN group_messages rm ON rm.id = gm.reply_to_id
            LEFT JOIN users ru ON ru.id = rm.sender_id
            WHERE gm.group_id = ?";
    if ($before_id) {
        $sql .= " AND gm.id < ? ORDER BY gm.created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $group_id, $before_id, $limit);
    } else {
        $sql .= " ORDER BY gm.created_at DESC LIMIT ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $group_id, $limit);
    }
    $stmt->execute();
    return array_reverse($stmt->get_result()->fetch_all(MYSQLI_ASSOC));
}

function getNewGroupMessages($group_id, $conn, $after_id) {
    $stmt = $conn->prepare("SELECT gm.*, u.name as sender_name, u.profile_photo as sender_photo,
            rm.message as reply_message, rm.sender_id as reply_sender_id, ru.name as reply_sender_name
            FROM group_messages gm
            JOIN users u ON u.id = gm.sender_id
            LEFT JOIN group_messages rm ON rm.id = gm.reply_to_id
            LEFT JOIN users ru ON ru.id = rm.sender_id
            WHERE gm.group_id = ? AND gm.id > ?
            ORDER BY gm.created_at ASC");
    $stmt->bind_param("ii", $group_id, $after_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function markGroupMessagesRead($group_id, $user_id, $last_id, $conn) {
    $stmt = $conn->prepare("INSERT INTO group_message_reads (group_id, user_id, last_read_id)
        VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_read_id = GREATEST(last_read_id, VALUES(last_read_id))");
    $stmt->bind_param("iii", $group_id, $user_id, $last_id);
    $stmt->execute();
}

function getUnreadGroupMessageCount($user_id, $conn) {
    try {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(
            (SELECT COUNT(*) FROM group_messages WHERE group_id = gm.group_id AND id > COALESCE(
                (SELECT last_read_id FROM group_message_reads WHERE group_id = gm.group_id AND user_id = ?), 0
            ))
        ), 0) as total_unread
        FROM group_members gm WHERE gm.user_id = ?");
        $stmt->bind_param("ii", $user_id, $user_id);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_assoc()['total_unread'];
    } catch (\Exception $e) {
        return 0;
    }
}

function checkGroupChatRateLimit($user_id, $group_id, $conn, $max = 15) {
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM group_messages
        WHERE sender_id = ? AND group_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
    $stmt->bind_param("ii", $user_id, $group_id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc()['cnt'] < $max;
}

// Update group settings (leader only)
function updateGroupSettings($group_id, $leader_id, $name, $description, $allow_member_assign, $conn, $allow_member_invite = 0, $join_mode = 'open') {
    if (!in_array($join_mode, ['open', 'approval'])) $join_mode = 'open';
    $stmt = $conn->prepare("UPDATE study_groups SET name = ?, description = ?, allow_member_assign = ?, allow_member_invite = ?, join_mode = ? WHERE id = ? AND leader_id = ?");
    $stmt->bind_param("ssiisii", $name, $description, $allow_member_assign, $allow_member_invite, $join_mode, $group_id, $leader_id);
    return $stmt->execute();
}

// ── Join Request Functions (approval mode) ──

function getPendingJoinRequests($group_id, $conn) {
    try {
        $stmt = $conn->prepare("SELECT gjr.*, u.name, u.email, u.profile_photo, u.course, u.year_level
            FROM group_join_requests gjr
            JOIN users u ON u.id = gjr.user_id
            WHERE gjr.group_id = ? AND gjr.status = 'pending'
            ORDER BY gjr.created_at ASC");
        $stmt->bind_param("i", $group_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    } catch (\Exception $e) {
        return [];
    }
}

function approveJoinRequest($request_id, $leader_id, $conn) {
    // Get request info
    $stmt = $conn->prepare("SELECT gjr.*, sg.leader_id, sg.max_members
        FROM group_join_requests gjr
        JOIN study_groups sg ON sg.id = gjr.group_id
        WHERE gjr.id = ? AND gjr.status = 'pending'");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    $req = $stmt->get_result()->fetch_assoc();
    if (!$req || $req['leader_id'] != $leader_id) return false;

    // Check member cap
    $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM group_members WHERE group_id = ?");
    $stmt->bind_param("i", $req['group_id']);
    $stmt->execute();
    $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
    if ($cnt >= $req['max_members']) return false;

    // Add as member
    $role = 'member';
    $stmt = $conn->prepare("INSERT IGNORE INTO group_members (group_id, user_id, role) VALUES (?, ?, ?)");
    $stmt->bind_param("iis", $req['group_id'], $req['user_id'], $role);
    $stmt->execute();

    // Mark approved
    $stmt = $conn->prepare("UPDATE group_join_requests SET status = 'approved' WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();

    // System message
    $user = getUserInfo($req['user_id'], $conn);
    $sys_msg = htmlspecialchars($user['name']) . ' joined the group.';
    $type = 'system';
    $stmt = $conn->prepare("INSERT INTO group_messages (group_id, sender_id, message, message_type) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $req['group_id'], $req['user_id'], $sys_msg, $type);
    $stmt->execute();

    return true;
}

function rejectJoinRequest($request_id, $leader_id, $conn) {
    $stmt = $conn->prepare("SELECT gjr.group_id FROM group_join_requests gjr
        JOIN study_groups sg ON sg.id = gjr.group_id
        WHERE gjr.id = ? AND sg.leader_id = ? AND gjr.status = 'pending'");
    $stmt->bind_param("ii", $request_id, $leader_id);
    $stmt->execute();
    if ($stmt->get_result()->num_rows === 0) return false;

    $stmt = $conn->prepare("UPDATE group_join_requests SET status = 'rejected' WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    return $stmt->execute();
}

?>
