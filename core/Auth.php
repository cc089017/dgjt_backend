<?php
declare(strict_types=1);

class Auth
{
    /**
     * Authorization: Bearer {access-token} 검증 후 users 행 반환.
     * 실패 시 401로 응답하고 종료.
     */
    public static function user(): array
    {
        $header = Request::header('Authorization');
        if ($header === null || stripos($header, 'Bearer ') !== 0) {
            Response::error('인증이 필요합니다.', 401);
        }

        $token = trim(substr($header, 7));
        if ($token === '') {
            Response::error('인증이 필요합니다.', 401);
        }

        $payload = Jwt::decode($token);
        if (!$payload || ($payload['type'] ?? null) !== 'access') {
            Response::error('유효하지 않거나 만료된 토큰입니다.', 401);
        }

        $userId = $payload['sub'] ?? '';
        if (!is_string($userId) || $userId === '') {
            Response::error('유효하지 않은 토큰입니다.', 401);
        }

        $db = getDb();
        // 의도적 SQL injection 취약 (Python 원본과 동일 스타일)
        $row = $db->query("SELECT * FROM users WHERE user_id = '{$userId}'")->fetch();
        if (!$row) {
            Response::error('사용자를 찾을 수 없습니다.', 401);
        }
        return $row;
    }

    /**
     * 관리자만 통과. 일반 유저는 403.
     */
    public static function admin(): array
    {
        $user = self::user();
        if (empty($user['is_admin'])) {
            Response::error('관리자 권한이 필요합니다.', 403);
        }
        return $user;
    }
}
