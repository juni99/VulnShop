<?php

session_start();

require_once __DIR__ . '/config/db.php';

/*
 * 관리자 권한 확인
 *//*
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
 * 관리자 작업 처리
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';


    /*
     * 상품 등록
     */
    if ($action === 'add') {

        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $image = trim($_POST['image'] ?? '');

        if ($name === '' || $price < 0 || $stock < 0) {

            $error = '상품 정보를 올바르게 입력해주세요.';

        } else {

            $stmt = $conn->prepare("
                INSERT INTO products
                (name, description, price, stock, image)
                VALUES (?, ?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "ssdis",
                $name,
                $description,
                $price,
                $stock,
                $image
            );

            $stmt->execute();

            $productId = $conn->insert_id;

            $auditAction = 'ADMIN_ADD_PRODUCT';
            $target = 'product_id=' . $productId;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $details = 'name=' . $name;

            $log = $conn->prepare("
                INSERT INTO audit_logs
                (user_id, action, target, ip_address, details)
                VALUES (?, ?, ?, ?, ?)
            ");

            $log->bind_param(
                "issss",
                $adminId,
                $auditAction,
                $target,
                $ip,
                $details
            );

            $log->execute();

            $message = '상품이 등록되었습니다.';
        }
    }


    /*
     * 상품 수정
     */
    elseif ($action === 'update') {

        $productId = (int)($_POST['product_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $image = trim($_POST['image'] ?? '');

        if (
            $productId <= 0 ||
            $name === '' ||
            $price < 0 ||
            $stock < 0
        ) {

            $error = '수정할 상품 정보를 확인해주세요.';

        } else {

            $stmt = $conn->prepare("
                UPDATE products
                SET
                    name = ?,
                    description = ?,
                    price = ?,
                    stock = ?,
                    image = ?
                WHERE id = ?
            ");

            $stmt->bind_param(
                "ssdisi",
                $name,
                $description,
                $price,
                $stock,
                $image,
                $productId
            );

            $stmt->execute();

            $auditAction = 'ADMIN_UPDATE_PRODUCT';
            $target = 'product_id=' . $productId;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $details = 'name=' . $name;

            $log = $conn->prepare("
                INSERT INTO audit_logs
                (user_id, action, target, ip_address, details)
                VALUES (?, ?, ?, ?, ?)
            ");

            $log->bind_param(
                "issss",
                $adminId,
                $auditAction,
                $target,
                $ip,
                $details
            );

            $log->execute();

            $message = '상품 정보가 수정되었습니다.';
        }
    }


    /*
     * 상품 삭제
     */
    elseif ($action === 'delete') {

        $productId =
            (int)($_POST['product_id'] ?? 0);

        try {

            $stmt = $conn->prepare("
                DELETE FROM products
                WHERE id = ?
            ");

            $stmt->bind_param(
                "i",
                $productId
            );

            $stmt->execute();

            $auditAction = 'ADMIN_DELETE_PRODUCT';
            $target = 'product_id=' . $productId;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $details = 'deleted';

            $log = $conn->prepare("
                INSERT INTO audit_logs
                (user_id, action, target, ip_address, details)
                VALUES (?, ?, ?, ?, ?)
            ");

            $log->bind_param(
                "issss",
                $adminId,
                $auditAction,
                $target,
                $ip,
                $details
            );

            $log->execute();

            $message = '상품이 삭제되었습니다.';

        } catch (Throwable $e) {

            $error =
                '주문·리뷰 등의 기록이 있는 상품은 삭제할 수 없습니다.';
        }
    }
}


/*
 * 상품 목록
 */
$products = $conn->query("
    SELECT
        id,
        name,
        description,
        price,
        stock,
        image
    FROM products
    ORDER BY id ASC
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

    <title>상품 관리 - VulnShop</title>

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

        <h2>상품 관리</h2>

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

        <h2>새 상품 등록</h2>

        <form method="post">

            <input
                type="hidden"
                name="action"
                value="add"
            >

            <p>
                상품명<br>
                <input
                    type="text"
                    name="name"
                    required
                >
            </p>

            <p>
                설명<br>
                <textarea
                    name="description"
                    rows="4"
                    cols="60"
                ></textarea>
            </p>

            <p>
                가격<br>
                <input
                    type="number"
                    name="price"
                    min="0"
                    step="0.01"
                    required
                >
            </p>

            <p>
                재고<br>
                <input
                    type="number"
                    name="stock"
                    min="0"
                    required
                >
            </p>

            <p>
                이미지 파일명<br>
                <input
                    type="text"
                    name="image"
                    placeholder="product.jpg"
                >
            </p>

            <button
                class="button"
                type="submit"
            >
                상품 등록
            </button>

        </form>

    </section>


    <section class="hero">

        <h2>등록 상품</h2>

        <?php while ($product = $products->fetch_assoc()): ?>

            <div class="product-card">

                <form method="post">

                    <input
                        type="hidden"
                        name="action"
                        value="update"
                    >

                    <input
                        type="hidden"
                        name="product_id"
                        value="<?= (int)$product['id'] ?>"
                    >

                    <h3>
                        상품 #<?= (int)$product['id'] ?>
                    </h3>

                    <p>
                        상품명<br>

                        <input
                            type="text"
                            name="name"
                            value="<?= htmlspecialchars($product['name']) ?>"
                            required
                        >
                    </p>

                    <p>
                        설명<br>

                        <textarea
                            name="description"
                            rows="4"
                            cols="60"
                        ><?= htmlspecialchars($product['description']) ?></textarea>
                    </p>

                    <p>
                        가격<br>

                        <input
                            type="number"
                            name="price"
                            min="0"
                            step="0.01"
                            value="<?= htmlspecialchars($product['price']) ?>"
                            required
                        >
                    </p>

                    <p>
                        재고<br>

                        <input
                            type="number"
                            name="stock"
                            min="0"
                            value="<?= (int)$product['stock'] ?>"
                            required
                        >
                    </p>

                    <p>
                        이미지 파일명<br>

                        <input
                            type="text"
                            name="image"
                            value="<?= htmlspecialchars($product['image'] ?? '') ?>"
                        >
                    </p>

                    <button
                        class="button"
                        type="submit"
                    >
                        수정
                    </button>

                </form>


                <br>


                <form method="post">

                    <input
                        type="hidden"
                        name="action"
                        value="delete"
                    >

                    <input
                        type="hidden"
                        name="product_id"
                        value="<?= (int)$product['id'] ?>"
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

    </section>

</main>


<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>

</html>
