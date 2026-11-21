
<div class="top-bar">
    <div class="container top-bar-inner">

        <span>VulnShop 공식 온라인 스토어</span>

        <div>
            <a href="/board.php">고객센터</a>
            <span class="top-divider">|</span>
            <a href="/notices.php">공지사항</a>
        </div>

    </div>
</div>

<header class="store-header">

    <div class="container store-header-inner">

        <h1 class="store-logo">
            <a href="/">
                <span>Vuln</span>Shop
            </a>
        </h1>

        <form class="store-search"
              action="/search.php"
              method="GET">

            <input
                type="search"
                name="q"
                placeholder="찾으시는 상품을 검색해 보세요."
                aria-label="상품 검색"
                required
            >

            <button type="submit">검색</button>

        </form>

    </div>

    <div class="store-navigation">

        <div class="container navigation-inner">

            <?php require __DIR__ . '/nav.php'; ?>

        </div>

    </div>

</header>
