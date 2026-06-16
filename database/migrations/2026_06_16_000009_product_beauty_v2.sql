-- Phase 6b — Beauté v2 : formulaire « maquillage » ADAPTATIF au type de produit.
-- Le type (Fond de teint, Mascara, Rouge à lèvres…) pilote les champs affichés ;
-- ses caractéristiques propres (couvrance, fini, format, effet…) sont stockées
-- dans products.attributes (JSON souple) plutôt qu'une colonne par champ.
-- + products.line : gamme / ligne (ex. « Infaillible »).
-- Les déclinaisons (teinte/couleur) réutilisent la couche variantes : chaque
-- variante porte attributes.hex (pastille) et attributes.nuance (carnation).
-- Compatible MySQL 8.4 / TiDB. (L'appli ajoute aussi ces colonnes automatiquement
-- quand elle a les droits ALTER ; sinon, lancer ce script à la main.)

SET NAMES utf8mb4;

ALTER TABLE products
    ADD COLUMN line       VARCHAR(80) NULL,
    ADD COLUMN attributes TEXT NULL;
