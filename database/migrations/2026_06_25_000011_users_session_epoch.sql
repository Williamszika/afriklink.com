-- Sécurité : invalidation des autres sessions au changement de mot de passe.
-- session_epoch est incrémenté à chaque reset / changement de mot de passe ; une
-- session qui mémorise une époque différente de celle du compte est considérée
-- périmée (déconnexion). 0 par défaut → aucune session existante n'est éjectée
-- au déploiement. Compatible MySQL 8.4 / TiDB. (L'appli ajoute aussi la colonne
-- automatiquement quand elle a les droits ALTER ; sinon, lancer ce script.)

SET NAMES utf8mb4;

ALTER TABLE users
    ADD COLUMN session_epoch INT UNSIGNED NOT NULL DEFAULT 0;
