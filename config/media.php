<?php
declare(strict_types=1);

/**
 * Médias — nettoyage automatique des photos PRODUIT (détourage IA + fond neutre).
 *
 * DÉSACTIVÉ par défaut. Tant que MEDIA_AUTOCLEAN ≠ 1, les photos sont servies
 * exactement comme avant (aucun changement, aucun coût).
 *
 * Pour l'ACTIVER : il faut activer l'add-on « Cloudinary AI Background Removal »
 * sur le compte Cloudinary (gratuit jusqu'à un quota, puis facturé à l'image),
 * puis poser MEDIA_AUTOCLEAN=1. Le détourage est alors appliqué à la volée, en
 * transformation d'URL, UNIQUEMENT sur les photos de produit boutique (jamais
 * les logos, bannières, ni les annonces génériques type voiture/immobilier).
 *
 * ⚠️ Si on met 1 SANS l'add-on, Cloudinary renverra une erreur sur ces images :
 * ne l'allumer qu'une fois l'add-on actif.
 */
return [
    'autoclean'    => env('MEDIA_AUTOCLEAN', '0') === '1',
    // Couleur de fond neutre (hex SANS #) posée après le détourage. Gris clair de
    // catalogue par défaut — un blanc pur ferait disparaître un vêtement blanc.
    'autoclean_bg' => env('MEDIA_AUTOCLEAN_BG', 'eef1f5'),
];
