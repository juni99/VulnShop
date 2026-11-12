<?php

session_start();

require_once __DIR__ . '/config/db.php';

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
        p.stock
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>장바구니 - VulnShop</title>

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

        <h2>장바구니</h2>

        <?php if ($message): ?>

            <p>
                <?= htmlspecialchars($message) ?>
            </p>

        <?php endif; ?>


        <?php if ($items->num_rows === 0): ?>

            <p>장바구니가 비어 있습니다.</p>

        <?php else: ?>

            <?php while ($item = $items->fetch_assoc()): ?>

                <?php

                $subtotal =
                    $item['price'] * $item['quantity'];

                $total += $subtotal;

                ?>

                <div class="product-card">

                    <h3>
                        <?= htmlspecialchars($item['name']) ?>
                    </h3>

                    <p>
                        가격:
                        <?= number_format($item['price']) ?>원
                    </p>


                    <form method="post">

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
                            수량:

                            <input
                                type="number"
                                name="quantity"
                                min="1"
                                max="<?= (int)$item['stock'] ?>"
                                value="<?= (int)$item['quantity'] ?>"
                            >

                        </label>

                        <button
                            class="button"
                            type="submit"
                        >
                            변경
                        </button>

                    </form>


                    <p>
                        합계:
                        <?= number_format($subtotal) ?>원
                    </p>


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
                            class="button"
                            type="submit"
                        >
                            삭제
                        </button>

                    </form>

                </div>

                <br>

            <?php endwhile; ?>


            <?php

$discountAmount =
    $total * ($discountPercent / 100);

$finalTotal =
    $total - $discountAmount;

?>

<h3>
    상품 금액:
    <?= number_format($total) ?>원
</h3>

<?php if ($discountPercent > 0): ?>

    <p>
        적용 쿠폰:
        <?= htmlspecialchars($_SESSION['coupon_code']) ?>
    </p>

    <p>
        할인:
        <?= $discountPercent ?>%
        (-<?= number_format($discountAmount) ?>원)
    </p>

<?php endif; ?>

<h3>
    최종 결제 금액:
    <?= number_format($finalTotal) ?>원
</h3>

<form method="post">

    <input
        type="hidden"
        name="action"
        value="apply_coupon"
    >

    <label>
        쿠폰 코드:

        <input
            type="text"
            name="coupon_code"
            placeholder="WELCOME10"
        >
    </label>

    <button
        class="button"
        type="submit"
    >
        쿠폰 적용
    </button>

</form>

<br>

<form method="post" action="/order.php">

    <button
        class="button"
        type="submit"
    >
        주문하기
    </button>

</form>

        <?php endif; ?>

    </section>

</main>


<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>
</html>
