
<?php

session_start();

require_once __DIR__ . '/config/db.php';

/*
 * 관리자 전용 공지사항 관리
 *
 * 등록, 수정, 삭제 요청에 대해
 * 서버에서 관리자 권한을 검증합니다.
 */

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('로그인이 필요합니다.');
}

$userId = (int)$_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT role
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$currentUser = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$currentUser || $currentUser['role'] !== 'admin') {
    http_response_code(403);
    exit('관리자만 접근할 수 있습니다.');
}

/*
 * CSRF 토큰 생성
 */

if (empty($_SESSION['notice_csrf'])) {
    $_SESSION['notice_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$message = $_SESSION['notice_flash'] ?? '';
unset($_SESSION['notice_flash']);

/*
 * 공지사항 등록 / 수정 / 삭제
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrfToken = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION['notice_csrf'], $csrfToken)
    ) {
        http_response_code(403);
        exit('유효하지 않은 요청입니다.');
    }

    $action = $_POST['action'] ?? '';

    if (!is_string($action)) {
        $action = '';
    }

    if ($action === 'add' || $action === 'update') {

        $title = trim((string)($_POST['title'] ?? ''));
        $content = trim((string)($_POST['content'] ?? ''));
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;

        if (
            $title === '' ||
            $content === '' ||
            mb_strlen($title, 'UTF-8') > 255
        ) {

            $error = '제목과 내용을 올바르게 입력해 주세요.';

        } elseif ($action === 'add') {

            $stmt = $conn->prepare("
                INSERT INTO notices
                    (title, content, author_id, is_pinned)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssii",
                $title,
                $content,
                $userId,
                $isPinned
            );

            $stmt->execute();
            $stmt->close();

            $_SESSION['notice_flash'] =
                '공지사항이 등록되었습니다.';

            header('Location: /admin-notices.php', true, 303);
            exit;

        } else {

            $noticeId = (int)($_POST['notice_id'] ?? 0);

            if ($noticeId <= 0) {

                $error = '수정할 공지사항 번호가 올바르지 않습니다.';

            } else {

                $stmt = $conn->prepare("
                    UPDATE notices
                    SET
                        title = ?,
                        content = ?,
                        is_pinned = ?
                    WHERE id = ?
                ");

                $stmt->bind_param(
                    "ssii",
                    $title,
                    $content,
                    $isPinned,
                    $noticeId
                );

                $stmt->execute();
                $affected = $stmt->affected_rows;
                $stmt->close();

                $_SESSION['notice_flash'] =
                    $affected > 0
                    ? '공지사항이 수정되었습니다.'
                    : '변경된 내용이 없거나 공지사항이 존재하지 않습니다.';

                header('Location: /admin-notices.php', true, 303);
                exit;
            }
        }

    } elseif ($action === 'delete') {

        $noticeId = (int)($_POST['notice_id'] ?? 0);

        if ($noticeId <= 0) {

            $error = '삭제할 공지사항 번호가 올바르지 않습니다.';

        } else {

            $stmt = $conn->prepare("
                DELETE FROM notices
                WHERE id = ?
            ");

            $stmt->bind_param("i", $noticeId);
            $stmt->execute();

            $affected = $stmt->affected_rows;
            $stmt->close();

            $_SESSION['notice_flash'] =
                $affected > 0
                ? '공지사항이 삭제되었습니다.'
                : '삭제할 공지사항을 찾을 수 없습니다.';

            header('Location: /admin-notices.php', true, 303);
            exit;
        }

    } else {

        $error = '지원하지 않는 작업입니다.';
    }
}

/*
 * 전체 공지사항 조회
 */

$stmt = $conn->prepare("
    SELECT
        n.id,
        n.title,
        n.content,
        n.is_pinned,
        n.created_at,
        u.username AS author_name
    FROM notices n
    JOIN users u
        ON n.author_id = u.id
    ORDER BY
        n.is_pinned DESC,
        n.created_at DESC,
        n.id DESC
");

$stmt->execute();
$notices = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>공지사항 관리 - VulnShop</title>

    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">
    <link rel="stylesheet" href="/css/products.css">
    <link rel="stylesheet" href="/css/notices.css">
</head>

<body class="notice-page">

<?php require __DIR__ . '/config/header.php'; ?>

<main class="container notice-main">

    <div class="breadcrumb">
        <a href="/">홈</a>
        <span>/</span>
        <a href="/admin.php">관리자</a>
        <span>/</span>
        <span>공지사항 관리</span>
    </div>

    <section class="notice-heading">
        <span class="section-label">
            VULNSHOP ADMINISTRATION
        </span>

        <h2>공지사항 관리</h2>

        <p>
            공지사항을 등록하고 수정하거나 삭제할 수 있습니다.
        </p>
    </section>

    <?php if ($message): ?>
        <div class="notice-alert notice-success"
             role="status">
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice-alert notice-error"
             role="alert">
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>

    <!-- 새 공지 등록 -->

    <section class="notice-admin-panel">

        <h3>새 공지사항 작성</h3>

        <form method="post"
              class="notice-admin-form">

            <input type="hidden"
                   name="action"
                   value="add">

            <input type="hidden"
                   name="csrf_token"
                   value="<?= htmlspecialchars(
                       $_SESSION['notice_csrf'],
                       ENT_QUOTES,
                       'UTF-8'
                   ) ?>">

            <div class="notice-field">
                <label for="new-notice-title">
                    공지 제목
                </label>

                <input
                    id="new-notice-title"
                    type="text"
                    name="title"
                    maxlength="255"
                    required
                >
            </div>

            <div class="notice-field">
                <label for="new-notice-content">
                    공지 내용
                </label>

                <textarea
                    id="new-notice-content"
                    name="content"
                    rows="8"
                    required
                ></textarea>
            </div>

            <label class="notice-checkbox">
                <input type="checkbox"
                       name="is_pinned"
                       value="1">
                중요 공지로 상단 고정
            </label>

            <div class="notice-form-actions">
                <button class="notice-primary-button"
                        type="submit">
                    공지사항 등록
                </button>
            </div>

        </form>

    </section>

    <!-- 등록된 공지 관리 -->

    <section class="notice-admin-list">

        <div class="notice-panel-heading">
            <h3>등록된 공지사항</h3>
            <span>총 <?= count($notices) ?>건</span>
        </div>

        <?php if (empty($notices)): ?>

            <p class="notice-admin-empty">
                아직 등록된 공지사항이 없습니다.
            </p>

        <?php else: ?>

            <?php foreach ($notices as $notice): ?>

                <?php $noticeId = (int)$notice['id']; ?>

                <article class="notice-admin-item">

                    <div class="notice-admin-item-heading">

                        <div>
                            <strong>
                                공지 #<?= $noticeId ?>
                            </strong>

                            <?php if ((int)$notice['is_pinned'] === 1): ?>
                                <span class="notice-pin">상단 고정</span>
                            <?php endif; ?>

                            <p>
                                작성자:
                                <?= htmlspecialchars(
                                    $notice['author_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                                ·
                                <?= htmlspecialchars(
                                    $notice['created_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>
                        </div>

                        <a href="/notice-detail.php?id=<?= $noticeId ?>">
                            상세 보기
                        </a>

                    </div>

                    <form method="post"
                          class="notice-admin-form">

                        <input type="hidden"
                               name="action"
                               value="update">

                        <input type="hidden"
                               name="notice_id"
                               value="<?= $noticeId ?>">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= htmlspecialchars(
                                   $_SESSION['notice_csrf'],
                                   ENT_QUOTES,
                                   'UTF-8'
                               ) ?>">

                        <div class="notice-field">
                            <label for="title-<?= $noticeId ?>">
                                제목
                            </label>

                            <input
                                id="title-<?= $noticeId ?>"
                                type="text"
                                name="title"
                                maxlength="255"
                                value="<?= htmlspecialchars(
                                    $notice['title'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >
                        </div>

                        <div class="notice-field">
                            <label for="content-<?= $noticeId ?>">
                                내용
                            </label>

                            <textarea
                                id="content-<?= $noticeId ?>"
                                name="content"
                                rows="5"
                                required
                            ><?= htmlspecialchars(
                                $notice['content'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></textarea>
                        </div>

                        <label class="notice-checkbox">
                            <input
                                type="checkbox"
                                name="is_pinned"
                                value="1"
                                <?= (int)$notice['is_pinned'] === 1
                                    ? 'checked'
                                    : '' ?>
                            >
                            중요 공지로 상단 고정
                        </label>

                        <div class="notice-form-actions">
                            <button
                                type="submit"
                                class="notice-primary-button">
                                수정 저장
                            </button>
                        </div>

                    </form>

                    <!-- 삭제는 별도 POST 요청 -->

                    <form method="post"
                          class="notice-delete-form">

                        <input type="hidden"
                               name="action"
                               value="delete">

                        <input type="hidden"
                               name="notice_id"
                               value="<?= $noticeId ?>">

                        <input type="hidden"
                               name="csrf_token"
                               value="<?= htmlspecialchars(
                                   $_SESSION['notice_csrf'],
                                   ENT_QUOTES,
                                   'UTF-8'
                               ) ?>">

                        <button
                            type="submit"
                            class="notice-danger-button">
                            공지사항 삭제
                        </button>

                    </form>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>

    <div class="notice-actions">
        <a class="notice-outline-button"
           href="/notices.php">
            공지사항 목록 보기
        </a>
    </div>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
