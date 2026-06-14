<?php
/**
 * Prix bi-devises : prix dans la devise de la boutique (qui fait foi pour le
 * règlement) + équivalent indicatif dans la devise d'affichage de l'acheteur.
 * @var int $cents  @var string $cur  devise de la boutique
 */
$cents = (int) ($cents ?? 0);
$cur   = (string) ($cur ?? 'EUR');
$approx = format_price_approx($cents, $cur);
?>
<?= e(format_price($cents, $cur)) ?><?php if ($approx !== ''): ?> <span class="price-approx" title="<?= e(t('price.approx_title')) ?>">≈&nbsp;<?= e($approx) ?></span><?php endif; ?>
