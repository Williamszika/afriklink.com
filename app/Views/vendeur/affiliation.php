<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var int $rate  @var ?array $program */
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1><?= icon('store', ['size' => 24]) ?> <?= e(t('aff.title')) ?></h1>
            <p class="muted"><?= e(t('aff.vendor_lead')) ?></p>
        </div>

        <?= render_partial('partials/affiliate_hub', [
            'can_earn' => false,
            'program'  => $program,
        ]) ?>

    </div>
</div>
