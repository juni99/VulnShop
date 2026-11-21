<?php

session_start();

require_once __DIR__ . '/config/db.php';

$result = $conn->query("
    SELECT
        bp.id,
        bp.title,
        bp.created_at,
        u.username
    FROM board_posts bp
    JOIN users u
        ON bp.user_id = u.id
    ORDER BY bp.id DESC
");

?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>고객센터 - VulnShop</title>

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
        <span>고객센터</span>
    </div>

    <section class="board-heading">
        <span class="section-label">
            VULNSHOP CUSTOMER CENTER
        </span>

        <h2>고객센터</h2>

        <p>
            VulnShop 상품 및 서비스 관련 문의를
            자유롭게 남겨주세요.
        </p>
    </section>

    <section class="board-notice">
        <strong>문의 게시판 안내</strong>

        <p>
            게시글은 누구나 확인할 수 있으며,
            로그인한 회원은 문의 글과 첨부파일을
            등록할 수 있습니다.
        </p>
    </section>

    <section class="board-panel">

        <div class="board-panel-heading">
            <h3>문의 게시판</h3>

            <span>
                총 <?= (int)$result->num_rows ?>건
            </span>
        </div>

        <div class="board-table-wrap">

            <table class="board-table">

                <thead>
                    <tr>
                        <th scope="col">번호</th>
                        <th scope="col">제목</th>
                        <th scope="col">작성자</th>
                        <th scope="col">작성일</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($result->num_rows === 0): ?>

                    <tr>
                        <td colspan="4" class="board-no-data">
                            아직 등록된 게시글이 없습니다.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php while ($post = $result->fetch_assoc()): ?>

                        <tr>

                            <td class="board-number">
                                <?= (int)$post['id'] ?>
                            </td>

                            <td class="board-title-cell">

                                <a href="/board-detail.php?id=<?= (int)$post['id'] ?>">

                                    <?= htmlspecialchars(
                                        $post['title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </a>

                            </td>

                            <td>
                                <?= htmlspecialchars(
                                    $post['username'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                            <td class="board-date">
                                <?= htmlspecialchars(
                                    $post['created_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

    <div class="board-actions">

        <?php if (isset($_SESSION['user_id'])): ?>

            <a class="board-primary-button"
               href="/board-write.php">
                문의 작성하기
            </a>

        <?php else: ?>

            <p>문의 작성은 로그인 후 이용할 수 있습니다.</p>

            <a class="board-primary-button"
               href="/login.php">
                로그인
            </a>

        <?php endif; ?>

    </div>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
