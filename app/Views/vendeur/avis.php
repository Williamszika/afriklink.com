<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var ?array $boutique  @var list<array> $reviews  @var array{avg:float,count:int} $summary */
$avg   = (float) ($summary['avg'] ?? 0);
$total = (int) ($summary['count'] ?? count($reviews));

// Répartition par note + « à répondre », calculées depuis les avis chargés.
$dist = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
$toRespond = 0;
foreach ($reviews as $r) {
    $n = max(1, min(5, (int) $r['rating']));
    $dist[$n] = ($dist[$n] ?? 0) + 1;
    if (trim((string) ($r['reply'] ?? '')) === '') { $toRespond++; }
}

$filter = whitelist((string) input_string('note', 'tous'), ['tous', 'a_repondre', '5', '4', '3', '2', '1'], 'tous');
$shown = array_values(array_filter($reviews, static function (array $r) use ($filter): bool {
    if ($filter === 'tous') { return true; }
    if ($filter === 'a_repondre') { return trim((string) ($r['reply'] ?? '')) === ''; }
    return (int) $r['rating'] === (int) $filter;
}));

/** Rangée d'étoiles pleines/vides. */
$starsHtml = static function (int $n): string {
    $n = max(0, min(5, $n));
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<span class="' . ($i <= $n ? 'on' : 'off') . '">' . ($i <= $n ? '★' : '☆') . '</span>';
    }
    return $out;
};
$tabs = ['tous' => $total, 'a_repondre' => $toRespond, '5' => $dist[5], '4' => $dist[4], '3' => $dist[3], '2' => $dist[2], '1' => $dist[1]];
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main srev">

        <div class="srev-topbar">
            <h1><?= icon('star', ['size' => 22]) ?> <?= e(t('reviews.title')) ?></h1>
            <p><?= e(t('reviews.subtitle')) ?></p>
        </div>

        <?php if ($boutique === null): ?>
            <div class="srev-panel"><div class="srev-empty"><div class="il" aria-hidden="true">⭐</div><b><?= e(t('reviews.no_shop')) ?></b></div></div>
        <?php else: ?>
            <div class="srev-grid">

                <!-- Résumé -->
                <aside class="srev-summary srev-panel">
                    <div class="srev-avg-wrap">
                        <div class="srev-avg"><?= e(number_format($avg, 1)) ?></div>
                        <div class="srev-stars srev-stars--big" aria-hidden="true"><?= $starsHtml((int) round($avg)) ?></div>
                        <div class="srev-count"><?= e(t('reviews.count', ['n' => $total])) ?></div>
                    </div>
                    <?php foreach ([5, 4, 3, 2, 1] as $s): $pct = $total > 0 ? (int) round(($dist[$s] ?? 0) / $total * 100) : 0; ?>
                        <div class="srev-dist">
                            <span class="srev-dist-lab"><?= $s ?><span class="srev-star-mini">★</span></span>
                            <span class="srev-dist-bar"><i style="width:<?= $pct ?>%"></i></span>
                            <span class="srev-dist-n"><?= (int) ($dist[$s] ?? 0) ?></span>
                        </div>
                    <?php endforeach; ?>
                </aside>

                <!-- Liste -->
                <div class="srev-list-col">
                    <div class="srev-filters" role="tablist" aria-label="<?= e(t('reviews.title')) ?>">
                        <a class="srev-ftab <?= $filter === 'tous' ? 'on' : '' ?>" href="<?= e(url('/vendeur/avis')) ?>"><?= e(t('reviews.filter_all')) ?> <span class="cnt">· <?= $total ?></span></a>
                        <a class="srev-ftab <?= $filter === 'a_repondre' ? 'on' : '' ?>" href="<?= e(url('/vendeur/avis?note=a_repondre')) ?>"><?= e(t('reviews.filter_torespond')) ?> <span class="cnt">· <?= $toRespond ?></span></a>
                        <?php foreach ([5, 4, 3, 2, 1] as $s): ?>
                            <a class="srev-ftab <?= $filter === (string) $s ? 'on' : '' ?>" href="<?= e(url('/vendeur/avis?note=' . $s)) ?>"><?= $s ?><span class="srev-star-mini">★</span><span class="cnt">· <?= (int) ($dist[$s] ?? 0) ?></span></a>
                        <?php endforeach; ?>
                    </div>

                    <?php if ($shown === []): ?>
                        <div class="srev-panel"><div class="srev-empty">
                            <div class="il" aria-hidden="true">⭐</div>
                            <b><?= e($total === 0 ? t('reviews.empty') : t('reviews.empty_filter')) ?></b>
                            <?php if ($total > 0): ?><p><?= e(t('reviews.empty_filter_hint')) ?></p><?php endif; ?>
                        </div></div>
                    <?php else: ?>
                        <?php foreach ($shown as $r): $hasReply = trim((string) ($r['reply'] ?? '')) !== ''; $author = (string) $r['author_name']; ?>
                            <div class="srev-card">
                                <div class="srev-card-head">
                                    <span class="srev-av" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($author, 0, 1))) ?></span>
                                    <div class="srev-who">
                                        <span class="srev-name"><?= e($author) ?><?php if (!empty($r['verified'])): ?> <span class="srev-verified"><?= icon('check', ['size' => 11]) ?> <?= e(t('reviews.verified')) ?></span><?php endif; ?></span>
                                    </div>
                                    <span class="srev-date"><?= e(date('d/m/Y', strtotime((string) $r['created_at']))) ?></span>
                                </div>
                                <div class="srev-stars" aria-label="<?= (int) $r['rating'] ?>/5"><?= $starsHtml((int) $r['rating']) ?></div>
                                <?php if (!empty($r['product_name'])): ?><span class="srev-product"><?= e((string) $r['product_name']) ?></span><?php endif; ?>
                                <?php if (!empty($r['comment'])): ?><p class="srev-text"><?= e((string) $r['comment']) ?></p><?php endif; ?>

                                <?php if ($hasReply): ?>
                                    <div class="srev-reply">
                                        <div class="srev-reply-who"><?= e(t('reviews.reply_label')) ?></div>
                                        <p><?= e((string) $r['reply']) ?></p>
                                    </div>
                                <?php endif; ?>

                                <details class="srev-reply-form">
                                    <summary class="btn btn-ghost btn-sm"><?= e($hasReply ? t('reviews.reply_edit') : t('reviews.reply_cta')) ?></summary>
                                    <form method="post" action="<?= e(url('/vendeur/avis/' . $r['public_id'] . '/repondre')) ?>" data-submit-once>
                                        <?= csrf_field() ?>
                                        <textarea name="reply" maxlength="1000" rows="3" placeholder="<?= e(t('reviews.reply_ph')) ?>"><?= e((string) ($r['reply'] ?? '')) ?></textarea>
                                        <button type="submit" class="btn btn-green btn-sm"><?= e(t('reviews.reply_send')) ?></button>
                                    </form>
                                </details>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
