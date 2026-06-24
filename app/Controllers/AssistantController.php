<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Request;
use App\Services\HelpAssistant;
use App\Models\Boutique;

/**
 * Agnès — assistant d'aide du site. Endpoint de chat (JSON) + centre d'aide
 * (page de repli, sans JS / sans clé IA). Distinct de l'assistant d'achat d'une
 * boutique (BoutiqueController::assistant).
 */
final class AssistantController
{
    /** POST /agnes — une question du visiteur → réponse JSON (texte + captures + liens). */
    public function message(Request $request): void
    {
        if (!HelpAssistant::enabled()) {
            json_response(['text' => '', 'screens' => [], 'links' => [], 'disabled' => true]);
        }
        $msg = trim((string) input_string('message', ''));
        $msg = mb_substr($msg, 0, max(50, (int) config('assistant.input_max', 1500)));

        $hist = $_SESSION['agnes_history'] ?? [];
        if (!is_array($hist)) {
            $hist = [];
        }

        $ctx = self::context($request);
        $reply = HelpAssistant::reply($msg, $hist, $ctx);

        // Historique (user + assistant) conservé en session, plafonné.
        if ($msg !== '') {
            $hist[] = ['role' => 'user', 'text' => $msg];
            if ((string) ($reply['text'] ?? '') !== '') {
                $hist[] = ['role' => 'assistant', 'text' => (string) $reply['text']];
            }
            $_SESSION['agnes_history'] = array_slice($hist, -max(2, (int) config('assistant.history_max', 12)));
        }

        json_response([
            'text'    => (string) ($reply['text'] ?? ''),
            'screens' => array_map(static fn (string $s): array => ['src' => asset('img/help/' . $s . '.png'), 'alt' => $s], (array) ($reply['screens'] ?? [])),
            'links'   => array_map(static fn (array $l): array => ['url' => url((string) $l['path']), 'label' => (string) $l['label']], (array) ($reply['links'] ?? [])),
            'mode'    => (string) ($reply['mode'] ?? 'kb'),
            'name'    => HelpAssistant::name(),
        ]);
    }

    /** GET /aide — centre d'aide : tous les sujets (repli sans JS, et page browsable). */
    public function center(Request $request): void
    {
        $locale = current_locale();
        $topics = [];
        foreach ((array) config('help.topics', []) as $id => $t) {
            $screen = (string) ($t['screen'] ?? '');
            $topics[] = [
                'id'     => (string) $id,
                'text'   => HelpAssistant::topicText((string) $id, $t, $locale),
                'screen' => $screen !== '' ? asset('img/help/' . $screen . '.png') : '',
                'link'   => (string) ($t['link'] ?? ''),
            ];
        }
        view('help/center', [
            'topics'     => $topics,
            'name'       => HelpAssistant::name(),
            'configured' => HelpAssistant::isConfigured(),
            'page_title' => t('agnes.center_title', ['name' => HelpAssistant::name()]),
        ]);
    }

    /** Contexte visiteur transmis à l'assistant (rôle, page, pays, devise, langue). */
    private static function context(Request $request): array
    {
        $uid  = current_user_id();
        $role = 'guest';
        if ($uid !== null) {
            $role = 'buyer';
            try {
                if (Boutique::findByUserId((int) $uid) !== null) {
                    $role = 'seller';
                }
            } catch (\Throwable) {
                // best-effort : pas de contexte boutique → reste « buyer »
            }
        }
        $geo = detected_geo();
        return [
            'locale'   => current_locale(),
            'role'     => $role,
            'path'     => mb_substr((string) input_string('path', '/'), 0, 120),
            'city'     => (string) ($geo['city'] ?? ''),
            'country'  => (string) ($geo['country_code'] ?? ''),
            'currency' => current_currency(),
        ];
    }
}
