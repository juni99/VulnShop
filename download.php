<?php

session_start();

require_once __DIR__ . '/config/db.php';

$attachmentId = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT
        original_name,
        stored_name,
        file_path
    FROM attachments
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $attachmentId);
$stmt->execute();

$file = $stmt->get_result()->fetch_assoc();

if (!$file) {
    http_response_code(404);
    die('파일을 찾을 수 없습니다.');
}

$fullPath =
    __DIR__ . '/uploads/' . basename($file['stored_name']);

if (!is_file($fullPath)) {
    http_response_code(404);
    die('서버에 파일이 존재하지 않습니다.');
}

$downloadName = basename($file['original_name']);

header('Content-Type: application/octet-stream');
header(
    'Content-Disposition: attachment; filename="' .
    rawurlencode($downloadName) .
    '"'
);

header('Content-Length: ' . filesize($fullPath));

readfile($fullPath);
exit;
