<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url */
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1>📦 <?= e(t('seller.orders_title')) ?></h1>
            <p class="muted"><?= e(t('seller.orders_sub')) ?></p>
        </div>

        <div class="panel">
            <div class="empty-state">
                <p style="font-size:2rem;margin:0 0 6px" aria-hidden="true">📦</p>
                <p><?= e(t('seller.orders_empty')) ?></p>
                <span class="chip-soon"><?= e(t('dash.phase', ['n' => 3])) ?></span>
            </div>
        </div>

    </div>
</div>
