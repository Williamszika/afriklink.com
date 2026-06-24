<?php
/** @var list<array{id:string,text:string,screen:string,link:string}> $topics
 *  @var string $name  @var bool $configured */
?>
<section class="container">
    <div class="page-head">
        <h1>🙋🏾‍♀️ <?= e(t('agnes.center_title', ['name' => $name])) ?></h1>
        <p class="muted"><?= e(t('agnes.center_lead')) ?></p>
        <?php if (!$configured): ?>
            <p class="hint"><?= e(t('agnes.center_ai_off', ['name' => $name])) ?></p>
        <?php endif; ?>
    </div>

    <div class="help-center-grid">
        <?php foreach ($topics as $t): ?>
            <article class="help-card">
                <?php if ($t['screen'] !== ''): ?>
                    <img src="<?= e($t['screen']) ?>" alt="" loading="lazy">
                <?php endif; ?>
                <div class="help-card-body">
                    <p><?= e($t['text']) ?></p>
                    <?php if ($t['link'] !== ''): ?>
                        <p><a class="btn btn-primary btn-sm" href="<?= e(url($t['link'])) ?>"><?= e(t('agnes.center_open')) ?> →</a></p>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
