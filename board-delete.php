
<?php
session_start();

require_once __DIR__ . '/config/db.php';

/*
 * 게시글 삭제는 POST 요청만 허용
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('허용되지 않은 요청 방식입니다.');
}

/*
 * 로그인 확인
 */
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('로그인이 필요합니다.');
}

$userId = (int)$_SESSION['user_id'];

$postId = filter_input(
    INPUT_POST,
    'post_id',
    FILTER_VALIDATE_INT
);

if (!$postId || $postId <= 0) {
    http_response_code(400);
    exit('잘못된 게시글 번호입니다.');
}

/*
 * CSRF 검증
 */
$csrf = $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['board_delete_csrf']) ||
    !is_string($csrf) ||
    !hash_equals($_SESSION['board_delete_csrf'], $csrf)
) {
    http_response_code(403);
    exit('유효하지 않은 삭제 요청입니다.');
}

$storedNames = [];
$transactionStarted = false;

try {
    /*
     * 트랜잭션 시작
     */
    $conn->begin_transaction();
    $transactionStarted = true;

    /*
     * 게시글 작성자 확인 및 행 잠금
     */
    $stmt = $conn->prepare("
        SELECT id
        FROM board_posts
        WHERE id = ?
          AND user_id = ?
        LIMIT 1
        FOR UPDATE
    ");

    $stmt->bind_param("ii", $postId, $userId);
    $stmt->execute();

    $post = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$post) {
        $conn->rollback();
        $transactionStarted = false;

        http_response_code(403);
        exit('본인이 작성한 게시글만 삭제할 수 있습니다.');
    }

    /*
     * 삭제 전 첨부파일명 확보
     */
    $stmt = $conn->prepare("
        SELECT stored_name
        FROM attachments
        WHERE post_id = ?
    ");

    $stmt->bind_param("i", $postId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $storedNames[] = $row['stored_name'];
    }

    $stmt->close();

    /*
     * 작성자 본인의 게시글만 삭제
     *
     * attachments DB 행은
     * ON DELETE CASCADE로 자동 삭제
     */
    $stmt = $conn->prepare("
        DELETE FROM board_posts
        WHERE id = ?
          AND user_id = ?
    ");

    $stmt->bind_param("ii", $postId, $userId);
    $stmt->execute();

    $deleted = $stmt->affected_rows;
    $stmt->close();

    if ($deleted !== 1) {
        throw new RuntimeException(
            '게시글 삭제 결과를 확인할 수 없습니다.'
        );
    }

    $conn->commit();
    $transactionStarted = false;

} catch (Throwable $e) {

    if ($transactionStarted) {
        $conn->rollback();
    }

    error_log(
        'Board deletion failed: ' . $e->getMessage()
    );

    http_response_code(500);
    exit('게시글 삭제 중 오류가 발생했습니다.');
}

/*
 * DB 삭제 성공 후 실제 첨부파일 정리
 *
 * DB에 저장된 경로를 직접 사용하지 않습니다.
 * 허용된 파일명과 업로드 폴더만 사용합니다.
 */
$uploadDir = realpath(__DIR__ . '/uploads');

$cleanupFailed = false;

foreach ($storedNames as $storedName) {

    /*
     * 기존 업로드 파일명 형식만 허용
     * 32자리 랜덤 16진수 + 허용 확장자
     */
    if (
        !is_string($storedName) ||
        !preg_match(
            '/^[a-f0-9]{32}\.(jpg|jpeg|png|pdf|txt)$/i',
            $storedName
        )
    ) {
        error_log('Invalid stored attachment filename');
        $cleanupFailed = true;
        continue;
    }

    if ($uploadDir === false) {
        $cleanupFailed = true;
        break;
    }

    $file = $uploadDir . DIRECTORY_SEPARATOR . $storedName;

    /*
     * 심볼릭 링크는 삭제 처리에서 제외
     */
    if (is_link($file)) {
        $cleanupFailed = true;
        continue;
    }

    if (is_file($file) && !unlink($file)) {
        error_log('Attachment cleanup failed');
        $cleanupFailed = true;
    }
}

/*
 * 삭제 요청 토큰 갱신
 */
$_SESSION['board_delete_csrf'] =
    bin2hex(random_bytes(32));

$_SESSION['board_delete_message'] =
    $cleanupFailed
        ? '게시글은 삭제되었지만 첨부파일 정리 확인이 필요합니다.'
        : '게시글이 삭제되었습니다.';

header('Location: /board.php', true, 303);
exit;
