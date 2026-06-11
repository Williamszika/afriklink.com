<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url */
$verticals = [
    ['key' => 'boutique',   'icon' => '🛍️'],
    ['key' => 'restaurant', 'icon' => '🍽️'],
    ['key' => 'salon',      'icon' => '💈'],
    ['key' => 'service',    'icon' => '🛠️'],
];
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1>🏪 <?= e(t('pro.dash.create_title')) ?></h1>
            <p class="muted"><?= e(t('pro.dash.create_desc')) ?></p>
        </div>

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
</div>
