<?php
session_start();

require_once __DIR__ . '/config/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("
        SELECT id, username, password, role
        FROM users
        WHERE username = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $username);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $success = false;
    $userId = null;

    if ($user && password_verify($password, $user['password'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        $success = true;
        $userId = $user['id'];

    } else {

        $error = '아이디 또는 비밀번호가 올바르지 않습니다.';
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    $log = $conn->prepare("
        INSERT INTO login_logs
        (user_id, username, ip_address, success)
        VALUES (?, ?, ?, ?)
    ");

    $log->bind_param(
        "issi",
        $userId,
        $username,
        $ip,
        $success
    );

    $log->execute();

    if ($success) {
        header('Location: /');
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ko">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>로그인 - VulnShop</title>

    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">
    <link rel="stylesheet" href="/css/auth.css">
</head>

<body class="auth-page">

<?php require __DIR__ . '/config/header.php'; ?>

<main class="auth-main">

    <section class="auth-card">

        <div class="auth-heading">

            <span class="auth-label">
                VULNSHOP MEMBERSHIP
            </span>

            <h2 class="auth-logo">
                <span>Vuln</span>Shop
            </h2>

            <h3>로그인</h3>

            <p>
                다시 만나서 반갑습니다.
                <br>
                VulnShop의 다양한 상품을 만나보세요.
            </p>

        </div>

        <?php if ($error): ?>

            <div class="auth-alert auth-error"
                 role="alert">

                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>

            </div>

        <?php endif; ?>

        <form method="post" class="auth-form">

            <div class="auth-field">

                <label for="username">
                    아이디
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="아이디를 입력해 주세요."
                    autocomplete="username"
                    required
                >

            </div>

            <div class="auth-field">

                <label for="password">
                    비밀번호
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="비밀번호를 입력해 주세요."
                    autocomplete="current-password"
                    required
                >

            </div>

            <button
                class="auth-button"
                type="submit"
            >
                로그인
            </button>

        </form>

        <div class="auth-divider"></div>

        <div class="auth-bottom">

            <p>
                아직 VulnShop 회원이 아니신가요?
            </p>

            <a class="auth-outline-button"
               href="/register.php">

                회원가입

            </a>

            <a class="auth-home-link"
               href="/">

                쇼핑몰 홈으로 돌아가기

            </a>

        </div>

    </section>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>