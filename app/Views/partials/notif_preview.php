<?php
/** Aperçu des notifications (menu déroulant de la cloche). @var list<array> $notifications */
$icons = ['message' => '💬', 'order' => '🧾', 'review' => '⭐', 'info' => '🔔'];
?>
<?php if (empty($notifications)): ?>
    <p class="nav-dd-empty"><?= e(t('notif.empty')) ?></p>
<?php else: ?>
    <ul class="nav-dd-list">
        <?php foreach ($notifications as $n): ?>
            <li>
                <a class="nav-dd-row<?= empty($n['read_at']) ? ' is-unread' : '' ?>" href="<?= e(url('/notifications/' . (int) $n['id'] . '/ouvrir')) ?>">
                    <span class="dd-thumb dd-thumb--ico" aria-hidden="true"><?= $icons[$n['type']] ?? '🔔' ?></span>
                    <span class="dd-info">
                        <span class="dd-name"><?= e((string) $n['title']) ?></span>
                        <span class="muted dd-sub"><?= e(date('d/m H:i', strtotime((string) $n['created_at']))) ?></span>
                    </span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<a class="nav-dd-all" href="<?= e(url('/notifications')) ?>"><?= e(t('common.see_all')) ?> →</a>
