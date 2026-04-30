<?php
declare(strict_types=1);

class Jwt
{
    private const ACCESS_EXPIRE  = 1800;    // 30분
    private const REFRESH_EXPIRE = 604800;  // 7일

    private static function secret(): string
    {
        $s = getenv('JWT_SECRET');
        if ($s !== false && $s !== '') {
            return $s;
        }
        // 운영에서는 반드시 환경변수로 교체할 것
        return 'change-this-jwt-secret-in-production-please-use-long-random-string';
    }

    public static function createAccessToken(string $userId): string
    {
        return self::encode([
            'sub'  => $userId,
            'type' => 'access',
            'iat'  => time(),
            'exp'  => time() + self::ACCESS_EXPIRE,
        ]);
    }

    public static function createRefreshToken(string $userId): string
    {
        return self::encode([
            'sub'  => $userId,
            'type' => 'refresh',
            'iat'  => time(),
            'exp'  => time() + self::REFRESH_EXPIRE,
        ]);
    }

    /**
     * JWT 디코드 + 시그니처 + 만료 검증. 실패 시 null.
     */
    public static function decode(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }
        [$h, $p, $s] = $parts;

        $signature = self::base64UrlDecode($s);
        $expected  = hash_hmac('sha256', $h . '.' . $p, self::secret(), true);
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $payloadJson = self::base64UrlDecode($p);
        $payload = json_decode($payloadJson, true);
        if (!is_array($payload)) {
            return null;
        }

        if (isset($payload['exp']) && (int)$payload['exp'] < time()) {
            return null;
        }
        return $payload;
    }

    private static function encode(array $payload): string
    {
        $header = ['alg' => 'HS256', 'typ' => 'JWT'];
        $h = self::base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $p = self::base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $h . '.' . $p, self::secret(), true);
        $s = self::base64UrlEncode($signature);
        return $h . '.' . $p . '.' . $s;
    }

    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder !== 0) {
            $data .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
