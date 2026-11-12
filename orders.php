<?php

session_start();

require_once __DIR__ . '/config/db.php';

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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>주문 내역 - VulnShop</title>

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

        <h2>주문 내역</h2>

        <?php if ($orders->num_rows === 0): ?>

            <p>주문 내역이 없습니다.</p>

        <?php else: ?>

            <?php while ($order = $orders->fetch_assoc()): ?>

                <div class="product-card">

                    <h3>
                        주문번호:
                        <?= (int)$order['id'] ?>
                    </h3>

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

                    <p>
                        <strong>
                            최종 결제 금액:
                            <?= number_format($order['total_amount']) ?>원
                        </strong>
                    </p>

                    <p>
                        상태:
                        <?= htmlspecialchars($order['status']) ?>
                    </p>

                    <p>
                        주문시간:
                        <?= htmlspecialchars($order['created_at']) ?>
                    </p>

                    <a
                        class="button"
                        href="/order-detail.php?id=<?= (int)$order['id'] ?>"
                    >
                        상세보기
                    </a>

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
