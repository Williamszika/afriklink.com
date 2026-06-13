<?php
/** Cœur « favori » réutilisable. @var string $pid identifiant public du produit.
 *  Sans JS : le formulaire bascule le cookie et revient. Avec JS (app.js) :
 *  bascule instantanée par fetch + mise à jour du compteur d'en-tête. */
$pid     = (string) ($pid ?? '');
$wished  = \App\Services\Wishlist::has($pid);
?>
<form method="post" action="<?= e(url('/favoris/' . $pid . '/basculer')) ?>" class="wish-form" data-wish-form>
    <?= csrf_field() ?>
    <input type="hidden" name="to" value="<?= e((string) ($_SERVER['REQUEST_URI'] ?? '/')) ?>">
    <button type="submit" class="wish-btn<?= $wished ? ' is-wished' : '' ?>" data-wish="<?= e($pid) ?>"
            aria-pressed="<?= $wished ? 'true' : 'false' ?>" aria-label="<?= e(t('wish.toggle')) ?>" title="<?= e(t('wish.toggle')) ?>">♥</button>
</form>
