<?php
declare(strict_types=1);

/** @var Router $router */

// ===== 헬퍼 =====

if (!function_exists('fetchShareThumbnail')) {
    function fetchShareThumbnail(PDO $db, int $shareId): ?string
    {
        $img = $db->query(
            "SELECT image_url FROM share_image WHERE share_id = {$shareId} "
            . "ORDER BY image_order LIMIT 1"
        )->fetch();
        return $img ? $img['image_url'] : null;
    }
}

// ===== 라우트 =====

// 나눔 목록
$router->get('/api/shares', function () {
    $search = Request::query('search');
    $skip   = (int)(Request::query('skip', 0));
    $limit  = (int)(Request::query('limit', 20));
    if ($skip < 0) $skip = 0;
    if ($limit < 1) $limit = 1;
    if ($limit > 100) $limit = 100;

    $sql = "SELECT * FROM share WHERE 1=1";
    if ($search !== null && $search !== '') {
        $sql .= " AND (share_title LIKE '%{$search}%' OR share_body LIKE '%{$search}%')";
    }
    $sql .= " LIMIT {$limit} OFFSET {$skip}";

    $db = getDb();
    $shares = $db->query($sql)->fetchAll();
    foreach ($shares as &$s) {
        $s['thumbnail_url'] = fetchShareThumbnail($db, (int)$s['share_id']);
    }
    Response::json($shares);
});

// 나눔 등록
$router->post('/api/shares', function () {
    $current = Auth::user();
    $body = Request::jsonBody();

    $title   = (string)($body['share_title'] ?? '');
    $bodyTxt = (string)($body['share_body']  ?? '');

    if ($title === '') {
        Response::error('제목은 필수입니다.', 400);
    }

    $db = getDb();
    $db->exec(
        "INSERT INTO share (user_id, share_title, share_body) "
        . "VALUES ('{$current['user_id']}', '{$title}', '{$bodyTxt}')"
    );
    $shareId = (int)$db->lastInsertId();

    $share = $db->query("SELECT * FROM share WHERE share_id = {$shareId}")->fetch();
    $share['thumbnail_url'] = null;
    Response::json($share, 201);
});

// 내 나눔 목록
$router->get('/api/shares/me', function () {
    $current = Auth::user();
    $db = getDb();
    $shares = $db->query("SELECT * FROM share WHERE user_id = '{$current['user_id']}'")->fetchAll();
    foreach ($shares as &$s) {
        $s['thumbnail_url'] = fetchShareThumbnail($db, (int)$s['share_id']);
    }
    Response::json($shares);
});

// 나눔 상세
$router->get('/api/shares/{share_id}', function (string $shareId) {
    $sid = (int)$shareId;
    $db = getDb();
    $share = $db->query("SELECT * FROM share WHERE share_id = {$sid}")->fetch();
    if (!$share) {
        Response::error('나눔을 찾을 수 없습니다.', 404);
    }

    $images = $db->query(
        "SELECT image_url FROM share_image WHERE share_id = {$sid} ORDER BY image_order"
    )->fetchAll();

    $seller = $db->query("SELECT nickname, region FROM users WHERE user_id = '{$share['user_id']}'")->fetch();

    $imageUrls = array_column($images, 'image_url');

    $share['thumbnail_url']   = $imageUrls[0] ?? null;
    $share['seller_nickname'] = $seller['nickname'] ?? '';
    $share['seller_region']   = $seller['region']   ?? '';
    $share['image_urls']      = $imageUrls;

    Response::json($share);
});

// 이미지 목록
$router->get('/api/shares/{share_id}/images', function (string $shareId) {
    $sid = (int)$shareId;
    $db = getDb();
    $share = $db->query("SELECT share_id FROM share WHERE share_id = {$sid}")->fetch();
    if (!$share) {
        Response::error('나눔을 찾을 수 없습니다.', 404);
    }
    $images = $db->query(
        "SELECT image_order, image_url FROM share_image WHERE share_id = {$sid} ORDER BY image_order"
    )->fetchAll();

    Response::json([
        'share_id'     => $sid,
        'image_orders' => array_column($images, 'image_order'),
        'image_urls'   => array_column($images, 'image_url'),
    ]);
});

// 나눔 수정
$router->patch('/api/shares/{share_id}', function (string $shareId) {
    $current = Auth::user();
    $body = Request::jsonBody();
    $sid = (int)$shareId;

    $db = getDb();
    $share = $db->query("SELECT * FROM share WHERE share_id = {$sid}")->fetch();
    if (!$share) {
        Response::error('나눔을 찾을 수 없습니다.', 404);
    }
    if ($share['user_id'] !== $current['user_id']) {
        Response::error('수정 권한이 없습니다.', 403);
    }

    $allowed = ['share_title', 'share_body'];
    $sets = [];
    foreach ($allowed as $key) {
        if (array_key_exists($key, $body)) {
            $val = (string)$body[$key];
            $sets[] = "{$key} = '{$val}'";
        }
    }
    if (!empty($sets)) {
        $clause = implode(', ', $sets);
        $db->exec("UPDATE share SET {$clause} WHERE share_id = {$sid}");
    }

    $updated = $db->query("SELECT * FROM share WHERE share_id = {$sid}")->fetch();
    $updated['thumbnail_url'] = fetchShareThumbnail($db, $sid);
    Response::json($updated);
});

// 나눔 삭제
$router->delete('/api/shares/{share_id}', function (string $shareId) {
    $current = Auth::user();
    $sid = (int)$shareId;

    $db = getDb();
    $share = $db->query("SELECT * FROM share WHERE share_id = {$sid}")->fetch();
    if (!$share) {
        Response::error('나눔을 찾을 수 없습니다.', 404);
    }
    if ($share['user_id'] !== $current['user_id'] && empty($current['is_admin'])) {
        Response::error('삭제 권한이 없습니다.', 403);
    }

    $db->exec("DELETE FROM share WHERE share_id = {$sid}");
    Response::json(['message' => '나눔이 삭제되었습니다.']);
});

// 이미지 업로드
$router->post('/api/shares/{share_id}/images', function (string $shareId) {
    $current = Auth::user();
    $sid = (int)$shareId;

    $db = getDb();
    $share = $db->query("SELECT * FROM share WHERE share_id = {$sid}")->fetch();
    if (!$share) {
        Response::error('나눔을 찾을 수 없습니다.', 404);
    }
    if ($share['user_id'] !== $current['user_id']) {
        Response::error('권한이 없습니다.', 403);
    }

    $files = Request::files('files');
    if (empty($files)) {
        Response::error('업로드할 파일이 없습니다.', 400);
    }

    $uploadDir = config('upload_dirs')['shares'];

    foreach ($files as $idx => $file) {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::error('파일 업로드 실패', 400);
        }
        $content = file_get_contents($file['tmp_name']);
        if ($content === false) {
            Response::error('파일을 읽을 수 없습니다.', 400);
        }
        if (!isValidProductImage($content, (string)($file['type'] ?? ''), (string)($file['name'] ?? ''))) {
            Response::error('이미지 파일만 업로드할 수 있습니다.', 400);
        }

        $ext      = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $basename = pathinfo((string)$file['name'], PATHINFO_FILENAME);
        $filename = date('Ymd') . '_' . md5($basename) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename);

        $imageUrl = '/uploads/shares/' . $filename;
        $db->exec(
            "INSERT INTO share_image (share_id, image_url, image_order) "
            . "VALUES ({$sid}, '{$imageUrl}', {$idx})"
        );
    }

    Response::json(['message' => count($files) . '개의 이미지가 업로드되었습니다.']);
});

// 상태 변경
$router->patch('/api/shares/{share_id}/status', function (string $shareId) {
    $current = Auth::user();
    $body = Request::jsonBody();
    $status = (string)($body['status'] ?? '');
    $sid = (int)$shareId;

    $db = getDb();
    $share = $db->query("SELECT * FROM share WHERE share_id = {$sid}")->fetch();
    if (!$share) {
        Response::error('나눔을 찾을 수 없습니다.', 404);
    }
    if ($share['user_id'] !== $current['user_id']) {
        Response::error('권한이 없습니다.', 403);
    }
    $db->exec("UPDATE share SET share_status = '{$status}' WHERE share_id = {$sid}");

    $updated = $db->query("SELECT * FROM share WHERE share_id = {$sid}")->fetch();
    $updated['thumbnail_url'] = fetchShareThumbnail($db, $sid);
    Response::json($updated);
});
