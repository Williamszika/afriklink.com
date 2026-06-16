<?php

/**
 * Caractéristiques « beauté & cosmétiques ». Le formulaire produit bascule en
 * mode beauté quand la boutique est de catégorie 'beaute' : marque + type de
 * produit + contenance + finition + type de peau + couvrance + PAO + EAN/SKU +
 * atouts + composition (INCI). Les déclinaisons sont des TEINTES, chacune avec
 * une pastille couleur DÉDUITE de son nom (table 'teinte_hex'), affichée côté
 * vendeur (aperçu) et côté client (sélecteur).
 *
 * Libellés en français (marché francophone), comme les rayons. Extensible.
 */
return [
    // Catégories de boutique qui activent le mode beauté.
    'shop_categories' => ['beaute'],

    // Types de produit (le plus structurant pour la fiche).
    'product_types' => [
        'Fond de teint', 'Poudre', 'Anticernes', 'Rouge à lèvres', 'Gloss',
        'Crayon à lèvres', 'Mascara', 'Eyeliner', 'Fard à paupières', 'Blush',
        'Highlighter', 'Palette', 'Base / primer', 'Spray fixateur',
        'Soin visage', 'Soin corps', 'Parfum', 'Vernis', 'Perruque',
    ],

    'finishes'    => ['Mat', 'Satiné', 'Naturel', 'Lumineux', 'Brillant', 'Poudré'],
    'skin_types'  => ['Sèche', 'Grasse', 'Mixte', 'Sensible', 'Mature'],
    'coverages'   => ['Légère', 'Moyenne', 'Haute'],
    // Période après ouverture (symbole « 12M »).
    'pao'         => ['6M', '12M', '18M', '24M', '36M'],
    'volume_units' => ['ml', 'g', 'pièce(s)'],

    // Atouts (cases à cocher multiples), stockés en CSV.
    'atouts' => ['Vegan', 'Cruelty-free', 'Sans parabène', 'Hypoallergénique', 'Bio', 'Halal'],

    // Teinte → pastille couleur (hex). Sert à dessiner le swatch partout, déduit
    // du NOM de la teinte (pas de sélecteur de couleur manuel : swatch cohérent).
    'teinte_hex' => [
        'Ivoire'      => '#F1DDC4',
        'Beige clair' => '#E7C8A3',
        'Sable'       => '#D8B489',
        'Doré'        => '#CD9F63',
        'Miel'        => '#BF8D4E',
        'Caramel'     => '#A9743C',
        'Noisette'    => '#946233',
        'Cannelle'    => '#82502A',
        'Cacao'       => '#5F3A20',
        'Chocolat'    => '#4A2E1B',
        'Moka'        => '#3D2417',
        'Ébène'       => '#2E1812',
        'Nude'        => '#C98D72',
        'Rosé'        => '#D98A8A',
        'Corail'      => '#E3705A',
        'Rouge'       => '#B8243B',
        'Prune'       => '#6E2A4D',
        // Quelques teintes couleur additionnelles courantes.
        'Noir'        => '#1B1B1B',
        'Brun'        => '#5A3A22',
        'Bordeaux'    => '#6E1B2E',
        'Fuchsia'     => '#C0276E',
    ],
];
