
<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/product-image.php';

/*
 * VulnShop Vulnerable Lab
 * Vulnerability: SQL Injection
 * Cause: User input concatenated into SQL query
 */

$keyword = $_GET['q'] ?? '';
$products = [];


if ($keyword !== '') {

    /*
     * [VULNERABLE LAB]
     * SQL Injection - Product Search
     *
     * 검색어를 SQL 쿼리에 직접 삽입하는
     * 의도적 취약점 실습 코드입니다.
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
    <link rel="stylesheet" href="/css/home.css">
    <link rel="stylesheet" href="/css/products.css">
</head>

<body class="catalog-page">

<?php require __DIR__ . '/config/header.php'; ?>

<main class="container catalog-main">

    <!-- 페이지 경로 -->

    <div class="breadcrumb">
        <a href="/">홈</a>
        <span>/</span>
        <span>상품 검색</span>
    </div>

    <!-- 검색 영역 -->

    <section class="catalog-heading">

        <span class="section-label">
            VULNSHOP SEARCH
        </span>

        <h2>상품 검색</h2>

        <p>
            찾으시는 IT·디지털 제품을 검색해 보세요.
        </p>

        <form class="result-search"
              method="GET"
              action="/search.php">

            <input
                type="search"
                name="q"
                placeholder="상품 이름을 입력해 주세요."
                aria-label="상품 이름 검색"
                value="<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>"
                required
            >

            <button type="submit">
                검색
            </button>

        </form>

    </section>

    <!-- 검색 결과 제목 -->

    <div class="search-result-heading">

        <h2>

            <?php if ($keyword !== ''): ?>

                '<?= htmlspecialchars($keyword, ENT_QUOTES, 'UTF-8') ?>'
                검색 결과

            <?php else: ?>

                상품 검색

            <?php endif; ?>

        </h2>

        <span>
            총 <strong><?= count($products) ?></strong>개 상품
        </span>

    </div>

    <!-- 검색 결과 -->

    <?php if ($keyword === ''): ?>

        <section class="search-empty">

            <span class="search-empty-icon">⌕</span>

            <h3>찾고 싶은 상품을 검색해 보세요.</h3>

            <p>
                노트북, 마우스, 키보드 등 다양한 상품이
                준비되어 있습니다.
            </p>

            <a class="button" href="/products.php">
                전체 상품 보기
            </a>

        </section>

    <?php elseif (empty($products)): ?>

        <section class="search-empty">

            <span class="search-empty-icon">⌕</span>

            <h3>검색된 상품이 없습니다.</h3>

            <p>
                다른 검색어를 입력하거나 전체 상품을
                확인해 주세요.
            </p>

            <a class="button" href="/products.php">
                전체 상품 보기
            </a>

        </section>

    <?php else: ?>

        <section>

            <div class="product-grid">

                <?php foreach ($products as $product): ?>

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
                                재고: <?= (int)$product['stock'] ?>개
                            </p>

                            <a class="button product-button"
                               href="/products.php?id=<?= $productId ?>">

                                상품 상세 보기

                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        </section>

    <?php endif; ?>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>


</html>
