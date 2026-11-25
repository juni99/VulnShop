
<?php

session_start();

require_once __DIR__ . '/config/db.php';

/*
 * 문의 게시글 수정
 * 작성자 본인만 접근 가능
 */

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$postId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$postId || $postId <= 0) {
    http_response_code(404);
    exit('게시글을 찾을 수 없습니다.');
}

/*
 * 게시글 소유권 검사
 */

$stmt = $conn->prepare("
    SELECT id, title, content
    FROM board_posts
    WHERE id = ?
      AND user_id = ?
    LIMIT 1
");

$stmt->bind_param("ii", $postId, $userId);
$stmt->execute();

$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) {
    http_response_code(403);
    exit('본인이 작성한 게시글만 수정할 수 있습니다.');
}

/*
 * CSRF 토큰
 */

if (empty($_SESSION['board_edit_csrf'])) {
    $_SESSION['board_edit_csrf'] =
        bin2hex(random_bytes(32));
}

$error = '';

/*
 * 게시글 수정 처리
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION['board_edit_csrf'], $csrf)
    ) {
        http_response_code(403);
        exit('유효하지 않은 요청입니다.');
    }

    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';

    if (!is_string($title) || !is_string($content)) {
        http_response_code(400);
        exit('잘못된 입력 형식입니다.');
    }

    $title = trim($title);
    $content = trim($content);

    if ($title === '' || $content === '') {

        $error = '제목과 내용을 모두 입력해 주세요.';

    } elseif (mb_strlen($title, 'UTF-8') > 200) {

        $error = '제목은 200자 이하로 입력해 주세요.';

    } else {

        /*
         * SQL 실행 시에도 작성자 ID 검증
         */

        $stmt = $conn->prepare("
            UPDATE board_posts
            SET title = ?,
                content = ?
            WHERE id = ?
              AND user_id = ?
        ");

        $stmt->bind_param(
            "ssii",
            $title,
            $content,
            $postId,
            $userId
        );

        $stmt->execute();
        $stmt->close();

        /*
         * 감사 로그 기록
         */

        $action = 'UPDATE_BOARD_POST';
        $target = 'post_id=' . $postId;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $details = 'Board post updated';

        $log = $conn->prepare("
            INSERT INTO audit_logs
                (user_id, action, target, ip_address, details)
            VALUES (?, ?, ?, ?, ?)
        ");

        $log->bind_param(
            "issss",
            $userId,
            $action,
            $target,
            $ip,
            $details
        );

        $log->execute();
        $log->close();

        header(
            'Location: /board-detail.php?id=' . $postId,
            true,
            303
        );
        exit;
    }

    /*
     * 입력 오류 시 작성한 내용 유지
     */

    $post['title'] = $title;
    $post['content'] = $content;
}

?>

<!DOCTYPE html>
<html lang="ko">
<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>문의 수정 - VulnShop</title>

    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">
    <link rel="stylesheet" href="/css/products.css">
    <link rel="stylesheet" href="/css/board.css">

</head>

<body class="board-page">

<?php require __DIR__ . '/config/header.php'; ?>

<main class="container board-main">

    <div class="breadcrumb">
        <a href="/">홈</a>
        <span>/</span>
        <a href="/board.php">문의 사항</a>
        <span>/</span>
        <span>게시글 수정</span>
    </div>

    <section class="board-heading">

        <span class="section-label">
            VULNSHOP CUSTOMER CENTER
        </span>

        <h2>문의 수정</h2>

        <p>작성한 문의 내용을 수정할 수 있습니다.</p>

    </section>

    <section class="board-form-panel">

        <?php if ($error): ?>

            <div class="board-alert" role="alert">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <form method="POST"
            action="/board-edit.php?id=<?= (int)$postId ?>"
            class="board-form">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['board_edit_csrf'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <!-- board-form -->
            <div class="board-field">

                <label for="edit-title">
                    제목 <span class="required-mark">*</span>
                </label>

                <input
                    type="text"
                    id="edit-title"
                    name="title"
                    maxlength="200"
                    value="<?= htmlspecialchars(
                        $post['title'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    required
                >

            </div>

            <!-- 문의 내용 -->
            <div class="board-field">

                <label for="edit-content">
                    문의 내용 <span class="required-mark">*</span>
                </label>

                <textarea
                    id="edit-content"
                    name="content"
                    rows="12"
                    required
                ><?= htmlspecialchars(
                    $post['content'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>

            </div>

            <!-- 버튼 -->
            <div class="board-form-actions">

                <a
                    href="/board-detail.php?id=<?= (int)$postId ?>"
                    class="board-outline-button">
                    취소
                </a>

                <button
                    type="submit"
                    class="board-primary-button">
                    수정 완료
                </button>

            </div>

        </form>

    </section>



</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
