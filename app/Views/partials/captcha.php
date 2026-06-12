<?php
/**
 * CAPTCHA d'inscription : widget Turnstile si configuré, sinon défi
 * arithmétique intégré. Toujours accompagné du pot de miel (champ caché).
 */
use App\Services\Captcha;

$mode = Captcha::mode();
?>
<div class="captcha-box">
    <?php if ($mode === 'turnstile'): ?>
        <div class="cf-turnstile" data-sitekey="<?= e((string) env('TURNSTILE_SITE_KEY')) ?>"></div>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php else: $c = Captcha::challenge(); ?>
        <label for="captcha_answer">🤖 <?= e(t('captcha.label')) ?></label>
        <p class="captcha-q"><?= e(t('captcha.question', ['a' => $c['a'], 'b' => $c['b']])) ?></p>
        <input type="text" id="captcha_answer" name="captcha_answer" inputmode="numeric"
               autocomplete="off" required maxlength="3" class="captcha-input" placeholder="?">
    <?php endif; ?>
    <?php if (has_error('captcha')): ?><p class="field-error"><?= e(error('captcha')) ?></p><?php endif; ?>
</div>
<p class="hp-wrap" aria-hidden="true">
    <label>Website <input type="text" name="website_url" tabindex="-1" autocomplete="off"></label>
</p>
