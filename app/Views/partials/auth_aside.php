<?php
/**
 * Colonne de réassurance des pages d'inscription (design .authx) : arguments +
 * étapes « en 1 minute » + paiement sécurisé. Orientée vendeur par défaut.
 * Purement décorative — aucune donnée sensible.
 */
?>
<aside class="aside">
    <div class="promo">
        <p class="eyebrow"><?= e(t('nav.about')) ?></p>
        <h3><?= e(t('auth.aside.title')) ?></h3>
        <div class="benefit"><span class="bi" aria-hidden="true">✅</span><div><b><?= e(t('auth.aside.free_t')) ?></b><span><?= e(t('auth.aside.free_d')) ?></span></div></div>
        <div class="benefit"><span class="bi" aria-hidden="true">🌍</span><div><b><?= e(t('auth.aside.intl_t')) ?></b><span><?= e(t('auth.aside.intl_d')) ?></span></div></div>
        <div class="benefit"><span class="bi" aria-hidden="true">🔒</span><div><b><?= e(t('auth.aside.pay_t')) ?></b><span><?= e(t('auth.aside.pay_d')) ?></span></div></div>
        <div class="benefit"><span class="bi" aria-hidden="true">🛡️</span><div><b><?= e(t('auth.aside.kyc_t')) ?></b><span><?= e(t('auth.aside.kyc_d')) ?></span></div></div>
    </div>

    <div class="mini">
        <p class="eyebrow"><?= e(t('auth.aside.steps')) ?></p>
        <div class="mstep"><span class="num">1</span><div><b><?= e(t('auth.aside.s1_t')) ?></b><p><?= e(t('auth.aside.s1_d')) ?></p></div></div>
        <div class="mstep"><span class="num">2</span><div><b><?= e(t('auth.aside.s2_t')) ?></b><p><?= e(t('auth.aside.s2_d')) ?></p></div></div>
        <div class="mstep"><span class="num">3</span><div><b><?= e(t('auth.aside.s3_t')) ?></b><p><?= e(t('auth.aside.s3_d')) ?></p></div></div>
    </div>

    <div class="pay">
        <div class="lock">🔒 <?= e(t('auth.pay_secure')) ?></div>
        <div class="paybadges">
            <span>VISA</span><span>Mastercard</span><span>PayPal</span><span>Orange Money</span><span>MTN MoMo</span><span>Wave</span><span>Moov Money</span>
        </div>
    </div>
</aside>
