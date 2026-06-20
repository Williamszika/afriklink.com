<?php
/** @var bool $ok  @var ?string $email  @var string $context  'newsletter' (défaut) | 'cart' */
$context = ($context ?? 'newsletter') === 'cart' ? 'cart' : 'newsletter';
$en = current_locale() === 'en';
$doneTitle = $context === 'cart' ? t('cart.remind.optout_done_title') : t('newsletter.unsub_done_title');
$doneText  = $context === 'cart' ? t('cart.remind.optout_done') : t('newsletter.unsub_done', ['email' => (string) $email]);
?>
<section class="trust-page">
    <p class="muted"><a href="<?= e(url('/')) ?>">← <?= e($en ? 'Home' : 'Accueil') ?></a></p>
    <header class="trust-hero">
        <span class="trust-hero__ic" aria-hidden="true"><?= $ok ? '👋' : '🤔' ?></span>
        <h1 class="afk-h1"><?= e($ok ? $doneTitle : t('newsletter.unsub_fail_title')) ?></h1>
        <p class="trust-lead">
            <?= e($ok ? $doneText : t('newsletter.unsub_fail')) ?>
        </p>
    </header>
    <div class="trust-cta">
        <a class="afk-btn afk-btn--gold afk-btn--lg" href="<?= e(url('/explorer')) ?>"><?= e(t('newsletter.unsub_browse')) ?></a>
    </div>
</section>
