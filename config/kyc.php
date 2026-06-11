<?php
declare(strict_types=1);

/**
 * Vérification avancée (KYC) — 3 niveaux portant sur LA PERSONNE.
 * Chaque niveau est verrouillé tant que le précédent n'est pas approuvé par un
 * relecteur. Les `slots` listent les documents demandés (clé => requis ?).
 * La vérification de l'entreprise sera un parcours distinct, par type de boutique.
 */
return [
    'levels' => [
        1 => [
            'slots' => ['id_front' => true, 'id_back' => false],
            'has_doc_type' => true, // CNI / passeport
        ],
        2 => [
            'slots' => ['selfie' => true],
            'has_doc_type' => false,
        ],
        3 => [
            'slots' => ['address_proof' => true],
            'has_doc_type' => false,
        ],
    ],
    'id_types'  => ['cni', 'passport', 'permit'],
    'max_bytes' => 10 * 1024 * 1024, // 10 Mo/pièce
];
