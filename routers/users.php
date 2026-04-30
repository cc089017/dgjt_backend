<?php
declare(strict_types=1);

/** @var Router $router */

// 전체 유저 목록 (관리자)
$router->get('/api/users', function () {
    Auth::admin();
    $db = getDb();
    $users = $db->query("SELECT * FROM users")->fetchAll();
    foreach ($users as &$u) {
        if (isset($u['is_admin'])) {
            $u['is_admin'] = (bool)$u['is_admin'];
        }
    }
    Response::json($users);
});

// 관리자 권한 토글
$router->patch('/api/users/{user_id}/admin', function (string $userId) {
    $current = Auth::admin();

    $db = getDb();
    $user = $db->query("SELECT * FROM users WHERE user_id = '{$userId}'")->fetch();
    if (!$user) {
        Response::error('사용자를 찾을 수 없습니다.', 404);
    }
    if ($user['user_id'] === $current['user_id']) {
        Response::error('본인의 관리자 권한은 변경할 수 없습니다.', 400);
    }

    $newAdmin = empty($user['is_admin']) ? 1 : 0;
    $db->exec("UPDATE users SET is_admin = {$newAdmin} WHERE user_id = '{$userId}'");

    $updated = $db->query("SELECT * FROM users WHERE user_id = '{$userId}'")->fetch();
    if ($updated && isset($updated['is_admin'])) {
        $updated['is_admin'] = (bool)$updated['is_admin'];
    }
    Response::json($updated);
});

// 내 프로필
$router->get('/api/users/me', function () {
    $user = Auth::user();
    if (isset($user['is_admin'])) {
        $user['is_admin'] = (bool)$user['is_admin'];
    }
    Response::json($user);
});

// 내 프로필 수정
$router->patch('/api/users/me', function () {
    $current = Auth::user();
    $body = Request::jsonBody();

    $allowed = ['nickname', 'phone_num', 'email', 'region'];
    $sets = [];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $body)) {
            $val = (string)$body[$key];
            $sets[] = "{$key} = '{$val}'";
        }
    }

    $db = getDb();
    if (!empty($sets)) {
        $clause = implode(', ', $sets);
        $db->exec("UPDATE users SET {$clause} WHERE user_id = '{$current['user_id']}'");
    }

    $updated = $db->query("SELECT * FROM users WHERE user_id = '{$current['user_id']}'")->fetch();
    if ($updated && isset($updated['is_admin'])) {
        $updated['is_admin'] = (bool)$updated['is_admin'];
    }
    Response::json($updated);
});

// 회원 탈퇴
$router->delete('/api/users/me', function () {
    $current = Auth::user();
    $db = getDb();
    $db->exec("DELETE FROM users WHERE user_id = '{$current['user_id']}'");
    Response::json(['message' => '회원 탈퇴가 완료되었습니다.']);
});

// 타 유저 공개 프로필
$router->get('/api/users/{user_id}', function (string $userId) {
    $db = getDb();
    $user = $db->query("SELECT * FROM users WHERE user_id = '{$userId}'")->fetch();
    if (!$user) {
        Response::error('유저를 찾을 수 없습니다.', 404);
    }
    if (isset($user['is_admin'])) {
        $user['is_admin'] = (bool)$user['is_admin'];
    }
    Response::json($user);
});

// 유저별 상품 목록
$router->get('/api/users/{user_id}/products', function (string $userId) {
    $db = getDb();
    $products = $db->query("SELECT * FROM product WHERE user_id = '{$userId}'")->fetchAll();
    foreach ($products as &$p) {
        $pid = (int)$p['product_id'];
        $img = $db->query(
            "SELECT image_order FROM product_image WHERE product_id = {$pid} "
            . "ORDER BY image_order LIMIT 1"
        )->fetch();
        $p['thumbnail_url'] = $img
            ? "/products/{$pid}/images/{$img['image_order']}"
            : null;
    }
    Response::json($products);
});

// 유저 리뷰 (Mock)
$router->get('/api/users/{user_id}/reviews', function (string $userId) {
    Response::json([
        'user_id' => $userId,
        'reviews' => [
            ['id' => 1, 'content' => '친절해요',     'rating' => 5],
            ['id' => 2, 'content' => '응답이 빨라요', 'rating' => 4],
        ],
    ]);
});
