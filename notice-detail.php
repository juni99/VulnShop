
<?php

session_start();

require_once __DIR__ . '/config/db.php';

/*
 * 공지사항 상세 조회
 */

$noticeId = (int)($_GET['id'] ?? 0);
$notice = null;

if ($noticeId > 0) {

    $stmt = $conn->prepare("
        SELECT
            n.id,
            n.title,
            n.content,
            n.is_pinned,
            n.created_at,
            n.updated_at,
            u.username AS author_name
        FROM notices n
        JOIN users u
            ON n.author_id = u.id
        WHERE n.id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $noticeId);
    $stmt->execute();

    $notice = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if (!$notice) {
    http_response_code(404);
}

?>
<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>공지사항 상세 - VulnShop</title>

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
        <a href="/notices.php">공지사항</a>
        <span>/</span>
        <span>상세</span>
    </div>

    <section class="notice-heading">

        <span class="section-label">
            VULNSHOP NOTICE
        </span>

        <h2>공지사항</h2>

        <p>
            VulnShop의 공식 안내 사항입니다.
        </p>

    </section>

    <?php if (!$notice): ?>

        <section class="notice-detail-empty">

            <h3>공지사항을 찾을 수 없습니다.</h3>

            <p>
                존재하지 않거나 삭제된 공지사항입니다.
            </p>

            <a class="notice-primary-button"
               href="/notices.php">
                목록으로 돌아가기
            </a>

        </section>

    <?php else: ?>

        <article class="notice-detail-panel">

            <div class="notice-detail-heading">

                <?php if ((int)$notice['is_pinned'] === 1): ?>

                    <span class="notice-pin">
                        중요 공지
                    </span>

                <?php endif; ?>

                <h3>
                    <?= htmlspecialchars(
                        $notice['title'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h3>

                <div class="notice-detail-meta">

                    <span>
                        작성자:
                        <?= htmlspecialchars(
                            $notice['author_name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                    <span>
                        등록일:
                        <?= htmlspecialchars(
                            $notice['created_at'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                    <?php if (
                        $notice['updated_at'] !==
                        $notice['created_at']
                    ): ?>

                        <span>
                            수정일:
                            <?= htmlspecialchars(
                                $notice['updated_at'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </span>

                    <?php endif; ?>

                </div>

            </div>

            <div class="notice-detail-content">
                <?= nl2br(
                    htmlspecialchars(
                        $notice['content'],
                        ENT_QUOTES,
                        'UTF-8'
                    )
                ) ?>
            </div>

        </article>

    <?php endif; ?>

    <div class="notice-actions">

        <a class="notice-outline-button"
           href="/notices.php">
            목록으로 돌아가기
        </a>

    </div>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
