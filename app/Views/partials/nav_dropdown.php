<?php
/** Contenu d'un menu déroulant d'en-tête (liste de produits) + lien « Voir tout ».
 * @var list<array> $items  chacun : url, name, main(?url), sub(?), price(?)
 * @var string $all_url  @var string $all_label  @var string $empty */
?>
<?php if (empty($items)): ?>
    <p class="nav-dd-empty"><?= e($empty) ?></p>
<?php else: ?>
    <ul class="nav-dd-list">
        <?php foreach ($items as $it): ?>
            <li>
                <a class="nav-dd-row" href="<?= e((string) $it['url']) ?>">
                    <span class="dd-thumb"><?php if (!empty($it['main'])): ?><img src="<?= e((string) $it['main']) ?>" alt="" loading="lazy"><?php else: ?><span aria-hidden="true">📦</span><?php endif; ?></span>
                    <span class="dd-info">
                        <span class="dd-name"><?= e((string) $it['name']) ?></span>
                        <?php if (!empty($it['sub'])): ?><span class="muted dd-sub"><?= e((string) $it['sub']) ?></span><?php endif; ?>
                    </span>
                    <?php if (!empty($it['price'])): ?><span class="dd-price"><?= e((string) $it['price']) ?></span><?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<a class="nav-dd-all" href="<?= e($all_url) ?>"><?= e($all_label) ?> →</a>
