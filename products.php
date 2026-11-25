<?php

session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/product-image.php';

$productId = $_GET['id'] ?? 0;
$message = '';
$error = '';

/*
 * 리뷰 등록
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_id'])) {

        header('Location: /login.php');
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'review') {

        $productId = (int)($_POST['product_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if ($rating < 1 || $rating > 5) {

            $error = '평점은 1점부터 5점까지 선택해주세요.';

        } elseif ($content === '') {

            $error = '리뷰 내용을 입력해주세요.';

        } else {

            $userId = (int)$_SESSION['user_id'];

            $stmt = $conn->prepare("
                INSERT INTO reviews
                (user_id, product_id, rating, content)
                VALUES (?, ?, ?, ?)
            ");

            $stmt->bind_param(
                "iiis",
                $userId,
                $productId,
                $rating,
                $content
            );

            $stmt->execute();

            /*
             * 감사 로그 기록
             */
            $auditAction = 'CREATE_REVIEW';
            $target = 'product_id=' . $productId;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $details = 'rating=' . $rating;

            $log = $conn->prepare("
                INSERT INTO audit_logs
                (user_id, action, target, ip_address, details)
                VALUES (?, ?, ?, ?, ?)
            ");

            $log->bind_param(
                "issss",
                $userId,
                $auditAction,
                $target,
                $ip,
                $details
            );

            $log->execute();

            $message = '리뷰가 등록되었습니다.';
        }
    }
}


/*
 * 상품 목록 페이지
 */
if ($productId <= 0) {

    $result = $conn->query("
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

} else {

    /*
     * 상품 상세 조회
     */
    $sql = "
    SELECT
        id,
        name,
        description,
        price,
        stock,
        image
    FROM products
    WHERE id = $productId
    LIMIT 1
";

$result = $conn->query($sql);
$product = $result ? $result->fetch_assoc() : null;

    if (!$product) {
        http_response_code(404);
    }

    /*
     * 리뷰 조회
     */
    if ($product) {

        $stmt = $conn->prepare("
            SELECT
                r.rating,
                r.content,
                r.created_at,
                u.username
            FROM reviews r
            JOIN users u
                ON r.user_id = u.id
            WHERE r.product_id = ?
            ORDER BY r.id DESC
        ");

        $stmt->bind_param("i", $productId);
        $stmt->execute();

        $reviews = $stmt->get_result();
    }
}

?>


<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>상품 - VulnShop</title>

    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">
    <link rel="stylesheet" href="/css/products.css">
</head>

<body class="catalog-page">

<?php require __DIR__ . '/config/header.php'; ?>

<main class="container catalog-main">

    <div class="breadcrumb">
        <a href="/">홈</a>
        <span>/</span>
        <a href="/products.php">전체 상품</a>

        <?php if (!empty($product)): ?>
            <span>/</span>
            <span>
                <?= htmlspecialchars($product['name']) ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if ($productId <= 0): ?>

        <!-- 전체 상품 목록 -->

        <section class="catalog-heading">

            <span class="section-label">
                VULNSHOP COLLECTION
            </span>

            <h2>전체 상품</h2>

            <p>
                당신의 디지털 라이프를 위한
                다양한 제품을 만나보세요.
            </p>

        </section>

        <section>

            <div class="product-grid">

                <?php while ($row = $result->fetch_assoc()): ?>

                    <?php
                    $imageUrl = getProductImage(
                        $row['image'] ?? null
                    );
                    ?>

                    <article class="product-card">

                        <a class="product-image"
                           href="/products.php?id=<?= (int)$row['id'] ?>">

                            <?php if ($imageUrl !== null): ?>

                                <img
                                    src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    width="1000"
                                    height="1000"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <span class="image-placeholder">
                                    <?= htmlspecialchars($row['name']) ?>
                                </span>

                            <?php endif; ?>

                        </a>

                        <div class="product-info">

                            <span class="product-category">
                                IT · DIGITAL
                            </span>

                            <h3>
                                <?= htmlspecialchars($row['name']) ?>
                            </h3>

                            <p class="product-description">
                                <?= htmlspecialchars($row['description'] ?? '') ?>
                            </p>

                            <p class="price">
                                <?= number_format($row['price']) ?>원
                            </p>

                            <p class="stock-info">
                                재고: <?= (int)$row['stock'] ?>개
                            </p>

                            <a class="button product-button"
                               href="/products.php?id=<?= (int)$row['id'] ?>">
                                상품 상세 보기
                            </a>

                        </div>

                    </article>

                <?php endwhile; ?>

            </div>

        </section>

    <?php elseif (!$product): ?>

        <!-- 상품이 존재하지 않는 경우 -->

        <section class="empty-state">

            <h2>상품을 찾을 수 없습니다.</h2>

            <p>존재하지 않는 상품입니다.</p>

            <a class="button" href="/products.php">
                상품 목록으로 돌아가기
            </a>

        </section>

    <?php else: ?>

        <!-- 상품 상세 페이지 -->

        <?php
        $imageUrl = getProductImage(
            $product['image'] ?? null
        );

        $stock = (int)$product['stock'];
        ?>

        <section class="product-detail-layout">

            <div class="detail-image">

                <?php if ($imageUrl !== null): ?>

                    <img
                        src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                        alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                        width="1000"
                        height="1000"
                    >

                <?php else: ?>

                    <span class="image-placeholder">
                        <?= htmlspecialchars($product['name']) ?>
                    </span>

                <?php endif; ?>

            </div>

            <div class="detail-info">

                <span class="section-label">
                    VULNSHOP COLLECTION
                </span>

                <h2>
                    <?= htmlspecialchars($product['name']) ?>
                </h2>

                <p class="detail-description">
                    <?= htmlspecialchars($product['description'] ?? '') ?>
                </p>

                <div class="detail-price">
                    <?= number_format($product['price']) ?>원
                </div>

                <div class="detail-stock">

                    <span>판매 상태</span>

                    <?php if ($stock > 0): ?>

                        <span class="stock-available">
                            구매 가능 · 재고 <?= $stock ?>개
                        </span>

                    <?php else: ?>

                        <span class="stock-unavailable">
                            품절
                        </span>

                    <?php endif; ?>

                </div>

                <form class="purchase-form"
                      method="post"
                      action="/cart.php">

                    <input type="hidden"
                           name="action"
                           value="add">

                    <input type="hidden"
                           name="product_id"
                           value="<?= (int)$product['id'] ?>">

                    <div class="quantity-control">

                        <label for="quantity">
                            구매 수량
                        </label>

                        <input
                            type="number"
                            id="quantity"
                            name="quantity"
                            value="1"
                            min="1"
                            max="<?= max(1, $stock) ?>"
                            required
                            <?= $stock <= 0 ? 'disabled' : '' ?>
                        >

                    </div>

                    <button
                        class="add-to-cart"
                        type="submit"
                        <?= $stock <= 0 ? 'disabled' : '' ?>
                    >
                        <?= $stock > 0
                            ? '장바구니 담기'
                            : '품절된 상품입니다.' ?>
                    </button>

                </form>

            </div>

        </section>

        <!-- 상세 정보 이동 메뉴 -->

        <div class="detail-tabs">

            <a href="#product-description">
                상품 상세 정보
            </a>

            <a href="#product-reviews">
                상품 리뷰
            </a>

        </div>

        <!-- 상품 설명 -->

        <section class="detail-section"
                 id="product-description">

            <h2>상품 상세 정보</h2>

            <p>
                <?= nl2br(
                    htmlspecialchars(
                        $product['description'] ?? ''
                    )
                ) ?>
            </p>

        </section>

        <!-- 리뷰 작성 -->

        <section class="detail-section"
                 id="product-reviews">

            <h2>상품 리뷰 작성</h2>

            <?php if ($message): ?>

                <p style="color: #15803d;">
                    <?= htmlspecialchars($message) ?>
                </p>

            <?php endif; ?>

            <?php if ($error): ?>

                <p style="color: #dc2626;">
                    <?= htmlspecialchars($error) ?>
                </p>

            <?php endif; ?>

            <?php if (isset($_SESSION['user_id'])): ?>

                <form method="post">

                    <input type="hidden"
                           name="action"
                           value="review">

                    <input type="hidden"
                           name="product_id"
                           value="<?= (int)$product['id'] ?>">

                    <p>
                        <label>
                            평점

                            <select name="rating" required>
                                <option value="5">★★★★★ 5점</option>
                                <option value="4">★★★★☆ 4점</option>
                                <option value="3">★★★☆☆ 3점</option>
                                <option value="2">★★☆☆☆ 2점</option>
                                <option value="1">★☆☆☆☆ 1점</option>
                            </select>

                        </label>
                    </p>

                    <p>
                        <label>

                            리뷰 내용

                            <textarea
                                name="content"
                                rows="5"
                                placeholder="상품에 대한 의견을 작성해 주세요."
                                required
                            ></textarea>

                        </label>
                    </p>

                    <button class="button"
                            type="submit">
                        리뷰 등록
                    </button>

                </form>

            <?php else: ?>

                <p>
                    리뷰를 작성하려면 로그인이 필요합니다.
                </p>

                <a class="button" href="/login.php">
                    로그인
                </a>

            <?php endif; ?>

        </section>

        <!-- 등록된 리뷰 목록 -->

        <section class="detail-section">

            <h2>등록된 리뷰</h2>

            <?php if ($reviews->num_rows === 0): ?>

                <div class="empty-state">
                    아직 등록된 리뷰가 없습니다.
                </div>

            <?php else: ?>

                <?php while ($review = $reviews->fetch_assoc()): ?>

                    <?php
                    $rating = max(
                        0,
                        min(5, (int)$review['rating'])
                    );
                    ?>

                    <article class="review-card">

                        <strong>
                            <?= htmlspecialchars($review['username']) ?>
                        </strong>

                        <p class="review-rating">
                            <?= str_repeat('★', $rating) ?>
                            <?= str_repeat('☆', 5 - $rating) ?>
                        </p>

                        <p>
                            <?= nl2br(
                                htmlspecialchars(
                                    $review['content']
                                )
                            ) ?>
                        </p>

                        <small>
                            <?= htmlspecialchars($review['created_at']) ?>
                        </small>

                    </article>

                <?php endwhile; ?>

            <?php endif; ?>

        </section>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>


</html>
