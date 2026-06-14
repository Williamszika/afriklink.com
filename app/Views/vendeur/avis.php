<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var ?array $boutique  @var list<array> $reviews  @var array{avg:float,count:int} $summary */
$stars = static function (int $n): string {
    $n = max(0, min(5, $n));
    return str_repeat('★', $n) . str_repeat('☆', 5 - $n);
};
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">
        <div class="seller-head">
            <h1><?= icon('star', ['size' => 24]) ?> <?= e(t('reviews.title')) ?></h1>
            <p class="muted"><?= e(t('reviews.subtitle')) ?></p>
        </div>

        <?php if ($boutique === null): ?>
            <div class="panel empty-state"><p><?= e(t('reviews.no_shop')) ?></p></div>
        <?php else: ?>
            <div class="panel reviews-summary">
                <div class="reviews-avg">
                    <strong class="reviews-avg-num"><?= e(number_format((float) $summary['avg'], 1)) ?></strong>
                    <span class="reviews-stars" aria-hidden="true"><?= $stars((int) round((float) $summary['avg'])) ?></span>
                    <span class="muted"><?= e(t('reviews.count', ['n' => (int) $summary['count']])) ?></span>
                </div>
            </div>

            <?php if ($reviews === []): ?>
                <div class="panel empty-state"><p><?= e(t('reviews.empty')) ?></p></div>
            <?php else: ?>
                <div class="panel">
                    <ul class="reviews-list">
                        <?php foreach ($reviews as $r): $hasReply = trim((string) ($r['reply'] ?? '')) !== ''; ?>
                            <li class="review-item">
                                <div class="review-head">
                                    <span class="reviews-stars" aria-label="<?= (int) $r['rating'] ?>/5"><?= $stars((int) $r['rating']) ?></span>
                                    <strong><?= e((string) $r['author_name']) ?></strong>
                                    <?php if (!empty($r['verified'])): ?>
                                        <span class="badge badge-ok">✓ <?= e(t('reviews.verified')) ?></span>
                                    <?php endif; ?>
                                    <span class="muted review-meta"><?= e(date('d/m/Y', strtotime((string) $r['created_at']))) ?><?= !empty($r['product_name']) ? ' · ' . e((string) $r['product_name']) : '' ?></span>
                                </div>
                                <?php if (!empty($r['comment'])): ?>
                                    <p class="review-comment"><?= e((string) $r['comment']) ?></p>
                                <?php endif; ?>

                                <?php if ($hasReply): ?>
                                    <div class="review-reply">
                                        <span class="review-reply-label"><?= icon('store', ['size' => 14]) ?> <?= e(t('reviews.reply_label')) ?></span>
                                        <p><?= e((string) $r['reply']) ?></p>
                                    </div>
                                <?php endif; ?>

                                <details class="review-reply-form">
                                    <summary class="btn btn-ghost btn-sm"><?= e($hasReply ? t('reviews.reply_edit') : t('reviews.reply_cta')) ?></summary>
                                    <form method="post" action="<?= e(url('/vendeur/avis/' . $r['public_id'] . '/repondre')) ?>">
                                        <?= csrf_field() ?>
                                        <textarea name="reply" maxlength="1000" rows="2" placeholder="<?= e(t('reviews.reply_ph')) ?>"><?= e((string) ($r['reply'] ?? '')) ?></textarea>
                                        <button type="submit" class="btn btn-primary btn-sm"><?= e(t('reviews.reply_send')) ?></button>
                                    </form>
                                </details>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>
