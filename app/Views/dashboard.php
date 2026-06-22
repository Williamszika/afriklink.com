<?php
/** @var array $user  @var int $completion  @var list<string> $missing  @var ?string $avatar_version
 *  @var array{listings:int,sold:int} $counts  @var list<array> $recent  @var array<int,string> $recent_mains */
use App\Services\CloudinaryService;
$hasEmail      = !empty($user['email']);
$verifiedEmail = !empty($user['email_verified_at']);
$contact       = $hasEmail ? (string) $user['email'] : (string) ($user['phone'] ?? '');
$fullName      = trim((string) ($user['full_name'] ?? ''));
$nickname      = (string) ($user['nickname'] ?? '');
$firstName     = $fullName !== '' ? explode(' ', $fullName)[0] : ($nickname !== '' ? $nickname : $contact);
$initials      = user_initials($user);
$avatarUrl     = avatar_url($user, $avatar_version ?? null);

$cc        = strtoupper((string) ($user['country_code'] ?? ''));
$place     = trim(($cc !== '' ? flag_emoji($cc) . ' ' . country_name($cc) : '') .
                  (!empty($user['city']) ? ' · ' . $user['city'] : ''), ' ·');
$birthdate = !empty($user['birthdate']) ? date('d/m/Y', strtotime((string) $user['birthdate'])) : '—';
$genderLbl = !empty($user['gender']) ? t('gender.' . $user['gender']) : '—';
$contactOk = !empty($user['phone']) || $verifiedEmail;

$nListings = (int) ($counts['listings'] ?? 0);
$stats = [
    ['icon' => '🛒', 'key' => 'purchases', 'value' => (int) ($purchase_count ?? 0), 'note' => t('dash.stat.purchases_note'), 'href' => '#buys'],
    ['icon' => '🏷️', 'key' => 'listings',  'value' => $nListings, 'note' => t('dash.stat.listings_note'),'href' => url('/annonces')],
    ['icon' => '💬', 'key' => 'messages',  'value' => 0,          'note' => t('dash.phase', ['n' => 5]), 'href' => url('/bientot/messages')],
];
?>
<section class="dash">

    <!-- Bandeau profil -->
    <div class="panel dash-profile">
        <?php if ($avatarUrl !== null): ?>
            <img class="avatar avatar-img" src="<?= e($avatarUrl) ?>" alt="" width="64" height="64">
        <?php else: ?>
            <div class="avatar" aria-hidden="true"><?= e($initials) ?></div>
        <?php endif; ?>
        <div class="dash-id">
            <h1><?= e(t('dash.welcome', ['name' => $firstName])) ?></h1>
            <p class="dash-sub">
                <?php if ($nickname !== ''): ?>@<?= e($nickname) ?><?php endif; ?>
                <?php if ($place !== ''): ?> · <?= e($place) ?><?php endif; ?>
            </p>
            <p class="dash-sub">
                <?= $hasEmail ? '✉️ ' . e($contact) : '📱 ' . e($contact) ?>
                <?php if ($contactOk): ?>
                    <span class="badge badge-ok"><?= e(t('dash.badge_verified')) ?></span>
                <?php else: ?>
                    <span class="badge badge-warn"><?= e(t('dash.badge_unverified')) ?></span>
                <?php endif; ?>
                <span class="badge badge-neutral"><?= e(t('register.particulier_title')) ?></span>
            </p>
        </div>
    </div>

    <?php if ($hasEmail && !$verifiedEmail): ?>
        <div class="notice notice-warning">
            <p><?= e(t('dashboard.email_unverified')) ?></p>
            <form method="post" action="<?= e(url('/verify-email/resend')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary"><?= e(t('verify.resend')) ?></button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Progression du profil -->
    <div class="panel dash-progress">
        <div class="progress-head">
            <strong><?= e(t('dash.progress', ['pct' => $completion])) ?></strong>
            <span class="muted"><?= $completion ?>%</span>
        </div>
        <div class="progress-track"><div class="progress-fill" style="width: <?= (int) $completion ?>%"></div></div>
        <?php if ($missing !== []): ?>
            <p class="hint">
                <?= e(t('dash.progress_missing')) ?>
                <?= e(implode(' · ', array_map(static fn (string $k): string => t($k), $missing))) ?>
            </p>
        <?php endif; ?>
        <a class="btn btn-ghost btn-sm" href="<?= e(url('/profile')) ?>">
            <?= e(t($missing !== [] ? 'dash.complete_profile' : 'dash.edit_profile')) ?>
        </a>
    </div>

    <!-- Compteurs (cliquables) -->
    <div class="stat-grid">
        <?php foreach ($stats as $s): ?>
            <a class="stat-card" href="<?= e($s['href']) ?>">
                <div class="num"><span aria-hidden="true"><?= $s['icon'] ?></span> <?= (int) $s['value'] ?></div>
                <div class="lbl"><?= e(t('dash.stat.' . $s['key'])) ?></div>
                <div class="phase"><?= e($s['note']) ?></div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Actions rapides -->
    <div class="action-grid">
        <a class="action-card" href="<?= e(url('/vendre')) ?>">
            <span class="action-head">🏷️ <strong><?= e(t('dash.action.sell_title')) ?></strong></span>
            <span class="muted"><?= e(t('dash.action.sell_desc')) ?></span>
        </a>
        <a class="action-card" href="<?= e(url('/profile')) ?>">
            <span class="action-head">👤 <strong><?= e(t('dash.action.profile_title')) ?></strong></span>
            <span class="muted"><?= e(t('dash.action.profile_desc')) ?></span>
        </a>
        <a class="action-card" href="<?= e(url('/')) ?>#verticals">
            <span class="action-head">🧭 <strong><?= e(t('dash.action.explore_title')) ?></strong></span>
            <span class="muted"><?= e(t('dash.action.explore_desc')) ?></span>
        </a>
        <a class="action-card" href="<?= e(url('/affiliation')) ?>">
            <span class="action-head">💸 <strong><?= e(t('aff.title')) ?></strong></span>
            <span class="muted"><?= e(t('aff.dash_card')) ?></span>
        </a>
    </div>

    <!-- Achats / Ventes -->
    <div class="grid-2 dash-cols">
        <div class="panel" id="buys">
            <h2 class="panel-title">🛒 <?= e(t('dash.buys_title')) ?></h2>
            <?php if (empty($purchases)): ?>
                <div class="empty-state">
                    <p><?= e(t('dash.buys_empty')) ?></p>
                    <a class="btn btn-ghost" href="<?= e(url('/')) ?>#verticals"><?= e(t('dash.action.explore_title')) ?></a>
                </div>
            <?php else: ?>
                <ul class="order-list">
                    <?php foreach ($purchases as $o): ?>
                        <li class="order-row">
                            <a class="order-row-main" href="<?= e(url('/boutique/commande/' . $o['public_id'])) ?>">
                                <span class="order-shop"><?= e((string) $o['boutique_name']) ?></span>
                                <span class="muted order-meta"><?= e(date('d/m/Y', strtotime((string) $o['created_at']))) ?> · <?= e(format_price_local((int) $o['total_cents'], (string) $o['currency'])) ?></span>
                            </a>
                            <span class="order-status order-status--<?= e((string) $o['status']) ?>"><?= e(t('order.status.' . $o['status'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p><a class="btn btn-ghost btn-sm" href="<?= e(url('/mes-achats')) ?>"><?= e(t('purchases.see_all')) ?> →</a></p>
            <?php endif; ?>
        </div>
        <div class="panel" id="sales">
            <div class="panel-title-row">
                <h2 class="panel-title">🏷️ <?= e(t('dash.sales_title')) ?></h2>
                <?php if ($recent !== []): ?>
                    <a class="btn btn-ghost btn-sm" href="<?= e(url('/annonces')) ?>"><?= e(t('dash.all_listings')) ?></a>
                <?php endif; ?>
            </div>
            <?php if ($recent === []): ?>
                <div class="empty-state">
                    <p><?= e(t('dash.sales_empty')) ?></p>
                    <a class="btn btn-ghost" href="<?= e(url('/vendre')) ?>"><?= e(t('dash.action.sell_title')) ?></a>
                </div>
            <?php else: ?>
                <div class="mini-listings">
                    <?php foreach ($recent as $l): ?>
                        <a class="mini-listing" href="<?= e(url('/annonce/' . $l['public_id'])) ?>">
                            <?php $main = $recent_mains[(int) $l['id']] ?? null; ?>
                            <?php if ($main !== null): ?>
                                <img src="<?= e(CloudinaryService::imageUrl($main, 96, 72)) ?>" alt="" loading="lazy" width="96" height="72">
                            <?php else: ?>
                                <span class="listing-thumb-empty" aria-hidden="true">🏷️</span>
                            <?php endif; ?>
                            <span class="mini-listing-body">
                                <span class="mini-listing-title"><?= e((string) $l['title']) ?></span>
                                <span class="muted"><?= e(format_price_local((int) $l['price_cents'], (string) $l['currency'])) ?>
                                    · <?= e(t('listing.status.' . $l['status'])) ?></span>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Mes informations -->
    <div class="panel">
        <div class="panel-title-row">
            <h2 class="panel-title"><?= e(t('dash.info_title')) ?></h2>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/profile')) ?>"><?= e(t('profile.edit')) ?></a>
        </div>
        <dl class="meta">
            <dt><?= e(t('field.full_name')) ?></dt><dd><?= $fullName !== '' ? e($fullName) : '—' ?></dd>
            <dt><?= e(t('field.nickname')) ?></dt><dd><?= $nickname !== '' ? '@' . e($nickname) : '—' ?></dd>
            <dt><?= e($hasEmail ? t('field.email') : t('field.phone')) ?></dt><dd><?= e($contact) ?></dd>
            <dt><?= e(t('field.birthdate')) ?></dt><dd><?= e($birthdate) ?></dd>
            <dt><?= e(t('field.gender')) ?></dt><dd><?= e($genderLbl) ?></dd>
            <dt><?= e(t('field.country')) ?></dt><dd><?= $cc !== '' ? flag_emoji($cc) . ' ' . e(country_name($cc)) : '—' ?></dd>
            <dt><?= e(t('field.city')) ?></dt><dd><?= !empty($user['city']) ? e($user['city']) : '—' ?></dd>
            <dt><?= e(t('dashboard.member_since')) ?></dt><dd><?= e(substr((string) ($user['created_at'] ?? ''), 0, 10)) ?></dd>
        </dl>
    </div>

</section>
