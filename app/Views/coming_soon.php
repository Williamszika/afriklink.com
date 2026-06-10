<?php
/** @var string $feature */
$icons = ['vendre' => '🏷️', 'annonces' => '🏷️', 'messages' => '💬'];
$icon  = $icons[$feature] ?? '🚧';
?>
<section class="auth-card auth-card--wide soon-card">
    <div class="soon-icon" aria-hidden="true"><?= $icon ?></div>
    <h1><?= e(t('soon.' . $feature . '.title')) ?></h1>
    <p class="lead"><?= e(t('soon.' . $feature . '.desc')) ?></p>
    <p class="muted"><?= e(t('soon.note')) ?></p>
    <div class="hero-actions">
        <a class="btn btn-primary" href="<?= e(url('/dashboard')) ?>">← <?= e(t('profile.back_dashboard')) ?></a>
        <a class="btn btn-ghost" href="<?= e(url('/')) ?>#verticals"><?= e(t('dash.action.explore_title')) ?></a>
    </div>
</section>
