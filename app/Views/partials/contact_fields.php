<?php
/**
 * Canaux de contact de la boutique : le vendeur renseigne ceux qu'il utilise
 * et choisit le canal principal. Réutilisé par l'assistant (étape 1) et la
 * page d'édition.
 * @var array $values   valeurs existantes [canal => valeur]
 * @var string $primary canal principal choisi
 */
use App\Services\ContactChannels;

$values = $values ?? [];
$primary = $primary ?? '';
?>
<fieldset class="contact-fields">
    <legend><?= e(t('contact.legend')) ?></legend>
    <p class="hint"><?= e(t('contact.intro')) ?></p>

    <div class="contact-grid">
        <?php foreach (ContactChannels::CHANNELS as $ch): ?>
            <?php $m = ContactChannels::meta($ch); $v = old('contact_' . $ch) !== '' ? old('contact_' . $ch) : (string) ($values[$ch] ?? ''); ?>
            <div class="contact-field">
                <label for="contact-<?= e($ch) ?>"><span aria-hidden="true"><?= $m['icon'] ?></span> <?= e($m['label']) ?></label>
                <input type="text" id="contact-<?= e($ch) ?>" name="contact_<?= e($ch) ?>"
                       value="<?= e($v) ?>" maxlength="120" autocomplete="off"
                       placeholder="<?= e(t('contact.ph.' . $ch)) ?>">
            </div>
        <?php endforeach; ?>
    </div>

    <label for="contact-primary"><?= e(t('contact.primary')) ?></label>
    <select id="contact-primary" name="contact_primary">
        <?php $selP = old('contact_primary') ?: $primary; ?>
        <?php foreach (ContactChannels::CHANNELS as $ch): $m = ContactChannels::meta($ch); ?>
            <option value="<?= e($ch) ?>" <?= $selP === $ch ? 'selected' : '' ?>><?= $m['icon'] ?> <?= e($m['label']) ?></option>
        <?php endforeach; ?>
    </select>
    <p class="hint"><?= e(t('contact.primary_hint')) ?></p>
</fieldset>
