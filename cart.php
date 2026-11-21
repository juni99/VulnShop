<?php

session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/product-image.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];
$message = '';

/*
 * 사용자 장바구니 조회
 */
$stmt = $conn->prepare("
    SELECT id
    FROM carts
    WHERE user_id = ?
    ORDER BY id ASC
    LIMIT 1
");

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$cart = $result->fetch_assoc();

if (!$cart) {

    $stmt = $conn->prepare("
        INSERT INTO carts (user_id)
        VALUES (?)
    ");

    $stmt->bind_param("i", $userId);
    $stmt->execute();

    $cartId = $conn->insert_id;

} else {

    $cartId = (int)$cart['id'];
}


/*
 * POST 요청 처리
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? 'add';

if ($action === 'apply_coupon') {

    $couponCode = strtoupper(
        trim($_POST['coupon_code'] ?? '')
    );

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

        $_SESSION['coupon_code'] =
            $coupon['code'];

        $message =
            $coupon['code'] .
            ' 쿠폰이 적용되었습니다.';

    } else {

        unset($_SESSION['coupon_code']);

        $message =
            '유효하지 않은 쿠폰입니다.';
    }
}

    /*
     * 상품 추가
     */
    elseif ($action === 'add') {

        $productId = (int)($_POST['product_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);

        if ($quantity < 1) {
            $quantity = 1;
        }

        $stmt = $conn->prepare("
            SELECT id, stock
            FROM products
            WHERE id = ?
            LIMIT 1
        ");

        $stmt->bind_param("i", $productId);
        $stmt->execute();

        $productResult = $stmt->get_result();
        $product = $productResult->fetch_assoc();

        if (!$product) {

            $message = '존재하지 않는 상품입니다.';

        } elseif ($quantity > $product['stock']) {

            $message = '재고보다 많은 수량을 담을 수 없습니다.';

        } else {

            $stmt = $conn->prepare("
                SELECT id, quantity
                FROM cart_items
                WHERE cart_id = ?
                AND product_id = ?
                LIMIT 1
            ");

            $stmt->bind_param("ii", $cartId, $productId);
            $stmt->execute();

            $itemResult = $stmt->get_result();
            $existingItem = $itemResult->fetch_assoc();

            if ($existingItem) {

                $newQuantity =
                    (int)$existingItem['quantity'] + $quantity;

                if ($newQuantity > $product['stock']) {
                    $newQuantity = (int)$product['stock'];
                }

                $stmt = $conn->prepare("
                    UPDATE cart_items
                    SET quantity = ?
                    WHERE id = ?
                    AND cart_id = ?
                ");

                $stmt->bind_param(
                    "iii",
                    $newQuantity,
                    $existingItem['id'],
                    $cartId
                );

                $stmt->execute();

            } else {

                $stmt = $conn->prepare("
                    INSERT INTO cart_items
                    (cart_id, product_id, quantity)
                    VALUES (?, ?, ?)
                ");

                $stmt->bind_param(
                    "iii",
                    $cartId,
                    $productId,
                    $quantity
                );

                $stmt->execute();
            }

            $message = '상품을 장바구니에 담았습니다.';
        }
    }


    /*
     * 수량 변경
     */
    elseif ($action === 'update') {

        $itemId = (int)($_POST['item_id'] ?? 0);
        $quantity = (int)($_POST['quantity'] ?? 1);

        if ($quantity < 1) {
            $quantity = 1;
        }

        $stmt = $conn->prepare("
            SELECT ci.id, p.stock
            FROM cart_items ci
            JOIN products p
                ON ci.product_id = p.id
            WHERE ci.id = ?
            AND ci.cart_id = ?
            LIMIT 1
        ");

        $stmt->bind_param("ii", $itemId, $cartId);
        $stmt->execute();

        $item = $stmt->get_result()->fetch_assoc();

        if ($item) {

            if ($quantity > $item['stock']) {
                $quantity = (int)$item['stock'];
            }

            $stmt = $conn->prepare("
                UPDATE cart_items
                SET quantity = ?
                WHERE id = ?
                AND cart_id = ?
            ");

            $stmt->bind_param(
                "iii",
                $quantity,
                $itemId,
                $cartId
            );

            $stmt->execute();

            $message = '수량을 변경했습니다.';
        }
    }


    /*
     * 상품 삭제
     */
    elseif ($action === 'delete') {

        $itemId = (int)($_POST['item_id'] ?? 0);

        $stmt = $conn->prepare("
            DELETE FROM cart_items
            WHERE id = ?
            AND cart_id = ?
        ");

        $stmt->bind_param(
            "ii",
            $itemId,
            $cartId
        );

        $stmt->execute();

        $message = '상품을 장바구니에서 삭제했습니다.';
    }
}


/*
 * 장바구니 상품 조회
 */
$stmt = $conn->prepare("
    SELECT
        ci.id,
        ci.quantity,
        p.id AS product_id,
        p.name,
        p.price,
        p.stock,
        p.image
    FROM cart_items ci
    JOIN products p
        ON ci.product_id = p.id
    WHERE ci.cart_id = ?
    ORDER BY ci.id ASC
");

$stmt->bind_param("i", $cartId);
$stmt->execute();

$items = $stmt->get_result();

$total = 0;

$discountPercent = 0;
$discountAmount = 0;
$finalTotal = 0;

if (isset($_SESSION['coupon_code'])) {

    $stmt = $conn->prepare("
        SELECT discount_percent
        FROM coupons
        WHERE code = ?
        AND active = TRUE
        LIMIT 1
    ");

    $stmt->bind_param(
        "s",
        $_SESSION['coupon_code']
    );

    $stmt->execute();

    $couponData = $stmt
        ->get_result()
        ->fetch_assoc();

    if ($couponData) {

        $discountPercent =
            (int)$couponData['discount_percent'];

    } else {

        unset($_SESSION['coupon_code']);
    }
}

?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>장바구니 - VulnShop</title>

    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">
    <link rel="stylesheet" href="/css/products.css">
    <link rel="stylesheet" href="/css/cart.css">
</head>

<body class="cart-page">

<?php require __DIR__ . '/config/header.php'; ?>

<main class="container cart-main">

    <!-- 페이지 경로 -->

    <div class="breadcrumb">
        <a href="/">홈</a>
        <span>/</span>
        <span>장바구니</span>
    </div>

    <!-- 페이지 제목 -->

    <section class="cart-heading">

        <span class="section-label">
            VULNSHOP SHOPPING
        </span>

        <h2>장바구니</h2>

        <p>
            장바구니에 담은 상품을 확인하고
            주문을 진행해 주세요.
        </p>

    </section>

    <!-- 주문 진행 단계 -->

    <div class="checkout-steps">

        <div class="checkout-step active">
            <span>01</span>
            장바구니
        </div>

        <span class="step-arrow">›</span>

        <div class="checkout-step">
            <span>02</span>
            주문
        </div>

        <span class="step-arrow">›</span>

        <div class="checkout-step">
            <span>03</span>
            주문 완료
        </div>

    </div>

    <!-- 처리 결과 메시지 -->

    <?php if ($message): ?>

        <div class="cart-message" role="status">
            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>

    <!-- 장바구니가 비어 있는 경우 -->

    <?php if ($items->num_rows === 0): ?>

        <section class="cart-empty">

            <span class="empty-cart-icon">
                &#128722;
            </span>

            <h3>장바구니가 비어 있습니다.</h3>

            <p>
                VulnShop의 다양한 IT·디지털 제품을
                만나보세요.
            </p>

            <a class="button" href="/products.php">
                쇼핑 계속하기
            </a>

        </section>

    <?php else: ?>

        <div class="cart-layout">

            <!-- 왼쪽 상품 영역 -->

            <div class="cart-content">

                <section class="cart-panel">

                    <div class="cart-panel-heading">

                        <h3>장바구니 상품</h3>

                        <span>
                            총 <?= (int)$items->num_rows ?>종
                        </span>

                    </div>

                    <?php while ($item = $items->fetch_assoc()): ?>

                        <?php

                        $subtotal =
                            $item['price'] *
                            $item['quantity'];

                        $total += $subtotal;

                        $imageUrl = getProductImage(
                            $item['image'] ?? null
                        );

                        $productId =
                            (int)$item['product_id'];

                        ?>

                        <article class="cart-item">

                            <!-- 상품 이미지 -->

                            <a class="cart-item-image"
                               href="/products.php?id=<?= $productId ?>">

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

                            </a>

                            <!-- 상품 정보 -->

                            <div class="cart-item-info">

                                <span class="product-category">
                                    IT · DIGITAL
                                </span>

                                <h3>
                                    <a href="/products.php?id=<?= $productId ?>">

                                        <?= htmlspecialchars($item['name']) ?>

                                    </a>
                                </h3>

                                <p class="cart-unit-price">
                                    단가:
                                    <?= number_format($item['price']) ?>원
                                </p>

                                <!-- 수량 변경 -->

                                <form method="post"
                                      class="cart-quantity-form">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="update"
                                    >

                                    <input
                                        type="hidden"
                                        name="item_id"
                                        value="<?= (int)$item['id'] ?>"
                                    >

                                    <label>
                                        수량

                                        <input
                                            type="number"
                                            name="quantity"
                                            min="1"
                                            max="<?= (int)$item['stock'] ?>"
                                            value="<?= (int)$item['quantity'] ?>"
                                            required
                                        >

                                    </label>

                                    <button
                                        class="cart-outline-button"
                                        type="submit"
                                    >
                                        변경
                                    </button>

                                </form>

                                <p class="cart-stock">
                                    구매 가능 재고:
                                    <?= (int)$item['stock'] ?>개
                                </p>

                            </div>

                            <!-- 상품 합계 및 삭제 -->

                            <div class="cart-item-total">

                                <strong>
                                    <?= number_format($subtotal) ?>원
                                </strong>

                                <form method="post">

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="delete"
                                    >

                                    <input
                                        type="hidden"
                                        name="item_id"
                                        value="<?= (int)$item['id'] ?>"
                                    >

                                    <button
                                        class="cart-delete-button"
                                        type="submit"
                                    >
                                        삭제
                                    </button>

                                </form>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </section>

                <!-- 할인 쿠폰 -->

                <section class="cart-panel coupon-panel">

                    <h3>할인 쿠폰</h3>

                    <p>
                        보유하고 있는 쿠폰 코드를
                        입력해 주세요.
                    </p>

                    <form method="post"
                          class="coupon-form">

                        <input
                            type="hidden"
                            name="action"
                            value="apply_coupon"
                        >

                        <input
                            type="text"
                            name="coupon_code"
                            placeholder="쿠폰 코드 입력 (WELCOME10)"
                            aria-label="쿠폰 코드"
                            required
                        >

                        <button
                            class="button"
                            type="submit"
                        >
                            쿠폰 적용
                        </button>

                    </form>

                    <?php if ($discountPercent > 0): ?>

                        <div class="coupon-applied">

                            <strong>
                                할인 쿠폰이 적용되었습니다.
                            </strong>

                            <p>
                                <?= htmlspecialchars(
                                    $_SESSION['coupon_code'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                ·

                                <?= htmlspecialchars(
                                    (string)$discountPercent
                                ) ?>% 할인
                            </p>

                        </div>

                    <?php endif; ?>

                </section>

            </div>

            <?php

            $discountAmount =
                $total * ($discountPercent / 100);

            $finalTotal =
                $total - $discountAmount;

            ?>

            <!-- 오른쪽 결제 금액 영역 -->

            <aside class="cart-summary">

                <h3>주문 금액</h3>

                <div class="summary-line">

                    <span>총 상품 금액</span>

                    <strong>
                        <?= number_format($total) ?>원
                    </strong>

                </div>

                <div class="summary-line">

                    <span>쿠폰 할인</span>

                    <strong class="discount-price">
                        -<?= number_format($discountAmount) ?>원
                    </strong>

                </div>

                <?php if ($discountPercent > 0): ?>

                    <div class="summary-coupon">

                        적용 할인율:
                        <?= htmlspecialchars(
                            (string)$discountPercent
                        ) ?>%

                    </div>

                <?php endif; ?>

                <div class="summary-divider"></div>

                <div class="summary-final">

                    <span>최종 주문 금액</span>

                    <strong>
                        <?= number_format($finalTotal) ?>원
                    </strong>

                </div>

                <form method="post"
                      action="/order.php">

                    <button
                        class="checkout-button"
                        type="submit"
                    >
                        주문하기
                    </button>

                </form>

                <a class="continue-shopping"
                   href="/products.php">

                    쇼핑 계속하기

                </a>

            </aside>

        </div>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
