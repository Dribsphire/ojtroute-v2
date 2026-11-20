<?php
/**
 * Gatekeeper for uploaded files.
 *
 * storage/uploads/* and public/uploads/* are not served directly any more: their
 * .htaccess rewrites every request to this script, which checks the login and
 * whether the viewer may see that file. Existing <img src="../../storage/uploads/...">
 * links keep working.
 *
 * Who may see what:
 *  - attendance photos, submitted documents, timeout letters:
 *      the student they belong to, that student's instructor, and admins
 *  - profile pictures, document templates, the DTR header: any logged-in user
 */

session_start();

require_once __DIR__ . '/../config/database.php';

function deny($code = 404)
{
    http_response_code($code);
    header('Content-Type: text/plain');
    echo $code === 403 ? 'Forbidden' : 'Not found';
    exit;
}

$roots = [
    'storage' => realpath(__DIR__ . '/../storage/uploads'),
    'public' => realpath(__DIR__ . '/uploads'),
];

$rootKey = $_GET['root'] ?? '';
$rel = str_replace('\\', '/', (string) ($_GET['f'] ?? ''));

// Only "folder/file.ext" or "file.ext" with safe characters
if (!isset($roots[$rootKey]) || !$roots[$rootKey] || !preg_match('#^(?:[A-Za-z0-9_-]+/)?[A-Za-z0-9_.-]+$#', $rel) || strpos($rel, '..') !== false) {
    deny(404);
}

$path = realpath($roots[$rootKey] . '/' . $rel);
if (!$path || !is_file($path) || strpos($path, $roots[$rootKey] . DIRECTORY_SEPARATOR) !== 0) {
    deny(404);
}

// Must be logged in
$userId = $_SESSION['user_id'] ?? null;
$role = $_SESSION['role'] ?? null;
if (!$userId || !in_array($role, ['admin', 'instructor', 'student'], true)) {
    deny(403);
}

$folder = strpos($rel, '/') !== false ? strstr($rel, '/', true) : '';
$name = basename($rel);

// Which student does this file belong to? (null = shared file)
$ownerStudentId = null;
$timeoutRecordId = null;
if ($rootKey === 'storage' && $folder === 'attendance_images' && preg_match('/^att_(\d+)_/', $name, $m)) {
    $ownerStudentId = (int) $m[1];
} elseif ($rootKey === 'storage' && $folder === 'student_docs' && preg_match('/^doc_(\d+)_/', $name, $m)) {
    $ownerStudentId = (int) $m[1];
} elseif ($rootKey === 'public' && $folder === 'documents' && preg_match('/^timeout_req_(\d+)_/', $name, $m)) {
    $timeoutRecordId = (int) $m[1];
} elseif (!($rootKey === 'storage' && in_array($folder, ['profiles', 'profile_pics', 'templates', ''], true))) {
    deny(404); // unknown folder
}

if ($role !== 'admin' && ($ownerStudentId !== null || $timeoutRecordId !== null)) {
    $config = require __DIR__ . '/../config/database.php';
    $db = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['dbname'], $config['charset']),
        $config['username'],
        $config['password'],
        $config['options']
    );

    if ($timeoutRecordId !== null) {
        $stmt = $db->prepare('SELECT student_id FROM attendance_records WHERE id = ?');
        $stmt->execute([$timeoutRecordId]);
        $ownerStudentId = (int) $stmt->fetchColumn();
    }

    if ($role === 'student') {
        $stmt = $db->prepare('SELECT id FROM students WHERE user_id = ?');
        $stmt->execute([$userId]);
        if ((int) $stmt->fetchColumn() !== $ownerStudentId) {
            deny(403);
        }
    } else { // instructor: only students in their own sections
        $stmt = $db->prepare('
            SELECT 1
            FROM students st
            JOIN users u ON st.user_id = u.id
            JOIN sections s ON u.section_id = s.id
            JOIN instructors i ON s.instructor_id = i.id
            WHERE st.id = ? AND i.user_id = ?
        ');
        $stmt->execute([$ownerStudentId, $userId]);
        if (!$stmt->fetchColumn()) {
            deny(403);
        }
    }
}

// Serve it with a type we choose, never executed
$types = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
];
$ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
if (!isset($types[$ext])) {
    deny(404);
}

session_write_close();
header('Content-Type: ' . $types[$ext]);
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, max-age=3600');
$inline = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'pdf'], true);
header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
readfile($path);
