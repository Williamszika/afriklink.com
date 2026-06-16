<?php
/**
 * Prix bi-devises : prix dans la devise de la boutique (qui fait foi pour le
 * règlement) + équivalent indicatif dans la devise d'affichage de l'acheteur.
 * Si $compare > $cents, le prix d'origine est affiché barré (promotion).
 * @var int $cents  @var string $cur  devise de la boutique  @var int $compare
 */
$cents   = (int) ($cents ?? 0);
$cur     = (string) ($cur ?? 'EUR');
$compare = (int) ($compare ?? 0);
$approx  = format_price_approx($cents, $cur);
?>
<?php if ($compare > $cents): ?><del class="price-was"><?= e(format_price($compare, $cur)) ?></del> <?php endif; ?><span class="<?= $compare > $cents ? 'price-now' : '' ?>"><?= e(format_price($cents, $cur)) ?></span><?php if ($approx !== ''): ?> <span class="price-approx" title="<?= e(t('price.approx_title')) ?>">≈&nbsp;<?= e($approx) ?></span><?php endif; ?>
