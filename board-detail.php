<?php

session_start();

require_once __DIR__ . '/config/db.php';

$postId = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare("
    SELECT
        bp.id,
        bp.user_id,
        bp.title,
        bp.content,
        bp.created_at,
        u.username
    FROM board_posts bp
    JOIN users u
        ON bp.user_id = u.id
    WHERE bp.id = ?
    LIMIT 1
");

$stmt->bind_param("i", $postId);
$stmt->execute();

$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    http_response_code(404);
}


/*
 * 첨부파일 조회
 */
$attachments = null;

if ($post) {

    $stmt = $conn->prepare("
        SELECT
            id,
            original_name,
            uploaded_at
        FROM attachments
        WHERE post_id = ?
        ORDER BY id ASC
    ");

    $stmt->bind_param("i", $postId);
    $stmt->execute();

    $attachments = $stmt->get_result();
}

?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>게시글 상세 - VulnShop</title>

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
        <a href="/board.php">고객센터</a>
        <span>/</span>
        <span>게시글 상세</span>
    </div>

    <?php if (!$post): ?>

        <section class="board-empty">
            <h2>게시글을 찾을 수 없습니다.</h2>

            <p>
                존재하지 않는 게시글이거나
                삭제된 게시글입니다.
            </p>

            <a class="board-primary-button"
               href="/board.php">
                게시판으로 돌아가기
            </a>
        </section>

    <?php else: ?>

        <section class="board-detail-panel">

            <div class="board-detail-heading">

                <span class="section-label">
                    CUSTOMER INQUIRY
                </span>

                <h2>
                    <?= htmlspecialchars(
                        $post['title'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h2>

                <div class="board-post-meta">

                    <span>
                        작성자:
                        <?= htmlspecialchars(
                            $post['username'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                    <span>
                        작성일:
                        <?= htmlspecialchars(
                            $post['created_at'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                </div>

            </div>

            <!-- 기존 Stored XSS 실습 코드 유지 -->

            <div class="board-detail-content">
                <?= nl2br($post['content']) ?>
            </div>

            <?php if (
                $attachments &&
                $attachments->num_rows > 0
            ): ?>

                <div class="board-attachments">

                    <h3>첨부파일</h3>

                    <?php while (
                        $attachment =
                            $attachments->fetch_assoc()
                    ): ?>

                        <div class="attachment-item">

                            <span aria-hidden="true">📎</span>

                            <a href="/download.php?id=<?= (int)$attachment['id'] ?>">

                                <?= htmlspecialchars(
                                    $attachment['original_name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </a>

                        </div>

                    <?php endwhile; ?>

                </div>

            <?php endif; ?>

        </section>

    <?php endif; ?>

    <div class="board-actions">
        <a class="board-outline-button"
           href="/board.php">
            목록으로 돌아가기
        </a>
    </div>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
