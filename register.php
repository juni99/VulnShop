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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>회원가입 - VulnShop</title>

    <link rel="stylesheet" href="/css/style.css">
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

    <section class="hero">

        <h2>회원가입</h2>

        <?php if ($error): ?>
            <p style="color:red;">
                <?= htmlspecialchars($error) ?>
            </p>
        <?php endif; ?>

        <?php if ($success): ?>
            <p style="color:green;">
                <?= htmlspecialchars($success) ?>
            </p>
        <?php endif; ?>

        <form method="post">

            <p>
                <label>
                    아이디<br>
                    <input
                        type="text"
                        name="username"
                        required
                    >
                </label>
            </p>

            <p>
                <label>
                    이메일<br>
                    <input
                        type="email"
                        name="email"
                        required
                    >
                </label>
            </p>

            <p>
                <label>
                    비밀번호<br>
                    <input
                        type="password"
                        name="password"
                        required
                    >
                </label>
            </p>

            <p>
                <label>
                    비밀번호 확인<br>
                    <input
                        type="password"
                        name="password_confirm"
                        required
                    >
                </label>
            </p>

            <button class="button" type="submit">
                회원가입
            </button>

        </form>

    </section>

</main>

<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>
</html>
