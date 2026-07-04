<?php
/** Volet gauche de la messagerie : recherche + onglets + liste des conversations.
 * @var int $uid  @var list<array> $conversations  @var ?string $currentId */
use App\Models\Conversation;

$currentId = $currentId ?? '';
$q   = trim((string) input_string('q', ''));
$tab = input_string('tab', '') === 'non_lus' ? 'non_lus' : 'tous';

$rows = [];
$unreadTotal = 0;
foreach ($conversations as $c) {
    $isBuyer   = (int) $c['buyer_id'] === $uid;
    $otherName = $isBuyer
        ? Conversation::displayName($c['seller_name'] ?? null, $c['seller_nick'] ?? null)
        : Conversation::displayName($c['buyer_name'] ?? null, $c['buyer_nick'] ?? null);
    $unread = Conversation::isUnread($c, $uid);
    if ($unread) { $unreadTotal++; }
    if ($q !== '' && mb_stripos($otherName . ' ' . (string) ($c['subject'] ?? ''), $q) === false) { continue; }
    if ($tab === 'non_lus' && !$unread) { continue; }
    $c['_name'] = $otherName;
    $c['_unread'] = $unread;
    $rows[] = $c;
}
?>
<div class="smsg-list">
    <div class="smsg-list-head">
        <form class="smsg-search" method="get" action="<?= e(url('/messages')) ?>" role="search">
            <?= icon('search', ['size' => 16]) ?>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('msg.search_ph')) ?>" aria-label="<?= e(t('msg.search_ph')) ?>">
        </form>
        <div class="smsg-tabs">
            <a class="smsg-tab <?= $tab === 'tous' ? 'on' : '' ?>" href="<?= e(url('/messages')) ?>"><?= e(t('msg.tab_all')) ?></a>
            <a class="smsg-tab <?= $tab === 'non_lus' ? 'on' : '' ?>" href="<?= e(url('/messages?tab=non_lus')) ?>"><?= e(t('msg.tab_unread')) ?><?php if ($unreadTotal > 0): ?> · <?= (int) $unreadTotal ?><?php endif; ?></a>
        </div>
    </div>
    <div class="smsg-convos">
        <?php foreach ($rows as $c): $active = $currentId === (string) $c['public_id']; ?>
            <a class="smsg-convo <?= $active ? 'on' : '' ?> <?= $c['_unread'] ? 'is-unread' : '' ?>" href="<?= e(url('/messages/' . $c['public_id'])) ?>">
                <span class="smsg-cav" aria-hidden="true"><?= e(mb_strtoupper(mb_substr((string) $c['_name'], 0, 1))) ?></span>
                <span class="smsg-cmeta">
                    <span class="smsg-crow"><span class="smsg-cname"><?= e((string) $c['_name']) ?></span><span class="smsg-ctime"><?= e(date('d/m', strtotime((string) $c['last_at']))) ?></span></span>
                    <span class="smsg-clast"><?= e(mb_strimwidth((string) ($c['last_body'] ?? ''), 0, 58, '…')) ?></span>
                    <?php if (!empty($c['subject'])): ?><span class="smsg-cctx">📦 <?= e((string) $c['subject']) ?></span><?php endif; ?>
                </span>
                <?php if ($c['_unread']): ?><span class="smsg-unread" aria-label="<?= e(t('msg.new')) ?>">●</span><?php endif; ?>
            </a>
        <?php endforeach; ?>
        <?php if ($rows === []): ?>
            <div class="smsg-list-empty">
                <?= icon('chat', ['size' => 30]) ?>
                <span><?= e($conversations === [] ? t('msg.empty') : t('msg.no_match')) ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>
