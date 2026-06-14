-- Phase 5 — Versement des commissions d'affiliation au portefeuille.
-- paid_out_at marque qu'une conversion a déjà été créditée au portefeuille de
-- l'apporteur (verrou d'idempotence : un seul crédit par commande payée).
-- Compatible MySQL 8.4 / TiDB.
-- (L'application ajoute aussi cette colonne automatiquement via Affiliate::ensureTables().)

SET NAMES utf8mb4;

ALTER TABLE affiliate_conversions ADD COLUMN paid_out_at DATETIME NULL;
