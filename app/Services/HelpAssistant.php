<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Agnès — assistant d'aide SITE d'AfrikaLink (distinct de l'« assistant d'achat »
 * d'une boutique, App\Services\Assistant).
 *
 * Répond aux questions, guide la création de compte / boutique, les premiers
 * pas, et aide à résoudre les problèmes, dans la langue du visiteur, en
 * s'appuyant sur la base de connaissances (config/help.php) et en illustrant
 * par des captures d'écran.
 *
 * Deux modes, le second TOUJOURS disponible :
 *  1. IA (Claude) si une clé est configurée — réponses naturelles, multilingues.
 *  2. Repli base de connaissances (sans clé) — recherche par mots-clés, renvoie
 *     le sujet le mieux apparié avec sa capture et son lien. Jamais cassé.
 *
 * L'IA peut insérer des balises, retirées du texte et renvoyées à part :
 *   [[screen:NAME]]        → affiche public/assets/img/help/NAME.png
 *   [[go:/chemin|Libellé]] → bouton d'action vers une page interne
 */
final class HelpAssistant
{
    private const ANTHROPIC_URL = 'https://api.anthropic.com/v1/messages';

    public static function enabled(): bool
    {
        return (bool) config('assistant.enabled', true);
    }

    public static function isConfigured(): bool
    {
        return self::key() !== '';
    }

    public static function name(): string
    {
        return (string) config('assistant.name', 'Agnès');
    }

    private static function key(): string
    {
        return trim((string) config('assistant.api_key', ''));
    }

    /**
     * Génère une réponse.
     * @param list<array{role:string,text:string}> $history tours précédents
     * @param array<string,mixed> $ctx contexte (locale, auth, role, path, country, currency)
     * @return array{text:string,screens:list<string>,links:list<array{path:string,label:string}>,mode:string}
     */
    public static function reply(string $message, array $history, array $ctx): array
    {
        $message = trim($message);
        if ($message === '') {
            return ['text' => '', 'screens' => [], 'links' => [], 'mode' => 'empty'];
        }
        if (self::isConfigured() && (string) config('assistant.provider', 'anthropic') === 'anthropic'
            && self::withinGlobalBudget()) {
            $ai = self::viaAnthropic($message, $history, $ctx);
            if ($ai !== null) {
                return $ai + ['mode' => 'ai'];
            }
        }
        // Repli base de connaissances (toujours disponible).
        return self::fromKnowledgeBase($message, (string) ($ctx['locale'] ?? 'fr')) + ['mode' => 'kb'];
    }

    /**
     * Garde-fou de COÛT GLOBAL : plafonne le nombre d'appels LLM par heure pour
     * TOUTE la plateforme (et non par IP). Une attaque distribuée sur de
     * nombreuses IP ne peut donc pas faire flamber la facture Anthropic.
     * Fail-CLOSED : base injoignable → on n'appelle PAS le LLM (repli base de
     * connaissances). L'assistant reste utile dans tous les cas.
     */
    private static function withinGlobalBudget(): bool
    {
        $max = max(0, (int) config('assistant.global_hourly_max', 600));
        if ($max === 0) {
            return true; // 0 = pas de plafond global
        }
        return rate_limit_ok('agnes:llm:global', $max, 3600, false);
    }

    /** Captures autorisées (déclarées dans la base) — évite toute référence arbitraire. */
    private static function validScreens(): array
    {
        $out = [];
        foreach ((array) config('help.topics', []) as $t) {
            $s = (string) ($t['screen'] ?? '');
            if ($s !== '') {
                $out[$s] = true;
            }
        }
        return $out;
    }

    /** @return array{text:string,screens:list<string>,links:list<array{path:string,label:string}>}|null */
    private static function viaAnthropic(string $message, array $history, array $ctx): ?array
    {
        $messages = [];
        foreach ($history as $h) {
            $role = ($h['role'] ?? '') === 'assistant' ? 'assistant' : 'user';
            $text = trim((string) ($h['text'] ?? ''));
            if ($text !== '') {
                $messages[] = ['role' => $role, 'content' => $text];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $payload = [
            'model'      => (string) config('assistant.model', 'claude-haiku-4-5-20251001'),
            'max_tokens' => max(200, (int) config('assistant.max_tokens', 800)),
            'system'     => self::systemPrompt($ctx),
            'messages'   => $messages,
        ];
        $resp = self::http($payload);
        if ($resp === null) {
            return null;
        }
        $raw = '';
        foreach ((array) ($resp['content'] ?? []) as $b) {
            if (($b['type'] ?? '') === 'text') {
                $raw .= (string) ($b['text'] ?? '');
            }
        }
        $raw = trim($raw);
        return $raw === '' ? null : self::parseTags($raw);
    }

    private static function systemPrompt(array $ctx): string
    {
        $name    = self::name();
        $screens = implode(', ', array_keys(self::validScreens())) ?: '(none)';

        $role   = (string) ($ctx['role'] ?? 'guest');
        $who    = $role === 'seller' ? 'a logged-in SELLER' : ($role === 'buyer' ? 'a logged-in member' : 'a visitor (not logged in)');
        $here   = (string) ($ctx['path'] ?? '/');
        $locale = (string) ($ctx['locale'] ?? 'fr');
        $city   = trim((string) ($ctx['city'] ?? ''));
        $place  = trim(($city !== '' ? $city . ', ' : '')
            . (string) ($ctx['country'] ?? '') . ' · ' . (string) ($ctx['currency'] ?? ''));

        // Base de connaissances (anglais : ancrage universel ; l'IA traduit).
        $kb = '';
        foreach ((array) config('help.topics', []) as $id => $t) {
            $link   = (string) ($t['link'] ?? '');
            $screen = (string) ($t['screen'] ?? '');
            $kb .= "\n- [{$id}]" . ($link !== '' ? " link:{$link}" : '') . ($screen !== '' ? " screen:{$screen}" : '') . "\n  "
                . trim((string) ($t['en'] ?? $t['fr'] ?? '')) . "\n";
        }

        return <<<SYS
You are {$name}, the warm and helpful assistant of AfrikaLink — an international marketplace bridging Africa and Europe (online shops, restaurants, hair salons, trades/services), multi-country, multi-currency, available in 8 languages.

YOUR JOB: answer questions, help people CREATE ACCOUNTS, take their FIRST STEPS, create shops and products, understand payments / delivery / currency, manage orders, and SOLVE PROBLEMS.

RULES:
- LANGUAGE: the visitor's detected interface language is "{$locale}". Reply in THAT language by default; if they clearly write in another language, switch to it. Match their language for the whole reply.
- LOCATION: adapt your guidance to the visitor's detected location ({$place}) — e.g. the right shop/display currency, the delivery carriers available where they are (local EU, local Côte d'Ivoire, or international), and the local language. Never ask them for info you already have here.
- Be concise, friendly and concrete. Prefer short numbered steps over long paragraphs.
- Ground every answer in the KNOWLEDGE BASE below. Do NOT invent prices, policies, fees or features that are not there. If unsure, say so briefly and point to the relevant page or to support.
- When a screenshot genuinely helps, add a tag ALONE on its own line: [[screen:NAME]] where NAME is EXACTLY one of: {$screens}. Never invent a NAME; use at most one screenshot per reply.
- To guide the user, add an action link: [[go:/path|Short label]] using ONLY internal paths that appear in the knowledge base. You may add 1–2 links.
- Greet briefly only on the first message; afterwards get straight to the help.

VISITOR CONTEXT: {$who}; interface language: {$locale}; current page: {$here}; detected location: {$place}.

KNOWLEDGE BASE:{$kb}
SYS;
    }

    /**
     * Extrait les balises [[screen:..]] et [[go:/path|label]] du texte, les
     * valide, et renvoie le texte nettoyé + les éléments structurés.
     * @return array{text:string,screens:list<string>,links:list<array{path:string,label:string}>}
     */
    private static function parseTags(string $text): array
    {
        $valid   = self::validScreens();
        $screens = [];
        $links   = [];

        $text = (string) preg_replace_callback('/\[\[\s*screen\s*:\s*([a-z0-9_]+)\s*\]\]/i', static function (array $m) use (&$screens, $valid): string {
            $name = strtolower($m[1]);
            if (isset($valid[$name]) && !in_array($name, $screens, true)) {
                $screens[] = $name;
            }
            return '';
        }, $text);

        $text = (string) preg_replace_callback('/\[\[\s*go\s*:\s*(\/[^|\]]*?)\s*\|\s*([^\]]+?)\s*\]\]/i', static function (array $m) use (&$links): string {
            $path  = trim($m[1]);
            $label = trim($m[2]);
            // Lien INTERNE uniquement (commence par « / », pas « // »).
            if ($path !== '' && $path[0] === '/' && !str_starts_with($path, '//') && $label !== '' && count($links) < 3) {
                $links[] = ['path' => $path, 'label' => mb_substr($label, 0, 60)];
            }
            return '';
        }, $text);

        $text = trim((string) preg_replace("/\n{3,}/", "\n\n", $text));

        return ['text' => $text, 'screens' => $screens, 'links' => $links];
    }

    /**
     * Repli SANS IA : meilleur sujet de la base par recouvrement de mots-clés.
     * @return array{text:string,screens:list<string>,links:list<array{path:string,label:string}>}
     */
    private static function fromKnowledgeBase(string $message, string $locale): array
    {
        $hay       = ' ' . self::normalize($message) . ' ';
        $best      = null;
        $bestId    = '';
        $bestScore = 0;
        foreach ((array) config('help.topics', []) as $id => $t) {
            $score = 0;
            foreach ((array) ($t['kw'] ?? []) as $kw) {
                $n = self::normalize((string) $kw);
                if ($n === '') {
                    continue;
                }
                if (str_contains($hay, ' ' . $n . ' ')) {
                    $score += 2; // mot-clé entier
                } elseif (mb_strlen($n) >= 4 && str_contains($hay, $n)) {
                    $score += 1; // sous-chaîne
                }
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $t;
                $bestId = (string) $id;
            }
        }
        if ($best === null) {
            return [
                'text'    => t('agnes.kb_nomatch'),
                'screens' => [],
                'links'   => [
                    ['path' => '/aide', 'label' => t('agnes.center_title')],
                    ['path' => '/register', 'label' => t('agnes.q.account')],
                ],
            ];
        }
        $screen = (string) ($best['screen'] ?? '');
        $link   = (string) ($best['link'] ?? '');
        return [
            'text'    => self::topicText($bestId, $best, $locale),
            'screens' => $screen !== '' ? [$screen] : [],
            'links'   => $link !== '' ? [['path' => $link, 'label' => t('agnes.center_open')]] : [],
        ];
    }

    /**
     * Texte d'un sujet dans la langue du visiteur : traduction i18n help.<id>
     * (les 8 langues) si présente, sinon repli sur le contenu fr/en de
     * config/help.php. Ainsi Agnès « parle toutes les langues » même sans IA.
     */
    public static function topicText(string $id, array $topic, string $locale): string
    {
        $k = 'help.' . $id;
        $tr = t($k);
        if ($tr !== $k && trim($tr) !== '') {
            return $tr;
        }
        $cfg = in_array($locale, ['fr', 'en'], true) ? $locale : 'en';
        return trim((string) ($topic[$cfg] ?? $topic['fr'] ?? ''));
    }

    /** minuscule + sans accents + espaces normalisés, pour comparer les mots-clés. */
    private static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s));
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($ascii) && $ascii !== '') {
                $s = $ascii;
            }
        }
        $s = (string) preg_replace('/[^a-z0-9 ]+/', ' ', $s);
        return trim((string) preg_replace('/\s+/', ' ', $s));
    }

    /** @param array<string,mixed> $payload @return array<string,mixed>|null */
    private static function http(array $payload): ?array
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        $ch = curl_init(self::ANTHROPIC_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER     => [
                'x-api-key: ' . self::key(),
                'anthropic-version: 2023-06-01',
                'content-type: application/json',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 40,
        ]);
        $raw  = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($raw) || $code < 200 || $code >= 300) {
            log_message('warning', 'help-assistant API error', ['code' => $code]);
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }
}
