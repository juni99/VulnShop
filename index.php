
<?php
session_start();

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/product-image.php';

$result = $conn->query("
    SELECT id, name, description, price, stock, image
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

    <title>VulnShop | IT·디지털 전문 쇼핑몰</title>

    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">
</head>

<body class="home-page">

<?php require __DIR__ . '/config/header.php'; ?>

<main>

    <!-- 메인 광고 배너 -->
    <section class="main-banner">

        <a href="#featured-products">

            <img
                src="/images/banner/main-banner.webp"
                alt="VulnShop IT 디지털 전문 쇼핑몰"
                width="2172"
                height="724"
                fetchpriority="high"
            >

        </a>

    </section>

    <div class="container">

        <!-- 서비스 안내 -->
        <section class="store-benefits">

            <a href="/products.php" class="benefit-item">
                <span class="benefit-icon">01</span>
                <div>
                    <strong>IT 전문 쇼핑몰</strong>
                    <p>다양한 디지털 제품</p>
                </div>
            </a>

            <a href="/cart.php" class="benefit-item">
                <span class="benefit-icon">02</span>
                <div>
                    <strong>쿠폰 혜택</strong>
                    <p>다양한 할인 혜택</p>
                </div>
            </a>

            <a href="/orders.php" class="benefit-item">
                <span class="benefit-icon">03</span>
                <div>
                    <strong>주문 관리</strong>
                    <p>편리한 주문 내역 확인</p>
                </div>
            </a>

            <a href="/board.php" class="benefit-item">
                <span class="benefit-icon">04</span>
                <div>
                    <strong>고객센터</strong>
                    <p>문의 및 게시판</p>
                </div>
            </a>

        </section>

        <!-- 상품 목록 -->
        <section id="featured-products"
                 class="featured-products">

            <div class="section-heading">

                <div>
                    <span class="section-label">
                        VULNSHOP COLLECTION
                    </span>

                    <h2>추천 상품</h2>

                    <p>
                        당신의 디지털 라이프를 위한
                        다양한 제품을 만나보세요.
                    </p>
                </div>

                <a href="/products.php"
                   class="view-all">
                    전체 상품 보기 →
                </a>

            </div>

            <div class="product-grid">

                <?php while ($product = $result->fetch_assoc()): ?>

                    <?php
                    $productId = (int)$product['id'];

                    $imageUrl = getProductImage(
                        $product['image'] ?? null
                    );
                    ?>

                    <article class="product-card">

                        <a class="product-image"
                           href="/products.php?id=<?= $productId ?>">

                            <?php if ($imageUrl !== null): ?>

                                <img
                                    src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    alt="<?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>"
                                    width="1000"
                                    height="1000"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <span class="image-placeholder">
                                    <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                                </span>

                            <?php endif; ?>

                        </a>

                        <div class="product-info">

                            <span class="product-category">
                                IT · DIGITAL
                            </span>

                            <h3>
                                <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>

                            <p class="product-description">
                                <?= htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                            </p>

                            <p class="price">
                                <?= number_format($product['price']) ?>원
                            </p>

                            <p class="stock-info">
                                재고:
                                <?= (int)$product['stock'] ?>개
                            </p>

                            <a class="button product-button"
                               href="/products.php?id=<?= $productId ?>">

                                상품 상세 보기

                            </a>

                        </div>

                    </article>

                <?php endwhile; ?>

            </div>

        </section>

    </div>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
