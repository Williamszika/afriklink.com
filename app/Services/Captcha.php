<?php
declare(strict_types=1);

namespace App\Services;

/**
 * CAPTCHA d'inscription, à double mode :
 *  - « turnstile » : Cloudflare Turnstile dès que TURNSTILE_SITE_KEY et
 *    TURNSTILE_SECRET_KEY existent (vérification serveur via siteverify) ;
 *  - « builtin » sinon : défi arithmétique mémorisé en session, complété par
 *    un pot de miel (champ caché que seuls les robots remplissent) et un
 *    délai minimum de soumission. Actif tout de suite, sans compte externe.
 */
final class Captcha
{
    public static function mode(): string
    {
        return (env('TURNSTILE_SITE_KEY') !== null && env('TURNSTILE_SECRET_KEY') !== null)
            ? 'turnstile'
            : 'builtin';
    }

    /** Prépare un défi arithmétique et le mémorise en session. @return array{a:int,b:int} */
    public static function challenge(): array
    {
        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $_SESSION['captcha'] = ['answer' => $a + $b, 'ts' => time()];
        return ['a' => $a, 'b' => $b];
    }

    /** Vérifie la réponse soumise (l'échec d'un robot ne dit pas pourquoi). */
    public static function verify(): bool
    {
        // Pot de miel : champ invisible pour les humains.
        if (trim((string) ($_POST['website_url'] ?? '')) !== '') {
            return false;
        }

        if (self::mode() === 'turnstile') {
            return self::verifyTurnstile((string) ($_POST['cf-turnstile-response'] ?? ''));
        }

        $c = $_SESSION['captcha'] ?? null;
        unset($_SESSION['captcha']); // usage unique (anti-rejeu)
        if (!is_array($c)) {
            return false;
        }
        $age = time() - (int) ($c['ts'] ?? 0);
        if ($age < 2 || $age > 1800) {
            return false; // soumission instantanée = robot ; défi périmé
        }
        $given = trim((string) ($_POST['captcha_answer'] ?? ''));
        return $given !== '' && ctype_digit($given) && (int) $given === (int) $c['answer'];
    }

    private static function verifyTurnstile(string $token): bool
    {
        if ($token === '') {
            return false;
        }
        try {
            $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
            if ($ch === false) {
                return false;
            }
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_TIMEOUT        => 6,
                CURLOPT_POSTFIELDS     => http_build_query([
                    'secret'   => (string) env('TURNSTILE_SECRET_KEY'),
                    'response' => $token,
                ]),
            ]);
            $body = curl_exec($ch);
            curl_close($ch);
            if (!is_string($body)) {
                return false;
            }
            $json = json_decode($body, true);
            return is_array($json) && ($json['success'] ?? false) === true;
        } catch (\Throwable) {
            return false;
        }
    }
}
