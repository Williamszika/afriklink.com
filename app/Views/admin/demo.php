<?php
/** @var int $count  Outil de démo temporaire. */
?>
<div class="container ann-admin">
    <h1>🧪 Démo</h1>
    <p class="muted">Outil temporaire : crée 1–2 boutiques d'exemple (avec une promo) pour voir une fonctionnalité en situation — par ex. les « pépites » de la newsletter — puis les retire. Données clairement « démo », purge propre.</p>

    <div class="admin-stats">
        <div class="admin-stat"><span class="admin-stat-n"><?= number_format((float) $count, 0, ',', ' ') ?></span><span class="admin-stat-lbl">Boutiques de démo en ligne</span></div>
    </div>

    <div class="panel">
        <div class="product-row-actions">
            <form method="post" action="<?= e(url('/admin/demo/creer')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <button class="btn btn-primary"><?= icon('sparkle', ['size' => 16]) ?> Créer 2 boutiques de démo</button>
            </form>
            <form method="post" action="<?= e(url('/admin/demo/retirer')) ?>" class="inline-form"
                  onsubmit="return confirm('Retirer toutes les boutiques de démo ?')">
                <?= csrf_field() ?>
                <button class="btn btn-ghost">Tout retirer</button>
            </form>
        </div>
        <p class="muted" style="font-size:.85rem;margin-top:10px"><?= icon('info', ['size' => 14]) ?> Après ton test, clique « Tout retirer » — et je supprime cet outil du code.</p>
    </div>
</div>
