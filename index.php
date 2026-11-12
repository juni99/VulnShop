<?php
session_start();

require_once __DIR__ . '/config/db.php';

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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VulnShop</title>
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>

<header>
    <div class="container header-inner">
        <h1><a href="/">VulnShop</a></h1>
<?php require __DIR__ . '/config/nav.php'; ?>

    </div>
</header>

<main class="container">

    <section class="hero">
        <h2>VulnShop에 오신 것을 환영합니다.</h2>
        <p>웹 취약점 진단 및 보안 실습을 위한 테스트 쇼핑몰입니다.</p>
    </section>

    <section>
        <h2>상품 목록</h2>

        <div class="product-grid">

        <?php while ($product = $result->fetch_assoc()): ?>

            <div class="product-card">
                <div class="product-image">
                    <?= htmlspecialchars($product['name']) ?>
                </div>

                <h3><?= htmlspecialchars($product['name']) ?></h3>

                <p>
                    <?= htmlspecialchars($product['description']) ?>
                </p>

                <p class="price">
                    <?= number_format($product['price']) ?>원
                </p>

                <p>
                    재고: <?= (int)$product['stock'] ?>개
                </p>

                <a class="button"
                   href="/products.php?id=<?= (int)$product['id'] ?>">
                    상품 보기
                </a>
            </div>

        <?php endwhile; ?>

        </div>
    </section>

</main>

<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>
</html>
