<?php
declare(strict_types=1);

/**
 * Démo/staging : crée le schéma des VERTICALES (boutiques, produits, variantes,
 * avis, commandes…) en appelant les ensureTable() des modèles (CREATE TABLE IF
 * NOT EXISTS, idempotent). Le cœur (users, sessions…) est créé en amont par
 * database/install_tables_only.sql + migrations. Réservé au local/staging.
 *
 *   php database/demo_schema.php
 */

require __DIR__ . '/../app/bootstrap.php';

if (($_ENV['APP_ENV'] ?? 'production') === 'production') {
    fwrite(STDERR, "Refus : APP_ENV=production (réservé au local/staging).\n");
    exit(1);
}

$models = [
    'Affiliate', 'Announcement', 'Avatar', 'Boutique', 'BoutiqueDeleteCode', 'CashMovement',
    'Conversation', 'DeliveryArea', 'Discount', 'Kyc', 'Listing', 'MenuItem', 'NewsletterSubscriber',
    'Notification', 'Order', 'Payment', 'PaymentEvent', 'ProProfile', 'Product', 'ProductVariant',
    'Register', 'RegisterSession', 'Restaurant', 'RestaurantOrder', 'Review', 'ShippingZone',
    'ShopView', 'StockAlert', 'UserAddress', 'Wallet',
];
$ok = 0; $errors = [];
foreach ($models as $m) {
    $cls = "\\App\\Models\\$m";
    foreach (['ensureTable', 'ensureItemsTable', 'ensureTendersTable', 'ensureTables'] as $fn) {
        if (method_exists($cls, $fn)) {
            try { $cls::$fn(); $ok++; } catch (\Throwable $e) { $errors[] = "$m::$fn — " . $e->getMessage(); }
        }
    }
}
fwrite(STDOUT, "Schéma : {$ok} ensure*() exécutés, " . count($errors) . " erreur(s).\n");
foreach ($errors as $e) { fwrite(STDOUT, "  ! $e\n"); }
exit($errors === [] ? 0 : 1);
