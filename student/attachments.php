<?php
/**
 * STUDIFY – File Attachments Handler
 * Handles upload / list / delete for task & note attachments
 */
define('BASE_URL', '../');
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

// Check authentication - return JSON error instead of redirect for API
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = getCurrentUserId();
$action  = $_POST['action'] ?? $_GET['action'] ?? '';

// ─── UPLOAD ────────────────────────────────────────────────────────────────
if ($action === 'upload') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']); exit();
    }

    $task_id = intval($_POST['task_id'] ?? 0);
    $note_id = intval($_POST['note_id'] ?? 0);
    $group_task_id = intval($_POST['group_task_id'] ?? 0);

    if ($task_id <= 0 && $note_id <= 0 && $group_task_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid target']); exit();
    }

    // Verify ownership
    if ($task_id > 0) {
        $chk = $conn->prepare("SELECT id FROM tasks WHERE id = ? AND user_id = ?");
        $chk->bind_param("ii", $task_id, $user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
        }
    }
    if ($note_id > 0) {
        $chk = $conn->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
        $chk->bind_param("ii", $note_id, $user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
        }
    }
    // Verify group task access (user must be a member of the group)
    if ($group_task_id > 0) {
        $chk = $conn->prepare("SELECT gt.id FROM group_tasks gt
            JOIN group_members gm ON gt.group_id = gm.group_id
            WHERE gt.id = ? AND gm.user_id = ?");
        $chk->bind_param("ii", $group_task_id, $user_id);
        $chk->execute();
        if ($chk->get_result()->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Access denied']); exit();
        }
    }

    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file received or upload error']); exit();
    }

    $file     = $_FILES['file'];
    $max_size = 10 * 1024 * 1024; // 10 MB

    $allowed_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv',
        'application/zip', 'application/x-zip-compressed',
    ];

    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File too large (max 10 MB)']); exit();
    }

    $finfo    = new finfo(FILEINFO_MIME_TYPE);
    $mime     = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'File type not allowed: ' . $mime]); exit();
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_ext = preg_replace('/[^a-zA-Z0-9]/', '', $ext);
    $stored   = 'att_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $safe_ext;
    $attachments_dir = rtrim(UPLOAD_DIR, '/\\') . '/attachments/';
    if (!is_dir($attachments_dir) && !mkdir($attachments_dir, 0755, true)) {
        echo json_encode(['success' => false, 'message' => 'Upload storage unavailable']); exit();
    }
    if (!is_writable($attachments_dir)) {
        echo json_encode(['success' => false, 'message' => 'Upload storage is not writable']); exit();
    }
    $dest     = $attachments_dir . $stored;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save file']); exit();
    }

    $orig_name = basename($file['name']);
    $file_path = 'uploads/attachments/' . $stored;
    $file_size = $file['size'];

    // Build dynamic INSERT - only include the target column that has a value
    // This avoids CHECK constraint issues (bind_param converts null to 0)
    $columns = ['user_id', 'file_name', 'file_path', 'file_size', 'file_type'];
    $placeholders = ['?', '?', '?', '?', '?'];
    $types = 'issis';
    $values = [$user_id, $orig_name, $file_path, $file_size, $mime];

    if ($task_id > 0) {
        $columns[] = 'task_id';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $task_id;
    } elseif ($note_id > 0) {
        $columns[] = 'note_id';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $note_id;
    } elseif ($group_task_id > 0) {
        $columns[] = 'group_task_id';
        $placeholders[] = '?';
        $types .= 'i';
        $values[] = $group_task_id;
    }

    $sql = "INSERT INTO attachments (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        // Likely cause: group_task_id column doesn't exist — migration not run
        @unlink($dest);
        echo json_encode([
            'success' => false,
            'message' => 'Database schema error. Please ask admin to run group attachments migration.',
            'debug'   => APP_ENV !== 'production' ? $conn->error : null
        ]);
        exit();
    }
    $stmt->bind_param($types, ...$values);

    if ($stmt->execute()) {
        $att_id = $conn->insert_id;
        echo json_encode([
            'success'   => true,
            'message'   => 'File uploaded',
            'attachment' => [
                'id'        => $att_id,
                'file_name' => $orig_name,
                'file_path' => BASE_URL . $file_path,
                'file_size' => $file_size,
                'file_type' => $mime,
            ]
        ]);
    } else {
        @unlink($dest);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . ($stmt->error ?: 'unknown'),
            'debug'   => APP_ENV !== 'production' ? $stmt->error : null
        ]);
    }
    exit();
}

// ─── LIST ──────────────────────────────────────────────────────────────────
if ($action === 'list') {
    $task_id = intval($_GET['task_id'] ?? 0);
    $note_id = intval($_GET['note_id'] ?? 0);
    $group_task_id = intval($_GET['group_task_id'] ?? 0);

    if ($task_id > 0) {
        $stmt = $conn->prepare("SELECT a.* FROM attachments a JOIN tasks t ON a.task_id = t.id WHERE a.task_id = ? AND t.user_id = ? ORDER BY a.created_at ASC");
        $stmt->bind_param("ii", $task_id, $user_id);
    } elseif ($note_id > 0) {
        $stmt = $conn->prepare("SELECT a.* FROM attachments a JOIN notes n ON a.note_id = n.id WHERE a.note_id = ? AND n.user_id = ? ORDER BY a.created_at ASC");
        $stmt->bind_param("ii", $note_id, $user_id);
    } elseif ($group_task_id > 0) {
        // For group tasks, user must be a member of the group
        $stmt = $conn->prepare("SELECT a.* FROM attachments a
            JOIN group_tasks gt ON a.group_task_id = gt.id
            JOIN group_members gm ON gt.group_id = gm.group_id
            WHERE a.group_task_id = ? AND gm.user_id = ?
            ORDER BY a.created_at ASC");
        $stmt->bind_param("ii", $group_task_id, $user_id);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid target']); exit();
    }

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Add public URL
    foreach ($rows as &$r) {
        $r['url'] = BASE_URL . $r['file_path'];
    }

    echo json_encode(['success' => true, 'attachments' => $rows]);
    exit();
}

// ─── DELETE ────────────────────────────────────────────────────────────────
if ($action === 'delete') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']); exit();
    }

    $att_id = intval($_POST['attachment_id'] ?? 0);
    if ($att_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit();
    }

    // First check if it's a regular attachment (task/note) owned by user
    $stmt = $conn->prepare("SELECT a.* FROM attachments a WHERE a.id = ? AND a.user_id = ?");
    $stmt->bind_param("ii", $att_id, $user_id);
    $stmt->execute();
    $att = $stmt->get_result()->fetch_assoc();

    // If not found as owner, check if it's a group task attachment where user is a group member
    if (!$att) {
        $stmt = $conn->prepare("SELECT a.* FROM attachments a
            JOIN group_tasks gt ON a.group_task_id = gt.id
            JOIN group_members gm ON gt.group_id = gm.group_id
            WHERE a.id = ? AND gm.user_id = ?");
        $stmt->bind_param("ii", $att_id, $user_id);
        $stmt->execute();
        $att = $stmt->get_result()->fetch_assoc();
    }

    if (!$att) {
        echo json_encode(['success' => false, 'message' => 'Not found or access denied']); exit();
    }

    $full_path = __DIR__ . '/../' . $att['file_path'];
    if (file_exists($full_path)) {
        unlink($full_path);
    }

    // Delete attachment - we've already verified access above
    $del = $conn->prepare("DELETE FROM attachments WHERE id = ?");
    $del->bind_param("i", $att_id);

    if ($del->execute()) {
        echo json_encode(['success' => true, 'message' => 'Attachment deleted']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit();
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
