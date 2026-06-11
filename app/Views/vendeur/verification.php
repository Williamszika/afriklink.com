<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var array<int,array> $submissions  @var int $approvedLevel  @var bool $mediaReady */
$levels  = config('kyc.levels', []);
$idTypes = config('kyc.id_types', []);
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1>🪪 <?= e(t('kyc.title')) ?></h1>
            <p class="muted"><?= e(t('kyc.intro')) ?></p>
        </div>

        <?php if (!$mediaReady): ?>
            <div class="notice notice-warning"><p><?= e(t('listing.media_unconfigured')) ?></p></div>
        <?php endif; ?>
        <?php if (has_error('kyc')): ?><div class="notice notice-warning"><p><?= e(error('kyc')) ?></p></div><?php endif; ?>

        <ol class="kyc-levels">
            <?php foreach ($levels as $lvl => $cfg): ?>
                <?php
                $sub    = $submissions[$lvl] ?? null;
                $status = $sub['status'] ?? null;
                if ($status === 'approved') {
                    $state = 'approved';
                } elseif ($status === 'pending') {
                    $state = 'pending';
                } elseif ($lvl <= $approvedLevel + 1) {
                    $state = 'open'; // niveau courant (jamais soumis ou refusé)
                } else {
                    $state = 'locked';
                }
                $stateClass = ['approved' => 'badge-ok', 'pending' => 'badge-neutral', 'open' => 'badge-warn', 'locked' => 'badge-neutral'][$state];
                ?>
                <li class="kyc-level kyc-<?= $state ?>">
                    <div class="kyc-level-head">
                        <span class="kyc-level-num"><?= $state === 'approved' ? '✓' : (int) $lvl ?></span>
                        <div>
                            <h2><?= e(t('kyc.level' . $lvl . '_title')) ?></h2>
                            <p class="muted"><?= e(t('kyc.level' . $lvl . '_desc')) ?></p>
                        </div>
                        <span class="badge <?= $stateClass ?>"><?= e(t('kyc.state.' . $state)) ?></span>
                    </div>

                    <?php if ($state === 'pending'): ?>
                        <p class="hint">⏳ <?= e(t('kyc.pending_hint')) ?></p>

                    <?php elseif ($state === 'approved'): ?>
                        <p class="hint">✅ <?= e(t('kyc.approved_hint')) ?></p>

                    <?php elseif ($state === 'locked'): ?>
                        <p class="hint">🔒 <?= e(t('kyc.locked_hint')) ?></p>

                    <?php elseif ($state === 'open' && $mediaReady): ?>
                        <?php if ($status === 'rejected' && !empty($sub['review_note'])): ?>
                            <div class="notice notice-warning">
                                <p><strong><?= e(t('kyc.rejected_label')) ?></strong> <?= e((string) $sub['review_note']) ?></p>
                            </div>
                        <?php endif; ?>

                        <form method="post" action="<?= e(url('/vendeur/verification/' . $lvl)) ?>" class="kyc-form" novalidate
                              data-uploading="<?= e(t('kyc.uploading')) ?>" data-need="<?= e(t('kyc.missing_doc')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="docs_json" class="kyc-docs" value="">

                            <?php if (!empty($cfg['has_doc_type'])): ?>
                                <label><?= e(t('kyc.doc_type')) ?></label>
                                <select name="doc_type" required>
                                    <option value=""><?= e(t('field.choose')) ?></option>
                                    <?php foreach ($idTypes as $it): ?>
                                        <option value="<?= e($it) ?>"><?= e(t('kyc.idtype.' . $it)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>

                            <div class="kyc-slots">
                                <?php foreach ($cfg['slots'] as $slot => $required): ?>
                                    <div class="kyc-slot" data-slot="<?= e($slot) ?>" data-required="<?= $required ? '1' : '0' ?>">
                                        <label class="kyc-slot-label">
                                            <?= e(t('kyc.slot.' . $slot)) ?><?= $required ? ' *' : ' <span class="muted">(' . e(t('field.optional')) . ')</span>' ?>
                                        </label>
                                        <input type="file" accept="image/jpeg,image/png,image/webp" class="kyc-input" capture="environment">
                                        <span class="kyc-slot-state" aria-live="polite"></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <p class="hint">🔒 <?= e(t('kyc.privacy')) ?></p>
                            <button type="submit" class="btn btn-primary"><?= e(t('kyc.submit')) ?></button>
                        </form>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>

    </div>
</div>
