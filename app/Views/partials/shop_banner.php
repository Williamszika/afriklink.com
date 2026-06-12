<?php
/** @var list<string> $images  @var int $w  @var int $h
 *  Diaporama animé de la bannière : 0 image → placeholder, 1 → image fixe,
 *  ≥2 → fondu automatique entre les images (rotation gérée par app.js). */
use App\Services\CloudinaryService;

$images = $images ?? [];
$w = $w ?? 1100;
$h = $h ?? 300;
?>
<?php if (count($images) > 1): ?>
    <div class="shop-banner-slideshow" data-banner-slideshow>
        <?php foreach ($images as $i => $img): ?>
            <img class="<?= $i === 0 ? 'is-active' : '' ?>" src="<?= e(CloudinaryService::imageUrl((string) $img, $w, $h)) ?>"
                 alt="" <?= $i > 0 ? 'loading="lazy"' : '' ?>>
        <?php endforeach; ?>
        <span class="shop-banner-dots" aria-hidden="true">
            <?php foreach ($images as $i => $img): ?><i class="<?= $i === 0 ? 'is-active' : '' ?>"></i><?php endforeach; ?>
        </span>
    </div>
<?php elseif (count($images) === 1): ?>
    <img class="shop-hero-banner" src="<?= e(CloudinaryService::imageUrl((string) $images[0], $w, $h)) ?>" alt="">
<?php else: ?>
    <div class="shop-hero-banner shop-banner--empty"></div>
<?php endif; ?>
