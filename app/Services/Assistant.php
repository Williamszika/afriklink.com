<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Assistant d'achat — répond aux questions fréquentes des clients sur une
 * boutique (livraison, paiement, retours, stock, suivi) et oriente vers le
 * vendeur quand il ne sait pas.
 *
 * Deux modes, sur le modèle du paiement « simulation » :
 *   - « rules »  (défaut) : correspondance par mots-clés, à partir des infos
 *     réelles de la boutique. Actif tout de suite, sans clé.
 *   - « llm »    : si ASSISTANT_API_KEY est défini, on pourra brancher un vrai
 *     modèle ; en attendant, repli automatique sur les règles.
 *
 * Aucune donnée n'est inventée : toutes les réponses dérivent du contexte fourni
 * (paramètres de la boutique). En dernier recours, repli vers le vendeur.
 */
final class Assistant
{
    public static function mode(): string
    {
        $key = env('ASSISTANT_API_KEY');
        return ($key !== null && $key !== '') ? 'llm' : 'rules';
    }

    /**
     * @param array{shop:string,delivery_delay:string,delivery_methods:list<string>,payment_terms:list<string>,payment_methods:list<string>,return_policy:string,wa:string} $ctx
     * @return array{text:string,suggestions:list<string>,handoff:bool,mode:string}
     */
    public static function answer(string $question, array $ctx): array
    {
        if (self::mode() === 'llm') {
            $llm = self::answerWithLlm($question, $ctx);
            if ($llm !== null) {
                return $llm;
            }
        }
        return self::answerWithRules($question, $ctx);
    }

    /** Branchement futur d'un vrai modèle (clé ASSISTANT_API_KEY). Repli règles pour l'instant. */
    private static function answerWithLlm(string $question, array $ctx): ?array
    {
        return null;
    }

    /** @return array{text:string,suggestions:list<string>,handoff:bool,mode:string} */
    private static function answerWithRules(string $question, array $ctx): array
    {
        $q = self::normalize($question);
        $suggest = [t('assistant.s.delivery'), t('assistant.s.payment'), t('assistant.s.return'), t('assistant.s.contact')];

        // Livraison / délais
        if (self::hits($q, ['livr', 'expedi', 'deliver', 'ship', 'delai', 'recevoir', 'envoi', 'combien de temps'])) {
            $methods = self::labels($ctx['delivery_methods'], 'shop.method.');
            $delay   = $ctx['delivery_delay'] !== '' ? t('shop.prep.' . $ctx['delivery_delay']) : t('assistant.unknown');
            $text = $methods !== ''
                ? t('assistant.a.delivery', [':methods' => $methods, ':delay' => $delay])
                : t('assistant.a.delivery_generic', [':delay' => $delay]);
            return self::reply($text, $suggest, false);
        }

        // Paiement
        if (self::hits($q, ['paie', 'payer', 'paye', 'payment', 'pay', 'carte', 'mobile money', 'orange money', 'wave', 'espece', 'cash', 'reglement'])) {
            $methods = self::labels($ctx['payment_methods'], 'shop.paymethod.');
            $terms   = self::labels($ctx['payment_terms'], 'shop.payterm.');
            $text = $methods !== '' || $terms !== ''
                ? t('assistant.a.payment', [
                    ':methods' => $methods !== '' ? $methods : t('assistant.unknown'),
                    ':terms'   => $terms !== '' ? $terms : t('assistant.unknown'),
                ])
                : t('assistant.a.payment_generic');
            return self::reply($text, $suggest, false);
        }

        // Retours / remboursement
        if (self::hits($q, ['retour', 'rembours', 'return', 'refund', 'echange', 'exchange'])) {
            $text = trim($ctx['return_policy']) !== ''
                ? t('assistant.a.return', [':policy' => mb_substr(trim($ctx['return_policy']), 0, 600)])
                : t('assistant.a.return_none');
            return self::reply($text, $suggest, trim($ctx['return_policy']) === '');
        }

        // Stock / disponibilité
        if (self::hits($q, ['stock', 'dispo', 'available', 'taille', 'size', 'quantite', 'reste', 'rupture'])) {
            return self::reply(t('assistant.a.stock'), $suggest, false);
        }

        // Suivi de commande
        if (self::hits($q, ['suivi', 'suivre', 'track', 'commande', 'order', 'ou est', 'statut', 'status'])) {
            return self::reply(t('assistant.a.track'), $suggest, false);
        }

        // Demande explicite de parler à un humain / au vendeur
        if (self::hits($q, ['contact', 'parler', 'humain', 'vendeur', 'appeler', 'telephone', 'whatsapp', 'joindre', 'agent'])) {
            return self::reply(t('assistant.a.contact'), $suggest, true);
        }

        // Salutations
        if (self::hits($q, ['bonjour', 'salut', 'bonsoir', 'coucou', 'hello', 'hi ', 'hey', 'merci', 'thanks'])) {
            return self::reply(t('assistant.a.greeting'), $suggest, false);
        }

        // Repli : on oriente vers le vendeur.
        return self::reply(t('assistant.a.fallback'), $suggest, true);
    }

    /** @param list<string> $suggest @return array{text:string,suggestions:list<string>,handoff:bool,mode:string} */
    private static function reply(string $text, array $suggest, bool $handoff): array
    {
        return ['text' => $text, 'suggestions' => array_values($suggest), 'handoff' => $handoff, 'mode' => self::mode()];
    }

    /** @param list<string> $keys */
    private static function labels(array $keys, string $prefix): string
    {
        $out = [];
        foreach ($keys as $k) {
            $out[] = t($prefix . $k);
        }
        return implode(', ', $out);
    }

    /** @param list<string> $needles */
    private static function hits(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) {
            if ($n !== '' && str_contains($haystack, $n)) {
                return true;
            }
        }
        return false;
    }

    /** minuscule + sans accents, pour une correspondance robuste FR/EN. */
    private static function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        return strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i',
            'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);
    }
}
