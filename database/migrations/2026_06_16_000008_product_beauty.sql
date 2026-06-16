-- Phase 6 — Boutique BEAUTÉ & cosmétiques : fiche produit « maquillage » enrichie.
-- Caractéristiques propres au maquillage / cosmétique : type de produit, contenance
-- + unité, finition, type de peau, couvrance, PAO (période après ouverture), date de
-- péremption, code-barres EAN, référence/SKU, atouts (CSV : Vegan, Bio…), composition
-- (INCI). Les déclinaisons « teinte » réutilisent la couche variantes existante ; la
-- pastille couleur est déduite du NOM de la teinte (config/beauty.php), donc aucune
-- colonne couleur supplémentaire.
-- Compatible MySQL 8.4 / TiDB. (L'appli ajoute aussi ces colonnes automatiquement
-- quand elle a les droits ALTER ; sur TiDB sans droits, lancer ce script à la main.)

SET NAMES utf8mb4;

ALTER TABLE products
    ADD COLUMN product_type VARCHAR(40) NULL,
    ADD COLUMN volume       DECIMAL(8,2) NULL,
    ADD COLUMN volume_unit  VARCHAR(8) NULL,
    ADD COLUMN finish       VARCHAR(20) NULL,
    ADD COLUMN skin_type    VARCHAR(20) NULL,
    ADD COLUMN coverage     VARCHAR(12) NULL,
    ADD COLUMN pao          VARCHAR(8) NULL,
    ADD COLUMN expiry_date  DATE NULL,
    ADD COLUMN ean          VARCHAR(20) NULL,
    ADD COLUMN sku          VARCHAR(40) NULL,
    ADD COLUMN atouts       VARCHAR(255) NULL,
    ADD COLUMN ingredients  TEXT NULL;
