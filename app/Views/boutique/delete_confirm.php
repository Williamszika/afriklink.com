<?php
/** @var string $active  @var array $user  @var array $profile  @var ?string $avatar_url
 *  @var array $boutique  @var int $active_orders  @var bool $has_email  @var bool $code_sent */
?>
<div class="seller-shell">
    <?= render_partial('vendeur/_sidebar', ['active' => $active, 'user' => $user, 'profile' => $profile, 'avatar_url' => $avatar_url]) ?>
    <div class="seller-main">

        <div class="seller-head">
            <h1>🗑️ <?= e(t('shop.del.title')) ?></h1>
            <p class="muted"><?= e(t('shop.del.intro', ['name' => (string) $boutique['name']])) ?></p>
        </div>

        <div class="panel">
            <?php if ($active_orders > 0): ?>
                <div class="notice notice-warn"><p><?= e(t('shop.del.blocked_orders', ['n' => $active_orders])) ?></p></div>
                <a class="btn btn-ghost" href="<?= e(url('/vendeur/commandes')) ?>"><?= e(t('seller.cockpit.all_orders')) ?> →</a>

            <?php elseif (!$has_email): ?>
                <div class="notice notice-warn"><p><?= e(t('shop.del.no_email')) ?></p></div>
                <a class="btn btn-ghost" href="<?= e(url('/vendeur/profil')) ?>"><?= e(t('seller.nav.profile')) ?> →</a>

            <?php elseif (!$code_sent): ?>
                <div class="notice notice-warn">
                    <p><strong>⚠️ <?= e(t('shop.del.warn_title')) ?></strong></p>
                    <p><?= e(t('shop.del.warn_body')) ?></p>
                </div>
                <form method="post" action="<?= e(url('/boutique/supprimer/code')) ?>" class="del-actions">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-danger"><?= e(t('shop.del.send_code')) ?></button>
                    <a class="btn btn-ghost" href="<?= e(url('/boutique/modifier')) ?>"><?= e(t('shop.del.cancel')) ?></a>
                </form>

            <?php else: ?>
                <div class="notice notice-info"><p><?= e(t('shop.del.code_hint')) ?></p></div>
                <form method="post" action="<?= e(url('/boutique/supprimer')) ?>">
                    <?= csrf_field() ?>
                    <label for="del-code"><?= e(t('shop.del.code_label')) ?></label>
                    <input type="text" id="del-code" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                           autocomplete="one-time-code" required
                           style="max-width:220px;font-size:1.6rem;letter-spacing:.45em;text-align:center"
                           placeholder="------">
                    <div class="del-actions" style="margin-top:14px">
                        <button type="submit" class="btn btn-danger"><?= e(t('shop.del.confirm_btn')) ?></button>
                        <a class="btn btn-ghost" href="<?= e(url('/boutique/modifier')) ?>"><?= e(t('shop.del.cancel')) ?></a>
                    </div>
                </form>
                <form method="post" action="<?= e(url('/boutique/supprimer/code')) ?>" style="margin-top:10px">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('shop.del.resend')) ?></button>
                </form>
            <?php endif; ?>
        </div>

        <p class="auth-alt"><a href="<?= e(url('/boutique/modifier')) ?>">← <?= e(t('shop.back_manage')) ?></a></p>
    </div>
</div>
