<?php
declare(strict_types=1);

/**
 * Connexion PDO sécurisée (singleton).
 * Lit la config depuis $_ENV (charger .env au bootstrap).
 * Usage : db()->prepare('SELECT ...');
 *
 * Compatible MySQL (Hostinger) et TiDB Cloud Serverless (TLS requis — voir DB_SSL).
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
    $port = $_ENV['DB_PORT'] ?? '3306';
    $name = $_ENV['DB_NAME'] ?? '';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASS'] ?? '';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // erreurs => exceptions
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,                 // vraies requêtes préparées
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ] + pdo_ssl_options();

    $pdo = new PDO($dsn, $user, $pass, $options);

    return $pdo;
}

/**
 * Options TLS pour un MySQL managé (TiDB Cloud Serverless exige TLS).
 * Activées si DB_SSL est vrai.
 *
 * Vérification du certificat (DB_SSL_VERIFY) :
 *  - hors serverless (Hostinger…) : vérifiée par défaut, via DB_SSL_CA ou le bundle
 *    CA système auto-détecté ;
 *  - sur serverless (Vercel) : NON vérifiée par défaut — le bundle CA du runtime ne
 *    contient pas forcément la chaîne du certificat de la base managée, ce qui ferait
 *    échouer la connexion. On chiffre quand même (TLS), on ne vérifie juste pas le
 *    certificat. Forcer la vérification : DB_SSL_VERIFY=true + DB_SSL_CA=<bundle>.
 */
function pdo_ssl_options(): array
{
    if (!filter_var($_ENV['DB_SSL'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        return [];
    }

    $onServerless = !empty($_ENV['VERCEL']);
    $verify = filter_var(
        $_ENV['DB_SSL_VERIFY'] ?? ($onServerless ? 'false' : 'true'),
        FILTER_VALIDATE_BOOLEAN
    );

    $options = [];
    if ($verify) {
        $ca = $_ENV['DB_SSL_CA'] ?? '';
        if ($ca === '' || !is_file($ca)) {
            $ca = '';
            foreach ([
                '/etc/ssl/certs/ca-certificates.crt', // Debian/Ubuntu
                '/etc/pki/tls/certs/ca-bundle.crt',   // RHEL/Amazon Linux
                '/etc/ssl/ca-bundle.pem',             // openSUSE
                '/etc/ssl/cert.pem',                  // Alpine/macOS
            ] as $candidate) {
                if (is_file($candidate)) {
                    $ca = $candidate;
                    break;
                }
            }
        }
        if ($ca !== '') {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
        } else {
            $verify = false; // rien pour vérifier → on chiffre sans vérifier
        }
    }
    // En mode non-vérifié on ne passe PAS de CA (évite que mysqlnd réactive la
    // vérification) ; cette option seule suffit à activer le chiffrement TLS.
    if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $verify;
    }
    return $options;
}
