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
 * Options TLS pour un MySQL managé (TiDB Cloud Serverless exige TLS et REFUSE les
 * connexions non chiffrées). Activées si DB_SSL est vrai.
 *
 * Point clé : fournir un bundle CA (MYSQL_ATTR_SSL_CA) est ce qui *active* réellement
 * le TLS côté PDO/mysqlnd. On utilise donc toujours un CA :
 *   1. DB_SSL_CA si fourni, sinon
 *   2. le bundle embarqué config/cacert.pem (Mozilla — fiable partout, contient l'AC
 *      de TiDB), sinon
 *   3. un bundle système.
 * DB_SSL_VERIFY (défaut true) contrôle la vérification du certificat ; le mettre à
 * false chiffre sans vérifier (dépannage), mais garde le TLS actif.
 */
function pdo_ssl_options(): array
{
    if (!filter_var($_ENV['DB_SSL'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
        return [];
    }

    // PHP 8.5 moved these to Pdo\Mysql::ATTR_SSL_* (old PDO::MYSQL_ATTR_SSL_* are
    // deprecated). Resolve to whichever exists so we never touch a deprecated one.
    $sslCaKey = defined('Pdo\\Mysql::ATTR_SSL_CA')
        ? \constant('Pdo\\Mysql::ATTR_SSL_CA')
        : (defined('PDO::MYSQL_ATTR_SSL_CA') ? \constant('PDO::MYSQL_ATTR_SSL_CA') : null);
    $sslVerifyKey = defined('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')
        ? \constant('Pdo\\Mysql::ATTR_SSL_VERIFY_SERVER_CERT')
        : (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') ? \constant('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') : null);

    // A CA path is required to turn TLS on — find one.
    $ca = $_ENV['DB_SSL_CA'] ?? '';
    if ($ca === '' || !is_file($ca)) {
        $ca = '';
        foreach ([
            BASE_PATH . '/config/cacert.pem',     // bundle CA embarqué (fiable partout)
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

    $verify = filter_var($_ENV['DB_SSL_VERIFY'] ?? true, FILTER_VALIDATE_BOOLEAN);

    $options = [];
    if ($ca !== '' && $sslCaKey !== null) {
        $options[$sslCaKey] = $ca; // active le TLS + ancre de confiance
    }
    if ($sslVerifyKey !== null) {
        $options[$sslVerifyKey] = $verify;
    }
    return $options;
}
