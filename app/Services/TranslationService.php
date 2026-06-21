<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Traduction automatique de courts contenus (noms, descriptions de produits et
 * boutiques) via une API. S'active dès qu'une clé est présente (config/translate)
 * sinon translate() renvoie null et l'appelant garde le texte d'origine.
 *
 * Fournisseur par défaut : Anthropic (Claude). L'appel est volontairement
 * minimal et robuste : en cas d'erreur réseau/API on renvoie null (jamais
 * d'exception qui casserait un enregistrement produit).
 */
final class TranslationService
{
    private const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';

    /** Noms de langues pour guider le modèle. */
    private const LANG = [
        'fr' => 'French', 'en' => 'English', 'de' => 'German', 'es' => 'Spanish',
        'it' => 'Italian', 'pt' => 'Portuguese', 'nl' => 'Dutch', 'ar' => 'Arabic',
    ];

    public static function isConfigured(): bool
    {
        return self::key() !== '';
    }

    private static function key(): string
    {
        return trim((string) config('translate.api_key', ''));
    }

    /**
     * Traduit $text vers la locale $to. Renvoie null si non configuré, vide,
     * ou en cas d'échec (l'appelant garde alors l'original).
     */
    public static function translate(string $text, string $to): ?string
    {
        $text = trim($text);
        $to   = strtolower(trim($to));
        if ($text === '' || !isset(self::LANG[$to]) || !self::isConfigured()) {
            return null;
        }
        $provider = (string) config('translate.provider', 'anthropic');
        $out = $provider === 'anthropic' ? self::viaAnthropic($text, $to) : null;
        $out = $out !== null ? trim($out) : null;
        // Garde-fou : une traduction vide ou identique à un message d'erreur → null.
        return ($out === null || $out === '') ? null : $out;
    }

    private static function viaAnthropic(string $text, string $to): ?string
    {
        $lang = self::LANG[$to];
        $system = "You are a professional e-commerce translator for a marketplace. "
            . "Translate the user's product/shop text into {$lang}. "
            . "Return ONLY the translation, with no quotes, no notes, no preamble. "
            . "Preserve line breaks, emoji, numbers, units, measurements and brand names. "
            . "Keep it natural and concise for a storefront.";
        $payload = [
            'model'      => (string) config('translate.model', 'claude-haiku-4-5-20251001'),
            'max_tokens' => 1024,
            'system'     => $system,
            'messages'   => [['role' => 'user', 'content' => $text]],
        ];
        $resp = self::http(self::ANTHROPIC_URL, $payload, [
            'x-api-key: ' . self::key(),
            'anthropic-version: 2023-06-01',
            'content-type: application/json',
        ]);
        if ($resp === null) {
            return null;
        }
        // Réponse Anthropic : { content: [ { type:'text', text:'...' } ] }
        $blocks = $resp['content'] ?? [];
        $text   = '';
        foreach ((array) $blocks as $b) {
            if (($b['type'] ?? '') === 'text') {
                $text .= (string) ($b['text'] ?? '');
            }
        }
        return $text !== '' ? $text : null;
    }

    /** @param array<string,mixed> $payload @param list<string> $headers @return array<string,mixed>|null */
    private static function http(string $url, array $payload, array $headers): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
        ]);
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($raw) || $code < 200 || $code >= 300) {
            log_message('warning', 'translate API error', ['code' => $code]);
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
