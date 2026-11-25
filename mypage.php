
<?php
session_start();

require_once __DIR__ . '/config/db.php';

/*
 * 로그인 여부 확인
 */
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = (int)$_SESSION['user_id'];

if (empty($_SESSION['mypage_csrf'])) {
    $_SESSION['mypage_csrf'] = bin2hex(random_bytes(32));
}

$error = '';
$message = $_SESSION['mypage_message'] ?? '';
unset($_SESSION['mypage_message']);

/*
 * 현재 회원정보 조회
 * 사용자 ID는 URL이 아닌 세션에서 가져옵니다.
 */
function loadMyPageUser(mysqli $conn, int $userId): ?array
{
    $stmt = $conn->prepare("
        SELECT
            id,
            username,
            password,
            email,
            role,
            postal_code,
            address,
            address_detail,
            created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->bind_param('i', $userId);
    $stmt->execute();

    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

$user = loadMyPageUser($conn, $userId);

if (!$user) {
    http_response_code(403);
    exit('유효하지 않은 사용자 계정입니다.');
}

/*
 * 회원정보 수정 / 비밀번호 변경
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $csrf = $_POST['csrf_token'] ?? '';

    if (
        !is_string($csrf) ||
        !hash_equals($_SESSION['mypage_csrf'], $csrf)
    ) {
        http_response_code(403);
        exit('유효하지 않은 요청입니다.');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {

        $email = trim((string)($_POST['email'] ?? ''));
        $postalCode = trim((string)($_POST['postal_code'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));
        $addressDetail = trim((string)($_POST['address_detail'] ?? ''));

        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            mb_strlen($email, 'UTF-8') > 100
        ) {
            $error = '올바른 이메일 주소를 입력해 주세요.';

        } elseif (
            mb_strlen($postalCode, 'UTF-8') > 10 ||
            mb_strlen($address, 'UTF-8') > 255 ||
            mb_strlen($addressDetail, 'UTF-8') > 255
        ) {
            $error = '주소 입력 길이를 확인해 주세요.';

        } else {

            /*
             * 다른 회원이 사용 중인 이메일인지 확인
             */
            $stmt = $conn->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                  AND id <> ?
                LIMIT 1
            ");

            $stmt->bind_param('si', $email, $userId);
            $stmt->execute();

            $duplicate = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($duplicate) {

                $error = '이미 사용 중인 이메일입니다.';

            } else {

                try {
                    $stmt = $conn->prepare("
                        UPDATE users
                        SET
                            email = ?,
                            postal_code = ?,
                            address = ?,
                            address_detail = ?
                        WHERE id = ?
                    ");

                    $stmt->bind_param(
                        'ssssi',
                        $email,
                        $postalCode,
                        $address,
                        $addressDetail,
                        $userId
                    );

                    $stmt->execute();
                    $stmt->close();

                    $_SESSION['mypage_message'] =
                        '회원정보가 저장되었습니다.';

                    header('Location: /mypage.php', true, 303);
                    exit;

                } catch (mysqli_sql_exception $e) {

                    if ((int)$e->getCode() === 1062) {
                        $error = '이미 사용 중인 이메일입니다.';
                    } else {
                        error_log('MyPage profile update failed');
                        $error = '회원정보 저장 중 오류가 발생했습니다.';
                    }
                }
            }
        }

    } elseif ($action === 'password') {

        $currentPassword =
            (string)($_POST['current_password'] ?? '');

        $newPassword =
            (string)($_POST['new_password'] ?? '');

        $confirmPassword =
            (string)($_POST['confirm_password'] ?? '');

        if (!password_verify(
            $currentPassword,
            $user['password']
        )) {

            $error = '현재 비밀번호가 올바르지 않습니다.';

        } elseif (
            strlen($newPassword) < 8 ||
            strlen($newPassword) > 72
        ) {

            $error = '새 비밀번호는 8~72바이트로 입력해 주세요.';

        } elseif ($newPassword !== $confirmPassword) {

            $error = '새 비밀번호 확인이 일치하지 않습니다.';

        } elseif (hash_equals($currentPassword, $newPassword)) {

            $error = '기존 비밀번호와 다른 비밀번호를 입력해 주세요.';

        } else {

            $passwordHash = password_hash(
                $newPassword,
                PASSWORD_DEFAULT
            );

            /*
             * 비밀번호 변경 시점에 현재 비밀번호 해시가
             * 그대로인지 다시 확인합니다.
             */
            $stmt = $conn->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
                  AND password = ?
            ");

            $stmt->bind_param(
                'sis',
                $passwordHash,
                $userId,
                $user['password']
            );

            $stmt->execute();
            $changed = $stmt->affected_rows;
            $stmt->close();

            if ($changed === 1) {

                session_regenerate_id(true);

                $_SESSION['mypage_csrf'] =
                    bin2hex(random_bytes(32));

                $_SESSION['mypage_message'] =
                    '비밀번호가 변경되었습니다.';

                header('Location: /mypage.php', true, 303);
                exit;

            } else {

                $error = '계정 정보가 변경되었습니다. 다시 시도해 주세요.';
            }
        }

    } else {

        $error = '지원하지 않는 요청입니다.';
    }
}

/*
 * 표시할 회원정보 새로 조회
 */
$user = loadMyPageUser($conn, $userId);

?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>마이페이지 - VulnShop</title>

    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">
    <link rel="stylesheet" href="/css/products.css">
    <link rel="stylesheet" href="/css/mypage.css">
</head>

<body class="mypage-page">

<?php require __DIR__ . '/config/header.php'; ?>

<main class="container mypage-main">

    <div class="breadcrumb">
        <a href="/">홈</a>
        <span>/</span>
        <span>마이페이지</span>
    </div>

    <section class="mypage-heading">
        <span class="section-label">VULNSHOP ACCOUNT</span>
        <h2>마이페이지</h2>
        <p>회원정보와 배송지, 비밀번호를 관리합니다.</p>
    </section>

    <?php if ($message): ?>
        <div class="mypage-alert mypage-success" role="status">
            <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="mypage-alert mypage-error" role="alert">
            <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="mypage-layout">

        <!-- 회원정보 및 배송지 -->

        <section class="mypage-panel">

            <h3>회원정보 및 배송지</h3>

            <form method="post" action="/mypage.php">

                <input type="hidden" name="action" value="profile">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['mypage_csrf'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <div class="mypage-field">
                    <label for="mypage-username">아이디</label>
                    <input
                        id="mypage-username"
                        type="text"
                        value="<?= htmlspecialchars(
                            $user['username'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        readonly
                    >
                </div>

                <div class="mypage-field">
                    <label for="mypage-email">이메일</label>
                    <input
                        id="mypage-email"
                        type="email"
                        name="email"
                        maxlength="100"
                        value="<?= htmlspecialchars(
                            $user['email'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </div>

                <div class="mypage-field">
                    <label for="mypage-postal">우편번호</label>
                    <input
                        id="mypage-postal"
                        type="text"
                        name="postal_code"
                        maxlength="10"
                        autocomplete="postal-code"
                        value="<?= htmlspecialchars(
                            $user['postal_code'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                </div>

                <div class="mypage-field">
                    <label for="mypage-address">기본 주소</label>
                    <input
                        id="mypage-address"
                        type="text"
                        name="address"
                        maxlength="255"
                        autocomplete="street-address"
                        value="<?= htmlspecialchars(
                            $user['address'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                </div>

                <div class="mypage-field">
                    <label for="mypage-detail">상세 주소</label>
                    <input
                        id="mypage-detail"
                        type="text"
                        name="address_detail"
                        maxlength="255"
                        value="<?= htmlspecialchars(
                            $user['address_detail'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                </div>

                <div class="mypage-field">
                    <label>가입일</label>
                    <p class="mypage-readonly">
                        <?= htmlspecialchars(
                            $user['created_at'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </p>
                </div>

                <button type="submit" class="mypage-button">
                    회원정보 저장
                </button>

            </form>

        </section>

        <!-- 비밀번호 변경 -->

        <section class="mypage-panel">

            <h3>비밀번호 변경</h3>

            <p class="mypage-description">
                계정 보호를 위해 현재 비밀번호를 확인합니다.
            </p>

            <form method="post" action="/mypage.php">

                <input type="hidden" name="action" value="password">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['mypage_csrf'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <div class="mypage-field">
                    <label for="current-password">
                        현재 비밀번호
                    </label>
                    <input
                        id="current-password"
                        type="password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <div class="mypage-field">
                    <label for="new-password">
                        새 비밀번호
                    </label>
                    <input
                        id="new-password"
                        type="password"
                        name="new_password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                </div>

                <div class="mypage-field">
                    <label for="confirm-password">
                        새 비밀번호 확인
                    </label>
                    <input
                        id="confirm-password"
                        type="password"
                        name="confirm_password"
                        autocomplete="new-password"
                        minlength="8"
                        required
                    >
                </div>

                <p class="mypage-help">
                    새 비밀번호는 8~72바이트로 설정할 수 있습니다.
                </p>

                <button type="submit" class="mypage-button">
                    비밀번호 변경
                </button>

            </form>

        </section>

    </div>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
