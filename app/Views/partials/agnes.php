<?php
/**
 * Widget « Agnès » — assistant d'aide du site (bouton flottant + panneau de chat).
 * Comportement dans app.js (aucun JS inline, CSP stricte). Le token CSRF est
 * ajouté automatiquement aux requêtes fetch (wrapper en tête de app.js).
 * Distinct de l'assistant d'achat d'une boutique (partials/assistant.php).
 */
if (!\App\Services\HelpAssistant::enabled()) {
    return;
}
$agnesName = \App\Services\HelpAssistant::name();
?>
<div class="agnes" data-agnes
     data-endpoint="<?= e(url('/agnes')) ?>"
     data-err="<?= e(t('agnes.err')) ?>"
     data-thinking="<?= e(t('agnes.thinking', ['name' => $agnesName])) ?>">
    <button type="button" class="agnes-toggle" data-agnes-toggle aria-expanded="false">
        <span class="agnes-toggle-ava" aria-hidden="true">🙋🏾‍♀️</span>
        <span class="agnes-toggle-txt"><?= e(t('agnes.open')) ?></span>
    </button>
    <div class="agnes-panel" data-agnes-panel hidden role="dialog" aria-label="<?= e($agnesName) ?>">
        <div class="agnes-head">
            <span class="agnes-head-id">
                <span class="agnes-ava" aria-hidden="true">🙋🏾‍♀️</span>
                <span><strong><?= e($agnesName) ?></strong><span class="agnes-sub"><?= e(t('agnes.subtitle')) ?></span></span>
            </span>
            <button type="button" class="agnes-close" data-agnes-close aria-label="<?= e(t('agnes.close')) ?>">✕</button>
        </div>
        <div class="agnes-log" data-agnes-log>
            <div class="agnes-msg bot"><?= e(t('agnes.intro', ['name' => $agnesName])) ?></div>
        </div>
        <div class="agnes-suggest" data-agnes-suggest>
            <button type="button" class="agnes-chip" data-agnes-q><?= e(t('agnes.q.account')) ?></button>
            <button type="button" class="agnes-chip" data-agnes-q><?= e(t('agnes.q.shop')) ?></button>
            <button type="button" class="agnes-chip" data-agnes-q><?= e(t('agnes.q.first_steps')) ?></button>
        </div>
        <form class="agnes-form" data-agnes-form>
            <input type="text" data-agnes-input maxlength="500" autocomplete="off"
                   placeholder="<?= e(t('agnes.placeholder')) ?>" aria-label="<?= e(t('agnes.placeholder')) ?>">
            <button type="submit" class="btn btn-primary btn-sm"><?= e(t('agnes.send')) ?></button>
        </form>
    </div>
</div>
