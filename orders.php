<?php

session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/order-status.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

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
    WHERE user_id = ?
    ORDER BY id DESC
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$orders = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>주문 내역 - VulnShop</title>

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
        <span>주문내역</span>
    </div>

    <section class="orders-heading">

        <span class="section-label">
            VULNSHOP MY ACCOUNT
        </span>

        <h2>주문 내역</h2>

        <p>
            고객님의 주문 내역과 진행 상태를
            확인하실 수 있습니다.
        </p>

    </section>

    <?php

    $orderList = $orders->fetch_all(MYSQLI_ASSOC);

    $pendingCount = 0;
    $paidCount = 0;

    foreach ($orderList as $entry) {

        if ($entry['status'] === 'pending') {
            $pendingCount++;
        }

        if ($entry['status'] === 'paid') {
            $paidCount++;
        }

    }

    ?>

    <!-- 주문 현황 -->

    <section class="order-overview">

        <div class="overview-item">

            <span>전체 주문</span>

            <strong>
                <?= count($orderList) ?>
            </strong>

        </div>

        <div class="overview-item">

            <span>결제 대기</span>

            <strong class="pending-count">
                <?= $pendingCount ?>
            </strong>

        </div>

        <div class="overview-item">

            <span>결제 완료</span>

            <strong class="paid-count">
                <?= $paidCount ?>
            </strong>

        </div>

    </section>

    <!-- 주문 목록 제목 -->

    <div class="order-list-heading">

        <h3>전체 주문 내역</h3>

        <span>
            총 <?= count($orderList) ?>건
        </span>

    </div>

    <?php if (empty($orderList)): ?>

        <section class="orders-empty">

            <h3>아직 주문 내역이 없습니다.</h3>

            <p>
                VulnShop에서 다양한 상품을 만나보세요.
            </p>

            <a class="button" href="/products.php">
                쇼핑하러 가기
            </a>

        </section>

    <?php else: ?>

        <div class="order-list">

            <?php foreach ($orderList as $order): ?>

                <article class="order-card">

                    <div class="order-card-header">

                        <div>

                            <span class="order-date">
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

                    </div>

                    <div class="order-card-body">

                        <div class="order-payment-info">

                            <div class="order-info-line">

                                <span>상품 금액</span>

                                <strong>
                                    <?= number_format($order['subtotal_amount']) ?>원
                                </strong>

                            </div>

                            <?php if ($order['coupon_code']): ?>

                                <div class="order-info-line">

                                    <span>적용 쿠폰</span>

                                    <strong>
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

                        </div>

                        <div class="order-final-price">

                            <span>최종 주문 금액</span>

                            <strong>
                                <?= number_format($order['total_amount']) ?>원
                            </strong>

                        </div>

                    </div>

                    <div class="order-card-footer">

                        <a class="order-detail-button"
                           href="/order-detail.php?id=<?= (int)$order['id'] ?>">

                            주문 상세 보기 →

                        </a>

                    </div>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
