-- Phase 5 — Affiliation PAR PRODUIT.
-- Le vendeur choisit, produit par produit, s'il est affilié et à quel taux
-- (en points de base : 350 = 3,50 %). La commission est prélevée sur la commission
-- AfrikaLink (jamais en plus) ; le taux est plafonné côté application.
-- + colonne target sur les conversions : pour le suivi par lien partagé.
-- Compatible MySQL 8.4 / TiDB. (L'appli ajoute aussi ces colonnes automatiquement.)

SET NAMES utf8mb4;

ALTER TABLE products ADD COLUMN affiliate_enabled  TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE products ADD COLUMN affiliate_rate_bps SMALLINT UNSIGNED NOT NULL DEFAULT 0;

ALTER TABLE affiliate_conversions ADD COLUMN target VARCHAR(300) NULL;
