<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Canaux de contact d'une boutique (WhatsApp, SMS, Telegram, Facebook,
 * Instagram, TikTok) : métadonnées, normalisation à l'enregistrement et
 * construction du lien public cliquable. Le vendeur renseigne ceux qu'il
 * utilise et choisit le canal principal (mis en avant sur la vitrine).
 */
final class ContactChannels
{
    /** Ordre d'affichage. */
    public const CHANNELS = ['whatsapp', 'sms', 'telegram', 'facebook', 'instagram', 'tiktok'];

    /** @var array<string,array{icon:string,label:string,type:string,class:string}> */
    private const META = [
        'whatsapp'  => ['icon' => '💬', 'label' => 'WhatsApp',  'type' => 'phone',  'class' => 'wa'],
        'sms'       => ['icon' => '✉️', 'label' => 'SMS',       'type' => 'phone',  'class' => 'sms'],
        'telegram'  => ['icon' => '✈️', 'label' => 'Telegram',  'type' => 'handle', 'class' => 'tg'],
        'facebook'  => ['icon' => '📘', 'label' => 'Facebook',  'type' => 'fb',     'class' => 'fb'],
        'instagram' => ['icon' => '📸', 'label' => 'Instagram', 'type' => 'handle', 'class' => 'ig'],
        'tiktok'    => ['icon' => '🎵', 'label' => 'TikTok',    'type' => 'handle', 'class' => 'tt'],
    ];

    /** @return array{icon:string,label:string,type:string,class:string}|null */
    public static function meta(string $channel): ?array
    {
        return self::META[$channel] ?? null;
    }

    /** Valeur normalisée pour stockage, ou null si vide/invalide. */
    public static function normalize(string $channel, string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        return match (self::META[$channel]['type'] ?? 'handle') {
            'phone' => self::normPhone($raw),
            'fb'    => self::normFacebook($raw),
            default => self::normHandle($raw),
        };
    }

    private static function normPhone(string $raw): ?string
    {
        $plus   = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === null || strlen($digits) < 6 || strlen($digits) > 18) {
            return null;
        }
        return ($plus ? '+' : '') . $digits;
    }

    /** Pseudo : accepte une URL complète, @pseudo ou pseudo. */
    private static function normHandle(string $raw): ?string
    {
        if (preg_match('#https?://#i', $raw)) {
            $path  = (string) (parse_url($raw, PHP_URL_PATH) ?: '');
            $parts = array_values(array_filter(explode('/', $path)));
            $raw   = end($parts) ?: '';
        }
        $raw = preg_replace('/[^A-Za-z0-9._]/', '', ltrim($raw, '@'));
        return $raw !== null && $raw !== '' ? mb_substr($raw, 0, 60) : null;
    }

    /** Facebook : pseudo simple OU URL de page (on garde l'URL telle quelle car
     *  les pages FB ont des formats variés : profile.php?id=, /pages/…). */
    private static function normFacebook(string $raw): ?string
    {
        if (stripos($raw, 'facebook.com') !== false || stripos($raw, 'fb.com') !== false) {
            if (!preg_match('~^https?://~i', $raw)) {
                $raw = 'https://' . ltrim($raw, '/');
            }
            return mb_substr($raw, 0, 150);
        }
        $h = preg_replace('~[^A-Za-z0-9.]~', '', ltrim($raw, '@'));
        return $h !== null && $h !== '' ? mb_substr($h, 0, 80) : null;
    }

    /** Lien public pour une valeur stockée. */
    public static function url(string $channel, string $value): string
    {
        $digits = (string) preg_replace('/\D+/', '', $value);
        return match ($channel) {
            'whatsapp'  => 'https://wa.me/' . $digits,
            'sms'       => 'sms:' . (str_starts_with($value, '+') ? '+' : '') . $digits,
            'telegram'  => 'https://t.me/' . ltrim($value, '@'),
            'instagram' => 'https://www.instagram.com/' . ltrim($value, '@'),
            'tiktok'    => 'https://www.tiktok.com/@' . ltrim($value, '@'),
            'facebook'  => str_starts_with($value, 'http') ? $value : 'https://www.facebook.com/' . ltrim($value, '@'),
            default     => '#',
        };
    }

    /**
     * Canaux renseignés d'une boutique + canal principal effectif.
     * @return array{0: array<string,string>, 1: string} [canaux=>valeur, principal]
     */
    public static function forBoutique(array $boutique): array
    {
        $set = [];
        foreach (self::CHANNELS as $ch) {
            $v = (string) ($boutique['contact_' . $ch] ?? '');
            if ($v !== '') {
                $set[$ch] = $v;
            }
        }
        $primary = (string) ($boutique['contact_primary'] ?? '');
        if (!isset($set[$primary])) {
            $primary = (string) (array_key_first($set) ?? '');
        }
        return [$set, $primary];
    }
}
