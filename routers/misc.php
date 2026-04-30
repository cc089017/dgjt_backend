<?php
declare(strict_types=1);

/** @var Router $router */

$router->get('/api/categories', function () {
    Response::json([
        'categories' => [
            ['id' => 1, 'name' => '전자기기'],
            ['id' => 2, 'name' => '의류'],
            ['id' => 3, 'name' => '가구/인테리어'],
            ['id' => 4, 'name' => '도서/티켓'],
            ['id' => 5, 'name' => '기타'],
        ],
    ]);
});

$router->get('/api/regions', function () {
    Response::json([
        'regions' => [
            ['id' => 1, 'name' => '강남구'],
            ['id' => 2, 'name' => '서초구'],
            ['id' => 3, 'name' => '송파구'],
            ['id' => 4, 'name' => '마포구'],
        ],
    ]);
});

$router->get('/api/notices', function () {
    Response::json([
        'notices' => [
            ['id' => 1, 'title' => '중고거래 플랫폼 오픈 안내', 'date' => '2026-04-20'],
            ['id' => 2, 'title' => '개인정보 처리방침 개정 안내', 'date' => '2026-04-22'],
        ],
    ]);
});

// 찜 목록 (Mock)
$router->get('/api/products/liked', function () {
    Response::json([
        'liked_products' => [
            ['id' => 1, 'name' => '찜한 상품 예시'],
        ],
    ]);
});
