<?php
/**
 * Bandeau d'actualités défilant du bas de page. CSP-safe : défilement par
 * animation CSS pure (aucun JS inline). Le contenu est dupliqué (2 passes) pour
 * une boucle sans couture. Chaque info est un lien cliquable.
 *
 * Réservé aux pages PUBLIQUES : masqué sur les espaces de gestion (tableau de
 * bord vendeur, admin) pour ne pas encombrer le travail du commerçant.
 */
$tkPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
if (preg_match('#^/(dashboard|vendeur|admin)(/|$)#', $tkPath)
    || preg_match('#^/(boutique|restaurant)/(gerer|modifier|creer|nouveau|produits?|stats|commandes|promos)#', $tkPath)) {
    return;
}
$ticker = \App\Services\NewsTicker::items();
if ($ticker === []) {
    return;
}
?>
<aside class="ticker" aria-label="<?= e(t('ticker.label')) ?>">
    <span class="ticker-tag"><span class="ticker-blip" aria-hidden="true"></span> <?= e(t('ticker.label')) ?></span>
    <div class="ticker-viewport">
        <div class="ticker-track">
            <?php for ($pass = 0; $pass < 2; $pass++): ?>
                <?php foreach ($ticker as $it): ?>
                    <a class="ticker-item ticker--<?= e($it['kind']) ?>" href="<?= e($it['href']) ?>"<?= $pass === 1 ? ' aria-hidden="true" tabindex="-1"' : '' ?>>
                        <span class="ticker-ico" aria-hidden="true"><?= $it['icon'] ?></span> <?= e($it['text']) ?>
                    </a>
                <?php endforeach; ?>
            <?php endfor; ?>
        </div>
    </div>
</aside>
