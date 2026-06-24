<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Affiliate;
use App\Models\AuditLog;
use App\Models\Boutique;
use App\Models\Product;
use App\Models\Wallet;
use App\Request;

/**
 * Affiliation : lien intelligent « /r/{code} » (clic + cookie 30 j + redirection
 * interne), hub universel ouvert à TOUT membre (lien perso, gains, annuaire des
 * boutiques participantes) et configuration du programme côté vendeur (opt-in).
 */
final class AffiliateController
{
    public function go(Request $request): void
    {
        $code   = (string) $request->param('code', '');
        $target = Affiliate::safeTarget((string) input_string('to', '/'));

        $affiliateId = Affiliate::userIdForCode($code);
        if ($affiliateId !== null) {
            Affiliate::recordClick($affiliateId, $target);
            Affiliate::setRefCookie($code, $target);
        }
        redirect($target);
    }

    /** Hub d'affiliation accessible à tout membre connecté (acheteur comme vendeur). */
    public function hub(Request $request): void
    {
        $user    = current_user() ?? [];
        $uid     = (int) ($user['id'] ?? 0);
        // Le lien d'apporteur (et les gains) est réservé aux PARTICULIERS.
        $canEarn = ($user['account_type'] ?? '') !== 'professionnel';

        // Si le membre possède une boutique, on lui propose de configurer son programme.
        $shop    = Boutique::findByUserId($uid);
        $program = null;
        if ($shop !== null) {
            $aff = Boutique::affiliationOf((int) $shop['id']);
            $program = [
                'boutique' => $shop, 'enabled' => $aff['enabled'], 'rate' => $aff['rate'],
                'stats'    => Affiliate::programStats((int) $shop['id']),
                'recent'   => Affiliate::programRecent((int) $shop['id'], 8),
                'series'   => Affiliate::programSeries((int) $shop['id'], 14),
                'top'      => Affiliate::topReferrersForBoutique((int) $shop['id'], 5),
            ];
        }

        // Lien PERSONNEL unique : généré uniquement pour les particuliers.
        $code = $canEarn ? Affiliate::codeFor($uid) : '';
        $data = [
            'user'         => $user,
            'can_earn'     => $canEarn,
            'code'         => $code,
            'link'         => $code !== '' ? url('/r/' . $code) : '',
            'rate'         => Affiliate::RATE_PCT,
            'program'      => $program,
            'stats'        => ['clicks' => 0, 'conversions' => 0, 'earnings' => []],
            'recent'       => [],
            'directory'    => [],
            'dir_products' => [],
            'dir_mains'    => [],
            'wallet'       => null,
            'page_title'   => t('aff.title'),
        ];
        if ($canEarn) {
            $products = Product::participating(12);
            $cur      = Wallet::currencyFor($uid, 'EUR');
            $data['stats']        = Affiliate::statsFor($uid);
            $data['recent']       = Affiliate::recentFor($uid, 10);
            $data['directory']    = Boutique::participating(60);
            $data['dir_products'] = $products;
            $data['dir_mains']    = Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products));
            $cc = (string) ($user['country_code'] ?? '');
            $data['wallet']       = [
                'balance'     => Wallet::balanceCents($uid, $cur),
                'currency'    => $cur,
                'threshold'   => Wallet::thresholdCents($cur),
                'can'         => Wallet::canWithdraw($uid),
                'withdrawals' => Wallet::withdrawalsFor($uid),
                'country'     => $cc,
                'providers'   => payout_providers_for($cc),
            ];
        }
        view('affiliation/hub', $data);
    }

    /** Catalogue des produits affiliés à partager, avec filtres (recherche, catégorie, tri). */
    public function products(Request $request): void
    {
        $user    = current_user() ?? [];
        $uid     = (int) ($user['id'] ?? 0);
        $canEarn = ($user['account_type'] ?? '') !== 'professionnel';
        $code    = $canEarn ? Affiliate::codeFor($uid) : '';
        $filters = [
            'q'        => trim((string) input_string('q', '')),
            'category' => (string) input_string('cat', ''),
            'sort'     => (string) input_string('tri', ''),
        ];
        $products = Product::participating(60, $filters);
        view('affiliation/products', [
            'user'       => $user,
            'code'       => $code,
            'link'       => $code !== '' ? url('/r/' . $code) : '',
            'products'   => $products,
            'mains'      => Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products)),
            'filters'    => $filters,
            'categories' => (array) config('listings.categories', []),
            'page_title' => t('aff.cat_title'),
        ]);
    }

    /** Suivi PAR LIEN : pour chaque lien partagé, clics / ventes / gains. */
    public function links(Request $request): void
    {
        $user    = current_user() ?? [];
        $uid     = (int) ($user['id'] ?? 0);
        $canEarn = ($user['account_type'] ?? '') !== 'professionnel';
        $code    = $canEarn ? Affiliate::codeFor($uid) : '';
        $rows    = Affiliate::linkStats($uid, 100);
        foreach ($rows as &$r) {
            $r['label'] = Affiliate::labelForTarget((string) ($r['target'] ?? ''));
        }
        unset($r);
        view('affiliation/links', [
            'user'       => $user,
            'code'       => $code,
            'link'       => $code !== '' ? url('/r/' . $code) : '',
            'rows'       => $rows,
            'page_title' => t('aff.links_title'),
        ]);
    }

    /** Demande de retrait du solde (commissions d'affiliation) — ouvert à tout membre. */
    public function withdraw(Request $request): void
    {
        $user = current_user() ?? [];
        $uid  = (int) ($user['id'] ?? 0);
        if ($uid <= 0) {
            redirect('/login');
        }
        $method = whitelist((string) input_string('method', 'mobile_money'), ['mobile_money', 'bank'], 'mobile_money');
        $dest   = trim((string) input_string('destination', ''));
        if (mb_strlen($dest) < 4) {
            flash('error', t('wallet.err_destination'));
            redirect('/affiliation');
        }
        $pid = Wallet::requestWithdrawal($uid, $method, $dest);
        if ($pid === null) {
            $cur = Wallet::currencyFor($uid, 'EUR');
            flash('error', t('wallet.err_threshold', ['min' => format_price(Wallet::thresholdCents($cur), $cur)]));
        } else {
            AuditLog::record($uid, 'wallet.withdrawal_requested', 'withdrawal', null, [], $request->ipBinary());
            flash('success', t('wallet.requested'));
        }
        redirect('/affiliation');
    }

    /** Écran vendeur : la liste de SES produits, chacun activable en affiliation + son taux. */
    public function vendorProducts(Request $request): void
    {
        $user = current_user() ?? [];
        $uid  = (int) ($user['id'] ?? 0);
        $shop = Boutique::findByUserId($uid);
        if ($shop === null) {
            redirect('/affiliation'); // pas de boutique
        }
        $products = Product::forBoutiqueAffiliation((int) $shop['id']);
        view('affiliation/vendor_products', [
            'user'       => $user,
            'boutique'   => $shop,
            'products'   => $products,
            'mains'      => Product::mainPhotos(array_map(static fn (array $p): int => (int) $p['id'], $products)),
            'max_pct'    => rtrim(rtrim(number_format(affiliate_max_rate_bps() / 100, 1, ',', ''), '0'), ','),
            'keep_pct'   => rtrim(rtrim(number_format(affiliate_platform_keep_pct(), 1, ',', ''), '0'), ','),
            'page_title' => t('aff.vp_title'),
        ]);
    }

    /** Enregistre les choix d'affiliation par produit (activation + taux), pour SES produits. */
    public function vendorProductsSave(Request $request): void
    {
        $user = current_user() ?? [];
        $uid  = (int) ($user['id'] ?? 0);
        $shop = Boutique::findByUserId($uid);
        if ($shop === null) {
            redirect('/affiliation');
        }
        $enabled = is_array($_POST['enabled'] ?? null) ? $_POST['enabled'] : [];
        $rates   = is_array($_POST['rate'] ?? null) ? $_POST['rate'] : [];
        foreach (Product::forBoutiqueAffiliation((int) $shop['id']) as $p) {
            $pid  = (int) $p['id'];
            $isOn = (string) ($enabled[$pid] ?? '') === '1';
            $pct  = (float) str_replace(',', '.', (string) ($rates[$pid] ?? '0'));
            Product::setAffiliation($pid, $uid, $isOn, (int) round($pct * 100));
        }
        flash('success', t('aff.vp_saved'));
        redirect('/affiliation/mes-produits');
    }
}
