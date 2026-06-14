-- Phase 5 — Affiliation opt-in par boutique.
-- Chaque vendeur active (ou non) l'affiliation de SA boutique et fixe le taux de
-- commission reversé à l'apporteur (1 à 30 %, défaut 5). Tant que la boutique
-- n'a pas activé son programme, aucune commission n'est due.
-- Compatible MySQL 8.4 / TiDB Cloud. Séparation des ALTER pour compatibilité.
-- (L'application ajoute aussi ces colonnes automatiquement via Boutique::migrate().)

SET NAMES utf8mb4;

ALTER TABLE boutiques ADD COLUMN affiliation_enabled  TINYINT(1)       NOT NULL DEFAULT 0;
ALTER TABLE boutiques ADD COLUMN affiliation_rate_pct TINYINT UNSIGNED NOT NULL DEFAULT 5;
