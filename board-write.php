<?php

session_start();

require_once __DIR__ . '/config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');

    if ($title === '' || $content === '') {

        $error = '제목과 내용을 모두 입력해주세요.';

    } else {

        $userId = (int)$_SESSION['user_id'];

        $conn->begin_transaction();

        try {

            /*
             * 게시글 저장
             */
            $stmt = $conn->prepare("
                INSERT INTO board_posts
                (user_id, title, content)
                VALUES (?, ?, ?)
            ");

            $stmt->bind_param(
                "iss",
                $userId,
                $title,
                $content
            );

            $stmt->execute();

            $postId = $conn->insert_id;


            /*
             * 첨부파일 처리
             */
            if (
                isset($_FILES['attachment']) &&
                $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE
            ) {

                $file = $_FILES['attachment'];

                if ($file['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('파일 업로드 중 오류가 발생했습니다.');
                }

                /*
                 * 최대 2MB
                 */
                if ($file['size'] > 2 * 1024 * 1024) {
                    throw new Exception('첨부파일은 2MB 이하만 가능합니다.');
                }

                $originalName = basename($file['name']);

                $extension = strtolower(
                    pathinfo($originalName, PATHINFO_EXTENSION)
                );

                $allowedExtensions = [
                    'jpg',
                    'jpeg',
                    'png',
                    'pdf',
                    'txt'
                ];

                if (!in_array(
                    $extension,
                    $allowedExtensions,
                    true
                )) {
                    throw new Exception(
                        '허용되지 않는 파일 형식입니다.'
                    );
                }

                /*
                * [VULNERABLE LAB]
                * Insufficient File Type Validation
                *
                * 실제 파일의 MIME 유형 검증을 의도적으로 생략함.
                * 기존 확장자 검사와 크기 제한은 유지함.
                * uploads/.htaccess의 PHP 실행 차단도 유지함.
                */


                /*
                 * 서버 저장 파일명 랜덤 생성
                 */
                $storedName =
                    bin2hex(random_bytes(16))
                    . '.'
                    . $extension;

                $uploadDirectory =
                    __DIR__ . '/uploads/';

                $destination =
                    $uploadDirectory . $storedName;


                if (!move_uploaded_file(
                    $file['tmp_name'],
                    $destination
                )) {
                    throw new Exception(
                        '파일 저장에 실패했습니다.'
                    );
                }


                $filePath =
                    '/uploads/' . $storedName;


                /*
                 * attachments 테이블 저장
                 */
                $stmt = $conn->prepare("
                    INSERT INTO attachments
                    (
                        post_id,
                        original_name,
                        stored_name,
                        file_path
                    )
                    VALUES (?, ?, ?, ?)
                ");

                $stmt->bind_param(
                    "isss",
                    $postId,
                    $originalName,
                    $storedName,
                    $filePath
                );

                $stmt->execute();
            }


            /*
             * Audit Log
             */
            $action = 'CREATE_BOARD_POST';
            $target = 'post_id=' . $postId;
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            $details = 'title=' . $title;

            $log = $conn->prepare("
                INSERT INTO audit_logs
                (
                    user_id,
                    action,
                    target,
                    ip_address,
                    details
                )
                VALUES (?, ?, ?, ?, ?)
            ");

            $log->bind_param(
                "issss",
                $userId,
                $action,
                $target,
                $ip,
                $details
            );

            $log->execute();


            $conn->commit();


            header(
                'Location: /board-detail.php?id='
                . $postId
            );

            exit;


        } catch (Throwable $e) {

            $conn->rollback();

            $error =
                '게시글 등록 실패: '
                . $e->getMessage();
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

    <title>문의 작성 - VulnShop</title>

    <link rel="stylesheet" href="/css/style.css">
    <link rel="stylesheet" href="/css/home.css">
    <link rel="stylesheet" href="/css/products.css">
    <link rel="stylesheet" href="/css/board.css">
</head>

<body class="board-page">

<?php require __DIR__ . '/config/header.php'; ?>

<main class="container board-main">

    <div class="breadcrumb">
        <a href="/">홈</a>
        <span>/</span>
        <a href="/board.php">고객센터</a>
        <span>/</span>
        <span>문의 작성</span>
    </div>

    <section class="board-heading">
        <span class="section-label">
            VULNSHOP CUSTOMER CENTER
        </span>

        <h2>문의 작성</h2>

        <p>
            문의하실 내용을 작성해 주세요.
        </p>
    </section>

    <section class="board-form-panel">

        <?php if ($error): ?>

            <div class="board-alert" role="alert">
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        <?php endif; ?>

        <form method="post"
              enctype="multipart/form-data"
              class="board-form">

            <div class="board-field">

                <label for="post-title">
                    제목 <span class="required-mark">*</span>
                </label>

                <input
                    type="text"
                    id="post-title"
                    name="title"
                    placeholder="문의 제목을 입력해 주세요."
                    required
                >

            </div>

            <div class="board-field">

                <label for="post-content">
                    문의 내용
                    <span class="required-mark">*</span>
                </label>

                <textarea
                    id="post-content"
                    name="content"
                    rows="12"
                    placeholder="문의하실 내용을 자세히 작성해 주세요."
                    required
                ></textarea>

            </div>

            <div class="board-field">

                <label for="post-attachment">
                    첨부파일
                </label>

                <input
                    type="file"
                    id="post-attachment"
                    name="attachment"
                    accept=".jpg,.jpeg,.png,.pdf,.txt"
                >

                <p class="board-help">
                    jpg, jpeg, png, pdf, txt /
                    최대 2MB
                </p>

            </div>

            <div class="board-form-actions">

                <a class="board-outline-button"
                   href="/board.php">
                    취소
                </a>

                <button class="board-primary-button"
                        type="submit">
                    문의 등록
                </button>

            </div>

        </form>

    </section>

</main>

<?php require __DIR__ . '/config/footer.php'; ?>

</body>
</html>
