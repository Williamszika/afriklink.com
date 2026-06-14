<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var string $code  @var string $link  @var int $rate
 *  @var array{clicks:int,conversions:int,earnings:array<string,int>} $stats  @var list<array> $recent
 *  @var list<array> $directory  @var ?array $program */
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1><?= icon('users', ['size' => 24]) ?> <?= e(t('aff.title')) ?></h1>
            <p class="muted"><?= e(t('aff.hub_lead')) ?></p>
        </div>

        <?= render_partial('partials/affiliate_hub', [
            'code'      => $code,
            'link'      => $link,
            'rate'      => $rate,
            'stats'     => $stats,
            'recent'    => $recent,
            'directory' => $directory,
            'program'   => $program,
        ]) ?>

    </div>
</div>
