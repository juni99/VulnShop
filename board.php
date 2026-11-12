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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>게시판 - VulnShop</title>

    <link
        rel="stylesheet"
        href="/css/style.css"
    >

</head>

<body>

<header>

    <div class="container header-inner">

        <h1>
            <a href="/">VulnShop</a>
        </h1>

        <?php require __DIR__ . '/config/nav.php'; ?>

    </div>

</header>


<main class="container">

    <section class="hero">

        <h2>게시판</h2>

        <p>
            VulnShop 사용자 게시판입니다.
        </p>

        <?php if (isset($_SESSION['user_id'])): ?>

            <a
                class="button"
                href="/board-write.php"
            >
                글쓰기
            </a>

        <?php endif; ?>

    </section>


    <section class="hero">

        <?php if ($result->num_rows === 0): ?>

            <p>
                등록된 게시글이 없습니다.
            </p>

        <?php else: ?>

            <?php while ($post = $result->fetch_assoc()): ?>

                <div class="product-card">

                    <h3>

                        <a
                            href="/board-detail.php?id=<?= (int)$post['id'] ?>"
                        >
                            <?= htmlspecialchars($post['title']) ?>
                        </a>

                    </h3>

                    <p>
                        작성자:
                        <?= htmlspecialchars($post['username']) ?>
                    </p>

                    <p>
                        작성시간:
                        <?= htmlspecialchars($post['created_at']) ?>
                    </p>

                </div>

                <br>

            <?php endwhile; ?>

        <?php endif; ?>

    </section>

</main>


<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>

</html>
