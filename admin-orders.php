<?php

session_start();

require_once __DIR__ . '/config/db.php';

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    http_response_code(403);
    die('관리자만 접근할 수 있습니다.');
}

$adminId = (int)$_SESSION['user_id'];
$message = '';
$error = '';

/*
 * 주문 상태 변경
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $orderId = (int)($_POST['order_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    $allowedStatus = [
        'pending',
        'paid',
        'shipped',
        'completed',
        'cancelled'
    ];

    if (
        $orderId <= 0 ||
        !in_array($status, $allowedStatus, true)
    ) {

        $error = '잘못된 주문 상태입니다.';

    } else {

        $stmt = $conn->prepare("
            UPDATE orders
            SET status = ?
            WHERE id = ?
        ");

        $stmt->bind_param(
            "si",
            $status,
            $orderId
        );

        $stmt->execute();

        /*
         * Audit Log
         */
        $action = 'ADMIN_UPDATE_ORDER_STATUS';
        $target = 'order_id=' . $orderId;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $details = 'status=' . $status;

        $log = $conn->prepare("
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

        $log->bind_param(
            "issss",
            $adminId,
            $action,
            $target,
            $ip,
            $details
        );

        $log->execute();

        $message = '주문 상태가 변경되었습니다.';
    }
}


/*
 * 전체 주문 조회
 */
$orders = $conn->query("
    SELECT
        o.id,
        o.subtotal_amount,
        o.discount_amount,
        o.coupon_code,
        o.total_amount,
        o.status,
        o.created_at,
        u.username
    FROM orders o
    JOIN users u
        ON o.user_id = u.id
    ORDER BY o.id DESC
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

    <title>주문 관리 - VulnShop</title>

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

        <h2>주문 관리</h2>

        <a
            class="button"
            href="/admin.php"
        >
            관리자 대시보드
        </a>

        <?php if ($message): ?>

            <p style="color:green;">
                <?= htmlspecialchars($message) ?>
            </p>

        <?php endif; ?>

        <?php if ($error): ?>

            <p style="color:red;">
                <?= htmlspecialchars($error) ?>
            </p>

        <?php endif; ?>

    </section>


    <section class="hero">

        <?php if ($orders->num_rows === 0): ?>

            <p>등록된 주문이 없습니다.</p>

        <?php else: ?>

            <?php while ($order = $orders->fetch_assoc()): ?>

                <div class="product-card">

                    <h3>
                        주문 #<?= (int)$order['id'] ?>
                    </h3>

                    <p>
                        주문자:
                        <?= htmlspecialchars($order['username']) ?>
                    </p>

                    <p>
                        상품 금액:
                        <?= number_format($order['subtotal_amount']) ?>원
                    </p>

                    <?php if ($order['coupon_code']): ?>

                        <p>
                            쿠폰:
                            <?= htmlspecialchars($order['coupon_code']) ?>
                        </p>

                        <p>
                            할인:
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
                        주문시간:
                        <?= htmlspecialchars($order['created_at']) ?>
                    </p>


                    <form method="post">

                        <input
                            type="hidden"
                            name="order_id"
                            value="<?= (int)$order['id'] ?>"
                        >

                        <label>
                            주문 상태:

                            <select name="status">

                                <?php
                                $statuses = [
                                    'pending',
                                    'paid',
                                    'shipped',
                                    'completed',
                                    'cancelled'
                                ];
                                ?>

                                <?php foreach ($statuses as $status): ?>

                                    <option
                                        value="<?= $status ?>"
                                        <?= $order['status'] === $status ? 'selected' : '' ?>
                                    >
                                        <?= $status ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </label>

                        <button
                            class="button"
                            type="submit"
                        >
                            상태 변경
                        </button>

                    </form>

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
