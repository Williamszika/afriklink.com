<?php
/** @var list<array> $queue */
?>
<section class="profile">
    <div class="seller-head">
        <h1>🛡️ <?= e(t('admin.kyc_title')) ?></h1>
        <p class="muted"><?= e(t('admin.kyc_sub', ['n' => count($queue)])) ?></p>
    </div>

    <?php if ($queue === []): ?>
        <div class="panel"><div class="empty-state"><p><?= e(t('admin.kyc_empty')) ?></p></div></div>
    <?php else: ?>
        <div class="panel">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th><?= e(t('admin.col_person')) ?></th>
                        <th><?= e(t('admin.col_level')) ?></th>
                        <th><?= e(t('admin.col_submitted')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queue as $row): ?>
                        <tr>
                            <td>
                                <strong><?= e((string) ($row['full_name'] ?? '—')) ?></strong><br>
                                <span class="muted"><?= e((string) $row['email']) ?>
                                    <?php if (!empty($row['country_code'])): ?> · <?= flag_emoji((string) $row['country_code']) ?><?php endif; ?>
                                </span>
                            </td>
                            <td><span class="badge badge-neutral"><?= e(t('kyc.level' . $row['level'] . '_title')) ?></span></td>
                            <td class="muted"><?= e(substr((string) $row['submitted_at'], 0, 16)) ?></td>
                            <td><a class="btn btn-primary btn-sm" href="<?= e(url('/admin/kyc/' . $row['id'])) ?>"><?= e(t('admin.review')) ?> →</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
