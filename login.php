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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - VulnShop</title>
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

        <h2>로그인</h2>

        <?php if ($error): ?>
            <p style="color:red;">
                <?= htmlspecialchars($error) ?>
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
                    비밀번호<br>
                    <input
                        type="password"
                        name="password"
                        required
                    >
                </label>
            </p>

            <button class="button" type="submit">
                로그인
            </button>

        </form>

    </section>

</main>

<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>
</html>
