-- Anti-doublon des fils de messagerie DIRECTS : pair_key = « min-max » des deux
-- membres (NULL pour les fils boutique/produit ; MySQL autorise plusieurs NULL).
-- L'index UNIQUE rend impossible la création de deux fils directs pour la même
-- paire, même si le verrou applicatif (GET_LOCK) n'a pas pu être obtenu.
-- Compatible MySQL 8.4 / TiDB. (L'appli ajoute aussi la colonne + l'index
-- automatiquement quand elle a les droits ALTER ; sinon, lancer ce script.)

SET NAMES utf8mb4;

ALTER TABLE conversations
    ADD COLUMN pair_key VARCHAR(40) NULL,
    ADD UNIQUE KEY uniq_dm_pair (pair_key);
