<?php
/** Boîte de réception unifiée. @var int $uid  @var list<array> $conversations */
use App\Models\Conversation;
?>
<section class="msg-inbox">
    <h1>💬 <?= e(t('msg.title')) ?></h1>

    <?php if ($conversations === []): ?>
        <div class="empty-state">
            <p style="font-size:2rem;margin:0 0 6px" aria-hidden="true">💬</p>
            <p><?= e(t('msg.empty')) ?></p>
            <a class="btn btn-ghost" href="<?= e(url('/explorer')) ?>"><?= e(t('msg.empty_cta')) ?></a>
        </div>
    <?php else: ?>
        <ul class="conv-list">
            <?php foreach ($conversations as $c): ?>
                <?php
                $isBuyer   = (int) $c['buyer_id'] === $uid;
                $otherName = $isBuyer
                    ? Conversation::displayName($c['seller_name'] ?? null, $c['seller_nick'] ?? null)
                    : Conversation::displayName($c['buyer_name'] ?? null, $c['buyer_nick'] ?? null);
                $unread = Conversation::isUnread($c, $uid);
                ?>
                <li>
                    <a class="conv-item<?= $unread ? ' is-unread' : '' ?>" href="<?= e(url('/messages/' . $c['public_id'])) ?>">
                        <span class="conv-top">
                            <strong class="conv-name"><?= e($otherName) ?></strong>
                            <span class="conv-time muted"><?= e(date('d/m H:i', strtotime((string) $c['last_at']))) ?></span>
                        </span>
                        <?php if (!empty($c['subject'])): ?><span class="conv-subject muted">📦 <?= e((string) $c['subject']) ?></span><?php endif; ?>
                        <span class="conv-snippet muted"><?= e(mb_strimwidth((string) ($c['last_body'] ?? ''), 0, 90, '…')) ?></span>
                        <?php if ($unread): ?><span class="conv-badge"><?= e(t('msg.new')) ?></span><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
