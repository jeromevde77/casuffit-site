<?php
// includes/totp.php — TOTP (RFC 6238) pure PHP, sans Composer
// Compatible Google Authenticator, Authy, 1Password, Microsoft Authenticator

class TOTP {
    private static string $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // Génère un secret aléatoire base32
    public static function generateSecret(int $length = 16): string {
        $secret = '';
        for ($i = 0; $i < $length; $i++)
            $secret .= self::$chars[random_int(0, 31)];
        return $secret;
    }

    // Calcule le code TOTP à un instant donné
    public static function getCode(string $secret, ?int $ts = null): string {
        $counter = (int)floor(($ts ?? time()) / 30);
        $key     = self::base32Decode($secret);
        $msg     = pack('N*', 0) . pack('N*', $counter);
        $hash    = hash_hmac('sha1', $msg, $key, true);
        $offset  = ord($hash[19]) & 0x0f;
        $code    = (
            ((ord($hash[$offset])     & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8)  |
            ( ord($hash[$offset + 3]) & 0xff)
        ) % 1000000;
        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    // Vérifie un code (fenêtre ±1 intervalle = ±30s de tolérance)
    public static function verify(string $secret, string $code, int $window = 1): bool {
        $code = preg_replace('/\s/', '', $code);
        if (strlen($code) !== 6 || !ctype_digit($code)) return false;
        $ts = time();
        for ($i = -$window; $i <= $window; $i++)
            if (self::getCode($secret, $ts + $i * 30) === $code) return true;
        return false;
    }

    // URI otpauth:// pour le QR code
    public static function getUri(string $secret, string $account, string $issuer = 'Ça suffit !'): string {
        return 'otpauth://totp/'
            . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    // Génère N codes de secours lisibles (format XXXX-XXXX-XXXX)
    public static function generateBackupCodes(int $n = 8): array {
        $codes = [];
        for ($i = 0; $i < $n; $i++) {
            $raw = strtoupper(bin2hex(random_bytes(6)));
            $codes[] = implode('-', str_split($raw, 4)); // ex: A1B2-C3D4-E5F6
        }
        return $codes;
    }

    // Hash d'un code de secours pour stockage
    public static function hashBackupCode(string $code): string {
        return password_hash(strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code)), PASSWORD_DEFAULT);
    }

    // Vérifie un code de secours contre la liste hashée, retourne l'index ou -1
    public static function verifyBackupCode(string $code, array $hashes): int {
        $clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $code));
        foreach ($hashes as $i => $hash)
            if (password_verify($clean, $hash)) return $i;
        return -1;
    }

    // Décodage base32
    private static function base32Decode(string $secret): string {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret));
        $bin = '';
        foreach (str_split($secret) as $c) {
            $pos = strpos(self::$chars, $c);
            if ($pos === false) continue;
            $bin .= sprintf('%05b', $pos);
        }
        $result = '';
        foreach (str_split($bin, 8) as $chunk) {
            if (strlen($chunk) < 8) break;
            $result .= chr(bindec($chunk));
        }
        return $result;
    }
}
