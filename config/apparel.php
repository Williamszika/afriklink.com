<?php

/**
 * Taxonomie prêt-à-porter : genres (audiences), catégories de vêtements, et le
 * SYSTÈME DE TAILLES propre à chaque catégorie (un soutien-gorge ne se mesure pas
 * comme un pantalon ou une chaussure). Les tissus/pagnes se vendent AU MÈTRE.
 *
 * Extensible : ajoutez une catégorie en lui donnant un groupe, un système de
 * tailles (clé de 'size_systems') et son unité de vente ('piece' ou 'meter').
 * Les libellés sont traduits via i18n : apparel.aud.* / apparel.grp.* / apparel.cat.*
 */
return [
    // Catégories de boutique qui activent le mode « prêt-à-porter » (champs vêtements).
    'shop_categories' => ['mode'],

    // Publics visés.
    'audiences' => ['homme', 'femme', 'unisexe', 'enfant'],

    // Suggestions de tailles par système (la liste 'bra' est générée par helper).
    'size_systems' => [
        'alpha'     => ['XS', 'S', 'M', 'L', 'XL', 'XXL', '3XL', '4XL'],
        'shoe_eu'   => ['35', '36', '37', '38', '39', '40', '41', '42', '43', '44', '45', '46', '47'],
        'shoe_kids' => ['18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34'],
        'bra'       => [], // généré dynamiquement (tour de dos × bonnet)
        'waist_fr'  => ['36', '38', '40', '42', '44', '46', '48', '50', '52', '54'],
        'dress_fr'  => ['34', '36', '38', '40', '42', '44', '46', '48', '50'],
        'age'       => ['0-3 mois', '3-6 mois', '6-12 mois', '1 an', '2 ans', '3 ans', '4 ans', '6 ans', '8 ans', '10 ans', '12 ans', '14 ans'],
        'none'      => [],
        'meter'     => [],
    ],

    // Catégories : clé => [groupe, système de tailles, unité ('piece'|'meter'), genres].
    'categories' => [
        'tshirt'     => ['hauts',          'alpha',     'piece', ['homme', 'femme', 'unisexe', 'enfant']],
        'shirt'      => ['hauts',          'alpha',     'piece', ['homme', 'femme', 'unisexe']],
        'pull'       => ['hauts',          'alpha',     'piece', ['homme', 'femme', 'unisexe', 'enfant']],
        'jacket'     => ['hauts',          'alpha',     'piece', ['homme', 'femme', 'unisexe']],
        'pants'      => ['bas',            'waist_fr',  'piece', ['homme', 'femme', 'unisexe']],
        'shorts'     => ['bas',            'alpha',     'piece', ['homme', 'femme', 'unisexe', 'enfant']],
        'skirt'      => ['bas',            'dress_fr',  'piece', ['femme', 'enfant']],
        'dress'      => ['robes',          'dress_fr',  'piece', ['femme', 'enfant']],
        'bra'        => ['sous_vetements', 'bra',       'piece', ['femme']],
        'panties'    => ['sous_vetements', 'alpha',     'piece', ['femme']],
        'briefs'     => ['sous_vetements', 'alpha',     'piece', ['homme']],
        'socks'      => ['sous_vetements', 'none',      'piece', ['homme', 'femme', 'unisexe', 'enfant']],
        'shoes'      => ['chaussures',     'shoe_eu',   'piece', ['homme', 'femme', 'unisexe']],
        'shoes_kids' => ['chaussures',     'shoe_kids', 'piece', ['enfant']],
        'fabric'     => ['tissus',         'meter',     'meter', ['homme', 'femme', 'unisexe', 'enfant']],
        'uniform'    => ['uniformes',      'alpha',     'piece', ['homme', 'femme', 'unisexe', 'enfant']],
        'accessory'  => ['accessoires',    'none',      'piece', ['homme', 'femme', 'unisexe', 'enfant']],
    ],
];
