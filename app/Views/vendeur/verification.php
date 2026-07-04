<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var array<int,array> $submissions  @var int $approvedLevel  @var bool $mediaReady */
$levels  = config('kyc.levels', []);
$idTypes = config('kyc.id_types', []);
$total   = count($levels);

// Icône par niveau (fidèle à la maquette : pièce, selfie, domicile).
$lvlIcon = [1 => 'card', 2 => 'camera', 3 => 'home'];
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main skyc">

        <div class="skyc-topbar">
            <h1><?= e(t('kyc.title')) ?></h1>
            <p><?= e(t('kyc.intro')) ?></p>
        </div>

        <div class="skyc-why">
            <span class="ic" aria-hidden="true"><?= icon('shield', ['size' => 22]) ?></span>
            <div><b><?= e(t('kyc.why_title')) ?></b> <span><?= e(t('kyc.why_desc')) ?></span></div>
        </div>

        <?php if (!$mediaReady): ?>
            <div class="skyc-notice"><?= icon('info', ['size' => 18]) ?> <span><?= e(t('listing.media_unconfigured')) ?></span></div>
        <?php endif; ?>
        <?php if (has_error('kyc')): ?><div class="skyc-notice skyc-notice--err"><?= icon('info', ['size' => 18]) ?> <span><?= e(error('kyc')) ?></span></div><?php endif; ?>

        <ol class="skyc-levels">
            <?php $idx = 0; foreach ($levels as $lvl => $cfg): $idx++; $isLast = $idx === $total; ?>
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
                $nodeClass  = ['approved' => 'done', 'pending' => 'review', 'open' => 'active', 'locked' => 'locked'][$state];
                $badgeClass = ['approved' => 'lb-done', 'pending' => 'lb-review', 'open' => 'lb-todo', 'locked' => 'lb-lock'][$state];
                ?>
                <li class="skyc-level">
                    <div class="skyc-rail">
                        <span class="skyc-node skyc-node--<?= $nodeClass ?>"><?= $state === 'approved' ? '✓' : (int) $lvl ?></span>
                        <?php if (!$isLast): ?><span class="skyc-conn <?= $state === 'approved' ? 'is-done' : '' ?>"></span><?php endif; ?>
                    </div>

                    <div class="skyc-card <?= $state === 'locked' ? 'is-locked' : '' ?>">
                        <div class="skyc-head">
                            <div class="skyc-htext">
                                <h2><span class="skyc-hic" aria-hidden="true"><?= icon($lvlIcon[$lvl] ?? 'shield', ['size' => 16]) ?></span> <?= e(t('kyc.level' . $lvl . '_title')) ?></h2>
                                <p><?= e(t('kyc.level' . $lvl . '_desc')) ?></p>
                            </div>
                            <span class="skyc-badge <?= $badgeClass ?>">
                                <?php if ($state === 'approved'): ?><?= icon('check', ['size' => 12]) ?><?php elseif ($state === 'locked'): ?><?= icon('lock', ['size' => 12]) ?><?php endif; ?>
                                <?= e(t('kyc.state.' . $state)) ?>
                            </span>
                        </div>

                        <?php if ($state === 'pending'): ?>
                            <div class="skyc-submitted">
                                <span class="sic" aria-hidden="true"><?= icon('clock', ['size' => 20]) ?></span>
                                <div><b><?= e(t('kyc.submitted_title')) ?></b><p><?= e(t('kyc.pending_hint')) ?></p></div>
                            </div>

                        <?php elseif ($state === 'approved'): ?>
                            <p class="skyc-hint skyc-hint--ok"><?= icon('check', ['size' => 15]) ?> <?= e(t('kyc.approved_hint')) ?></p>

                        <?php elseif ($state === 'locked'): ?>
                            <div class="skyc-locked-msg"><?= icon('lock', ['size' => 18]) ?> <span><?= e(t('kyc.locked_hint')) ?></span></div>

                        <?php elseif ($state === 'open' && $mediaReady): ?>
                            <?php if ($status === 'rejected' && !empty($sub['review_note'])): ?>
                                <div class="skyc-notice skyc-notice--err">
                                    <?= icon('info', ['size' => 18]) ?>
                                    <span><strong><?= e(t('kyc.rejected_label')) ?></strong> <?= e((string) $sub['review_note']) ?></span>
                                </div>
                            <?php endif; ?>

                            <form method="post" action="<?= e(url('/vendeur/verification/' . $lvl)) ?>" class="kyc-form skyc-form" novalidate
                                  data-uploading="<?= e(t('kyc.uploading')) ?>" data-need="<?= e(t('kyc.missing_doc')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="docs_json" class="kyc-docs" value="">

                                <?php if (!empty($cfg['has_name'])): ?>
                                    <?php
                                    $acct = preg_split('/\s+/', trim((string) ($user['full_name'] ?? '')), 2);
                                    $preFirst = old('id_first_name') ?: (string) ($sub['id_first_name'] ?? ($acct[0] ?? ''));
                                    $preLast  = old('id_last_name')  ?: (string) ($sub['id_last_name'] ?? ($acct[1] ?? ''));
                                    ?>
                                    <div class="skyc-instr">
                                        <?= icon('info', ['size' => 17]) ?>
                                        <span><?= e(t('kyc.name_hint')) ?></span>
                                    </div>
                                    <div class="skyc-two">
                                        <div class="skyc-field">
                                            <label for="kyc-fn-<?= $lvl ?>"><?= e(t('kyc.first_name')) ?> <span class="req">*</span></label>
                                            <input type="text" id="kyc-fn-<?= $lvl ?>" name="id_first_name" value="<?= e($preFirst) ?>" maxlength="100" required autocomplete="given-name">
                                        </div>
                                        <div class="skyc-field">
                                            <label for="kyc-ln-<?= $lvl ?>"><?= e(t('kyc.last_name')) ?> <span class="req">*</span></label>
                                            <input type="text" id="kyc-ln-<?= $lvl ?>" name="id_last_name" value="<?= e($preLast) ?>" maxlength="100" required autocomplete="family-name">
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($cfg['has_doc_type'])): ?>
                                    <div class="skyc-field">
                                        <label for="kyc-dt-<?= $lvl ?>"><?= e(t('kyc.doc_type')) ?> <span class="req">*</span></label>
                                        <select id="kyc-dt-<?= $lvl ?>" name="doc_type" required>
                                            <option value=""><?= e(t('field.choose')) ?></option>
                                            <?php foreach ($idTypes as $it): ?>
                                                <option value="<?= e($it) ?>"><?= e(t('kyc.idtype.' . $it)) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                <?php endif; ?>

                                <div class="skyc-slots">
                                    <?php foreach ($cfg['slots'] as $slot => $required): ?>
                                        <div class="kyc-slot skyc-drop" data-slot="<?= e($slot) ?>" data-required="<?= $required ? '1' : '0' ?>">
                                            <label class="skyc-drop-lab">
                                                <span class="dic" aria-hidden="true"><?= icon('camera', ['size' => 20]) ?></span>
                                                <span class="skyc-drop-txt">
                                                    <strong><?= e(t('kyc.slot.' . $slot)) ?><?= $required ? ' <span class="req">*</span>' : ' <span class="opt">(' . e(t('field.optional')) . ')</span>' ?></strong>
                                                    <small><?= e(t('kyc.file_hint')) ?></small>
                                                </span>
                                                <input type="file" accept="image/jpeg,image/png,image/webp" class="kyc-input" capture="environment">
                                                <span class="kyc-slot-state skyc-drop-state" aria-live="polite"></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                                <div class="skyc-privacy">
                                    <?= icon('lock', ['size' => 16]) ?>
                                    <span><?= e(t('kyc.privacy')) ?></span>
                                </div>
                                <button type="submit" class="btn btn-green"><?= e(t('kyc.submit')) ?></button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>

    </div>
</div>
