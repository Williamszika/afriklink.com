-- Taux de change rafraîchissables (affichage « ≈ équivalent »). La routine
-- /cron/taux y écrit les devises FLOTTANTES (USD, GBP, NGN…) depuis une API ;
-- ExchangeRates lit cette table par-dessus les valeurs par défaut de
-- config/currencies.php. Les parités FIXES (EUR=1, XOF/XAF=655,957) n'y sont
-- jamais écrites. Table vide = on utilise simplement les taux de config.
-- Compatible MySQL 8.4 / TiDB. (L'appli crée aussi la table au 1er /cron/taux.)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS currency_rates (
    code       CHAR(3) NOT NULL PRIMARY KEY,
    per_eur    DECIMAL(18,6) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
