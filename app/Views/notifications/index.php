<?php
/** @var list<array> $notifications */
$icons = ['message' => '💬', 'order' => '🧾', 'review' => '⭐', 'info' => '🔔'];
?>
<section class="notif-page">
    <div class="notif-head">
        <h1>🔔 <?= e(t('notif.title')) ?></h1>
        <?php if ($notifications !== []): ?>
            <form method="post" action="<?= e(url('/notifications/lus')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-ghost btn-sm"><?= e(t('notif.mark_all')) ?></button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($notifications === []): ?>
        <div class="empty-state">
            <p style="font-size:2rem;margin:0 0 6px" aria-hidden="true">🔔</p>
            <p><?= e(t('notif.empty')) ?></p>
            <a class="btn btn-primary" href="<?= e(url('/explorer')) ?>"><?= e(t('notif.empty_cta')) ?></a>
        </div>
    <?php else: ?>
        <ul class="notif-list">
            <?php foreach ($notifications as $n): $unread = empty($n['read_at']); ?>
                <li>
                    <a class="notif-item<?= $unread ? ' is-unread' : '' ?>" href="<?= e(url('/notifications/' . (int) $n['id'] . '/ouvrir')) ?>">
                        <span class="notif-ico" aria-hidden="true"><?= $icons[$n['type']] ?? '🔔' ?></span>
                        <span class="notif-body">
                            <strong class="notif-title"><?= e((string) $n['title']) ?></strong>
                            <?php if (!empty($n['body'])): ?><span class="notif-text muted"><?= e(mb_strimwidth((string) $n['body'], 0, 120, '…')) ?></span><?php endif; ?>
                            <span class="notif-time muted"><?= e(date('d/m/Y H:i', strtotime((string) $n['created_at']))) ?></span>
                        </span>
                        <?php if ($unread): ?><span class="notif-dot" aria-hidden="true">●</span><?php endif; ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
