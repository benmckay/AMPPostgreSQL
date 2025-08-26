<?php

class JWT {
    public static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    public static function sign(array $payload, string $secret, array $header = ['alg' => 'HS256', 'typ' => 'JWT']): string {
        $headerJson = json_encode($header);
        $payloadJson = json_encode($payload);
        $segments = [self::base64UrlEncode($headerJson), self::base64UrlEncode($payloadJson)];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = self::base64UrlEncode($signature);
        return implode('.', $segments);
    }

    public static function verify(string $token, string $secret, string $aud = null, string $iss = null): array {
        $parts = explode('.', $token);
        if (count($parts) !== 3) throw new Exception('Invalid token');
        [$h, $p, $s] = $parts;
        $header = json_decode(self::base64UrlDecode($h), true);
        $payload = json_decode(self::base64UrlDecode($p), true);
        $sig = self::base64UrlDecode($s);
        $expected = hash_hmac('sha256', $h . '.' . $p, $secret, true);
        if (!hash_equals($expected, $sig)) throw new Exception('Signature mismatch');
        $now = time();
        if (isset($payload['exp']) && $payload['exp'] < $now) throw new Exception('Token expired');
        if ($aud !== null && isset($payload['aud']) && $payload['aud'] !== $aud) throw new Exception('Invalid audience');
        if ($iss !== null && isset($payload['iss']) && $payload['iss'] !== $iss) throw new Exception('Invalid issuer');
        return $payload;
    }
}


