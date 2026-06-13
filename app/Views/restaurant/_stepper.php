<?php
/** @var string $id  @var string $size  @var string $name  @var int $price */
?>
<span class="qty-stepper" data-order-item data-id="<?= e($id) ?>" data-size="<?= e($size) ?>"
      data-name="<?= e($name) ?>" data-price="<?= (int) $price ?>">
    <button type="button" class="qty-btn" data-qty-dec aria-label="−" hidden>−</button>
    <span class="qty-val" data-qty-val hidden>0</span>
    <button type="button" class="qty-btn qty-add" data-qty-inc>＋ <?= e(t('rorder.add')) ?></button>
</span>
