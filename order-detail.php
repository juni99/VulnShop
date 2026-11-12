<?php

session_start();

require_once __DIR__ . '/config/db.php';

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
    AND user_id = ?
    LIMIT 1
");

$stmt->bind_param(
    "ii",
    $orderId,
    $userId
);

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
            p.name
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>주문 상세 - VulnShop</title>

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

        <?php if (!$order): ?>

            <h2>주문을 찾을 수 없습니다.</h2>

            <p>
                존재하지 않거나 접근할 수 없는 주문입니다.
            </p>

        <?php else: ?>

            <h2>
                주문 상세 #<?= (int)$order['id'] ?>
            </h2>

            <p>
                주문 상태:
                <?= htmlspecialchars($order['status']) ?>
            </p>

            <p>
                주문 시간:
                <?= htmlspecialchars($order['created_at']) ?>
            </p>

            <hr>

            <h3>주문 상품</h3>

            <?php while ($item = $items->fetch_assoc()): ?>

                <?php
                $subtotal =
                    $item['price'] * $item['quantity'];
                ?>

                <div class="product-card">

                    <h3>
                        <?= htmlspecialchars($item['name']) ?>
                    </h3>

                    <p>
                        주문 당시 가격:
                        <?= number_format($item['price']) ?>원
                    </p>

                    <p>
                        수량:
                        <?= (int)$item['quantity'] ?>개
                    </p>

                    <p>
                        합계:
                        <?= number_format($subtotal) ?>원
                    </p>

                </div>

                <br>

            <?php endwhile; ?>

            <hr>

            <p>
                상품 금액:
                <?= number_format($order['subtotal_amount']) ?>원
            </p>

            <?php if ($order['coupon_code']): ?>

                <p>
                    적용 쿠폰:
                    <?= htmlspecialchars($order['coupon_code']) ?>
                </p>

                <p>
                    할인 금액:
                    -<?= number_format($order['discount_amount']) ?>원
                </p>

            <?php endif; ?>

            <h3>
                최종 결제 금액:
                <?= number_format($order['total_amount']) ?>원
            </h3>

            <a
                class="button"
                href="/orders.php"
            >
                주문내역으로 돌아가기
            </a>

        <?php endif; ?>

    </section>

</main>


<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>

</html>
