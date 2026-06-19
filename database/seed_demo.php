<?php
declare(strict_types=1);

/**
 * Jeu de DÉMONSTRATION : 50 boutiques publiées (Europe + Côte d'Ivoire), avec
 * leurs produits, réparties sur plusieurs vendeurs — dont certains possèdent
 * PLUSIEURS boutiques (le code multi-boutique existe déjà : Boutique::allForUser
 * + /vendeur/boutique-active). À lancer en local UNIQUEMENT :
 *
 *   php database/seed_demo.php --force
 *
 * Idempotent : purge d'abord les données de démo précédentes (vendeurs
 * @afriklink.demo et leurs boutiques/produits), puis réinsère. Ne JAMAIS lancer
 * en production.
 */

require __DIR__ . '/../app/bootstrap.php';

use App\Models\Boutique;
use App\Models\Product;
use App\Models\User;

if (!in_array('--force', $argv, true)) {
    fwrite(STDERR, "Refus : ajoutez --force (jeu de démo, à n'exécuter qu'en local).\n");
    exit(1);
}
if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    fwrite(STDERR, "Refus : APP_ENV=production. Le seeder de démo est réservé au local.\n");
    exit(1);
}

$pdo = db();
fwrite(STDOUT, "→ Purge des données de démo précédentes…\n");
$demoIds = $pdo->query("SELECT id FROM users WHERE email LIKE '%@afriklink.demo'")->fetchAll(PDO::FETCH_COLUMN) ?: [];
if ($demoIds !== []) {
    $in = implode(',', array_map('intval', $demoIds));
    $bids = $pdo->query("SELECT id FROM boutiques WHERE user_id IN ($in)")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    if ($bids !== []) {
        $binp = implode(',', array_map('intval', $bids));
        $pdo->exec("DELETE FROM product_variants WHERE boutique_id IN ($binp)");
        $pdo->exec("DELETE FROM product_photos WHERE product_id IN (SELECT id FROM products WHERE boutique_id IN ($binp))");
        $pdo->exec("DELETE FROM products WHERE boutique_id IN ($binp)");
        $pdo->exec("DELETE FROM boutiques WHERE id IN ($binp)");
    }
    $pdo->exec("DELETE FROM users WHERE id IN ($in)");
}

/* ----- Vendeurs : 14 comptes pro ; la 1ʳᵉ poignée possède plusieurs boutiques ----- */
$sellerNames = [
    'Aminata Koné', 'Jean-Marc Dubois', 'Fatou Bamba', 'Sophie Laurent', 'Kouadio N\'Guessan',
    'Marco Rossi', 'Awa Traoré', 'Lucas Martin', 'Ngozi Okafor', 'Émilie Bernard',
    'Yao Kouassi', 'Carla Santos', 'Ibrahim Cissé', 'Hannah Schmidt',
];
$shopsPerSeller = [6, 5, 5, 4, 4, 4, 4, 3, 3, 3, 3, 3, 2, 1]; // somme = 50
$pwd = password_hash('demo1234', PASSWORD_DEFAULT);
$sellerIds = [];
foreach ($sellerNames as $i => $name) {
    $sellerIds[] = User::create([
        'email'        => 'seed' . ($i + 1) . '@afriklink.demo',
        'password_hash'=> $pwd,
        'account_type' => 'professionnel',
        'full_name'    => $name,
        'locale'       => 'fr',
        'preferred_currency' => 'EUR',
        'status'       => 'active',
    ]);
}
// Aplati : un identifiant vendeur par boutique (selon la répartition).
$ownerOf = [];
foreach ($shopsPerSeller as $si => $n) {
    for ($k = 0; $k < $n; $k++) { $ownerOf[] = $sellerIds[$si]; }
}

/* ----- Lieux : Europe (EUR/GBP) + Côte d'Ivoire (XOF) ----- */
$places = [
    ['Paris', 'FR', 'Europe', 'EUR'], ['Lyon', 'FR', 'Europe', 'EUR'], ['Marseille', 'FR', 'Europe', 'EUR'],
    ['Toulouse', 'FR', 'Europe', 'EUR'], ['Bruxelles', 'BE', 'Europe', 'EUR'], ['Liège', 'BE', 'Europe', 'EUR'],
    ['Berlin', 'DE', 'Europe', 'EUR'], ['Munich', 'DE', 'Europe', 'EUR'], ['Madrid', 'ES', 'Europe', 'EUR'],
    ['Barcelone', 'ES', 'Europe', 'EUR'], ['Milan', 'IT', 'Europe', 'EUR'], ['Rome', 'IT', 'Europe', 'EUR'],
    ['Amsterdam', 'NL', 'Europe', 'EUR'], ['Lisbonne', 'PT', 'Europe', 'EUR'], ['Londres', 'GB', 'Europe', 'GBP'],
    ['Manchester', 'GB', 'Europe', 'GBP'],
    ['Abidjan', 'CI', 'Afrique', 'XOF'], ['Bouaké', 'CI', 'Afrique', 'XOF'], ['Yamoussoukro', 'CI', 'Afrique', 'XOF'],
    ['San-Pédro', 'CI', 'Afrique', 'XOF'], ['Korhogo', 'CI', 'Afrique', 'XOF'], ['Daloa', 'CI', 'Afrique', 'XOF'],
    ['Man', 'CI', 'Afrique', 'XOF'], ['Gagnoa', 'CI', 'Afrique', 'XOF'],
];

/* ----- Catégories (verticales) + gabarits de noms + produits ----- */
$cats = ['maison', 'alimentation', 'mode', 'auto', 'artisanat', 'beaute', 'bebe', 'sport', 'electronique'];
$catLabel = [
    'maison' => 'Maison & Déco', 'alimentation' => 'Épicerie', 'mode' => 'Boutique Mode',
    'auto' => 'Auto Pièces', 'artisanat' => 'Artisanat', 'beaute' => 'Beauté', 'bebe' => 'Univers Bébé',
    'sport' => 'Sport Zone', 'electronique' => 'TechZone',
];
$catTagline = [
    'maison' => 'Meubles & décoration pour votre intérieur',
    'alimentation' => 'Saveurs d\'ici et d\'ailleurs',
    'mode' => 'Vêtements & accessoires tendance',
    'auto' => 'Pièces & accessoires auto',
    'artisanat' => 'Pièces faites main, art africain',
    'beaute' => 'Cosmétiques & soins',
    'bebe' => 'Tout pour bébé & enfant',
    'sport' => 'Équipement & vêtements de sport',
    'electronique' => 'Smartphones, accessoires & gadgets',
];

// Gabarit produit par catégorie : [rayon, nom, marque, prix EUR (cents), attributs].
$productTpl = [
    'maison' => [
        ['Salon', 'Canapé d\'angle convertible', 'HomeStyle', 49900, ['matiere' => 'Tissu', 'dimension' => '250×150 cm', 'condition' => 'Neuf', 'garantie' => '2 ans']],
        ['Cuisine', 'Robot pâtissier 1200 W', 'KitchenPro', 12900, ['product_type' => 'Robot', 'condition' => 'Neuf', 'garantie' => '2 ans', 'specs' => ['Bol' => '5 L', 'Vitesses' => '6']]],
        ['Décoration', 'Lampe en rotin tressé', 'Atelier', 4500, ['matiere' => 'Rotin', 'condition' => 'Neuf', 'specs' => ['Hauteur' => '45 cm']]],
        ['Chambre', 'Parure de lit 240×260', 'CocoonHome', 3990, ['matiere' => 'Coton', 'condition' => 'Neuf', 'specs' => ['Pièces' => '3']]],
    ],
    'alimentation' => [
        ['Épicerie', 'Riz parfumé 5 kg', 'Terroir', 1290, ['conservation' => 'Ambiante', 'origine' => 'Importé']],
        ['Boissons', 'Jus de bissap 1 L', 'Saveurs', 390, ['conservation' => 'Réfrigérée', 'origine' => 'Sénégal']],
        ['Épices', 'Mélange d\'épices grillades 200 g', 'Terroir', 590, ['conservation' => 'Ambiante', 'specs' => ['Poids' => '200 g']]],
        ['Snacks', 'Chips de plantain 150 g', 'Banania', 290, ['conservation' => 'Ambiante', 'origine' => 'Côte d\'Ivoire']],
    ],
    'mode' => [
        ['Chaussures', 'Baskets en cuir blanches', 'UrbanFeet', 7990, ['genre' => 'Homme', 'couleur' => 'Blanc', 'matiere' => 'Cuir', 'condition' => 'Neuf']],
        ['Robes', 'Robe wax longue', 'WaxStyle', 4990, ['genre' => 'Femme', 'couleur' => 'Multicolore', 'matiere' => 'Wax / coton', 'condition' => 'Neuf']],
        ['T-shirts', 'T-shirt coton bio', 'BasicWear', 1990, ['genre' => 'Mixte', 'couleur' => 'Noir', 'matiere' => 'Coton bio', 'condition' => 'Neuf']],
        ['Sacs', 'Sac à main cuir grainé', 'Élégance', 8990, ['couleur' => 'Camel', 'matiere' => 'Cuir', 'condition' => 'Neuf']],
    ],
    'auto' => [
        ['Accessoires', 'Caméra de recul HD', 'AutoTech', 3490, ['compatibilite' => 'Universel', 'condition' => 'Neuf', 'garantie' => '1 an']],
        ['Entretien', 'Kit vidange huile 5W30', 'MotorCare', 4290, ['condition' => 'Neuf', 'specs' => ['Volume' => '5 L']]],
        ['Pneus', 'Pneu 205/55 R16', 'RoadGrip', 6990, ['condition' => 'Neuf', 'dimension' => '205/55 R16']],
        ['Audio', 'Autoradio Bluetooth 2 DIN', 'SoundCar', 8990, ['compatibilite' => 'Universel', 'condition' => 'Neuf']],
    ],
    'artisanat' => [
        ['Décoration', 'Masque sculpté à la main', 'Mains d\'Or', 5900, ['matiere' => 'Bois d\'ébène', 'origine' => 'Côte d\'Ivoire', 'fait_main' => 1, 'piece_unique' => 1]],
        ['Bijoux', 'Collier perles de verre', 'Atelier Awa', 2490, ['matiere' => 'Perles', 'fait_main' => 1]],
        ['Textile', 'Pagne tissé Baoulé 6 yards', 'TissAfrik', 3990, ['matiere' => 'Coton tissé', 'origine' => 'Côte d\'Ivoire', 'fait_main' => 1]],
        ['Vannerie', 'Panier en raphia tressé', 'Mains d\'Or', 1790, ['matiere' => 'Raphia', 'fait_main' => 1]],
    ],
    'beaute' => [
        ['Soins corps', 'Beurre de karité brut 200 ml', 'NaturAfrik', 1290, ['condition' => 'Neuf', 'specs' => ['Contenance' => '200 ml']]],
        ['Maquillage', 'Palette fards 12 teintes', 'GlowUp', 1990, ['condition' => 'Neuf']],
        ['Cheveux', 'Huile de ricin noire 100 ml', 'NaturAfrik', 990, ['condition' => 'Neuf', 'specs' => ['Contenance' => '100 ml']]],
        ['Parfums', 'Eau de parfum 50 ml', 'Essence', 3490, ['condition' => 'Neuf', 'specs' => ['Contenance' => '50 ml']]],
    ],
    'bebe' => [
        ['Jouets', 'Cube d\'éveil musical', 'PetitMonde', 2490, ['age_min' => '6 mois', 'matiere' => 'Plastique sans BPA', 'ce' => 1, 'condition' => 'Neuf']],
        ['Vêtements bébé', 'Body coton bio (lot de 3)', 'Câlin', 1790, ['taille' => '6 mois', 'matiere' => 'Coton bio', 'condition' => 'Neuf']],
        ['Puériculture', 'Biberon anti-colique 260 ml', 'BabyCare', 990, ['ce' => 1, 'condition' => 'Neuf', 'specs' => ['Contenance' => '260 ml']]],
        ['Soins', 'Liniment oléo-calcaire 500 ml', 'BabyCare', 690, ['condition' => 'Neuf', 'specs' => ['Contenance' => '500 ml']]],
    ],
    'sport' => [
        ['Chaussures', 'Chaussures de running légères', 'AfrikRun', 6990, ['product_type' => 'Running', 'public' => 'Homme', 'terrain' => 'Route', 'matiere' => 'Mesh', 'condition' => 'Neuf', 'par_paire' => 1, 'specs' => ['Drop' => '8 mm']]],
        ['Fitness', 'Haltères réglables 20 kg', 'IronFit', 8990, ['product_type' => 'Haltères', 'condition' => 'Neuf', 'specs' => ['Poids' => '2×10 kg']]],
        ['Vêtements', 'Maillot de foot domicile', 'TeamWear', 3490, ['public' => 'Mixte', 'matiere' => 'Polyester', 'condition' => 'Neuf']],
        ['Plein air', 'Tente 2 places imperméable', 'OutGear', 7990, ['condition' => 'Neuf', 'specs' => ['Places' => '2', 'Imperméabilité' => '3000 mm']]],
    ],
    'electronique' => [
        ['Téléphones', 'Smartphone 128 Go double SIM', 'Nova', 14990, ['variant_axis' => 'Stockage', 'variant_axis2' => 'Couleur', 'condition' => 'Neuf', 'garantie' => '1 an', 'specs' => ['RAM' => '6 Go', 'Écran' => '6,5"']]],
        ['Accessoires', 'Écouteurs Bluetooth ANC', 'SoundPods', 3990, ['compatibilite' => 'Universel', 'condition' => 'Neuf', 'specs' => ['Autonomie' => '24 h']]],
        ['Audio & écouteurs', 'Enceinte portable étanche', 'BoomBox', 4990, ['condition' => 'Neuf', 'specs' => ['Autonomie' => '12 h', 'Puissance' => '20 W']]],
        ['Accessoires', 'Powerbank 20000 mAh', 'ChargePro', 2490, ['condition' => 'Neuf', 'specs' => ['Capacité' => '20000 mAh']]],
    ],
];

/** Convertit un prix EUR (cents) vers la devise cible (cents). */
function price_for(string $cur, int $eurCents): int
{
    return match ($cur) {
        'XOF'   => $eurCents * 6,          // ~1 € ≈ 656 F → on stocke en « centimes » de F
        'GBP'   => (int) round($eurCents * 0.85),
        default => $eurCents,
    };
}

$created = 0; $prodCount = 0;
$catCount = count($cats); $placeCount = count($places);
for ($i = 0; $i < 50; $i++) {
    $cat   = $cats[$i % $catCount];
    $place = $places[$i % $placeCount];
    [$city, $cc, $continent, $cur] = $place;
    $owner = $ownerOf[$i];
    $name  = $catLabel[$cat] . ' ' . $city . ' #' . ($i + 1);
    $slug  = Boutique::uniqueSlug($name);
    $pubId = Boutique::create($owner, [
        'slug' => $slug, 'name' => $name, 'tagline' => $catTagline[$cat],
        'description' => 'Boutique ' . $catLabel[$cat] . ' basée à ' . $city . '. Livraison locale et internationale.',
        'category' => $cat, 'logo_public_id' => null, 'banners' => [], 'currency' => $cur,
        'shop_type' => 'online', 'address' => null, 'city' => $city, 'country_code' => $cc, 'continent' => $continent,
        'geo_lat' => null, 'geo_lng' => null, 'delivery_zones' => null, 'delivery_methods' => 'livraison,retrait',
        'free_ship_cents' => null, 'delivery_fee_cents' => 0, 'delivery_intl_cents' => null, 'delivery_delay' => '2-5 jours',
        'prep_time' => null, 'cod_enabled' => 1, 'payment_terms' => ['full'], 'payment_methods' => ['cash', 'mobile_money'],
        'payment_provider' => null,
        'contacts' => ['whatsapp' => '+2250700000000'], 'contact_primary' => ['whatsapp'],
    ]);
    $b = Boutique::findBySlug($slug);
    if ($b === null) { continue; }
    Boutique::setStatus((int) $b['id'], 'published');
    $created++;

    foreach ($productTpl[$cat] as $j => $tpl) {
        [$rayon, $pname, $brand, $eurCents, $attrs] = $tpl;
        $price = price_for($cur, $eurCents);
        $promo = ($j === 0) ? (int) round($price * 0.8) : null; // 1 produit en promo par boutique
        Product::create((int) $b['id'], $owner, [
            'name' => $pname, 'description' => $pname . ' — qualité garantie, expédié rapidement depuis ' . $city . '.',
            'price_cents' => $price, 'stock' => 5 + (($i + $j) % 25), 'status' => 'active',
            'brand' => $brand, 'product_type' => $attrs['product_type'] ?? null, 'collection' => $rayon,
            'promo_price_cents' => $promo, 'promo_until' => $promo !== null ? date('Y-m-d', time() + 14 * 86400) : null,
            'sale_unit' => 'piece',
            'attributes' => json_encode($attrs, JSON_UNESCAPED_UNICODE),
        ], []);
        $prodCount++;
    }
    fwrite(STDOUT, sprintf("  [%2d/50] %-34s %-3s %-12s (vendeur %d)\n", $i + 1, $name, $cc, $cur, $owner));
}

// Compte des boutiques par vendeur (preuve multi-boutique).
$multi = [];
foreach ($ownerOf as $o) { $multi[$o] = ($multi[$o] ?? 0) + 1; }
arsort($multi);
fwrite(STDOUT, "\n✅ Terminé : {$created} boutiques publiées, {$prodCount} produits.\n");
fwrite(STDOUT, "Vendeurs multi-boutiques (top) :\n");
$shown = 0;
foreach ($multi as $uid => $n) {
    if ($n < 2) { continue; }
    $u = User::findById((int) $uid);
    fwrite(STDOUT, sprintf("  • %-22s : %d boutiques\n", $u['full_name'] ?? ('#' . $uid), $n));
    if (++$shown >= 5) { break; }
}
fwrite(STDOUT, "Connexion vendeur de démo : seed1@afriklink.demo / demo1234\n");
