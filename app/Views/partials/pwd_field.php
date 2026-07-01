<?php
/**
 * Champ mot de passe « .authx » : bouton œil afficher/masquer, + jauge de force
 * et/ou indicateur de concordance en option. CSP-safe (piloté par data-*, la
 * logique vit dans app.js). Réutilisé par les 3 pages d'auth.
 * @var string  $id           id + name du champ (def. 'password')
 * @var ?string $name         name si différent de l'id
 * @var string  $label        libellé
 * @var bool    $strength     afficher la jauge de force
 * @var ?string $match        id du champ à comparer (indicateur de concordance)
 * @var ?string $hint         texte d'aide
 * @var ?int    $minlength
 * @var ?string $autocomplete def. 'new-password'
 * @var ?string $error        clé d'erreur à afficher (has_error/error)
 */
$pid  = $id ?? 'password';
$pname = $name ?? $pid;
$ac   = $autocomplete ?? 'new-password';
?>
<div class="afield">
    <label class="albl" for="<?= e($pid) ?>"><?= e($label ?? '') ?> <span class="req">*</span></label>
    <div class="pwd-wrap">
        <input type="password" id="<?= e($pid) ?>" name="<?= e($pname) ?>" required autocomplete="<?= e($ac) ?>"
               <?= isset($minlength) ? 'minlength="' . (int) $minlength . '"' : '' ?>
               <?= !empty($strength) ? 'data-pwd-strength' : '' ?>
               <?= !empty($match) ? 'data-pwd-match="' . e((string) $match) . '"' : '' ?>>
        <button type="button" class="pwd-toggle" data-pwd-toggle="<?= e($pid) ?>"
                data-show="<?= e(t('auth.pwd.show')) ?>" data-hide="<?= e(t('auth.pwd.hide')) ?>"
                aria-label="<?= e(t('auth.pwd.show')) ?>">
            <svg class="eye" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
            <svg class="eye-off" hidden width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l18 18M10.6 10.6a3 3 0 0 0 4.2 4.2M9.9 5.2A9.6 9.6 0 0 1 12 5c6.5 0 10 7 10 7a17 17 0 0 1-3.2 4M6.1 6.1A17 17 0 0 0 2 12s3.5 7 10 7a9.7 9.7 0 0 0 3.1-.5"/></svg>
        </button>
    </div>
    <?php if (!empty($strength)): ?>
        <div class="pwd-strength" data-lvl="0" aria-hidden="true"
             data-prefix="<?= e(t('auth.pwd.strength')) ?>"
             data-l1="<?= e(t('auth.pwd.lvl1')) ?>" data-l2="<?= e(t('auth.pwd.lvl2')) ?>"
             data-l3="<?= e(t('auth.pwd.lvl3')) ?>" data-l4="<?= e(t('auth.pwd.lvl4')) ?>"><i></i><i></i><i></i><i></i></div>
    <?php endif; ?>
    <?php if (!empty($hint)): ?>
        <p class="ahint"><?php if (!empty($strength)): ?><span data-pwd-strength-label></span><?php endif; ?><?= e((string) $hint) ?></p>
    <?php endif; ?>
    <?php if (!empty($match)): ?>
        <p class="pwd-match" data-pwd-match-msg data-ok="<?= e(t('auth.pwd.match')) ?>" data-no="<?= e(t('auth.pwd.nomatch')) ?>"></p>
    <?php endif; ?>
    <?php if (!empty($error) && has_error((string) $error)): ?><p class="field-error"><?= e(error((string) $error)) ?></p><?php endif; ?>
</div>
