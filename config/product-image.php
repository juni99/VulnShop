
<?php

/*
 * VulnShop 공통 상품 이미지 관리
 *
 * DB에 저장된 이미지 파일명을 기준으로
 * 이미지 경로와 실제 파일 존재 여부를 확인합니다.
 */

function getProductImage(?string $imageName): ?string
{
    if (empty($imageName)) {
        return null;
    }

    /*
     * 허용된 WebP 파일명 확인
     */
    if (!preg_match(
        '/\A[a-zA-Z0-9_-]+\.webp\z/',
        $imageName
    )) {
        return null;
    }

    /*
     * 실제 이미지 저장 경로
     */
    $imagePath =
        dirname(__DIR__) .
        '/images/products/' .
        $imageName;

    /*
     * 이미지 파일 존재 여부 확인
     */
    if (!is_file($imagePath)) {
        return null;
    }

    /*
     * 브라우저에 표시할 이미지 URL 반환
     */
    return '/images/products/' . $imageName;
}
