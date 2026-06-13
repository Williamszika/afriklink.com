<?php
/** Bouton « comparer » réutilisable. @var string $pid identifiant public du produit. */
$pid       = (string) ($pid ?? '');
$comparing = \App\Services\Compare::has($pid);
?>
<form method="post" action="<?= e(url('/comparer/' . $pid . '/basculer')) ?>" class="compare-form" data-compare-form>
    <?= csrf_field() ?>
    <input type="hidden" name="to" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
    <button type="submit" class="compare-btn<?= $comparing ? ' is-comparing' : '' ?>" data-compare="<?= e($pid) ?>"
            aria-pressed="<?= $comparing ? 'true' : 'false' ?>" aria-label="<?= e(t('compare.toggle')) ?>" title="<?= e(t('compare.toggle')) ?>">⇄</button>
</form>
