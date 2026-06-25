-- Annonces (particuliers) : option « détourage du fond » par annonce.
-- clean_bg = 1 → l'annonce demande le nettoyage auto des photos (détourage IA +
-- fond neutre), réservé aux OBJETS posés (vêtement, téléphone…). Reste 0 par
-- défaut, et sans effet tant que MEDIA_AUTOCLEAN n'est pas activé (add-on
-- Cloudinary). Inadapté aux voitures / scènes / logements → choix par annonce.
-- Compatible MySQL 8.4 / TiDB. (L'appli ajoute aussi la colonne automatiquement
-- quand elle a les droits ALTER ; sinon, lancer ce script à la main.)

SET NAMES utf8mb4;

ALTER TABLE listings
    ADD COLUMN clean_bg TINYINT(1) NOT NULL DEFAULT 0 AFTER whatsapp_optin;
