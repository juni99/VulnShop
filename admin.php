<?php

session_start();

require_once __DIR__ . '/config/db.php';

/*
 * 관리자 인증
 */
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {

    http_response_code(403);
    die('관리자만 접근할 수 있습니다.');
}


/*
 * 통계 조회
 */
$userCount = $conn
    ->query("SELECT COUNT(*) AS cnt FROM users")
    ->fetch_assoc()['cnt'];

$productCount = $conn
    ->query("SELECT COUNT(*) AS cnt FROM products")
    ->fetch_assoc()['cnt'];

$orderCount = $conn
    ->query("SELECT COUNT(*) AS cnt FROM orders")
    ->fetch_assoc()['cnt'];

$postCount = $conn
    ->query("SELECT COUNT(*) AS cnt FROM board_posts")
    ->fetch_assoc()['cnt'];

$reviewCount = $conn
    ->query("SELECT COUNT(*) AS cnt FROM reviews")
    ->fetch_assoc()['cnt'];

?>
<!DOCTYPE html>
<html lang="ko">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>관리자 - VulnShop</title>

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

        <h2>관리자 대시보드</h2>

        <p>
            관리자 계정:
            <?= htmlspecialchars($_SESSION['username']) ?>
        </p>

    </section>


    <section class="product-grid">

        <div class="product-card">

            <h3>회원</h3>

            <p>
                총 <?= (int)$userCount ?>명
            </p>

        </div>


        <div class="product-card">

            <h3>상품</h3>

            <p>
                총 <?= (int)$productCount ?>개
            </p>

        </div>


        <div class="product-card">

            <h3>주문</h3>

            <p>
                총 <?= (int)$orderCount ?>건
            </p>

        </div>


        <div class="product-card">

            <h3>게시글</h3>

            <p>
                총 <?= (int)$postCount ?>개
            </p>

        </div>


        <div class="product-card">

            <h3>리뷰</h3>

            <p>
                총 <?= (int)$reviewCount ?>개
            </p>

        </div>

    </section>


    <section class="hero">

        <h2>관리 기능</h2>

        <p>
            <a
                class="button"
                href="/admin-products.php"
            >
                상품 관리
            </a>
        </p>

        <p>
            <a
                class="button"
                href="/admin-orders.php"
            >
                주문 관리
            </a>
        </p>

    </section>

</main>


<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>

</html>
