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

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>주문 완료 - VulnShop</title>

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
        <span>주문 완료</span>
    </div>

    <section class="order-complete-card">

        <!-- 주문 완료 메시지 -->

        <div class="complete-heading">

            <div class="complete-icon">
                &#10003;
            </div>

            <span class="section-label">
                ORDER COMPLETE
            </span>

            <h2>주문이 완료되었습니다.</h2>

            <p>
                VulnShop을 이용해 주셔서 감사합니다.
                <br>
                주문내역에서 주문 진행 상태를 확인하실 수 있습니다.
            </p>

        </div>

        <!-- 주문 진행 단계 -->

        <div class="complete-steps">

            <span>01 장바구니</span>

            <span class="complete-arrow">›</span>

            <span>02 주문</span>

            <span class="complete-arrow">›</span>

            <span class="complete-active">
                03 주문 완료
            </span>

        </div>

        <!-- 주문 정보 -->

        <div class="complete-summary">

            <h3>주문 정보</h3>

            <div class="order-info-line">

                <span>주문번호</span>

                <strong>
                    #<?= sprintf('%06d', (int)$orderId) ?>
                </strong>

            </div>

            <div class="order-info-line">

                <span>상품 금액</span>

                <strong>
                    <?= number_format($subtotalAmount) ?>원
                </strong>

            </div>

            <?php if ($couponCode !== ''): ?>

                <div class="order-info-line">

                    <span>적용 쿠폰</span>

                    <strong class="order-coupon">

                        <?= htmlspecialchars($couponCode) ?>

                    </strong>

                </div>

                <div class="order-info-line">

                    <span>할인율</span>

                    <strong>

                        <?= htmlspecialchars((string)$discountPercent) ?>%

                    </strong>

                </div>

            <?php endif; ?>

            <div class="order-info-line">

                <span>할인 금액</span>

                <strong class="order-discount">

                    -<?= number_format($discountAmount) ?>원

                </strong>

            </div>

            <div class="payment-divider"></div>

            <div class="payment-total">

                <span>최종 주문 금액</span>

                <strong>

                    <?= number_format($totalAmount) ?>원

                </strong>

            </div>

            <div class="payment-status">

                <span>주문 상태</span>

                <span class="order-status status-pending">
                    결제 대기
                </span>

            </div>

        </div>

        <!-- 페이지 이동 버튼 -->

        <div class="complete-actions">

            <a class="order-detail-button"
               href="/products.php">

                쇼핑 계속하기

            </a>

            <a class="order-primary-button"
               href="/orders.php">

                주문내역 확인

            </a>

        </div>

    </section>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
