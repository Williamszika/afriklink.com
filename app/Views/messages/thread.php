<?php
/** Fil de conversation. @var int $uid  @var array $conv  @var list<array> $messages  @var string $other_name
 *  @var bool $i_blocked  @var bool $blocked */
$i_blocked = $i_blocked ?? false;
$blocked   = $blocked ?? false;
?>
<section class="msg-thread">
    <p class="muted"><a href="<?= e(url('/messages')) ?>">← <?= e(t('msg.back_inbox')) ?></a></p>
    <div class="msg-head" style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
        <h1 style="margin:0">💬 <?= e($other_name) ?></h1>
        <form method="post" action="<?= e(url('/messages/' . $conv['public_id'] . '/bloquer')) ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $i_blocked ? 'unblock' : 'block' ?>">
            <button type="submit" class="btn btn-ghost btn-sm"<?= $i_blocked ? '' : ' data-confirm="' . e(t('msg.block_confirm')) . '"' ?>><?= e($i_blocked ? t('msg.unblock') : t('msg.block')) ?></button>
        </form>
    </div>
    <?php if (!empty($conv['subject'])): ?><p class="muted msg-subject">📦 <?= e((string) $conv['subject']) ?></p><?php endif; ?>

    <div class="msg-log" data-msg-log>
        <?php if ($messages === []): ?>
            <p class="muted"><?= e(t('msg.thread_empty')) ?></p>
        <?php endif; ?>
        <?php foreach ($messages as $m): $mine = (int) $m['sender_id'] === $uid; ?>
            <div class="msg-bubble <?= $mine ? 'mine' : 'theirs' ?>">
                <p class="msg-body"><?= nl2br(e((string) $m['body'])) ?></p>
                <span class="msg-meta"><?= e(date('d/m/Y H:i', strtotime((string) $m['created_at']))) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($blocked): ?>
        <p class="muted msg-blocked-notice" style="padding:12px;background:#fbeaea;border-radius:8px;color:#b42318">
            🚫 <?= e($i_blocked ? t('msg.blocked_by_me') : t('msg.blocked_notice')) ?>
        </p>
    <?php else: ?>
    <form class="msg-reply" method="post" action="<?= e(url('/messages/' . $conv['public_id'] . '/repondre')) ?>">
        <?= csrf_field() ?>
        <textarea name="body" rows="3" maxlength="2000" required placeholder="<?= e(t('msg.reply_ph')) ?>" aria-label="<?= e(t('msg.reply_ph')) ?>"></textarea>
        <button type="submit" class="btn btn-primary"><?= e(t('msg.send')) ?></button>
    </form>
    <?php endif; ?>
</section>
