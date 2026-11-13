
<?php
session_start();
require_once __DIR__ . '/config/db.php';

/*
 * VulnShop Vulnerable Lab
 * Vulnerability: SQL Injection
 * Cause: User input concatenated into SQL query
 */

$keyword = $_GET['q'] ?? '';
$products = [];

if ($keyword !== '') {

    // [VULNERABLE] Prepared Statement 미사용
    $sql = "
        SELECT id, name, description, price, stock
        FROM products
        WHERE name LIKE '%$keyword%'
        ORDER BY id ASC
    ";

    $result = $conn->query($sql);

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $products[] = $row;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">
    <title>상품 검색 - VulnShop</title>
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
        <h2>상품 검색</h2>

        <form method="GET" action="/search.php">
            <input
                type="text"
                name="q"
                placeholder="상품 이름 검색"
                value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
            >
            <button class="button" type="submit">검색</button>
        </form>
    </section>

    <section>
        <h2>검색 결과</h2>

        <?php if (empty($products)): ?>
            <p>검색된 상품이 없습니다.</p>
        <?php else: ?>

            <div class="product-grid">
                <?php foreach ($products as $product): ?>
                    <div class="product-card">
                        <h3>
                            <?= htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>

                        <p>
                            <?= htmlspecialchars($product['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>
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
                <?php endforeach; ?>
            </div>

        <?php endif; ?>
    </section>
</main>

<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>
</html>
