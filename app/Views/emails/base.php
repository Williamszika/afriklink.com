<?php
/**
 * Gabarit d'e-mail de marque AfrikaLink — vert forêt + or, logo cauri, cauri en
 * filigrane de fond. Compatible e-mail (tableaux + styles essentiels en ligne) et
 * joli en aperçu. Le contenu (intro, corps, CTA) est passé par l'appelant.
 *
 * @var string $subject @var string $preheader @var string $heading
 * @var string $intro  (HTML autorisé)   @var string $body  (HTML, optionnel)
 * @var string $cta_url @var string $cta_label  @var string $outro  (HTML, optionnel)
 * @var string $accent  'gold' (défaut) ou 'forest'
 */
$site    = (string) config('app.name', 'Afriklink');
$heading = (string) ($heading ?? '');
$intro   = (string) ($intro ?? '');
$body    = (string) ($body ?? '');
$ctaUrl  = (string) ($cta_url ?? '');
$ctaLbl  = (string) ($cta_label ?? '');
$outro   = (string) ($outro ?? '');
$accent  = ($accent ?? 'gold') === 'forest' ? 'forest' : 'gold';
$ctaBg   = $accent === 'forest' ? '#103D30' : '#E5A02E';
$ctaFg   = $accent === 'forest' ? '#ffffff' : '#3A2A06';
// Cauri en filigrane (deux coquillages décalés, très discrets) — data URI tuilée.
$cauri   = "data:image/svg+xml,%3Csvg%20xmlns='http://www.w3.org/2000/svg'%20width='96'%20height='128'%20viewBox='0%200%2096%20128'%3E%3Cg%20fill='%23103D30'%20fill-opacity='0.045'%3E%3Cpath%20d='M24%205C33%205%2039.5%2016%2039.5%2032%2039.5%2049%2033%2059%2024%2059%2015%2059%208.5%2049%208.5%2032%208.5%2016%2015%205%2024%205Z'/%3E%3Cpath%20d='M72%2069C81%2069%2087.5%2080%2087.5%2096%2087.5%20113%2081%20123%2072%20123%2063%20123%2056.5%20113%2056.5%2096%2056.5%2080%2063%2069%2072%2069Z'/%3E%3C/g%3E%3C/svg%3E";
?>
<!doctype html>
<html lang="<?= e(current_locale()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title><?= e($subject ?? $heading) ?></title>
<style>
    body { margin:0; padding:0; background:#FBF7EF; -webkit-text-size-adjust:100%; }
    img { border:0; line-height:100%; outline:none; text-decoration:none; }
    .afk-wrap { width:100%; background-color:#FBF7EF; background-image:url("<?= $cauri ?>"); background-size:96px 128px; }
    .afk-card { max-width:600px; margin:0 auto; background:#ffffff; border-radius:18px; overflow:hidden;
        box-shadow:0 24px 50px -28px rgba(16,36,30,.5);
        font-family:'Inter',-apple-system,BlinkMacSystemFont,'Segoe UI',Arial,sans-serif; color:#16241F; }
    .afk-head { background:linear-gradient(135deg,#103D30 0%,#0B2C22 100%); padding:24px 30px;
        border-bottom:4px solid #E5A02E; }
    .afk-logo { width:42px; height:42px; background:#FBF7EF; border-radius:11px; text-align:center;
        line-height:42px; box-shadow:0 6px 14px -8px rgba(0,0,0,.6); }
    .afk-logo .cauri { width:26px; height:35px; vertical-align:middle; }
    .afk-word { font-family:'Bricolage Grotesque','Inter',Arial,sans-serif; font-weight:800; font-size:1.4rem;
        letter-spacing:-.02em; color:#ffffff; line-height:1; }
    .afk-word span { color:#E5A02E; }
    .afk-tag { font-size:.66rem; text-transform:uppercase; letter-spacing:.16em; color:#F5D699; margin-top:4px; }
    .afk-body { padding:30px 32px; background:#ffffff; background-image:url("<?= $cauri ?>"); background-size:120px 160px; }
    .afk-h1 { font-family:'Bricolage Grotesque','Inter',Arial,sans-serif; font-weight:800; font-size:1.5rem;
        line-height:1.2; margin:0 0 12px; color:#103D30; }
    .afk-p { font-size:.97rem; line-height:1.6; margin:0 0 14px; color:#2f3d36; }
    .afk-cta { display:inline-block; padding:13px 26px; background:<?= $ctaBg ?>; color:<?= $ctaFg ?>;
        text-decoration:none; border-radius:11px; font-weight:800; font-size:.97rem; }
    .afk-cta-wrap { margin:20px 0 8px; }
    .afk-link { font-size:.8rem; color:#7a857e; word-break:break-all; }
    .afk-panel { background:#FBF7EF; border:1px solid rgba(16,36,30,.1); border-left:3px solid #E5A02E;
        border-radius:12px; padding:14px 18px; margin:8px 0 16px; }
    .afk-foot { padding:18px 30px; background:#FBF7EF; border-top:1px solid rgba(16,36,30,.1);
        text-align:center; font-size:.78rem; color:#5B6B62; }
    .afk-foot b { color:#103D30; } .afk-foot b span { color:#E5A02E; }
    .afk-foot .cauri { width:13px; height:17px; vertical-align:middle; }
    @media (max-width:600px) { .afk-body { padding:24px 20px; } .afk-head { padding:20px; } }
</style>
</head>
<body>
<div style="display:none;max-height:0;overflow:hidden;opacity:0"><?= e((string) ($preheader ?? $heading)) ?></div>
<table role="presentation" class="afk-wrap" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
<td style="padding:28px 14px">
    <table role="presentation" class="afk-card" width="600" align="center" cellpadding="0" cellspacing="0" border="0">
        <tr><td class="afk-head">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>
                <td valign="middle" style="padding-right:13px"><span class="afk-logo"><?= render_partial('partials/logo', ['uid' => 'mail']) ?></span></td>
                <td valign="middle">
                    <div class="afk-word">Afrik<span>link</span></div>
                    <div class="afk-tag"><?= e(t('mail.tagline')) ?></div>
                </td>
            </tr></table>
        </td></tr>
        <tr><td class="afk-body">
            <?php if ($heading !== ''): ?><h1 class="afk-h1"><?= $heading ?></h1><?php endif; ?>
            <?php if ($intro !== ''): ?><p class="afk-p"><?= $intro ?></p><?php endif; ?>
            <?= $body ?>
            <?php if ($ctaUrl !== '' && $ctaLbl !== ''): ?>
                <div class="afk-cta-wrap"><a class="afk-cta" href="<?= e($ctaUrl) ?>"><?= e($ctaLbl) ?></a></div>
                <p class="afk-link"><?= e($ctaUrl) ?></p>
            <?php endif; ?>
            <?php if ($outro !== ''): ?><p class="afk-p" style="margin-top:16px"><?= $outro ?></p><?php endif; ?>
        </td></tr>
        <tr><td class="afk-foot">
            <?= render_partial('partials/logo', ['uid' => 'mailf']) ?>
            <span style="margin-left:5px"><b>Afrik<span>link</span></b> — <?= e(t('mail.footer_tag')) ?></span>
        </td></tr>
    </table>
</td></tr></table>
</body>
</html>
