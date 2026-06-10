<?php
declare(strict_types=1);

/**
 * ISO 3166-1 alpha-2 => international dialing code (without '+').
 * Same country set as config/countries.php. Used to auto-fill the phone indicatif.
 */

return [
    // Afrique de l'Ouest
    'BJ' => '229', 'BF' => '226', 'CV' => '238', 'CI' => '225', 'GM' => '220',
    'GH' => '233', 'GN' => '224', 'GW' => '245', 'LR' => '231', 'ML' => '223',
    'MR' => '222', 'NE' => '227', 'NG' => '234', 'SN' => '221', 'SL' => '232',
    'TG' => '228',
    // Afrique (autres)
    'CM' => '237', 'CG' => '242', 'CD' => '243', 'GA' => '241', 'MA' => '212',
    'DZ' => '213', 'TN' => '216',
    // Europe
    'FR' => '33', 'BE' => '32', 'DE' => '49', 'ES' => '34', 'IT' => '39',
    'GB' => '44', 'NL' => '31', 'PT' => '351', 'CH' => '41', 'LU' => '352',
    // Amérique du Nord
    'US' => '1', 'CA' => '1',
];
