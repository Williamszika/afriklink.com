<?php
declare(strict_types=1);

/**
 * Minimal forward-only SQL migration runner.
 *
 *   php database/migrate.php          # apply all pending migrations
 *   php database/migrate.php --status # list applied / pending without changing anything
 *
 * Migrations are *.sql files in database/migrations, applied in filename order.
 * Uses the DDL account (DB_DDL_USER/DB_DDL_PASS, falling back to DB_USER/DB_PASS) so
 * the least-privilege application account never needs DDL rights in production.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit("CLI only.\n");
}

require dirname(__DIR__) . '/app/bootstrap.php';

$host = $_ENV['DB_HOST'] ?? '127.0.0.1';
$port = $_ENV['DB_PORT'] ?? '3306';
$name = $_ENV['DB_NAME'] ?? '';
$user = ($_ENV['DB_DDL_USER'] ?? '') ?: ($_ENV['DB_USER'] ?? '');
$pass = ($_ENV['DB_DDL_PASS'] ?? '') ?: ($_ENV['DB_PASS'] ?? '');

if ($name === '') {
    fwrite(STDERR, "DB_NAME is not set. Copy .env.example to .env and configure the database.\n");
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4",
        $user,
        $pass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (Throwable $e) {
    fwrite(STDERR, 'Cannot connect to database: ' . $e->getMessage() . "\n");
    exit(1);
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS schema_migrations (
        id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        filename   VARCHAR(191) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_schema_migrations (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$applied = $pdo->query('SELECT filename FROM schema_migrations')
    ->fetchAll(PDO::FETCH_COLUMN) ?: [];

$files = glob(DATABASE_PATH . '/migrations/*.sql') ?: [];
sort($files);

$statusOnly = in_array('--status', $argv, true);
$pending = 0;

foreach ($files as $file) {
    $base = basename($file);
    $isApplied = in_array($base, $applied, true);

    if ($statusOnly) {
        printf("[%s] %s\n", $isApplied ? 'x' : ' ', $base);
        continue;
    }
    if ($isApplied) {
        continue;
    }

    $pending++;
    fwrite(STDOUT, "Applying {$base} ... ");
    try {
        $pdo->exec((string) file_get_contents($file));
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (filename) VALUES (:f)');
        $stmt->execute(['f' => $base]);
        fwrite(STDOUT, "done\n");
    } catch (Throwable $e) {
        fwrite(STDOUT, "FAILED\n");
        fwrite(STDERR, '  ' . $e->getMessage() . "\n");
        exit(1);
    }
}

if (!$statusOnly) {
    fwrite(STDOUT, $pending === 0 ? "Nothing to migrate. Database is up to date.\n" : "Migrated {$pending} file(s).\n");
}
