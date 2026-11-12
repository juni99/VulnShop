<nav>

    <?php if (isset($_SESSION['user_id'])): ?>

        <span>
            <?= htmlspecialchars($_SESSION['username']) ?>님
        </span>

        <a href="/">홈</a>
        <a href="/products.php">상품</a>
        <a href="/board.php">게시판</a>
        <a href="/orders.php">주문내역</a>
        <a href="/cart.php">장바구니</a>

        <?php if (
            isset($_SESSION['role']) &&
            $_SESSION['role'] === 'admin'
        ): ?>

            <a href="/admin.php">관리자</a>

        <?php endif; ?>

        <a href="/logout.php">로그아웃</a>

    <?php else: ?>

        <a href="/">홈</a>
        <a href="/products.php">상품</a>
        <a href="/board.php">게시판</a>
        <a href="/login.php">로그인</a>
        <a href="/register.php">회원가입</a>

    <?php endif; ?>

</nav>
