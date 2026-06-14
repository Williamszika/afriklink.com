<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url */
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1><?= icon('chat', ['size' => 24]) ?> <?= e(t('seller.nav.messages')) ?></h1>
            <p class="muted"><?= e(t('seller.messages_sub')) ?></p>
        </div>

        <div class="panel">
            <div class="empty-state">
                <p style="margin:0 0 6px" aria-hidden="true"><?= icon('chat', ['size' => 34]) ?></p>
                <p><?= e(t('seller.messages_empty')) ?></p>
                <span class="chip-soon"><?= e(t('dash.phase', ['n' => 5])) ?></span>
            </div>
        </div>

    </div>
</div>
