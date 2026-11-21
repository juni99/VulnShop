
<?php

/*
 * VulnShop 주문 상태 공통 관리
 */

function getOrderStatusLabel(string $status): string
{
    $labels = [
        'pending'   => '결제 대기',
        'paid'      => '결제 완료',
        'shipped'   => '배송 중',
        'completed' => '주문 완료',
        'cancelled' => '주문 취소'
    ];

    return $labels[$status] ?? '상태 미확인';
}

function getOrderStatusClass(string $status): string
{
    $classes = [
        'pending'   => 'status-pending',
        'paid'      => 'status-paid',
        'shipped'   => 'status-shipped',
        'completed' => 'status-completed',
        'cancelled' => 'status-cancelled'
    ];

    return $classes[$status] ?? 'status-unknown';
}
