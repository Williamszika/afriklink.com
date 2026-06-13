<?php
/** @var list<array> $listings  @var array<int,string> $mains */
use App\Services\CloudinaryService;
?>
<section class="profile">
    <div class="profile-head">
        <h1>🏷️ <?= e(t('listing.mine_title')) ?></h1>
        <a class="btn btn-primary" href="<?= e(url('/vendre')) ?>">+ <?= e(t('listing.new')) ?></a>
    </div>

    <?php if ($listings === []): ?>
        <div class="panel">
            <div class="empty-state">
                <p><?= e(t('listing.mine_empty')) ?></p>
                <a class="btn btn-primary" href="<?= e(url('/vendre')) ?>"><?= e(t('dash.action.sell_title')) ?></a>
            </div>
        </div>
    <?php else: ?>
        <div class="listing-rows">
            <?php foreach ($listings as $l): ?>
                <?php $main = $mains[(int) $l['id']] ?? null; ?>
                <div class="panel listing-row">
                    <a class="listing-thumb" href="<?= e(url('/annonce/' . $l['public_id'])) ?>">
                        <?php if ($main !== null): ?>
                            <img src="<?= e(CloudinaryService::imageUrl($main, 160, 120)) ?>" alt="" loading="lazy" width="160" height="120">
                        <?php else: ?>
                            <span class="listing-thumb-empty" aria-hidden="true">🏷️</span>
                        <?php endif; ?>
                    </a>
                    <div class="listing-row-body">
                        <p class="listing-row-title">
                            <a href="<?= e(url('/annonce/' . $l['public_id'])) ?>"><?= e((string) $l['title']) ?></a>
                            <span class="badge badge-<?= $l['status'] === 'active' ? 'ok' : ($l['status'] === 'sold' ? 'neutral' : 'warn') ?>">
                                <?= e(t('listing.status.' . $l['status'])) ?>
                            </span>
                        </p>
                        <p class="listing-row-meta">
                            <strong><?= e(format_price((int) $l['price_cents'], (string) $l['currency'])) ?></strong>
                            · <?= e(t('listing.cat.' . $l['category'])) ?>
                            <?php if (!empty($l['city'])): ?> · <?= e((string) $l['city']) ?><?php endif; ?>
                            <?php if (!empty($l['video_public_id'])): ?> · 🎬<?php endif; ?>
                        </p>
                        <div class="listing-row-actions">
                            <a class="btn btn-ghost btn-sm" href="<?= e(url('/annonce/' . $l['public_id'] . '/modifier')) ?>"><?= e(t('profile.edit')) ?></a>
                            <?php if ($l['status'] === 'active'): ?>
                                <form method="post" action="<?= e(url('/annonce/' . $l['public_id'] . '/promouvoir')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <?php if (\App\Models\Listing::isPromoted($l)): ?>
                                        <button class="btn btn-ghost btn-sm" name="action" value="stop">✨ <?= e(t('ads.stop')) ?></button>
                                    <?php else: ?>
                                        <button class="btn btn-primary btn-sm" name="action" value="promote">✨ <?= e(t('ads.promote', ['days' => 7])) ?></button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= e(url('/annonce/' . $l['public_id'] . '/statut')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <?php if ($l['status'] === 'active'): ?>
                                    <button class="btn btn-ghost btn-sm" name="action" value="pause"><?= e(t('listing.action.pause')) ?></button>
                                    <button class="btn btn-ghost btn-sm" name="action" value="sold"><?= e(t('listing.action.sold')) ?></button>
                                <?php elseif ($l['status'] === 'paused'): ?>
                                    <button class="btn btn-ghost btn-sm" name="action" value="activate"><?= e(t('listing.action.activate')) ?></button>
                                <?php elseif ($l['status'] === 'sold'): ?>
                                    <button class="btn btn-ghost btn-sm" name="action" value="activate"><?= e(t('listing.action.relist')) ?></button>
                                <?php endif; ?>
                                <button class="btn btn-ghost btn-sm btn-danger" name="action" value="delete"
                                        data-confirm="<?= e(t('listing.delete_confirm')) ?>"><?= e(t('listing.action.delete')) ?></button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="auth-alt"><a href="<?= e(url('/dashboard')) ?>">← <?= e(t('profile.back_dashboard')) ?></a></p>
</section>
