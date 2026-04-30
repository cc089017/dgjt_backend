<?php
declare(strict_types=1);

/** @var Router $router */

if (!function_exists('uuidv4')) {
    function uuidv4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

// 활성 배너 목록
$router->get('/api/banners', function () {
    $db = getDb();
    $banners = $db->query("SELECT * FROM banners WHERE is_active = 1")->fetchAll();
    foreach ($banners as &$b) {
        if (isset($b['is_active'])) {
            $b['is_active'] = (bool)$b['is_active'];
        }
    }
    Response::json($banners);
});

// 배너 등록 (관리자, multipart)
$router->post('/api/banners', function () {
    Auth::admin();

    $title    = (string)(Request::form('title', ''));
    $linkUrl  = (string)(Request::form('link_url', ''));
    $isActive = filter_var(Request::form('is_active', '1'), FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $image    = Request::file('image');

    if (!$image || ($image['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        Response::error('이미지 파일이 필요합니다.', 400);
    }

    $ext = strtolower(pathinfo((string)$image['name'], PATHINFO_EXTENSION));
    if ($ext === '') $ext = 'jpg';

    $filename  = uuidv4() . '.' . $ext;
    $bannerDir = __DIR__ . '/../uploads/banners';
    if (!is_dir($bannerDir)) {
        @mkdir($bannerDir, 0755, true);
    }
    $savePath = $bannerDir . '/' . $filename;

    if (!move_uploaded_file($image['tmp_name'], $savePath)) {
        Response::error('파일 저장 실패', 500);
    }

    $imageUrl = "/uploads/banners/{$filename}";

    $db = getDb();
    $db->exec(
        "INSERT INTO banners (title, link_url, is_active, image_url) "
        . "VALUES ('{$title}', '{$linkUrl}', {$isActive}, '{$imageUrl}')"
    );
    $bannerId = (int)$db->lastInsertId();

    $banner = $db->query("SELECT * FROM banners WHERE id = {$bannerId}")->fetch();
    if ($banner && isset($banner['is_active'])) {
        $banner['is_active'] = (bool)$banner['is_active'];
    }
    Response::json($banner);
});

// 배너 삭제 (관리자)
$router->delete('/api/banners/{banner_id}', function (string $bannerId) {
    Auth::admin();
    $bid = (int)$bannerId;

    $db = getDb();
    $banner = $db->query("SELECT * FROM banners WHERE id = {$bid}")->fetch();
    if (!$banner) {
        Response::error('배너를 찾을 수 없습니다.', 404);
    }

    // 디스크 파일 삭제
    if (!empty($banner['image_url'])) {
        $imgUrl = (string)$banner['image_url'];
        // /uploads/banners/xxx.jpg 형태만 처리 (외부 URL은 무시)
        if (strpos($imgUrl, '/uploads/banners/') === 0) {
            $relative = ltrim($imgUrl, '/');
            $abs = __DIR__ . '/../' . $relative;
            if (is_file($abs)) {
                @unlink($abs);
            }
        }
    }

    $db->exec("DELETE FROM banners WHERE id = {$bid}");
    Response::json(['message' => '배너가 삭제되었습니다.']);
});
