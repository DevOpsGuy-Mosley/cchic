<?php
class TokenManager {
    private static $secretKey = '42f06a5417a7103cb078b4ef656f47792ab6b46d5fdc06902fa0864735c8875c';
    private static $tokenExpiration = 3600; // 1 heure en secondes

    public static function generateToken($userId) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode([
            'user_id' => $userId,
            'iat' => time(),
            'exp' => time() + self::$tokenExpiration
        ]);

        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, self::$secretKey, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }

    public static function validateToken($token) {
        $tokenParts = explode('.', $token);
        if (count($tokenParts) != 3) {
            return false;
        }

        $header = $tokenParts[0];
        $payload = $tokenParts[1];
        $signature = $tokenParts[2];

        $validSignature = hash_hmac('sha256', $header . "." . $payload, self::$secretKey, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($validSignature));

        if ($base64UrlSignature !== $signature) {
            return false;
        }

        $decodedPayload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $payload)), true);
        
        if (isset($decodedPayload['exp']) && $decodedPayload['exp'] < time()) {
            return false;
        }

        return $decodedPayload;
    }

    public static function refreshToken($token) {
        $payload = self::validateToken($token);
        if (!$payload) {
            return false;
        }
        return self::generateToken($payload['user_id']);
    }
}
?> 