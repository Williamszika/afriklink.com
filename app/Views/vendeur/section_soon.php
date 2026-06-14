<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var string $icon  @var string $prefix  liste de clés <prefix>_title/_lead/_b1.._b3 */
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1><?= icon($icon, ['size' => 22]) ?> <?= e(t($prefix . '_title')) ?> <span class="chip-soon"><?= e(t('dash.soon')) ?></span></h1>
            <p class="muted"><?= e(t($prefix . '_lead')) ?></p>
        </div>

        <div class="panel">
            <ul class="tips">
                <li><?= icon('check', ['size' => 16]) ?> <?= e(t($prefix . '_b1')) ?></li>
                <li><?= icon('check', ['size' => 16]) ?> <?= e(t($prefix . '_b2')) ?></li>
                <li><?= icon('check', ['size' => 16]) ?> <?= e(t($prefix . '_b3')) ?></li>
            </ul>
        </div>

    </div>
</div>
