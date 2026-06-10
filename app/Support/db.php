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
 *  - DB_SSL_CA : chemin du bundle CA ; si absent, on auto-détecte le bundle système
 *    (Debian, RHEL/Amazon Linux — cas de Vercel —, Alpine).
 *  - Sans CA trouvable : TLS quand même, mais sans vérification du certificat
 *    (mieux que pas de TLS ; DB_SSL_VERIFY=false force aussi ce mode en dépannage).
 */
function pdo_ssl_options(): array
{
    if (!filter_var($_ENV['DB_SSL'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        return [];
    }

    $verify = filter_var($_ENV['DB_SSL_VERIFY'] ?? true, FILTER_VALIDATE_BOOLEAN);

    $ca = $_ENV['DB_SSL_CA'] ?? '';
    if ($ca === '' || !is_file($ca)) {
        $ca = '';
        foreach ([
            '/etc/ssl/certs/ca-certificates.crt', // Debian/Ubuntu
            '/etc/pki/tls/certs/ca-bundle.crt',   // RHEL/Amazon Linux (Vercel)
            '/etc/ssl/ca-bundle.pem',             // openSUSE
            '/etc/ssl/cert.pem',                  // Alpine/macOS
        ] as $candidate) {
            if (is_file($candidate)) {
                $ca = $candidate;
                break;
            }
        }
    }

    $options = [];
    if ($ca !== '') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
    } else {
        $verify = false; // pas de CA disponible : TLS actif mais non vérifiable
    }
    if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = $verify;
    }
    return $options;
}
