<?php

session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/order-status.php';
/*
if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    $_SESSION['role'] !== 'admin'
) {
    http_response_code(403);
    die('관리자만 접근할 수 있습니다.');
}*/

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
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>주문 관리 - VulnShop</title>

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
        <a href="/admin.php">관리자</a>
        <span>/</span>
        <span>주문 관리</span>
    </div>

    <section class="admin-heading">

        <span class="section-label">
            VULNSHOP ADMINISTRATION
        </span>

        <h2>주문 관리</h2>

        <p>
            전체 주문 정보를 확인하고
            주문 처리 상태를 관리할 수 있습니다.
        </p>

    </section>

    <div class="admin-top-actions">

        <a class="admin-secondary-button"
           href="/admin.php">

            관리자 대시보드로 돌아가기

        </a>

    </div>

    <!-- 처리 결과 -->

    <?php if ($message): ?>

        <div class="admin-alert admin-alert-success"
             role="status">

            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>

    <?php if ($error): ?>

        <div class="admin-alert admin-alert-error"
             role="alert">

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>

    <!-- 주문 목록 -->

    <div class="admin-section-heading">

        <h3>전체 주문 목록</h3>

        <span>
            총 <?= (int)$orders->num_rows ?>건
        </span>

    </div>

    <?php if ($orders->num_rows === 0): ?>

        <section class="admin-empty">

            <h3>등록된 주문이 없습니다.</h3>

            <p>
                고객의 주문이 등록되면
                이곳에서 확인할 수 있습니다.
            </p>

        </section>

    <?php else: ?>

        <section class="admin-order-list">

            <?php while ($order = $orders->fetch_assoc()): ?>

                <?php
                $orderId = (int)$order['id'];

                $statuses = [
                    'pending',
                    'paid',
                    'shipped',
                    'completed',
                    'cancelled'
                ];
                ?>

                <article class="admin-order-card">

                    <!-- 주문 기본 정보 -->

                    <div class="admin-order-card-heading">

                        <div>

                            <span class="admin-order-date">

                                <?= htmlspecialchars(
                                    $order['created_at'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                            <h3>
                                주문번호
                                #<?= sprintf('%06d', $orderId) ?>
                            </h3>

                            <p>
                                주문자:
                                <?= htmlspecialchars(
                                    $order['username'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </p>

                        </div>

                        <span class="order-status <?= getOrderStatusClass($order['status']) ?>">

                            <?= getOrderStatusLabel(
                                $order['status']
                            ) ?>

                        </span>

                    </div>

                    <div class="admin-order-card-body">

                        <!-- 주문 금액 -->

                        <div class="admin-order-payment">

                            <h4>주문 정보</h4>

                            <div class="admin-order-line">

                                <span>상품 금액</span>

                                <strong>
                                    <?= number_format(
                                        $order['subtotal_amount']
                                    ) ?>원
                                </strong>

                            </div>

                            <?php if ($order['coupon_code']): ?>

                                <div class="admin-order-line">

                                    <span>적용 쿠폰</span>

                                    <strong class="order-coupon">

                                        <?= htmlspecialchars(
                                            $order['coupon_code'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </strong>

                                </div>

                            <?php endif; ?>

                            <div class="admin-order-line">

                                <span>할인 금액</span>

                                <strong class="order-discount">

                                    -<?= number_format(
                                        $order['discount_amount']
                                    ) ?>원

                                </strong>

                            </div>

                            <div class="admin-order-total">

                                <span>최종 주문 금액</span>

                                <strong>
                                    <?= number_format(
                                        $order['total_amount']
                                    ) ?>원
                                </strong>

                            </div>

                        </div>

                        <!-- 상태 변경 -->

                        <div class="admin-order-management">

                            <h4>주문 상태 관리</h4>

                            <p>
                                변경할 주문 상태를 선택한 후
                                저장해 주세요.
                            </p>

                            <form method="post"
                                  class="admin-order-status-form">

                                <input
                                    type="hidden"
                                    name="order_id"
                                    value="<?= $orderId ?>"
                                >

                                <label for="status-<?= $orderId ?>">
                                    주문 상태
                                </label>

                                <select
                                    id="status-<?= $orderId ?>"
                                    name="status"
                                    required
                                >

                                    <?php foreach ($statuses as $status): ?>

                                        <option
                                            value="<?= htmlspecialchars(
                                                $status,
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            <?= $order['status'] === $status
                                                ? 'selected'
                                                : '' ?>
                                        >

                                            <?= getOrderStatusLabel($status) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </select>

                                <button
                                    class="admin-primary-button"
                                    type="submit"
                                >

                                    상태 변경 저장

                                </button>

                            </form>

                        </div>

                    </div>

                </article>

            <?php endwhile; ?>

        </section>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
