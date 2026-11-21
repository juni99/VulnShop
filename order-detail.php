<?php

session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/product-image.php';
require_once __DIR__ . '/config/order-status.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$orderId = (int)($_GET['id'] ?? 0);


/*
 * 본인 주문인지 확인
 */
$stmt = $conn->prepare("
    SELECT
        id,
        subtotal_amount,
        discount_amount,
        coupon_code,
        total_amount,
        status,
        created_at
    FROM orders
    WHERE id = ?
    LIMIT 1
    ");

$stmt->bind_param("i", $orderId);


$stmt->execute();

$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    http_response_code(404);
}


/*
 * 주문 상품 조회
 */
$items = null;

if ($order) {

    $stmt = $conn->prepare("
        SELECT
            oi.quantity,
            oi.price,
            p.name,
            p.image
        FROM order_items oi
        JOIN products p
            ON oi.product_id = p.id
        WHERE oi.order_id = ?
        ORDER BY oi.id ASC
    ");

    $stmt->bind_param("i", $orderId);
    $stmt->execute();

    $items = $stmt->get_result();
}

?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>주문 상세 - VulnShop</title>

    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">
    <link rel="stylesheet" href="/css/products.css">
    <link rel="stylesheet" href="/css/orders.css">
</head>

<body class="orders-page">

<?php require __DIR__ . '/config/header.php'; ?>

<main class="container orders-main">

    <div class="breadcrumb">
        <a href="/">홈</a>
        <span>/</span>
        <a href="/orders.php">주문내역</a>
        <span>/</span>
        <span>주문 상세</span>
    </div>

    <section class="orders-heading">

        <span class="section-label">
            VULNSHOP MY ACCOUNT
        </span>

        <h2>주문 상세</h2>

        <p>
            주문하신 상품과 결제 정보를 확인하세요.
        </p>

    </section>

    <?php if (!$order): ?>

        <section class="orders-empty">

            <h3>주문을 찾을 수 없습니다.</h3>

            <p>
                존재하지 않거나 접근할 수 없는 주문입니다.
            </p>

            <a class="button" href="/orders.php">
                주문내역으로 돌아가기
            </a>

        </section>

    <?php else: ?>

        <!-- 주문 기본 정보 -->

        <section class="order-detail-banner">

            <div>

                <span class="order-date">
                    주문 일시:
                    <?= htmlspecialchars($order['created_at']) ?>
                </span>

                <h3>
                    주문번호
                    #<?= sprintf('%06d', (int)$order['id']) ?>
                </h3>

            </div>

            <span class="order-status <?= getOrderStatusClass($order['status']) ?>">

                <?= getOrderStatusLabel($order['status']) ?>

            </span>

        </section>

        <div class="order-detail-grid">

            <!-- 주문 상품 -->

            <section class="order-detail-panel">

                <h3>주문 상품</h3>

                <?php while ($item = $items->fetch_assoc()): ?>

                    <?php

                    $subtotal =
                        $item['price'] *
                        $item['quantity'];

                    $imageUrl = getProductImage(
                        $item['image'] ?? null
                    );

                    ?>

                    <article class="ordered-item">

                        <div class="ordered-item-image">

                            <?php if ($imageUrl !== null): ?>

                                <img
                                    src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    width="1000"
                                    height="1000"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <span class="image-placeholder">
                                    <?= htmlspecialchars($item['name']) ?>
                                </span>

                            <?php endif; ?>

                        </div>

                        <div class="ordered-item-info">

                            <span class="product-category">
                                IT · DIGITAL
                            </span>

                            <h4>
                                <?= htmlspecialchars($item['name']) ?>
                            </h4>

                            <p>
                                주문 당시 가격:
                                <?= number_format($item['price']) ?>원
                            </p>

                            <p>
                                구매 수량:
                                <?= (int)$item['quantity'] ?>개
                            </p>

                        </div>

                        <strong class="ordered-item-price">

                            <?= number_format($subtotal) ?>원

                        </strong>

                    </article>

                <?php endwhile; ?>

            </section>

            <!-- 결제 정보 -->

            <aside class="order-detail-panel payment-panel">

                <h3>결제 정보</h3>

                <div class="order-info-line">

                    <span>상품 금액</span>

                    <strong>
                        <?= number_format($order['subtotal_amount']) ?>원
                    </strong>

                </div>

                <?php if ($order['coupon_code']): ?>

                    <div class="order-info-line">

                        <span>적용 쿠폰</span>

                        <strong class="order-coupon">

                            <?= htmlspecialchars($order['coupon_code']) ?>

                        </strong>

                    </div>

                <?php endif; ?>

                <div class="order-info-line">

                    <span>할인 금액</span>

                    <strong class="order-discount">

                        -<?= number_format($order['discount_amount']) ?>원

                    </strong>

                </div>

                <div class="payment-divider"></div>

                <div class="payment-total">

                    <span>최종 주문 금액</span>

                    <strong>

                        <?= number_format($order['total_amount']) ?>원

                    </strong>

                </div>

                <div class="payment-status">

                    <span>주문 상태</span>

                    <span class="order-status <?= getOrderStatusClass($order['status']) ?>">

                        <?= getOrderStatusLabel($order['status']) ?>

                    </span>

                </div>

            </aside>

        </div>

        <div class="order-back-actions">

            <a class="order-detail-button"
               href="/products.php">

                쇼핑 계속하기

            </a>

            <a class="order-primary-button"
               href="/orders.php">

                주문내역으로 돌아가기

            </a>

        </div>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
