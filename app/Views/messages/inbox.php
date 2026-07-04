<?php
/** Boîte de réception unifiée — messagerie deux volets. @var int $uid  @var list<array> $conversations */
?>
<section class="smsg-page">
    <div class="smsg-topbar">
        <h1>💬 <?= e(t('msg.title')) ?></h1>
        <p><?= e(t('msg.subtitle')) ?></p>
    </div>

    <div class="smsg">
        <?= render_partial('messages/_list', ['uid' => $uid, 'conversations' => $conversations, 'currentId' => '']) ?>

        <div class="smsg-thread smsg-thread--empty">
            <div class="smsg-empty">
                <div class="il" aria-hidden="true">💬</div>
                <b><?= e($conversations === [] ? t('msg.empty_title') : t('msg.select_convo')) ?></b>
                <p><?= e($conversations === [] ? t('msg.empty') : t('msg.select_hint')) ?></p>
                <?php if ($conversations === []): ?>
                    <a class="btn btn-gold" href="<?= e(url('/explorer')) ?>"><?= icon('search', ['size' => 16]) ?> <?= e(t('msg.empty_cta')) ?></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
