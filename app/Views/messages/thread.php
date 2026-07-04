<?php
/** Fil de conversation — messagerie deux volets. @var int $uid  @var array $conv  @var list<array> $messages
 *  @var string $other_name  @var bool $i_blocked  @var bool $blocked  @var list<array> $conversations */
$i_blocked     = $i_blocked ?? false;
$blocked       = $blocked ?? false;
$conversations = $conversations ?? [];
$initial       = mb_strtoupper(mb_substr($other_name, 0, 1));
?>
<section class="smsg-page">
    <div class="smsg-topbar">
        <h1>💬 <?= e(t('msg.title')) ?></h1>
        <p><?= e(t('msg.subtitle')) ?></p>
    </div>

    <div class="smsg show-thread">
        <?= render_partial('messages/_list', ['uid' => $uid, 'conversations' => $conversations, 'currentId' => (string) $conv['public_id']]) ?>

        <div class="smsg-thread">
            <div class="smsg-thread-head">
                <a class="smsg-back" href="<?= e(url('/messages')) ?>" aria-label="<?= e(t('msg.back_inbox')) ?>"><span aria-hidden="true">←</span></a>
                <span class="smsg-tav" aria-hidden="true"><?= e($initial) ?></span>
                <div class="smsg-thread-id">
                    <b><?= e($other_name) ?></b>
                    <?php if (!empty($conv['subject'])): ?><span>📦 <?= e((string) $conv['subject']) ?></span><?php endif; ?>
                </div>
                <form class="smsg-thread-acts" method="post" action="<?= e(url('/messages/' . $conv['public_id'] . '/bloquer')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $i_blocked ? 'unblock' : 'block' ?>">
                    <button type="submit" class="btn btn-ghost btn-sm"<?= $i_blocked ? '' : ' data-confirm="' . e(t('msg.block_confirm')) . '"' ?>><?= e($i_blocked ? t('msg.unblock') : t('msg.block')) ?></button>
                </form>
            </div>

            <div class="smsg-msgs" data-msg-log>
                <?php if ($messages === []): ?>
                    <p class="smsg-thread-empty-note"><?= e(t('msg.thread_empty')) ?></p>
                <?php endif; ?>
                <?php foreach ($messages as $m): $mine = (int) $m['sender_id'] === $uid; ?>
                    <div class="smsg-bubble <?= $mine ? 'out' : 'in' ?>">
                        <div class="smsg-bubble-body"><?= nl2br(e((string) $m['body'])) ?></div>
                        <span class="smsg-bt"><?= e(date('H:i', strtotime((string) $m['created_at']))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($blocked): ?>
                <div class="smsg-blocked">🚫 <?= e($i_blocked ? t('msg.blocked_by_me') : t('msg.blocked_notice')) ?></div>
            <?php else: ?>
                <form class="smsg-composer" method="post" action="<?= e(url('/messages/' . $conv['public_id'] . '/repondre')) ?>" data-submit-once>
                    <?= csrf_field() ?>
                    <input type="text" name="body" maxlength="2000" required autocomplete="off" placeholder="<?= e(t('msg.reply_ph')) ?>" aria-label="<?= e(t('msg.reply_ph')) ?>">
                    <button type="submit" class="smsg-send" aria-label="<?= e(t('msg.send')) ?>"><?= icon('send', ['size' => 18]) ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
