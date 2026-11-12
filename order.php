<?php

session_start();

require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

$conn->begin_transaction();

try {

    /*
     * 장바구니 조회
     */
    $stmt = $conn->prepare("
        SELECT id
        FROM carts
        WHERE user_id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $cart = $stmt->get_result()->fetch_assoc();

    if (!$cart) {
        throw new Exception('장바구니가 없습니다.');
    }

    $cartId = (int)$cart['id'];


    /*
     * 장바구니 상품 조회
     */
    $stmt = $conn->prepare("
        SELECT
            ci.product_id,
            ci.quantity,
            p.name,
            p.price,
            p.stock
        FROM cart_items ci
        JOIN products p
            ON ci.product_id = p.id
        WHERE ci.cart_id = ?
        FOR UPDATE
    ");

    $stmt->bind_param("i", $cartId);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception('장바구니가 비어 있습니다.');
    }

    $items = [];
    $subtotalAmount = 0;

    while ($row = $result->fetch_assoc()) {

        if ($row['quantity'] > $row['stock']) {
            throw new Exception(
                $row['name'] . ' 상품의 재고가 부족합니다.'
            );
        }

        $items[] = $row;

        $subtotalAmount +=
            $row['price'] * $row['quantity'];
    }


    /*
     * 쿠폰 확인
     */
    $couponCode = $_SESSION['coupon_code'] ?? '';
    $discountPercent = 0;
    $discountAmount = 0;

    if ($couponCode !== '') {

        $stmt = $conn->prepare("
            SELECT
                code,
                discount_percent
            FROM coupons
            WHERE code = ?
            AND active = TRUE
            LIMIT 1
        ");

        $stmt->bind_param(
            "s",
            $couponCode
        );

        $stmt->execute();

        $coupon = $stmt
            ->get_result()
            ->fetch_assoc();

        if ($coupon) {

            $couponCode =
                $coupon['code'];

            $discountPercent =
                (int)$coupon['discount_percent'];

            $discountAmount = round(
                $subtotalAmount *
                ($discountPercent / 100),
                2
            );

        } else {

            $couponCode = '';
        }
    }


    /*
     * 최종 결제금액
     */
    $totalAmount =
        $subtotalAmount - $discountAmount;


    /*
     * 주문 생성
     */
    $stmt = $conn->prepare("
        INSERT INTO orders
        (
            user_id,
            subtotal_amount,
            discount_amount,
            coupon_code,
            total_amount,
            status
        )
        VALUES (
            ?,
            ?,
            ?,
            NULLIF(?, ''),
            ?,
            'pending'
        )
    ");

    $stmt->bind_param(
        "iddsd",
        $userId,
        $subtotalAmount,
        $discountAmount,
        $couponCode,
        $totalAmount
    );

    $stmt->execute();

    $orderId = $conn->insert_id;


    /*
     * 주문 상품 저장 및 재고 차감
     */
    foreach ($items as $item) {

        $stmt = $conn->prepare("
            INSERT INTO order_items
            (
                order_id,
                product_id,
                quantity,
                price
            )
            VALUES (?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "iiid",
            $orderId,
            $item['product_id'],
            $item['quantity'],
            $item['price']
        );

        $stmt->execute();


        $stmt = $conn->prepare("
            UPDATE products
            SET stock = stock - ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "ii",
            $item['quantity'],
            $item['product_id']
        );

        $stmt->execute();
    }


    /*
     * 장바구니 비우기
     */
    $stmt = $conn->prepare("
        DELETE FROM cart_items
        WHERE cart_id = ?
    ");

    $stmt->bind_param("i", $cartId);
    $stmt->execute();


    /*
     * Audit Log
     */
    $action = 'CREATE_ORDER';
    $target = 'order_id=' . $orderId;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $details =
        'subtotal=' . $subtotalAmount .
        ', coupon=' .
        ($couponCode !== '' ? $couponCode : 'none') .
        ', discount=' . $discountAmount .
        ', total=' . $totalAmount;

    $stmt = $conn->prepare("
        INSERT INTO audit_logs
        (
            user_id,
            action,
            target,
            ip_address,
            details
        )
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        "issss",
        $userId,
        $action,
        $target,
        $ip,
        $details
    );

    $stmt->execute();


    $conn->commit();

    /*
     * 사용한 쿠폰 세션 제거
     */
    unset($_SESSION['coupon_code']);

} catch (Throwable $e) {

    $conn->rollback();

    die(
        '주문 처리 실패: ' .
        htmlspecialchars($e->getMessage())
    );
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

    <title>주문 완료 - VulnShop</title>

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

        <h2>주문이 완료되었습니다.</h2>

        <p>
            주문번호:
            <?= (int)$orderId ?>
        </p>

        <p>
            상품 금액:
            <?= number_format($subtotalAmount) ?>원
        </p>

        <?php if ($couponCode !== ''): ?>

            <p>
                적용 쿠폰:
                <?= htmlspecialchars($couponCode) ?>
            </p>

            <p>
                할인:
                <?= $discountPercent ?>%
                (-<?= number_format($discountAmount) ?>원)
            </p>

        <?php endif; ?>

        <p>
            최종 결제 금액:
            <strong>
                <?= number_format($totalAmount) ?>원
            </strong>
        </p>

        <p>
            주문 상태: pending
        </p>

        <a
            class="button"
            href="/orders.php"
        >
            주문내역 확인
        </a>

    </section>

</main>


<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>

</html>
