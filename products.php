<?php

session_start();

require_once __DIR__ . '/config/db.php';

$productId = (int)($_GET['id'] ?? 0);
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
            stock
        FROM products
        ORDER BY id ASC
    ");

} else {

    /*
     * 상품 상세 조회
     */
    $stmt = $conn->prepare("
        SELECT
            id,
            name,
            description,
            price,
            stock,
            image
        FROM products
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param("i", $productId);
    $stmt->execute();

    $product = $stmt->get_result()->fetch_assoc();

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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>상품 - VulnShop</title>

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

<?php if ($productId <= 0): ?>

    <section class="hero">

        <h2>전체 상품</h2>

        <p>
            VulnShop에서 판매 중인 상품입니다.
        </p>

    </section>

    <section>

        <div class="product-grid">

        <?php while ($row = $result->fetch_assoc()): ?>

            <div class="product-card">

                <div class="product-image">
                    <?= htmlspecialchars($row['name']) ?>
                </div>

                <h3>
                    <?= htmlspecialchars($row['name']) ?>
                </h3>

                <p>
                    <?= htmlspecialchars($row['description']) ?>
                </p>

                <p class="price">
                    <?= number_format($row['price']) ?>원
                </p>

                <p>
                    재고:
                    <?= (int)$row['stock'] ?>개
                </p>

                <a
                    class="button"
                    href="/products.php?id=<?= (int)$row['id'] ?>"
                >
                    상품 보기
                </a>

            </div>

        <?php endwhile; ?>

        </div>

    </section>


<?php elseif (!$product): ?>

    <section class="hero">

        <h2>상품을 찾을 수 없습니다.</h2>

        <a
            class="button"
            href="/products.php"
        >
            상품 목록
        </a>

    </section>


<?php else: ?>

    <section class="hero">

        <h2>
            <?= htmlspecialchars($product['name']) ?>
        </h2>

        <p>
            <?= htmlspecialchars($product['description']) ?>
        </p>

        <p class="price">
            <?= number_format($product['price']) ?>원
        </p>

        <p>
            재고:
            <?= (int)$product['stock'] ?>개
        </p>


        <form
            method="post"
            action="/cart.php"
        >

            <input
                type="hidden"
                name="action"
                value="add"
            >

            <input
                type="hidden"
                name="product_id"
                value="<?= (int)$product['id'] ?>"
            >

            <label>
                수량:

                <input
                    type="number"
                    name="quantity"
                    value="1"
                    min="1"
                    max="<?= (int)$product['stock'] ?>"
                >
            </label>

            <br><br>

            <button
                class="button"
                type="submit"
            >
                장바구니 담기
            </button>

        </form>

    </section>


    <section class="hero">

        <h2>상품 리뷰</h2>

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


        <?php if (isset($_SESSION['user_id'])): ?>

            <form method="post">

                <input
                    type="hidden"
                    name="action"
                    value="review"
                >

                <input
                    type="hidden"
                    name="product_id"
                    value="<?= (int)$product['id'] ?>"
                >

                <p>
                    <label>
                        평점

                        <select name="rating" required>
                            <option value="5">5점</option>
                            <option value="4">4점</option>
                            <option value="3">3점</option>
                            <option value="2">2점</option>
                            <option value="1">1점</option>
                        </select>
                    </label>
                </p>

                <p>
                    <label>
                        리뷰 내용<br>

                        <textarea
                            name="content"
                            rows="5"
                            cols="50"
                            required
                        ></textarea>
                    </label>
                </p>

                <button
                    class="button"
                    type="submit"
                >
                    리뷰 등록
                </button>

            </form>

        <?php else: ?>

            <p>
                리뷰를 작성하려면 로그인이 필요합니다.
            </p>

            <a
                class="button"
                href="/login.php"
            >
                로그인
            </a>

        <?php endif; ?>

    </section>


    <section class="hero">

        <h2>등록된 리뷰</h2>

        <?php if ($reviews->num_rows === 0): ?>

            <p>
                아직 등록된 리뷰가 없습니다.
            </p>

        <?php else: ?>

            <?php while ($review = $reviews->fetch_assoc()): ?>

                <div class="product-card">

                    <strong>
                        <?= htmlspecialchars($review['username']) ?>
                    </strong>

                    <p>
                        평점:
                        <?= (int)$review['rating'] ?>/5
                    </p>

                    <p>
                        <?= nl2br(
                            htmlspecialchars($review['content'])
                        ) ?>
                    </p>

                    <small>
                        <?= htmlspecialchars($review['created_at']) ?>
                    </small>

                </div>

                <br>

            <?php endwhile; ?>

        <?php endif; ?>

    </section>

<?php endif; ?>

</main>


<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>

</html>
