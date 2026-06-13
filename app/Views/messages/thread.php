<?php
/** Fil de conversation. @var int $uid  @var array $conv  @var list<array> $messages  @var string $other_name */
?>
<section class="msg-thread">
    <p class="muted"><a href="<?= e(url('/messages')) ?>">← <?= e(t('msg.back_inbox')) ?></a></p>
    <h1>💬 <?= e($other_name) ?></h1>
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

    <form class="msg-reply" method="post" action="<?= e(url('/messages/' . $conv['public_id'] . '/repondre')) ?>">
        <?= csrf_field() ?>
        <textarea name="body" rows="3" maxlength="2000" required placeholder="<?= e(t('msg.reply_ph')) ?>" aria-label="<?= e(t('msg.reply_ph')) ?>"></textarea>
        <button type="submit" class="btn btn-primary"><?= e(t('msg.send')) ?></button>
    </form>
</section>
