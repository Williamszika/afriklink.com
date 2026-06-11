<?php
/** @var array $user  @var array $profile  @var ?string $avatar_version */
$companyName = (string) ($profile['company_name'] ?? ($user['full_name'] ?? ''));
$verified    = ($profile['verification_status'] ?? 'pending') === 'verified';
$emailOk     = !empty($user['email_verified_at']);
$hasReg      = !empty($profile['reg_number']);
$avatarUrl   = avatar_url($user, $avatar_version ?? null);
$cc          = strtoupper((string) ($user['country_code'] ?? ''));

$verticals = [
    ['key' => 'boutique',   'icon' => '🛍️'],
    ['key' => 'restaurant', 'icon' => '🍽️'],
    ['key' => 'salon',      'icon' => '💈'],
    ['key' => 'service',    'icon' => '🛠️'],
];

$legalLabel = t('pro.legal.' . ($profile['legal_form'] ?? 'autre'));
?>
<section class="profile">

    <!-- En-tête entreprise -->
    <div class="panel dash-profile">
        <?php if ($avatarUrl !== null): ?>
            <img class="avatar avatar-img" src="<?= e($avatarUrl) ?>" alt="" width="64" height="64">
        <?php else: ?>
            <div class="avatar" aria-hidden="true">🏢</div>
        <?php endif; ?>
        <div class="dash-id">
            <h1><?= e(t('pro.dash.welcome', ['name' => $companyName])) ?></h1>
            <p class="dash-sub">
                <?php if ($cc !== ''): ?><?= flag_emoji($cc) ?> <?= e(country_name($cc)) ?><?php endif; ?>
                <?php if (!empty($user['city'])): ?> · <?= e((string) $user['city']) ?><?php endif; ?>
                · <?= e($legalLabel) ?>
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
    </div>

    <?php if (!$verified): ?>
        <div class="notice notice-info">
            <p><?= e(t('pro.dash.pending_note')) ?></p>
        </div>
    <?php endif; ?>

    <!-- Checklist de démarrage -->
    <div class="panel">
        <h2 class="panel-title">🚀 <?= e(t('pro.dash.checklist_title')) ?></h2>
        <ul class="checklist">
            <li class="<?= $emailOk ? 'done' : '' ?>">
                <span class="check-ico"><?= $emailOk ? '✅' : '⬜' ?></span>
                <?= e(t('pro.dash.check_email')) ?>
                <?php if (!$emailOk): ?>
                    — <a href="<?= e(url('/verify-email/notice')) ?>"><?= e(t('verify.resend')) ?></a>
                <?php endif; ?>
            </li>
            <li class="<?= $hasReg ? 'done' : '' ?>">
                <span class="check-ico"><?= $hasReg ? '✅' : '⬜' ?></span>
                <?= e(t('pro.dash.check_reg')) ?>
            </li>
            <li>
                <span class="check-ico">⬜</span>
                <?= e(t('pro.dash.check_storefront')) ?>
            </li>
        </ul>
    </div>

    <!-- Créer sa vitrine -->
    <div class="panel">
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

    <!-- Informations entreprise -->
    <div class="panel">
        <h2 class="panel-title">🏢 <?= e(t('pro.dash.info_title')) ?></h2>
        <dl class="meta">
            <dt><?= e(t('pro.field.company_name')) ?></dt><dd><?= e($companyName) ?></dd>
            <?php if (!empty($profile['legal_name'])): ?>
                <dt><?= e(t('pro.field.legal_name')) ?></dt><dd><?= e((string) $profile['legal_name']) ?></dd>
            <?php endif; ?>
            <dt><?= e(t('pro.field.legal_form')) ?></dt><dd><?= e($legalLabel) ?></dd>
            <dt><?= e(t('pro.field.reg_number')) ?></dt>
            <dd><?= $hasReg ? e((string) $profile['reg_number']) : '<span class="muted">' . e(t('pro.recap_none')) . '</span>' ?></dd>
            <?php if (!empty($profile['vat_number'])): ?>
                <dt><?= e(t('pro.field.vat_number')) ?></dt><dd><?= e((string) $profile['vat_number']) ?></dd>
            <?php endif; ?>
            <dt><?= e(t('pro.field.address')) ?></dt><dd><?= e((string) ($profile['address'] ?? '')) ?></dd>
            <dt><?= e(t('pro.field.email')) ?></dt><dd><?= e((string) ($user['email'] ?? '')) ?></dd>
            <dt><?= e(t('field.phone')) ?></dt>
            <dd><?= e((string) ($user['phone'] ?? '')) ?><?= !empty($profile['whatsapp_optin']) ? ' · WhatsApp ✓' : '' ?></dd>
            <?php if (!empty($profile['website'])): ?>
                <dt><?= e(t('pro.field.website')) ?></dt>
                <dd><a href="<?= e((string) $profile['website']) ?>" rel="noopener nofollow" target="_blank"><?= e((string) $profile['website']) ?></a></dd>
            <?php endif; ?>
            <dt><?= e(t('pro.field.contact_person')) ?></dt><dd><?= e((string) ($user['full_name'] ?? '')) ?></dd>
            <dt><?= e(t('dashboard.member_since')) ?></dt><dd><?= e(substr((string) ($user['created_at'] ?? ''), 0, 10)) ?></dd>
        </dl>
    </div>

</section>
