
<?php

session_start();

require_once __DIR__ . '/config/db.php';

/*
 * 공지사항 목록 조회
 * 고정 공지를 먼저 표시하고
 * 최신 작성순으로 정렬합니다.
 */

$stmt = $conn->prepare("
    SELECT
        id,
        title,
        is_pinned,
        created_at
    FROM notices
    ORDER BY
        is_pinned DESC,
        created_at DESC,
        id DESC
");

$stmt->execute();

$notices = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>공지사항 - VulnShop</title>

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
        <span>공지사항</span>
    </div>

    <section class="notice-heading">

        <span class="section-label">
            VULNSHOP NOTICE
        </span>

        <h2>공지사항</h2>

        <p>
            VulnShop의 새로운 소식과
            중요한 안내 사항을 확인하세요.
        </p>

    </section>

    <section class="notice-info">

        <strong>VulnShop 공식 공지사항</strong>

        <p>
            쇼핑몰 운영, 서비스 변경 사항 및
            주요 안내를 전달해 드립니다.
        </p>

    </section>

    <section class="notice-panel">

        <div class="notice-panel-heading">

            <h3>공지사항 목록</h3>

            <span>
                총 <?= (int)$notices->num_rows ?>건
            </span>

        </div>

        <div class="notice-table-wrap">

            <table class="notice-table">

                <thead>
                    <tr>
                        <th scope="col">번호</th>
                        <th scope="col">제목</th>
                        <th scope="col">등록일</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($notices->num_rows === 0): ?>

                    <tr>
                        <td colspan="3"
                            class="notice-empty">

                            아직 등록된 공지사항이 없습니다.

                        </td>
                    </tr>

                <?php else: ?>

                    <?php while (
                        $notice = $notices->fetch_assoc()
                    ): ?>

                        <tr>

                            <td class="notice-number">

                                <?php if ((int)$notice['is_pinned'] === 1): ?>

                                    <span class="notice-pin">
                                        공지
                                    </span>

                                <?php else: ?>

                                    <?= (int)$notice['id'] ?>

                                <?php endif; ?>

                            </td>

                            <td class="notice-title-cell">

                                <a href="/notice-detail.php?id=<?= (int)$notice['id'] ?>">

                                    <?= htmlspecialchars(
                                        $notice['title'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </a>

                            </td>

                            <td class="notice-date">

                                <?= htmlspecialchars(
                                    $notice['created_at'],
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

    <?php if (
        isset($_SESSION['role']) &&
        $_SESSION['role'] === 'admin'
    ): ?>

        <div class="notice-actions">

            <a class="notice-primary-button"
               href="/admin-notices.php">

                공지사항 관리

            </a>

        </div>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
