<?php

session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/product-image.php';

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
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>상품 관리 - VulnShop</title>

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
        <span>상품 관리</span>
    </div>

    <section class="admin-heading">
        <span class="section-label">
            VULNSHOP ADMINISTRATION
        </span>

        <h2>상품 관리</h2>

        <p>
            상품을 등록하고 판매 가격, 재고 및
            상품 정보를 관리할 수 있습니다.
        </p>
    </section>

    <div class="admin-top-actions">
        <a class="admin-secondary-button"
           href="/admin.php">
            관리자 대시보드로 돌아가기
        </a>
    </div>

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

    <!-- 신규 상품 등록 -->

    <section class="admin-form-panel">

        <div class="admin-panel-heading">
            <div>
                <h3>새 상품 등록</h3>
                <p>새로운 상품의 정보를 입력해 주세요.</p>
            </div>
        </div>

        <form method="post" class="admin-product-form">

            <input type="hidden"
                   name="action"
                   value="add">

            <div class="admin-field">
                <label for="new-name">상품명</label>

                <input
                    id="new-name"
                    type="text"
                    name="name"
                    placeholder="상품명을 입력해 주세요."
                    required
                >
            </div>

            <div class="admin-field">
                <label for="new-description">
                    상품 설명
                </label>

                <textarea
                    id="new-description"
                    name="description"
                    rows="4"
                    placeholder="상품의 특징과 상세 정보를 입력해 주세요."
                ></textarea>
            </div>

            <div class="admin-field-grid">

                <div class="admin-field">
                    <label for="new-price">
                        판매 가격 (원)
                    </label>

                    <input
                        id="new-price"
                        type="number"
                        name="price"
                        min="0"
                        step="0.01"
                        placeholder="0"
                        required
                    >
                </div>

                <div class="admin-field">
                    <label for="new-stock">
                        재고 수량
                    </label>

                    <input
                        id="new-stock"
                        type="number"
                        name="stock"
                        min="0"
                        placeholder="0"
                        required
                    >
                </div>

            </div>

            <div class="admin-field">
                <label for="new-image">
                    이미지 파일명
                </label>

                <input
                    id="new-image"
                    type="text"
                    name="image"
                    placeholder="example.webp"
                >

                <p class="admin-field-help">
                    images/products/ 폴더에 있는
                    WebP 파일명을 입력해 주세요.
                </p>
            </div>

            <div class="admin-form-actions">
                <button class="admin-primary-button"
                        type="submit">
                    상품 등록
                </button>
            </div>

        </form>

    </section>

    <!-- 등록된 상품 -->

    <div class="admin-section-heading">
        <h3>등록 상품</h3>

        <span>
            총 <?= (int)$products->num_rows ?>개
        </span>
    </div>

    <?php if ($products->num_rows === 0): ?>

        <section class="admin-empty">
            <h3>등록된 상품이 없습니다.</h3>
            <p>상단에서 첫 상품을 등록해 주세요.</p>
        </section>

    <?php else: ?>

        <section class="admin-products-list">

            <?php while ($product = $products->fetch_assoc()): ?>

                <?php
                $productId = (int)$product['id'];

                $imageUrl = getProductImage(
                    $product['image'] ?? null
                );
                ?>

                <article class="admin-product-card">

                    <div class="admin-product-card-heading">

                        <div class="admin-product-preview">

                            <div class="admin-product-image">

                                <?php if ($imageUrl !== null): ?>

                                    <img
                                        src="<?= htmlspecialchars(
                                            $imageUrl,
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        alt="<?= htmlspecialchars(
                                            $product['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>"
                                        width="1000"
                                        height="1000"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <span>이미지 없음</span>

                                <?php endif; ?>

                            </div>

                            <div class="admin-product-summary">

                                <span class="admin-product-id">
                                    상품 #<?= $productId ?>
                                </span>

                                <h4>
                                    <?= htmlspecialchars(
                                        $product['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </h4>

                                <p>
                                    판매가
                                    <?= number_format(
                                        (float)$product['price']
                                    ) ?>원
                                </p>

                                <p>
                                    재고
                                    <?= (int)$product['stock'] ?>개
                                </p>

                            </div>

                        </div>

                        <a class="admin-view-button"
                           href="/products.php?id=<?= $productId ?>">
                            상품 보기
                        </a>

                    </div>

                    <!-- 상품 수정 -->

                    <form method="post"
                          class="admin-product-form admin-edit-form">

                        <input
                            type="hidden"
                            name="action"
                            value="update"
                        >

                        <input
                            type="hidden"
                            name="product_id"
                            value="<?= $productId ?>"
                        >

                        <div class="admin-field">
                            <label for="name-<?= $productId ?>">
                                상품명
                            </label>

                            <input
                                id="name-<?= $productId ?>"
                                type="text"
                                name="name"
                                value="<?= htmlspecialchars(
                                    $product['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                required
                            >
                        </div>

                        <div class="admin-field">
                            <label for="description-<?= $productId ?>">
                                상품 설명
                            </label>

                            <textarea
                                id="description-<?= $productId ?>"
                                name="description"
                                rows="3"
                            ><?= htmlspecialchars(
                                $product['description'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?></textarea>
                        </div>

                        <div class="admin-field-grid">

                            <div class="admin-field">
                                <label for="price-<?= $productId ?>">
                                    판매 가격 (원)
                                </label>

                                <input
                                    id="price-<?= $productId ?>"
                                    type="number"
                                    name="price"
                                    min="0"
                                    step="0.01"
                                    value="<?= htmlspecialchars(
                                        (string)$product['price'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    required
                                >
                            </div>

                            <div class="admin-field">
                                <label for="stock-<?= $productId ?>">
                                    재고 수량
                                </label>

                                <input
                                    id="stock-<?= $productId ?>"
                                    type="number"
                                    name="stock"
                                    min="0"
                                    value="<?= (int)$product['stock'] ?>"
                                    required
                                >
                            </div>

                        </div>

                        <div class="admin-field">
                            <label for="image-<?= $productId ?>">
                                이미지 파일명
                            </label>

                            <input
                                id="image-<?= $productId ?>"
                                type="text"
                                name="image"
                                value="<?= htmlspecialchars(
                                    $product['image'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                placeholder="example.webp"
                            >
                        </div>

                        <div class="admin-form-actions">
                            <button
                                type="submit"
                                class="admin-primary-button">
                                상품 수정
                            </button>
                        </div>

                    </form>

                    <!-- 상품 삭제 -->

                    <form method="post"
                          class="admin-delete-form">

                        <input
                            type="hidden"
                            name="action"
                            value="delete"
                        >

                        <input
                            type="hidden"
                            name="product_id"
                            value="<?= $productId ?>"
                        >

                        <button
                            class="admin-danger-button"
                            type="submit">
                            상품 삭제
                        </button>

                    </form>

                </article>

            <?php endwhile; ?>

        </section>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
