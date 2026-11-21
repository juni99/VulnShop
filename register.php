<?php
session_start();

require_once __DIR__ . '/config/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        $error = '모든 항목을 입력해주세요.';
    } elseif ($password !== $passwordConfirm) {
        $error = '비밀번호가 일치하지 않습니다.';
    } elseif (strlen($password) < 8) {
        $error = '비밀번호는 8자 이상이어야 합니다.';
    } else {

        $check = $conn->prepare("
            SELECT id
            FROM users
            WHERE username = ? OR email = ?
            LIMIT 1
        ");

        $check->bind_param("ss", $username, $email);
        $check->execute();

        $result = $check->get_result();

        if ($result->fetch_assoc()) {

            $error = '이미 사용 중인 아이디 또는 이메일입니다.';

        } else {

            $passwordHash = password_hash(
                $password,
                PASSWORD_DEFAULT
            );

            $stmt = $conn->prepare("
                INSERT INTO users
                (username, password, email, role)
                VALUES (?, ?, ?, 'user')
            ");

            $stmt->bind_param(
                "sss",
                $username,
                $passwordHash,
                $email
            );

            if ($stmt->execute()) {

                $success = '회원가입이 완료되었습니다. 로그인해주세요.';

            } else {

                $error = '회원가입 처리 중 오류가 발생했습니다.';
            }
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

    <title>회원가입 - VulnShop</title>

    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">
    <link rel="stylesheet" href="/css/auth.css">
</head>

<body class="auth-page">

<?php require __DIR__ . '/config/header.php'; ?>

<main class="auth-main">

    <section class="auth-card register-card">

        <div class="auth-heading">

            <span class="auth-label">
                VULNSHOP MEMBERSHIP
            </span>

            <h2 class="auth-logo">
                <span>Vuln</span>Shop
            </h2>

            <h3>회원가입</h3>

            <p>
                VulnShop의 새로운 회원이 되어
                <br>
                다양한 디지털 제품을 만나보세요.
            </p>

        </div>

        <?php if ($error): ?>

            <div class="auth-alert auth-error"
                 role="alert">

                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>

            </div>

        <?php endif; ?>

        <?php if ($success): ?>

            <div class="auth-alert auth-success"
                 role="status">

                <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>

                <br>

                <a href="/login.php">
                    로그인 페이지로 이동 →
                </a>

            </div>

        <?php endif; ?>

        <form method="post" class="auth-form">

            <div class="auth-field">

                <label for="username">
                    아이디
                    <span class="required-mark">*</span>
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="사용할 아이디를 입력해 주세요."
                    autocomplete="username"
                    required
                >

            </div>

            <div class="auth-field">

                <label for="email">
                    이메일
                    <span class="required-mark">*</span>
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="example@email.com"
                    autocomplete="email"
                    required
                >

            </div>

            <div class="auth-field">

                <label for="password">
                    비밀번호
                    <span class="required-mark">*</span>
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="사용할 비밀번호를 입력해 주세요."
                    autocomplete="new-password"
                    required
                >

            </div>

            <div class="auth-field">

                <label for="password_confirm">
                    비밀번호 확인
                    <span class="required-mark">*</span>
                </label>

                <input
                    type="password"
                    id="password_confirm"
                    name="password_confirm"
                    placeholder="비밀번호를 다시 입력해 주세요."
                    autocomplete="new-password"
                    required
                >

            </div>

            <p class="auth-required-info">
                * 표시된 항목은 필수 입력 항목입니다.
            </p>

            <button
                class="auth-button"
                type="submit"
            >
                회원가입
            </button>

        </form>

        <div class="auth-divider"></div>

        <div class="auth-bottom">

            <p>
                이미 VulnShop 회원이신가요?
            </p>

            <a class="auth-outline-button"
               href="/login.php">

                로그인

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
