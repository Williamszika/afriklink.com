-- Géolocalisation PAR ANNONCE (produit de boutique). Par défaut, un produit est
-- localisé là où se trouve la boutique ; ces colonnes permettent de préciser une
-- ville / un pays DIFFÉRENTS pour une annonce donnée (ex. un revendeur dont les
-- articles sont stockés à Abidjan ET à Dakar, ou « déjà en Europe, prêt à
-- expédier »). Affiché sur la vitrine publique (fiche + cartes), avec repli sur
-- la localisation de la boutique quand ces colonnes sont vides.
-- Types alignés sur la table boutiques (city VARCHAR(128), country_code CHAR(2),
-- coordonnées DECIMAL(9,6)). Compatible MySQL 8.4 / TiDB. (L'appli ajoute aussi
-- ces colonnes automatiquement quand elle a les droits ALTER ; sinon, lancer ce
-- script à la main.)

SET NAMES utf8mb4;

ALTER TABLE products
    ADD COLUMN city         VARCHAR(128)  NULL,
    ADD COLUMN country_code CHAR(2)       NULL,
    ADD COLUMN geo_lat      DECIMAL(9,6)  NULL,
    ADD COLUMN geo_lng      DECIMAL(9,6)  NULL;
