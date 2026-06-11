<?php
/** @var array $user  @var array $profile  @var ?string $avatar_version  @var int $completion */
$companyName = (string) ($profile['company_name'] ?? ($user['full_name'] ?? ''));
$verified    = ($profile['verification_status'] ?? 'pending') === 'verified';
$emailOk     = !empty($user['email_verified_at']);
$hasLogo     = ($avatar_version ?? null) !== null;
$hasReg      = !empty($profile['reg_number']);
$hasDesc     = !empty($profile['description']);
$avatarUrl   = avatar_url($user, $avatar_version ?? null);
$cc          = strtoupper((string) ($user['country_code'] ?? ''));
$cur         = (string) ($user['preferred_currency'] ?? 'EUR');

$legalLabel = !empty($profile['legal_form']) ? t('pro.legal.' . $profile['legal_form']) : null;

$stats = [
    ['icon' => '💰', 'label' => t('seller.stat.revenue'), 'value' => format_price(0, $cur), 'note' => t('dash.phase', ['n' => 3])],
    ['icon' => '🧾', 'label' => t('seller.stat.orders'),  'value' => '0',                   'note' => t('dash.phase', ['n' => 3])],
    ['icon' => '👁️', 'label' => t('seller.stat.views'),   'value' => '0',                   'note' => t('dash.phase', ['n' => 4])],
    ['icon' => '💬', 'label' => t('dash.stat.messages'),  'value' => '0',                   'note' => t('dash.phase', ['n' => 5])],
];

$verticals = [
    ['key' => 'boutique',   'icon' => '🛍️'],
    ['key' => 'restaurant', 'icon' => '🍽️'],
    ['key' => 'salon',      'icon' => '💈'],
    ['key' => 'service',    'icon' => '🛠️'],
];

$checklist = [
    ['done' => $emailOk,  'label' => t('pro.dash.check_email'),       'href' => $emailOk ? null : url('/verify-email/notice')],
    ['done' => $hasLogo,  'label' => t('seller.check_logo'),          'href' => $hasLogo ? null : '#dash-logo'],
    ['done' => $hasDesc,  'label' => t('seller.check_description'),   'href' => $hasDesc ? null : url('/vendeur/profil')],
    ['done' => $hasReg,   'label' => t('pro.dash.check_reg'),         'href' => $hasReg ? null : url('/vendeur/profil')],
    ['done' => false,     'label' => t('pro.dash.check_storefront'),  'href' => '#vitrines'],
];
?>
<section class="profile">

    <!-- Identité entreprise + logo -->
    <div class="panel dash-profile" id="dash-logo">
        <?php if ($avatarUrl !== null): ?>
            <img class="avatar avatar-img" src="<?= e($avatarUrl) ?>" alt="" width="64" height="64">
        <?php else: ?>
            <div class="avatar" aria-hidden="true">🏪</div>
        <?php endif; ?>
        <div class="dash-id">
            <h1><?= e(t('pro.dash.welcome', ['name' => $companyName])) ?></h1>
            <p class="dash-sub">
                <?php if ($cc !== ''): ?><?= flag_emoji($cc) ?> <?= e(country_name($cc)) ?><?php endif; ?>
                <?php if (!empty($user['city'])): ?> · <?= e((string) $user['city']) ?><?php endif; ?>
                <?php if ($legalLabel !== null): ?> · <?= e($legalLabel) ?><?php endif; ?>
            </p>
            <p class="dash-sub">
                <span class="badge badge-neutral"><?= e(t('pro.dash.badge_pro')) ?></span>
                <?php if ($verified): ?>
                    <span class="badge badge-ok">✓ <?= e(t('pro.dash.badge_verified')) ?></span>
                <?php else: ?>
                    <span class="badge badge-warn"><?= e(t('pro.dash.badge_pending')) ?></span>
                <?php endif; ?>
            </p>
        </div>
        <div class="dash-head-actions">
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/vendeur/profil')) ?>"><?= e(t('seller.profile_title')) ?> →</a>
            <div class="avatar-forms">
                <form method="post" action="<?= e(url('/profile/photo')) ?>" enctype="multipart/form-data" class="avatar-upload">
                    <?= csrf_field() ?>
                    <input type="file" id="avatar-input" name="photo" accept="image/jpeg,image/png,image/webp" required>
                    <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('seller.logo_change')) ?></button>
                </form>
                <?php if ($avatarUrl !== null): ?>
                    <form method="post" action="<?= e(url('/profile/photo/delete')) ?>" class="inline-form">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('profile.photo_delete')) ?></button>
                    </form>
                <?php endif; ?>
                <?php if (has_error('photo')): ?><p class="field-error"><?= e(error('photo')) ?></p><?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!$verified): ?>
        <div class="notice notice-info">
            <p><?= e(t('pro.dash.pending_note')) ?></p>
        </div>
    <?php endif; ?>

    <!-- Compteurs (zéros honnêtes, datés par phase) -->
    <div class="stat-grid cols-4">
        <?php foreach ($stats as $s): ?>
            <div class="stat-card">
                <div class="num"><span aria-hidden="true"><?= $s['icon'] ?></span> <?= e($s['value']) ?></div>
                <div class="lbl"><?= e($s['label']) ?></div>
                <div class="phase"><?= e($s['note']) ?></div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Complétion + checklist -->
    <div class="panel dash-progress">
        <div class="progress-head">
            <strong><?= e(t('seller.completion', ['pct' => $completion])) ?></strong>
            <span class="muted"><?= (int) $completion ?>%</span>
        </div>
        <div class="progress-track"><div class="progress-fill" style="width: <?= (int) $completion ?>%"></div></div>
        <ul class="checklist" style="margin-top:14px">
            <?php foreach ($checklist as $item): ?>
                <li class="<?= $item['done'] ? 'done' : '' ?>">
                    <span class="check-ico"><?= $item['done'] ? '✅' : '⬜' ?></span>
                    <?php if (!$item['done'] && $item['href'] !== null): ?>
                        <a href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
                    <?php else: ?>
                        <?= e($item['label']) ?>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <!-- Créer sa vitrine -->
    <div class="panel" id="vitrines">
        <h2 class="panel-title">✨ <?= e(t('pro.dash.create_title')) ?></h2>
        <p class="muted"><?= e(t('pro.dash.create_desc')) ?></p>
        <div class="action-grid">
            <?php foreach ($verticals as $v): ?>
                <a class="action-card" href="<?= e(url('/bientot/' . $v['key'])) ?>">
                    <span class="action-head"><?= $v['icon'] ?> <strong><?= e(t('pro.vertical.' . $v['key'])) ?></strong>
                        <span class="chip-soon"><?= e(t('dash.soon')) ?></span></span>
                    <span class="muted"><?= e(t('pro.vertical.' . $v['key'] . '_desc')) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="dash-cols">
        <!-- Commandes récentes (phase 3) -->
        <div class="panel">
            <h2 class="panel-title">📦 <?= e(t('seller.orders_title')) ?></h2>
            <div class="empty-state">
                <p><?= e(t('seller.orders_empty')) ?></p>
            </div>
        </div>

        <!-- Conseils -->
        <div class="panel">
            <h2 class="panel-title">💡 <?= e(t('seller.tips_title')) ?></h2>
            <ul class="tips">
                <li>📷 <?= e(t('seller.tip1')) ?></li>
                <li>⚡ <?= e(t('seller.tip2')) ?></li>
                <li>🛡️ <?= e(t('seller.tip3')) ?></li>
            </ul>
        </div>
    </div>

    <!-- Informations entreprise -->
    <div class="panel">
        <div class="panel-title-row">
            <h2 class="panel-title">🏢 <?= e(t('pro.dash.info_title')) ?></h2>
            <a class="btn btn-ghost btn-sm" href="<?= e(url('/vendeur/profil')) ?>">
                <?= $hasReg ? e(t('profile.edit')) : e(t('pro.dash.complete')) ?>
            </a>
        </div>
        <dl class="meta">
            <dt><?= e(t('pro.field.company_name')) ?></dt><dd><?= e($companyName) ?></dd>
            <?php if (!empty($profile['legal_name'])): ?>
                <dt><?= e(t('pro.field.legal_name')) ?></dt><dd><?= e((string) $profile['legal_name']) ?></dd>
            <?php endif; ?>
            <?php if ($legalLabel !== null): ?>
                <dt><?= e(t('pro.field.legal_form')) ?></dt><dd><?= e($legalLabel) ?></dd>
            <?php endif; ?>
            <dt><?= e(t('pro.field.reg_number')) ?></dt>
            <dd><?= $hasReg ? e((string) $profile['reg_number']) : '<span class="muted">' . e(t('pro.recap_none')) . '</span>' ?></dd>
            <?php if (!empty($profile['vat_number'])): ?>
                <dt><?= e(t('pro.field.vat_number')) ?></dt><dd><?= e((string) $profile['vat_number']) ?></dd>
            <?php endif; ?>
            <?php if (!empty($profile['address'])): ?>
                <dt><?= e(t('pro.field.address')) ?></dt><dd><?= e((string) $profile['address']) ?></dd>
            <?php endif; ?>
            <dt><?= e(t('pro.field.email')) ?></dt><dd><?= e((string) ($user['email'] ?? '')) ?></dd>
            <dt><?= e(t('field.phone')) ?></dt>
            <dd><?= e((string) ($user['phone'] ?? '')) ?><?= !empty($profile['whatsapp_optin']) ? ' · WhatsApp ✓' : '' ?></dd>
            <?php if (!empty($profile['website'])): ?>
                <dt><?= e(t('pro.field.website')) ?></dt>
                <dd><a href="<?= e((string) $profile['website']) ?>" rel="noopener nofollow" target="_blank"><?= e((string) $profile['website']) ?></a></dd>
            <?php endif; ?>
            <?php if (!empty($profile['languages'])): ?>
                <dt><?= e(t('pro.field.languages')) ?></dt>
                <dd><?= e(implode(' · ', array_map(static fn (string $l): string => t('pro.lang.' . $l), explode(',', (string) $profile['languages'])))) ?></dd>
            <?php endif; ?>
            <dt><?= e(t('pro.field.contact_person')) ?></dt><dd><?= e((string) ($user['full_name'] ?? '')) ?></dd>
            <dt><?= e(t('dashboard.member_since')) ?></dt><dd><?= e(substr((string) ($user['created_at'] ?? ''), 0, 10)) ?></dd>
        </dl>
    </div>

</section>
