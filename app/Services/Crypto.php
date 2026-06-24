<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Chiffrement « au repos » de données sensibles (messages, etc.).
 *
 * Chiffrement AUTHENTIFIÉ (libsodium secretbox — XSalsa20-Poly1305 ; repli
 * AES-256-GCM via OpenSSL). La clé est DÉRIVÉE de APP_KEY (jamais la clé brute),
 * séparée par domaine, de sorte qu'aucune valeur en clair n'est stockée en base :
 * une fuite/un vol de la base ne révèle rien sans APP_KEY (gardée hors-base, en
 * variable d'environnement).
 *
 * Le format est AUTODESCRIPTIF et VERSIONNÉ : « enc:1:<base64(nonce|cipher)> ».
 * decrypt() laisse passer tel quel toute valeur non préfixée (compatibilité avec
 * les anciens messages en clair) ; encrypt() est « fail-safe » : en cas
 * d'indisponibilité on renvoie le texte d'origine plutôt que de perdre la donnée.
 */
final class Crypto
{
    private const PREFIX = 'enc:1:';

    public static function available(): bool
    {
        return function_exists('sodium_crypto_secretbox') || function_exists('openssl_encrypt');
    }

    /** Une clé de chiffrement utilisable est-elle configurée (APP_KEY non vide) ? */
    public static function configured(): bool
    {
        return self::appKey() !== '' && self::available();
    }

    private static function appKey(): string
    {
        $k = (string) (config('app.key', '') ?: (function_exists('env') ? (string) env('APP_KEY', '') : ''));
        return trim($k);
    }

    /** Clé 32 octets dérivée de APP_KEY, séparée par domaine. */
    private static function key(string $domain): string
    {
        return hash_hmac('sha256', 'crypto.' . $domain . '.v1', self::appKey(), true);
    }

    /**
     * Chiffre $plain pour un domaine (« messages » par défaut). Renvoie le texte
     * d'origine si vide, ou si rien n'est disponible (fail-safe, jamais de perte).
     */
    public static function encrypt(string $plain, string $domain = 'messages'): string
    {
        if ($plain === '' || !self::configured()) {
            return $plain;
        }
        $key = self::key($domain);
        try {
            if (function_exists('sodium_crypto_secretbox')) {
                $nonce  = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
                $cipher = sodium_crypto_secretbox($plain, $nonce, $key);
                return self::PREFIX . 's.' . base64_encode($nonce . $cipher);
            }
            // Repli OpenSSL AES-256-GCM (authentifié).
            $iv  = random_bytes(12);
            $tag = '';
            $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if ($cipher === false) {
                return $plain;
            }
            return self::PREFIX . 'o.' . base64_encode($iv . $tag . $cipher);
        } catch (\Throwable) {
            return $plain; // fail-safe
        }
    }

    /**
     * Déchiffre une valeur produite par encrypt(). Renvoie la valeur telle quelle
     * si elle n'est pas préfixée (ancien message en clair). Renvoie '' si la
     * valeur est chiffrée mais indéchiffrable (clé erronée / corruption).
     */
    public static function decrypt(string $value, string $domain = 'messages'): string
    {
        if (!str_starts_with($value, self::PREFIX)) {
            return $value; // clair hérité ou non chiffré
        }
        $body = substr($value, strlen(self::PREFIX));
        $algo = substr($body, 0, 2);          // « s. » sodium, « o. » openssl
        $raw  = base64_decode((string) substr($body, 2), true);
        if ($raw === false || $raw === '') {
            return '';
        }
        try {
            if ($algo === 's.' && function_exists('sodium_crypto_secretbox_open')) {
                $nb     = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
                $plain  = sodium_crypto_secretbox_open(substr($raw, $nb), substr($raw, 0, $nb), self::key($domain));
                return $plain === false ? '' : $plain;
            }
            if ($algo === 'o.' && function_exists('openssl_decrypt')) {
                $iv     = substr($raw, 0, 12);
                $tag    = substr($raw, 12, 16);
                $cipher = substr($raw, 28);
                $plain  = openssl_decrypt($cipher, 'aes-256-gcm', self::key($domain), OPENSSL_RAW_DATA, $iv, $tag);
                return $plain === false ? '' : $plain;
            }
        } catch (\Throwable) {
            return '';
        }
        return '';
    }

    /** Une valeur est-elle déjà chiffrée par ce service ? */
    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }
}
