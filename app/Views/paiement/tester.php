<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var ?array $boutique  @var array<string,\App\Services\Payment\PaymentProvider> $providers
 *  @var string $chosen  @var string $recommended */
$cur = (string) ($boutique['currency'] ?? 'EUR');
$recommended = $recommended ?? 'simulation';
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1>💳 <?= e(t('pay.tester_title')) ?></h1>
            <p class="muted"><?= e(t('pay.tester_sub')) ?></p>
        </div>

        <div class="panel">
            <h2 class="panel-title"><?= e(t('pay.providers_title')) ?></h2>
            <div class="prov-list">
                <?php foreach ($providers as $key => $p): $ready = $p->isConfigured(); ?>
                    <div class="prov-row">
                        <span class="prov-name"><?= e($p->label()) ?>
                            <?php if ($key === $chosen): ?><span class="badge badge-neutral"><?= e(t('pay.your_choice')) ?></span><?php endif; ?>
                            <?php if ($key === $recommended && $key !== 'simulation'): ?><span class="badge badge-neutral">⭐ <?= e(t('pay.recommended')) ?></span><?php endif; ?>
                        </span>
                        <span class="muted prov-desc"><?= e((string) config('payment.providers.' . $key . '.desc', '')) ?></span>
                        <span class="badge <?= $ready ? 'badge-ok' : 'badge-warn' ?>">
                            <?= $ready ? '✅ ' . e(t('pay.ready')) : '⏳ ' . e(t('pay.to_configure')) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="hint"><?= e(t('pay.env_hint')) ?></p>
        </div>

        <?php if ($boutique !== null): ?>
            <div class="panel">
                <h2 class="panel-title"><?= e(t('pay.run_test')) ?></h2>
                <p class="muted"><?= e(t('pay.run_test_hint', ['provider' => \App\Services\Payment\PaymentProviders::resolve($chosen)->label()])) ?></p>
                <form method="post" action="<?= e(url('/paiement/demarrer')) ?>" class="pay-test-form">
                    <?= csrf_field() ?>
                    <label for="pay-amount"><?= e(t('pay.amount')) ?> (<?= e($cur) ?>)</label>
                    <input type="text" id="pay-amount" name="amount" inputmode="decimal" value="10" maxlength="12">
                    <button type="submit" class="btn btn-primary"><?= e(t('pay.start')) ?> →</button>
                </form>
            </div>
        <?php else: ?>
            <div class="panel"><div class="empty-state"><p><?= e(t('order.need_shop')) ?></p>
                <a class="btn btn-primary" href="<?= e(url('/boutique/creer')) ?>"><?= e(t('shop.cta_create')) ?></a></div></div>
        <?php endif; ?>

        <p><a class="btn btn-ghost btn-sm" href="<?= e(url('/dashboard')) ?>">← <?= e(t('profile.back_dashboard')) ?></a></p>
    </div>
</div>
