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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>게시글 - VulnShop</title>

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

        <?php if (!$post): ?>

            <h2>게시글을 찾을 수 없습니다.</h2>

        <?php else: ?>

            <h2>
                <?= htmlspecialchars($post['title']) ?>
            </h2>

            <p>
                작성자:
                <?= htmlspecialchars($post['username']) ?>
            </p>

            <p>
                작성시간:
                <?= htmlspecialchars($post['created_at']) ?>
            </p>

            <hr>

            <p>
                <?= nl2br(
                    htmlspecialchars($post['content'])
                ) ?>
            </p>


            <?php if (
                $attachments &&
                $attachments->num_rows > 0
            ): ?>

                <hr>

                <h3>첨부파일</h3>

                <?php while (
                    $attachment =
                        $attachments->fetch_assoc()
                ): ?>

                    <p>

                        <a
                            href="/download.php?id=<?= (int)$attachment['id'] ?>"
                        >
                            <?= htmlspecialchars(
                                $attachment['original_name']
                            ) ?>
                        </a>

                    </p>

                <?php endwhile; ?>

            <?php endif; ?>

        <?php endif; ?>

        <br>

        <a
            class="button"
            href="/board.php"
        >
            목록으로
        </a>

    </section>

</main>


<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>

</html>
