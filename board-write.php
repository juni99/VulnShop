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
                 * MIME Type 검사
                 */
                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $mimeType = $finfo->file(
                    $file['tmp_name']
                );

                $allowedMimeTypes = [
    'jpg' => [
        'image/jpeg'
    ],
    'jpeg' => [
        'image/jpeg'
    ],
    'png' => [
        'image/png'
    ],
    'pdf' => [
        'application/pdf'
    ],
    'txt' => [
        'text/plain',
        'application/octet-stream'
    ]
];

if (
    !isset($allowedMimeTypes[$extension]) ||
    !in_array(
        $mimeType,
        $allowedMimeTypes[$extension],
        true
    )
) {
    throw new Exception(
        '허용되지 않는 파일 형식입니다. MIME: ' . $mimeType
    );
}


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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>글쓰기 - VulnShop</title>

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

    <section class="hero">

        <h2>게시글 작성</h2>

        <?php if ($error): ?>

            <p style="color:red;">
                <?= htmlspecialchars($error) ?>
            </p>

        <?php endif; ?>


        <form
            method="post"
            enctype="multipart/form-data"
        >

            <p>

                <label>

                    제목<br>

                    <input
                        type="text"
                        name="title"
                        size="60"
                        required
                    >

                </label>

            </p>


            <p>

                <label>

                    내용<br>

                    <textarea
                        name="content"
                        rows="12"
                        cols="70"
                        required
                    ></textarea>

                </label>

            </p>


            <p>

                <label>

                    첨부파일<br>

                    <input
                        type="file"
                        name="attachment"
                        accept=".jpg,.jpeg,.png,.pdf,.txt"
                    >

                </label>

            </p>

            <p>
                jpg, jpeg, png, pdf, txt /
                최대 2MB
            </p>


            <button
                class="button"
                type="submit"
            >
                등록
            </button>

        </form>

    </section>

</main>


<footer>
    <p>VulnShop Security Lab</p>
</footer>

</body>

</html>
