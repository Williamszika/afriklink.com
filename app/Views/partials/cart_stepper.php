<?php
/** Sélecteur de quantité (panier). @var string $id  @var string $size  @var string $name  @var int $price
 *  @var ?string $add_label  libellé du bouton « ajouter » (défaut : « Ajouter »)
 *  @var ?int $qty  quantité déjà au panier (persistée serveur) */
$addLabel = $add_label ?? t('rorder.add');
$q = (int) ($qty ?? 0);
?>
<span class="qty-stepper<?= $q > 0 ? ' is-active' : '' ?>" data-order-item data-id="<?= e($id) ?>" data-size="<?= e($size ?? '') ?>"
      data-name="<?= e($name) ?>" data-price="<?= (int) $price ?>" data-qty="<?= $q ?>">
    <button type="button" class="qty-btn" data-qty-dec aria-label="−" <?= $q > 0 ? '' : 'hidden' ?>>−</button>
    <span class="qty-val" data-qty-val <?= $q > 0 ? '' : 'hidden' ?>><?= $q ?></span>
    <button type="button" class="qty-btn qty-add<?= $q > 0 ? ' is-compact' : '' ?>" data-qty-inc data-add-label="<?= e($addLabel) ?>"><?= $q > 0 ? '＋' : '＋ ' . e($addLabel) ?></button>
</span>
