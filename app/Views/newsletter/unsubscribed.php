<?php
/** @var bool $ok  @var ?string $email */
?>
<section class="trust-page">
    <p class="muted"><a href="<?= e(url('/')) ?>">← <?= e(current_locale() === 'en' ? 'Home' : 'Accueil') ?></a></p>
    <header class="trust-hero">
        <span class="trust-hero__ic" aria-hidden="true"><?= $ok ? '👋' : '🤔' ?></span>
        <h1 class="afk-h1"><?= e($ok ? t('newsletter.unsub_done_title') : t('newsletter.unsub_fail_title')) ?></h1>
        <p class="trust-lead">
            <?php if ($ok): ?>
                <?= e(t('newsletter.unsub_done', ['email' => (string) $email])) ?>
            <?php else: ?>
                <?= e(t('newsletter.unsub_fail')) ?>
            <?php endif; ?>
        </p>
    </header>
    <div class="trust-cta">
        <a class="afk-btn afk-btn--gold afk-btn--lg" href="<?= e(url('/explorer')) ?>"><?= e(t('newsletter.unsub_browse')) ?></a>
    </div>
</section>
