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
// Indicatif du pays déjà géolocalisé : pré-remplit WhatsApp/SMS (ex. +39 ).
$ctCountry = (isset($country) && $country !== '') ? $country : (string) (detected_geo()['country_code'] ?? '');
$ctDial = $ctCountry !== '' ? dial_code($ctCountry) : '';
$ctDialPrefix = $ctDial !== '' ? '+' . $ctDial . ' ' : '';
?>
<fieldset class="contact-fields">
    <legend><?= e(t('contact.legend')) ?></legend>
    <p class="hint"><?= e(t('contact.intro')) ?></p>

    <div class="contact-grid">
        <?php foreach (ContactChannels::CHANNELS as $ch): ?>
            <?php
            $m = ContactChannels::meta($ch);
            $isPhone = ($m['type'] ?? '') === 'phone';
            $v = old('contact_' . $ch) !== '' ? old('contact_' . $ch) : (string) ($values[$ch] ?? '');
            // Champ téléphone vide → on amorce avec l'indicatif du pays détecté.
            if ($v === '' && $isPhone && $ctDialPrefix !== '') {
                $v = $ctDialPrefix;
            }
            ?>
            <div class="contact-field">
                <label for="contact-<?= e($ch) ?>"><span aria-hidden="true"><?= $m['icon'] ?></span> <?= e($m['label']) ?></label>
                <input type="text" id="contact-<?= e($ch) ?>" name="contact_<?= e($ch) ?>"
                       value="<?= e($v) ?>" maxlength="120" autocomplete="off"
                       <?= $isPhone ? 'inputmode="tel" data-dialcode="1"' : '' ?>
                       placeholder="<?= e(t('contact.ph.' . $ch)) ?>">
            </div>
        <?php endforeach; ?>
    </div>

    <label><?= e(t('contact.primary')) ?></label>
    <?php $selP = old_array('contact_primary') !== [] ? old_array('contact_primary') : (array) $primary; ?>
    <div class="lang-checks">
        <?php foreach (ContactChannels::CHANNELS as $ch): $m = ContactChannels::meta($ch); ?>
            <label class="check-pill">
                <input type="checkbox" name="contact_primary[]" value="<?= e($ch) ?>" <?= in_array($ch, $selP, true) ? 'checked' : '' ?>>
                <span><?= $m['icon'] ?> <?= e($m['label']) ?></span>
            </label>
        <?php endforeach; ?>
    </div>
    <p class="hint"><?= e(t('contact.primary_hint')) ?></p>
</fieldset>
