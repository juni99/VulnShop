<?php

session_start();

require_once __DIR__ . '/config/db.php';

/*
 * 관리자 인증
 */
/*
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {

    http_response_code(403);
    die('관리자만 접근할 수 있습니다.');
} */

/*
 * [VULNERABLE LAB]
 * Broken Access Control
 *
 * 로그인 여부만 확인하고 관리자 역할은 검증하지 않음
 */

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('로그인이 필요합니다.');
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
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>관리자 대시보드 - VulnShop</title>

    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">
    <link rel="stylesheet" href="/css/products.css">
    <link rel="stylesheet" href="/css/admin.css">
</head>

<body class="admin-page">

<?php require __DIR__ . '/config/header.php'; ?>

<main class="container admin-main">

    <div class="breadcrumb">
        <a href="/">홈</a>
        <span>/</span>
        <span>관리자 대시보드</span>
    </div>

    <!-- 대시보드 제목 -->

    <section class="admin-heading">

        <span class="section-label">
            VULNSHOP ADMINISTRATION
        </span>

        <h2>관리자 대시보드</h2>

        <p>
            VulnShop 쇼핑몰의 운영 현황을
            한눈에 확인하고 관리할 수 있습니다.
        </p>

    </section>

    <!-- 접속 계정 -->

    <section class="admin-welcome">

        <div class="admin-welcome-icon">
            &#128100;
        </div>

        <div>

            <strong>
                <?= htmlspecialchars(
                    $_SESSION['username'] ?? '사용자',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>님, 환영합니다.
            </strong>

            <p>
                현재 접속 계정의 관리 화면입니다.
            </p>

        </div>

    </section>

    <!-- 운영 통계 -->

    <div class="admin-section-heading">

        <h3>운영 현황</h3>

        <span>
            SHOP OVERVIEW
        </span>

    </div>

    <section class="admin-stat-grid">

        <article class="admin-stat-card">

            <div class="admin-stat-header">
                <span>전체 회원</span>
                <span class="admin-stat-icon">&#128101;</span>
            </div>

            <strong>
                <?= number_format((int)$userCount) ?>
                <small>명</small>
            </strong>

            <p>가입한 전체 회원</p>

        </article>

        <article class="admin-stat-card">

            <div class="admin-stat-header">
                <span>등록 상품</span>
                <span class="admin-stat-icon">&#128230;</span>
            </div>

            <strong>
                <?= number_format((int)$productCount) ?>
                <small>개</small>
            </strong>

            <p>현재 등록된 상품</p>

        </article>

        <article class="admin-stat-card">

            <div class="admin-stat-header">
                <span>전체 주문</span>
                <span class="admin-stat-icon">&#128722;</span>
            </div>

            <strong>
                <?= number_format((int)$orderCount) ?>
                <small>건</small>
            </strong>

            <p>전체 주문 처리 내역</p>

        </article>

        <article class="admin-stat-card">

            <div class="admin-stat-header">
                <span>게시글</span>
                <span class="admin-stat-icon">&#128221;</span>
            </div>

            <strong>
                <?= number_format((int)$postCount) ?>
                <small>개</small>
            </strong>

            <p>등록된 고객 게시글</p>

        </article>

        <article class="admin-stat-card">

            <div class="admin-stat-header">
                <span>상품 리뷰</span>
                <span class="admin-stat-icon">&#11088;</span>
            </div>

            <strong>
                <?= number_format((int)$reviewCount) ?>
                <small>개</small>
            </strong>

            <p>상품에 등록된 리뷰</p>

        </article>

    </section>

    <!-- 관리 기능 -->

    <div class="admin-section-heading">

        <h3>빠른 관리 메뉴</h3>

        <span>
            MANAGEMENT
        </span>

    </div>

    <section class="admin-menu-grid">

        <!-- 상품 관리 카드 -->
        <a class="admin-menu-card" href="/admin-products.php">
            ...
        </a>

        <!-- 주문 관리 카드 -->
        <a class="admin-menu-card" href="/admin-orders.php">
            ...
        </a>

        <!-- 새로 추가한 공지사항 관리 카드 -->
        <a class="admin-menu-card" href="/admin-notices.php">
            ...
        </a>

    </section>

        <a class="admin-menu-card"
           href="/admin-orders.php">

            <span class="admin-menu-icon">
                &#128203;
            </span>

            <div class="admin-menu-info">

                <h3>주문 관리</h3>

                <p>
                    전체 주문 내역을 조회하고
                    주문 처리 상태를 변경합니다.
                </p>

                <span class="admin-menu-link">
                    주문 관리로 이동 &rarr;
                </span>

            </div>

        </a>

    </section>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
