<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\AbandonedCart;
use App\Models\ContentTranslation;
use App\Request;
use App\Services\MailService;
use App\Services\Supervision;
use App\Services\TranslationService;

/**
 * Tâches planifiées (déclenchées par Vercel Cron ou un planificateur externe).
 * Endpoint protégé par CRON_SECRET : tant que la variable n'est pas définie,
 * l'endpoint est désactivé (404) — jamais d'exécution ouverte au public.
 */
final class CronController
{
    /** Relance des paniers abandonnés (acheteurs connectés inactifs). */
    public function abandonedCart(Request $request): void
    {
        $this->authorize();

        $due  = AbandonedCart::due(
            (int) ($_ENV['CART_REMIND_AFTER_MIN'] ?? 120),
            (int) ($_ENV['CART_REMIND_MAX_AGE_H'] ?? 336),
            200
        );
        $sent = 0;
        foreach ($due as $row) {
            $resolved = AbandonedCart::resolveItems((string) $row['items_json']);
            // Tous les produits ont disparu (retirés/épuisés) : on ne relance pas.
            if ($resolved['count'] === 0) {
                AbandonedCart::markReminded((int) $row['id']);
                continue;
            }
            try {
                if (MailService::send(
                    (string) $row['email'],
                    t('cart.remind.subject'),
                    self::recoveryHtml($resolved, (string) $row['token']),
                    self::recoveryText($resolved)
                )) {
                    $sent++;
                }
            } catch (\Throwable) {
                // best-effort
            }
            AbandonedCart::markReminded((int) $row['id']);
        }

        json_response(['checked' => count($due), 'sent' => $sent]);
    }

    /**
     * Pré-traduit le contenu vendeur (noms/descriptions de produits & boutiques)
     * dans toutes les langues du site, par lots bornés (temps + coût). Actif
     * seulement si une clé de traduction est configurée ; sinon ne fait rien.
     */
    public function translateContent(Request $request): void
    {
        $this->authorize();
        if (!TranslationService::isConfigured()) {
            json_response(['enabled' => false, 'note' => 'no translation API key configured']);
        }
        ContentTranslation::ensureTable();

        $locales  = array_values((array) config('translate.locales', []));
        $maxItems = max(1, (int) config('translate.cron_max_items', 20));
        $maxCalls = max(1, (int) config('translate.cron_max_calls', 80));
        $calls = 0; $translated = 0; $items = 0;

        // Produits actifs sans aucune traduction de nom (contenu neuf), du plus récent au plus ancien.
        $prod = db()->query(
            "SELECT p.id, p.name, p.description, COALESCE(u.locale,'') AS seller_locale
               FROM products p
               JOIN boutiques b ON b.id = p.boutique_id
               LEFT JOIN users u ON u.id = b.user_id
               LEFT JOIN content_translations ct
                      ON ct.ref_type='product' AND ct.ref_id=p.id AND ct.field='name'
              WHERE p.status='active' AND ct.id IS NULL
              ORDER BY p.id DESC LIMIT " . $maxItems
        )->fetchAll() ?: [];
        foreach ($prod as $r) {
            if ($calls >= $maxCalls) {
                break;
            }
            $items++;
            $calls += $this->translateRow('product', (int) $r['id'],
                ['name' => (string) $r['name'], 'description' => (string) ($r['description'] ?? '')],
                $locales, (string) $r['seller_locale'], $maxCalls - $calls, $translated);
        }

        // Boutiques publiées sans traduction de nom.
        if ($calls < $maxCalls) {
            $shops = db()->query(
                "SELECT b.id, b.name, b.tagline, b.description, COALESCE(u.locale,'') AS seller_locale
                   FROM boutiques b
                   LEFT JOIN users u ON u.id = b.user_id
                   LEFT JOIN content_translations ct
                          ON ct.ref_type='boutique' AND ct.ref_id=b.id AND ct.field='name'
                  WHERE b.status='published' AND ct.id IS NULL
                  ORDER BY b.id DESC LIMIT " . $maxItems
            )->fetchAll() ?: [];
            foreach ($shops as $r) {
                if ($calls >= $maxCalls) {
                    break;
                }
                $items++;
                $calls += $this->translateRow('boutique', (int) $r['id'],
                    ['name' => (string) $r['name'], 'tagline' => (string) ($r['tagline'] ?? ''), 'description' => (string) ($r['description'] ?? '')],
                    $locales, (string) $r['seller_locale'], $maxCalls - $calls, $translated);
            }
        }

        json_response(['enabled' => true, 'items' => $items, 'api_calls' => $calls, 'translated' => $translated]);
    }

    /**
     * Traduit les champs d'un objet dans les langues cibles (hors langue source
     * présumée du vendeur), en sautant ce qui est déjà à jour. Renvoie le nombre
     * d'appels API consommés (borné par $budget).
     * @param array<string,string> $fields  @param list<string> $locales
     */
    private function translateRow(string $type, int $id, array $fields, array $locales, string $skipLocale, int $budget, int &$translated): int
    {
        $used = 0;
        foreach ($fields as $field => $text) {
            $text = trim($text);
            if ($text === '') {
                continue;
            }
            $hash = md5($text);
            foreach ($locales as $loc) {
                if ($used >= $budget) {
                    return $used;
                }
                if ($loc === $skipLocale) {
                    continue; // langue d'origine présumée : on garde l'original
                }
                if (ContentTranslation::hasFresh($type, $id, $field, $loc, $hash)) {
                    continue;
                }
                $tr = TranslationService::translate($text, $loc);
                $used++;
                if ($tr !== null) {
                    ContentTranslation::put($type, $id, $field, $loc, $tr, $hash);
                    $translated++;
                }
            }
        }
        return $used;
    }

    /** Agent Supervision : envoie un digest « À surveiller » aux opérateurs si besoin. */
    public function supervision(Request $request): void
    {
        $this->authorize();

        $report = Supervision::report();
        $alerts = $report['alerts'];
        $to     = Supervision::recipients();
        $sent   = 0;

        // On n'envoie que s'il y a quelque chose d'actionnable (pas de bruit).
        if ($alerts !== [] && $to !== []) {
            $html = self::supervisionHtml($report);
            $text = self::supervisionText($report);
            foreach ($to as $email) {
                try {
                    if (MailService::send($email, t('sup.subject', ['n' => count($alerts)]), $html, $text)) {
                        $sent++;
                    }
                } catch (\Throwable) {
                    // best-effort
                }
            }
        }

        json_response(['alerts' => count($alerts), 'recipients' => count($to), 'sent' => $sent]);
    }

    /** @param array{alerts:list<array>,stats:array<string,int>} $report */
    private static function supervisionHtml(array $report): string
    {
        $rows = '';
        foreach ($report['alerts'] as $al) {
            $dot = $al['level'] === 'warn' ? '🟠' : '🔵';
            $rows .= '<tr>'
                . '<td style="padding:9px 0;border-bottom:1px solid #eee">' . $dot . ' ' . e((string) $al['label']) . '</td>'
                . '<td align="right" style="padding:9px 0;border-bottom:1px solid #eee;white-space:nowrap">'
                . '<strong style="color:#103D30">' . (int) $al['count'] . '</strong> · '
                . '<a href="' . e((string) $al['href']) . '" style="color:#B8860B;font-weight:700;text-decoration:none">' . e(t('sup.view')) . '</a></td></tr>';
        }
        $s = $report['stats'];
        $statLine = t('sup.pulse', [
            'orders' => (int) ($s['orders_7d'] ?? 0),
            'shops'  => (int) ($s['boutiques_7d'] ?? 0),
            'subs'   => (int) ($s['subscribers'] ?? 0),
        ]);
        $body = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:2px 0 16px;font-size:.97rem">' . $rows . '</table>'
            . '<div class="afk-panel">📊 ' . e($statLine) . '</div>';

        return render_partial('emails/base', [
            'subject'   => t('sup.subject', ['n' => count($report['alerts'])]),
            'preheader' => t('sup.intro'),
            'heading'   => '🛰️ ' . e(t('sup.heading')),
            'intro'     => e(t('sup.intro')),
            'body'      => $body,
            'cta_url'   => url('/admin'),
            'cta_label' => t('sup.cta'),
            'accent'    => 'forest',
        ]);
    }

    /** @param array{alerts:list<array>,stats:array<string,int>} $report */
    private static function supervisionText(array $report): string
    {
        $t = t('sup.heading') . "\n";
        foreach ($report['alerts'] as $al) {
            $t .= '- ' . $al['label'] . ' : ' . (int) $al['count'] . ' (' . $al['href'] . ")\n";
        }
        return $t . url('/admin');
    }

    /* ---- Routines ajoutées ------------------------------------------ */

    /** MÉNAGE : purge des données expirées + expiration des mises en avant. */
    public function cleanup(Request $request): void
    {
        $this->authorize();
        json_response(['ok' => true, 'cleaned' => $this->cleanupInternal()]);
    }

    /** @return array<string,int|string> nb de lignes nettoyées par table */
    private function cleanupInternal(): array
    {
        return [
            'rate_limits'         => self::safeExec('DELETE FROM rate_limits WHERE window_start < (NOW() - INTERVAL 2 DAY)'),
            'password_resets'     => self::safeExec('DELETE FROM password_resets WHERE used_at IS NOT NULL OR expires_at < (NOW() - INTERVAL 1 DAY)'),
            'email_verifications' => self::safeExec('DELETE FROM email_verifications WHERE verified_at IS NOT NULL OR expires_at < (NOW() - INTERVAL 1 DAY)'),
            'abandoned_carts'     => self::safeExec('DELETE FROM abandoned_carts WHERE updated_at < (NOW() - INTERVAL 60 DAY)'),
            'report_tokens'       => self::safeExec('DELETE FROM storefront_report_tokens WHERE used_at IS NOT NULL OR created_at < (NOW() - INTERVAL 30 DAY)'),
            'sessions'            => self::safeExec('DELETE FROM sessions WHERE last_activity < (UNIX_TIMESTAMP() - 1209600)'),
            'ads_expired'         => self::safeExec("UPDATE ad_campaigns SET status = 'expired' WHERE status = 'active' AND ends_at IS NOT NULL AND ends_at < NOW()"),
        ];
    }

    /** RELANCE D'AVIS : commande livrée il y a ~3 j, sans avis → e-mail acheteur. */
    public function reviewReminders(Request $request): void
    {
        $this->authorize();
        json_response($this->reviewRemindersInternal());
    }

    /** @return array{checked:int,sent:int} */
    private function reviewRemindersInternal(): array
    {
        $sent = 0; $checked = 0;
        try {
            // Fenêtre d'un jour (livré il y a 3→4 j) : chaque commande n'est
            // candidate qu'une seule fois (le cron tourne chaque jour) → 1 relance max.
            $rows = db()->query(
                "SELECT o.id, o.public_id, o.boutique_id, o.buyer_user_id
                   FROM orders o
                  WHERE o.source = 'online' AND o.status = 'delivered' AND o.buyer_user_id > 0
                    AND o.delivered_at IS NOT NULL
                    AND o.delivered_at <  (NOW() - INTERVAL 3 DAY)
                    AND o.delivered_at >= (NOW() - INTERVAL 4 DAY)
                  ORDER BY o.id DESC LIMIT 300"
            )->fetchAll() ?: [];
        } catch (\Throwable) {
            return ['checked' => 0, 'sent' => 0]; // delivered_at non provisionné : on s'abstient
        }
        foreach ($rows as $o) {
            $checked++;
            $buyerId = (int) $o['buyer_user_id'];
            if (self::buyerReviewedOrder($buyerId, (int) $o['id'])) {
                continue; // a déjà laissé un avis : pas de relance
            }
            $user  = \App\Models\User::findById($buyerId);
            $email = trim((string) ($user['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $shop = self::boutiqueName((int) $o['boutique_id']);
            $html = render_partial('emails/base', [
                'subject'   => t('mail.review_reminder.subject'),
                'preheader' => t('mail.review_reminder.intro', ['shop' => $shop]),
                'heading'   => '🌟 ' . e(t('mail.review_reminder.subject')),
                'intro'     => e(t('mail.review_reminder.intro', ['shop' => $shop])),
                'cta_url'   => url('/boutique/commande/' . $o['public_id'] . '#avis'),
                'cta_label' => t('mail.review_reminder.cta'),
                'accent'    => 'gold',
            ]);
            try {
                if (MailService::send($email, t('mail.review_reminder.subject'), $html)) {
                    $sent++;
                }
            } catch (\Throwable) {
            }
        }
        return ['checked' => $checked, 'sent' => $sent];
    }

    /** DIGEST VENDEUR : récap hebdo (commandes à traiter, ventes 7 j, stock bas). */
    public function sellerDigest(Request $request): void
    {
        $this->authorize();
        json_response($this->sellerDigestInternal());
    }

    /** @return array{checked:int,sent:int} */
    private function sellerDigestInternal(): array
    {
        $sent = 0; $checked = 0;
        try {
            $shops = db()->query("SELECT id, user_id FROM boutiques WHERE status = 'published' ORDER BY id ASC LIMIT 2000")->fetchAll() ?: [];
        } catch (\Throwable) {
            return ['checked' => 0, 'sent' => 0];
        }
        foreach ($shops as $shop) {
            $checked++;
            $bid = (int) $shop['id'];
            $newCount = self::scalar("SELECT COUNT(*) FROM orders WHERE boutique_id = :b AND status = 'new'", ['b' => $bid]);
            $sales7   = self::scalar("SELECT COUNT(*) FROM orders WHERE boutique_id = :b AND status <> 'cancelled' AND created_at >= (NOW() - INTERVAL 7 DAY)", ['b' => $bid]);
            $lowStock = self::scalar("SELECT COUNT(*) FROM products WHERE boutique_id = :b AND status = 'active' AND stock IS NOT NULL AND stock <= 3", ['b' => $bid]);
            if ($newCount === 0 && $sales7 === 0 && $lowStock === 0) {
                continue; // rien à dire : pas d'e-mail (anti-bruit)
            }
            $user  = \App\Models\User::findById((int) $shop['user_id']);
            $email = trim((string) ($user['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $lines = '';
            if ($newCount > 0) { $lines .= '<li>' . e(t('mail.digest.line_new', ['n' => $newCount])) . '</li>'; }
            if ($sales7 > 0)   { $lines .= '<li>' . e(t('mail.digest.line_sales', ['n' => $sales7])) . '</li>'; }
            if ($lowStock > 0) { $lines .= '<li>' . e(t('mail.digest.line_lowstock', ['n' => $lowStock])) . '</li>'; }
            $html = render_partial('emails/base', [
                'subject'   => t('mail.digest.subject'),
                'preheader' => t('mail.digest.intro'),
                'heading'   => '📊 ' . e(t('mail.digest.subject')),
                'intro'     => e(t('mail.digest.intro')),
                'body'      => '<ul style="padding-left:18px;margin:6px 0 14px;line-height:1.8">' . $lines . '</ul>',
                'cta_url'   => url('/vendeur'),
                'cta_label' => t('mail.digest.cta'),
                'accent'    => 'forest',
            ]);
            try {
                if (MailService::send($email, t('mail.digest.subject'), $html)) {
                    $sent++;
                }
            } catch (\Throwable) {
            }
        }
        return ['checked' => $checked, 'sent' => $sent];
    }

    /**
     * MASTER QUOTIDIEN : enchaîne les routines journalières en UN seul appel —
     * pratique sur Vercel gratuit (2 crons max). Le digest vendeur ne part que le
     * lundi. Sur Hostinger, on peut au contraire planifier chaque endpoint
     * séparément pour un réglage fin (voir .env.example).
     */
    public function daily(Request $request): void
    {
        $this->authorize();
        $out = [
            'menage'    => self::runQuietly(fn (): array => $this->cleanupInternal()),
            'avis'      => self::runQuietly(fn (): array => $this->reviewRemindersInternal()),
            'commandes' => self::runQuietly(fn (): array => $this->orderRemindersInternal()),
            'securite'  => self::runQuietly(fn (): array => $this->securityAlertInternal()),
            'annonces'  => self::runQuietly(fn (): array => $this->expireListingsInternal()),
        ];
        if (date('N') === '1') { // lundi
            $out['digest'] = self::runQuietly(fn (): array => $this->sellerDigestInternal());
        }
        json_response(['ok' => true, 'ran' => $out]);
    }

    /** RAPPEL COMMANDES NON CONFIRMÉES : vendeur prévenu si une commande 'new'
     *  vient de dépasser 24 h sans être confirmée (1 rappel, sans harcèlement). */
    public function orderReminders(Request $request): void
    {
        $this->authorize();
        json_response($this->orderRemindersInternal());
    }

    /** @return array{checked:int,sent:int} */
    private function orderRemindersInternal(): array
    {
        $sent = 0; $checked = 0;
        try {
            // Vendeurs dont une commande 'new' (online) vient de franchir 24 h
            // (fenêtre 24→48 h) → 1 rappel ; l'âge la sort ensuite de la fenêtre,
            // donc pas de relance quotidienne sur la même commande.
            $rows = db()->query(
                "SELECT DISTINCT o.user_id
                   FROM orders o
                  WHERE o.source = 'online' AND o.status = 'new'
                    AND o.created_at <  (NOW() - INTERVAL 24 HOUR)
                    AND o.created_at >= (NOW() - INTERVAL 48 HOUR)
                  LIMIT 1000"
            )->fetchAll() ?: [];
        } catch (\Throwable) {
            return ['checked' => 0, 'sent' => 0];
        }
        foreach ($rows as $r) {
            $checked++;
            $uid     = (int) $r['user_id'];
            $pending = self::scalar("SELECT COUNT(*) FROM orders WHERE user_id = :u AND source = 'online' AND status = 'new'", ['u' => $uid]);
            if ($pending === 0) {
                continue; // déjà tout confirmé entre-temps
            }
            $user  = \App\Models\User::findById($uid);
            $email = trim((string) ($user['email'] ?? ''));
            if ($email === '') {
                continue;
            }
            $html = render_partial('emails/base', [
                'subject'   => t('mail.order_reminder.subject'),
                'preheader' => t('mail.order_reminder.intro', ['n' => $pending]),
                'heading'   => '📦 ' . e(t('mail.order_reminder.subject')),
                'intro'     => e(t('mail.order_reminder.intro', ['n' => $pending])),
                'cta_url'   => url('/vendeur/commandes'),
                'cta_label' => t('mail.order_reminder.cta'),
                'accent'    => 'gold',
            ]);
            try {
                if (MailService::send($email, t('mail.order_reminder.subject'), $html)) {
                    $sent++;
                }
            } catch (\Throwable) {
            }
        }
        return ['checked' => $checked, 'sent' => $sent];
    }

    /** RAFRAÎCHISSEMENT DES TAUX : met à jour les devises FLOTTANTES (jamais les
     *  parités fixes EUR/XOF/XAF) depuis une API gratuite, en base. */
    public function refreshRates(Request $request): void
    {
        $this->authorize();
        json_response($this->refreshRatesInternal());
    }

    /** @return array<string,mixed> */
    private function refreshRatesInternal(): array
    {
        // Base EUR ; couvre le F CFA. Surcouchable via RATES_API_URL.
        $endpoint = (string) ($_ENV['RATES_API_URL'] ?? 'https://open.er-api.com/v6/latest/EUR');
        // On ne rafraîchit JAMAIS les parités fixes (EUR base, XOF/XAF pegés).
        $fixed  = ['EUR', 'XOF', 'XAF'];
        $wanted = array_diff(
            array_map('strtoupper', array_keys((array) config('currencies.per_eur', []))),
            $fixed
        );
        if ($wanted === []) {
            return ['ok' => true, 'updated' => 0];
        }
        $body = self::httpGet($endpoint);
        if ($body === null) {
            return ['ok' => false, 'error' => 'fetch'];
        }
        $data  = json_decode($body, true);
        $rates = is_array($data) ? ($data['rates'] ?? $data['conversion_rates'] ?? []) : [];
        if (!is_array($rates) || $rates === []) {
            return ['ok' => false, 'error' => 'parse'];
        }
        self::ensureRatesTable();
        $upd  = 0;
        try {
            $stmt = db()->prepare(
                'INSERT INTO currency_rates (code, per_eur, updated_at) VALUES (:c, :r, NOW())
                 ON DUPLICATE KEY UPDATE per_eur = VALUES(per_eur), updated_at = NOW()'
            );
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'db'];
        }
        foreach ($wanted as $code) {
            $val = isset($rates[$code]) ? (float) $rates[$code] : 0.0;
            if ($val <= 0) {
                continue;
            }
            try {
                $stmt->execute(['c' => $code, 'r' => $val]);
                $upd++;
            } catch (\Throwable) {
            }
        }
        return ['ok' => true, 'updated' => $upd];
    }

    private static function ensureRatesTable(): void
    {
        ddl_safe(
            'CREATE TABLE IF NOT EXISTS currency_rates (
                code       CHAR(3) NOT NULL PRIMARY KEY,
                per_eur    DECIMAL(18,6) NOT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            )'
        );
    }

    /** GET HTTPS simple (taux de change) — HTTPS only, sans redirection, borné. */
    private static function httpGet(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }
        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_CONNECTTIMEOUT  => 5,
                CURLOPT_TIMEOUT         => 12,
                CURLOPT_FOLLOWLOCATION  => false,
                CURLOPT_PROTOCOLS       => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT       => 'AfrikaLink/1.0',
            ]);
            $body = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return ($code >= 200 && $code < 300 && is_string($body) && $body !== '') ? $body : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** ALERTE SÉCURITÉ : prévient les admins en cas de PIC d'échecs de connexion. */
    public function securityAlert(Request $request): void
    {
        $this->authorize();
        json_response($this->securityAlertInternal());
    }

    /** @return array<string,mixed> */
    private function securityAlertInternal(): array
    {
        $win       = max(10, min(1440, (int) ($_ENV['SECURITY_ALERT_WINDOW_MIN'] ?? 60)));
        $threshold = max(10, (int) ($_ENV['SECURITY_ALERT_THRESHOLD'] ?? 100));
        $fails = self::scalar("SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at > (NOW() - INTERVAL {$win} MINUTE)", []);
        if ($fails < $threshold) {
            return ['ok' => true, 'fails' => $fails, 'alerted' => false];
        }
        // Au-delà du seuil : on alerte (chiffres agrégés seulement, jamais les
        // identifiants en clair). Le verrouillage par compte + la limite par IP
        // restent la défense ; ceci est une SURVEILLANCE.
        $ips      = self::scalar("SELECT COUNT(DISTINCT ip) FROM login_attempts WHERE success = 0 AND created_at > (NOW() - INTERVAL {$win} MINUTE)", []);
        $accounts = self::scalar("SELECT COUNT(DISTINCT email) FROM login_attempts WHERE success = 0 AND created_at > (NOW() - INTERVAL {$win} MINUTE)", []);
        $to   = Supervision::recipients();
        $sent = 0;
        if ($to !== []) {
            $html = render_partial('emails/base', [
                'subject'   => t('mail.security_alert.subject'),
                'preheader' => t('mail.security_alert.intro', ['n' => $fails, 'm' => $win]),
                'heading'   => '🚨 ' . e(t('mail.security_alert.subject')),
                'intro'     => e(t('mail.security_alert.intro', ['n' => $fails, 'm' => $win])),
                'body'      => '<ul style="padding-left:18px;margin:6px 0 14px;line-height:1.8">'
                    . '<li>' . e(t('mail.security_alert.ips', ['n' => $ips])) . '</li>'
                    . '<li>' . e(t('mail.security_alert.accounts', ['n' => $accounts])) . '</li></ul>',
                'cta_url'   => url('/admin'),
                'cta_label' => t('mail.security_alert.cta'),
                'accent'    => 'gold',
            ]);
            foreach ($to as $email) {
                try {
                    if (MailService::send((string) $email, t('mail.security_alert.subject'), $html)) {
                        $sent++;
                    }
                } catch (\Throwable) {
                }
            }
        }
        return ['ok' => true, 'fails' => $fails, 'alerted' => true, 'sent' => $sent];
    }

    /** EXPIRATION DES ANNONCES : passe en 'expired' les annonces actives inactives
     *  depuis LISTING_EXPIRE_DAYS jours (0 = désactivé). Récupérable par le vendeur. */
    public function expireListings(Request $request): void
    {
        $this->authorize();
        json_response($this->expireListingsInternal());
    }

    /** @return array<string,mixed> */
    private function expireListingsInternal(): array
    {
        $days = (int) ($_ENV['LISTING_EXPIRE_DAYS'] ?? 180);
        if ($days <= 0) {
            return ['ok' => true, 'expired' => 0, 'disabled' => true];
        }
        $days = min(3650, $days);
        // updated_at se rafraîchit à chaque édition/republication → « bumper » une
        // annonce la garde active. 'expired' la sort des listes publiques mais
        // reste visible (et réactivable) par son propriétaire.
        $n = self::safeExec("UPDATE listings SET status = 'expired' WHERE status = 'active' AND updated_at < (NOW() - INTERVAL {$days} DAY)");
        return ['ok' => true, 'expired' => $n];
    }

    /** DELETE/UPDATE de ménage best-effort → nb de lignes, ou 'skip' si indisponible. */
    private static function safeExec(string $sql): int|string
    {
        try {
            $stmt = db()->prepare($sql);
            $stmt->execute();
            return $stmt->rowCount();
        } catch (\Throwable) {
            return 'skip';
        }
    }

    private static function scalar(string $sql, array $args): int
    {
        try {
            $stmt = db()->prepare($sql);
            $stmt->execute($args);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function boutiqueName(int $boutiqueId): string
    {
        try {
            $stmt = db()->prepare('SELECT name FROM boutiques WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $boutiqueId]);
            return (string) ($stmt->fetchColumn() ?: 'AfrikaLink');
        } catch (\Throwable) {
            return 'AfrikaLink';
        }
    }

    private static function buyerReviewedOrder(int $buyerId, int $orderId): bool
    {
        try {
            $stmt = db()->prepare(
                'SELECT 1 FROM reviews r JOIN order_items oi ON oi.product_id = r.product_id
                  WHERE oi.order_id = :o AND r.user_id = :u LIMIT 1'
            );
            $stmt->execute(['o' => $orderId, 'u' => $buyerId]);
            return $stmt->fetchColumn() !== false;
        } catch (\Throwable) {
            return false;
        }
    }

    /** @return array|string résultat de la routine, ou 'error' si elle a échoué. */
    private static function runQuietly(callable $fn): array|string
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function authorize(): void
    {
        $secret = trim((string) ($_ENV['CRON_SECRET'] ?? ''));
        // Secret accepté UNIQUEMENT via l'en-tête « Authorization: Bearer … » —
        // jamais en query string (?key=), qui finirait dans les journaux d'accès
        // (Vercel/Cloudflare), le Referer et l'historique. Vercel Cron envoie cet
        // en-tête automatiquement ; un planificateur externe doit faire de même.
        $auth   = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
        $given  = str_starts_with($auth, 'Bearer ') ? substr($auth, 7) : '';
        if ($secret === '' || $given === '' || !hash_equals($secret, $given)) {
            abort(404);
        }
    }

    /** @param array{lines:list<array>,total_cents:int,currency:string,count:int} $cart */
    private static function recoveryHtml(array $cart, string $token): string
    {
        $rows = '';
        foreach ($cart['lines'] as $l) {
            $rows .= '<tr><td style="padding:7px 0;border-bottom:1px solid #eee">' . (int) $l['qty'] . '× ' . e((string) $l['name'])
                . '</td><td align="right" style="padding:7px 0;border-bottom:1px solid #eee;white-space:nowrap">'
                . e(format_price((int) $l['price_cents'] * (int) $l['qty'], (string) $l['currency'])) . '</td></tr>';
        }
        $body = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:2px 0 14px;font-size:.95rem">' . $rows
            . '<tr><td style="padding:8px 0 0;border-top:2px solid #E5A02E;font-weight:800;color:#103D30">' . e(t('rorder.total')) . '</td>'
            . '<td align="right" style="padding:8px 0 0;border-top:2px solid #E5A02E;font-weight:800;color:#103D30">' . e(format_price((int) $cart['total_cents'], (string) $cart['currency'])) . '</td></tr></table>';

        return render_partial('emails/base', [
            'subject'         => t('cart.remind.subject'),
            'preheader'       => t('cart.remind.intro'),
            'heading'         => '🛒 ' . e(t('cart.remind.heading')),
            'intro'           => e(t('cart.remind.intro')),
            'body'            => $body,
            'cta_url'         => url('/panier'),
            'cta_label'       => t('cart.remind.cta'),
            'accent'          => 'gold',
            'unsubscribe_url' => AbandonedCart::optoutUrl($token),
            'unsub_text'      => t('cart.remind.unsub_pre'),
        ]);
    }

    /** @param array{lines:list<array>,total_cents:int,currency:string} $cart */
    private static function recoveryText(array $cart): string
    {
        $t = t('cart.remind.intro') . "\n";
        foreach ($cart['lines'] as $l) {
            $t .= (int) $l['qty'] . '× ' . $l['name'] . ' — ' . format_price((int) $l['price_cents'] * (int) $l['qty'], (string) $l['currency']) . "\n";
        }
        return $t . t('rorder.total') . ' : ' . format_price((int) $cart['total_cents'], (string) $cart['currency']) . "\n" . url('/panier');
    }
}
