<?php
/**
 * Widget assistant d'achat (chatbot) — bouton flottant + panneau.
 * Comportement dans app.js (aucun JS inline, CSP stricte). Le token CSRF est
 * ajouté automatiquement aux requêtes fetch (voir wrapper en tête de app.js).
 * @var array $boutique  @var string $wa  numéro WhatsApp (chiffres) ou ''
 */
$wa     = isset($wa) ? preg_replace('/\D+/', '', (string) $wa) : '';
$waLink = $wa !== '' ? 'https://wa.me/' . $wa : '';
?>
<div class="assistant" data-assistant
     data-endpoint="<?= e(url('/boutique/' . $boutique['slug'] . '/assistant')) ?>"
     data-wa="<?= e($waLink) ?>"
     data-wa-label="<?= e(t('assistant.handoff_wa')) ?>"
     data-err="<?= e(t('assistant.err')) ?>"
     data-thinking="<?= e(t('assistant.thinking')) ?>">
    <button type="button" class="assistant-toggle" data-assistant-toggle aria-expanded="false">💬 <?= e(t('assistant.open')) ?></button>
    <div class="assistant-panel" data-assistant-panel hidden role="dialog" aria-label="<?= e(t('assistant.title')) ?>">
        <div class="assistant-head">
            <strong>🤖 <?= e(t('assistant.title')) ?> <span class="assistant-beta"><?= e(t('assistant.beta')) ?></span></strong>
            <button type="button" class="assistant-close" data-assistant-close aria-label="<?= e(t('assistant.close')) ?>">✕</button>
        </div>
        <div class="assistant-log" data-assistant-log>
            <div class="assistant-msg bot"><?= e(t('assistant.intro')) ?></div>
        </div>
        <div class="assistant-suggest" data-assistant-suggest>
            <button type="button" class="assistant-chip" data-assistant-q><?= e(t('assistant.s.delivery')) ?></button>
            <button type="button" class="assistant-chip" data-assistant-q><?= e(t('assistant.s.payment')) ?></button>
            <button type="button" class="assistant-chip" data-assistant-q><?= e(t('assistant.s.return')) ?></button>
        </div>
        <form class="assistant-form" data-assistant-form>
            <input type="text" data-assistant-input maxlength="500" autocomplete="off"
                   placeholder="<?= e(t('assistant.placeholder')) ?>" aria-label="<?= e(t('assistant.placeholder')) ?>">
            <button type="submit" class="btn btn-primary btn-sm"><?= e(t('assistant.send')) ?></button>
        </form>
    </div>
</div>
