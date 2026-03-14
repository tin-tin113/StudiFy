<?php
/**
 * Quick functional smoke check for key flows:
 * - Tasks add/edit
 * - Notes render path
 * - Attachments upload/list/delete
 * - Study Buddy pairing request/accept
 */

$baseUrl = getenv('STUDIFY_BASE_URL') ?: 'http://localhost/Studify/';
$studentEmail = 'student@studify.com';
$studentPass = 'password123';

require_once __DIR__ . '/config/db.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function out($msg) { echo $msg . PHP_EOL; }
function pass($msg) { out("[PASS] $msg"); }
function fail($msg) { out("[FAIL] $msg"); }

function cleanupSmokeArtifacts($conn) {
    // Broad cleanup by prefixes so failed runs do not leave artifacts behind
    $conn->query("DELETE FROM notifications WHERE title LIKE 'SMOKE_%' OR message LIKE 'SMOKE_%'");
    $conn->query("DELETE FROM attachments WHERE task_id IN (SELECT id FROM tasks WHERE title LIKE 'SMOKE_TASK_%' OR title LIKE 'SMOKE_OVERDUE_%')");
    $conn->query("DELETE FROM notes WHERE title LIKE 'SMOKE_NOTE_%'");
    $conn->query("DELETE FROM tasks WHERE title LIKE 'SMOKE_TASK_%' OR title LIKE 'SMOKE_OVERDUE_%'");
    $conn->query("DELETE FROM study_buddies WHERE requester_id IN (SELECT id FROM users WHERE email LIKE 'smokebuddy+%@studify.local') OR partner_id IN (SELECT id FROM users WHERE email LIKE 'smokebuddy+%@studify.local')");
    $conn->query("DELETE FROM users WHERE email LIKE 'smokebuddy+%@studify.local'");
}

function httpRequest($url, $method = 'GET', $fields = null, $cookieJar = null, $headers = [], $isMultipart = false) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($cookieJar) {
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieJar);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieJar);
    }

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($isMultipart) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        } else {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($fields) ? http_build_query($fields) : $fields);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $resp = curl_exec($ch);
    if ($resp === false) {
        $err = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException("HTTP error: $err");
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $rawHeaders = substr($resp, 0, $headerSize);
    $body = substr($resp, $headerSize);
    curl_close($ch);

    return [$status, $rawHeaders, $body];
}

function extractInputToken($html) {
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/i', $html, $m)) return $m[1];
    return null;
}

function extractMetaToken($html) {
    if (preg_match('/<meta\s+name="csrf-token"\s+content="([^"]+)"/i', $html, $m)) return html_entity_decode($m[1], ENT_QUOTES);
    return null;
}

function loginSession($baseUrl, $email, $password, $cookieJar) {
    [$s1, , $loginPage] = httpRequest($baseUrl . 'auth/login.php', 'GET', null, $cookieJar);
    if ($s1 !== 200) throw new RuntimeException("Login page HTTP $s1");

    $csrf = extractInputToken($loginPage);
    if (!$csrf) throw new RuntimeException('CSRF token not found on login page');

    [$s2, $h2, $b2] = httpRequest($baseUrl . 'auth/login.php', 'POST', [
        'csrf_token' => $csrf,
        'email' => $email,
        'password' => $password,
    ], $cookieJar);

    if (!in_array($s2, [302, 303], true) && stripos($b2, 'Invalid email or password') !== false) {
        throw new RuntimeException('Login failed: invalid credentials');
    }

    // Verify authenticated page can be loaded
    [$s3, , $dash] = httpRequest($baseUrl . 'student/dashboard.php', 'GET', null, $cookieJar);
    if ($s3 !== 200 || stripos($dash, 'Login') !== false) {
        throw new RuntimeException("Authenticated page check failed (HTTP $s3)");
    }

    return true;
}

out('=== Studify Smoke Check ===');
out('Base URL: ' . $baseUrl);

try {
    // Base reachability
    [$s, , ] = httpRequest($baseUrl . 'index.php');
    if ($s !== 200) throw new RuntimeException("Base app not reachable (HTTP $s)");
    pass('Application reachable');

    // Student session
    $cookieA = tempnam(sys_get_temp_dir(), 'studifyA_');
    loginSession($baseUrl, $studentEmail, $studentPass, $cookieA);
    pass('Student login');

    // Fetch CSRF from tasks page
    [$stTasks, , $tasksHtml] = httpRequest($baseUrl . 'student/tasks.php', 'GET', null, $cookieA);
    if ($stTasks !== 200) throw new RuntimeException('Tasks page not accessible');
    $csrfA = extractMetaToken($tasksHtml);
    if (!$csrfA) throw new RuntimeException('CSRF meta token missing for student session');

    // Resolve student user id
    $stmt = $conn->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $studentEmail);
    $stmt->execute();
    $studentId = (int)($stmt->get_result()->fetch_assoc()['id'] ?? 0);
    if ($studentId <= 0) throw new RuntimeException('Student user not found in DB');

    // TASK ADD (AJAX)
    $taskTitle = 'SMOKE_TASK_' . date('Ymd_His');
    $deadline = date('Y-m-d\TH:i', strtotime('+1 day'));
    [$addStatus, , $addBody] = httpRequest(
        $baseUrl . 'student/tasks.php',
        'POST',
        [
            'action' => 'add',
            'csrf_token' => $csrfA,
            'title' => $taskTitle,
            'description' => 'Smoke task description',
            'type' => 'Assignment',
            'priority' => 'Medium',
            'deadline' => $deadline,
            'is_recurring' => '0',
            'subject_id' => '',
        ],
        $cookieA,
        ['X-Requested-With: XMLHttpRequest']
    );

    $addJson = json_decode($addBody, true);
    if ($addStatus !== 200 || empty($addJson['success'])) {
        throw new RuntimeException('Task add failed: ' . $addBody);
    }
    pass('Tasks add');

    // Get created task id
    $stmt = $conn->prepare('SELECT id FROM tasks WHERE user_id = ? AND title = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('is', $studentId, $taskTitle);
    $stmt->execute();
    $taskId = (int)($stmt->get_result()->fetch_assoc()['id'] ?? 0);
    if ($taskId <= 0) throw new RuntimeException('Created task not found in DB');

    // TASK EDIT (form POST)
    $editedTitle = $taskTitle . '_EDITED';
    [$editStatus, , ] = httpRequest(
        $baseUrl . 'student/tasks.php',
        'POST',
        [
            'action' => 'edit',
            'csrf_token' => $csrfA,
            'task_id' => $taskId,
            'title' => $editedTitle,
            'description' => 'Edited description',
            'type' => 'Assignment',
            'priority' => 'High',
            'status' => 'Pending',
            'deadline' => date('Y-m-d H:i:s', strtotime('+2 days')),
        ],
        $cookieA
    );
    if (!in_array($editStatus, [302, 303], true)) {
        throw new RuntimeException("Task edit expected redirect, got HTTP $editStatus");
    }

    $stmt = $conn->prepare('SELECT title, status, priority FROM tasks WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $taskId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if (($row['title'] ?? '') !== $editedTitle || ($row['status'] ?? '') !== 'Pending') {
        throw new RuntimeException('Task edit not persisted in DB');
    }
    pass('Tasks edit');

    // NOTES ADD + RENDER CHECK
    [$notesStatus, , $notesHtmlBefore] = httpRequest($baseUrl . 'student/notes.php', 'GET', null, $cookieA);
    if ($notesStatus !== 200) throw new RuntimeException('Notes page inaccessible');
    $csrfNotes = extractMetaToken($notesHtmlBefore) ?: $csrfA;

    $noteTitle = 'SMOKE_NOTE_' . date('Ymd_His');
    $noteContent = '<p>Smoke note body</p><script>alert(1)</script>';
    [$notePostStatus, , ] = httpRequest(
        $baseUrl . 'student/notes.php',
        'POST',
        [
            'action' => 'add',
            'csrf_token' => $csrfNotes,
            'title' => $noteTitle,
            'subject_id' => 0,
            'content' => $noteContent,
        ],
        $cookieA
    );
    if (!in_array($notePostStatus, [302, 303], true)) {
        throw new RuntimeException("Notes add expected redirect, got HTTP $notePostStatus");
    }

    [$notesStatus2, , $notesHtmlAfter] = httpRequest($baseUrl . 'student/notes.php', 'GET', null, $cookieA);
    if ($notesStatus2 !== 200 || stripos($notesHtmlAfter, 'function sanitizeRichHtml') === false) {
        throw new RuntimeException('Notes render/sanitize script path check failed');
    }

    $stmt = $conn->prepare('SELECT id FROM notes WHERE user_id = ? AND title = ? ORDER BY id DESC LIMIT 1');
    $stmt->bind_param('is', $studentId, $noteTitle);
    $stmt->execute();
    $noteId = (int)($stmt->get_result()->fetch_assoc()['id'] ?? 0);
    if ($noteId <= 0) throw new RuntimeException('Created note not found in DB');
    pass('Notes render path');

    // ATTACHMENTS upload/list/delete
    $tmpFile = tempnam(sys_get_temp_dir(), 'studify_att_');
    file_put_contents($tmpFile, 'Smoke attachment test file');

    $uploadFields = [
        'action' => 'upload',
        'csrf_token' => $csrfA,
        'task_id' => (string)$taskId,
        'file' => new CURLFile($tmpFile, 'text/plain', 'smoke.txt'),
    ];
    [$upStatus, , $upBody] = httpRequest(
        $baseUrl . 'student/attachments.php',
        'POST',
        $uploadFields,
        $cookieA,
        [],
        true
    );
    $upJson = json_decode($upBody, true);
    if ($upStatus !== 200 || empty($upJson['success']) || empty($upJson['attachment']['id'])) {
        throw new RuntimeException('Attachment upload failed: ' . $upBody);
    }
    $attId = (int)$upJson['attachment']['id'];

    [$listStatus, , $listBody] = httpRequest($baseUrl . 'student/attachments.php?action=list&task_id=' . $taskId, 'GET', null, $cookieA);
    $listJson = json_decode($listBody, true);
    if ($listStatus !== 200 || empty($listJson['success']) || !is_array($listJson['attachments']) || count($listJson['attachments']) < 1) {
        throw new RuntimeException('Attachment list failed: ' . $listBody);
    }

    [$delStatus, , $delBody] = httpRequest(
        $baseUrl . 'student/attachments.php',
        'POST',
        [
            'action' => 'delete',
            'csrf_token' => $csrfA,
            'attachment_id' => $attId,
        ],
        $cookieA
    );
    $delJson = json_decode($delBody, true);
    if ($delStatus !== 200 || empty($delJson['success'])) {
        throw new RuntimeException('Attachment delete failed: ' . $delBody);
    }
    @unlink($tmpFile);
    pass('Attachments upload/delete');

    // STUDY BUDDY PAIRING (request + accept)
    $buddyEmail = 'smokebuddy+' . time() . '@studify.local';
    $buddyPass = 'Password123';

    // Create second student account directly in DB for test
    $hash = password_hash($buddyPass, PASSWORD_BCRYPT);
    $stmt = $conn->prepare('INSERT INTO users (name, email, password, role, course, year_level) VALUES (?, ?, ?, ?, ?, ?)');
    $role = 'student';
    $course = 'QA';
    $year = 1;
    $name = 'Smoke Buddy';
    $stmt->bind_param('sssssi', $name, $buddyEmail, $hash, $role, $course, $year);
    $stmt->execute();
    $buddyId = (int)$conn->insert_id;

    // session B
    $cookieB = tempnam(sys_get_temp_dir(), 'studifyB_');
    loginSession($baseUrl, $buddyEmail, $buddyPass, $cookieB);

    // Student A send request to buddy
    [$sbPageStatusA, , $sbPageA] = httpRequest($baseUrl . 'student/study_buddy.php', 'GET', null, $cookieA);
    if ($sbPageStatusA !== 200) throw new RuntimeException('Study buddy page inaccessible for requester');
    $csrfSbA = extractMetaToken($sbPageA) ?: $csrfA;

    [$reqStatus, , ] = httpRequest(
        $baseUrl . 'student/study_buddy.php',
        'POST',
        [
            'action' => 'send_request',
            'csrf_token' => $csrfSbA,
            'partner_email' => $buddyEmail,
        ],
        $cookieA
    );
    if (!in_array($reqStatus, [200, 302, 303], true)) {
        throw new RuntimeException("Study buddy send request unexpected HTTP $reqStatus");
    }

    // Find pending request id
    $stmt = $conn->prepare("SELECT id FROM study_buddies WHERE requester_id = ? AND partner_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
    $stmt->bind_param('ii', $studentId, $buddyId);
    $stmt->execute();
    $requestId = (int)($stmt->get_result()->fetch_assoc()['id'] ?? 0);
    if ($requestId <= 0) throw new RuntimeException('Pending study buddy request not found');

    // Buddy B accept request
    [$sbPageStatusB, , $sbPageB] = httpRequest($baseUrl . 'student/study_buddy.php', 'GET', null, $cookieB);
    if ($sbPageStatusB !== 200) throw new RuntimeException('Study buddy page inaccessible for partner');
    $csrfSbB = extractMetaToken($sbPageB);
    if (!$csrfSbB) throw new RuntimeException('CSRF token missing for buddy partner');

    [$accStatus, , ] = httpRequest(
        $baseUrl . 'student/study_buddy.php',
        'POST',
        [
            'action' => 'accept_request',
            'csrf_token' => $csrfSbB,
            'request_id' => $requestId,
        ],
        $cookieB
    );
    if (!in_array($accStatus, [200, 302, 303], true)) {
        throw new RuntimeException("Study buddy accept unexpected HTTP $accStatus");
    }

    $stmt = $conn->prepare('SELECT status FROM study_buddies WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $requestId);
    $stmt->execute();
    $status = $stmt->get_result()->fetch_assoc()['status'] ?? '';
    if ($status !== 'accepted') {
        throw new RuntimeException("Study buddy pairing not accepted (status=$status)");
    }
    pass('Study Buddy pairing');

    // Cleanup test data
    cleanupSmokeArtifacts($conn);

    @unlink($cookieA);
    if (isset($cookieB)) @unlink($cookieB);

    out('');
    out('=== Smoke check completed successfully ===');
    exit(0);

} catch (Throwable $e) {
    fail($e->getMessage());
    try { cleanupSmokeArtifacts($conn); } catch (Throwable $cleanupError) {}
    out('=== Smoke check failed ===');
    exit(1);
}
