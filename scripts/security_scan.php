<?php
declare(strict_types=1);

/**
 * AfrikaLink — scanner de sécurité déterministe (« garde à l'entrée »).
 *
 * Analyse le code source à la recherche de :
 *   • fonctions dangereuses (eval, exec, system, shell_exec, unserialize…) ;
 *   • code obscurci (base64/gz + eval) ;
 *   • SECRETS EN DUR (clés Stripe/Brevo/AWS, clés privées…) — doivent vivre en .env ;
 *   • INTÉGRATIONS INCONNUES : tout hôte externe non déclaré dans
 *     scripts/allowed_hosts.txt (une nouvelle URL sortante = décision consciente) ;
 *   • restes de débogage (avertissements non bloquants).
 *
 * Sort en erreur (code 2) dès qu'un problème BLOQUANT (CRITICAL/HIGH) est trouvé →
 * le pipeline CI refuse la fusion. Pur PHP, sans dépendance, rapide.
 *
 * Options : --warn-only (n'échoue jamais, informatif)  --root=CHEMIN
 * Ignorer une ligne précise (faux positif justifié) : y ajouter le commentaire
 *   « security-scan-ignore ».
 *
 * Usage : php scripts/security_scan.php
 */

$opts = getopt('', ['warn-only', 'root::']);
$WARN_ONLY = isset($opts['warn-only']);
$ROOT = rtrim((string) ($opts['root'] ?? getcwd()), '/');

$INCLUDE_DIRS = ['app', 'public', 'config', 'database', 'api', 'lang', 'scripts'];
$SCAN_EXT     = ['php', 'js', 'sh', 'htaccess'];
$EXCLUDE_DIRS = ['vendor', 'node_modules', 'storage', '.git', '.claude', 'ai-ops', 'docs', 'dist', 'build', '__pycache__'];

/* Hôtes externes autorisés (intégrations connues). */
$allowFile = $ROOT . '/scripts/allowed_hosts.txt';
$ALLOWED_HOSTS = [];
if (is_file($allowFile)) {
    foreach (file($allowFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line !== '' && $line[0] !== '#') {
            $ALLOWED_HOSTS[strtolower($line)] = true;
        }
    }
}

/* ------------------------------------------------------------------ */
/* Règles de détection                                                 */
/* ------------------------------------------------------------------ */

// Fonctions d'exécution dangereuses. `(?<![>\w])` évite les méthodes ($pdo->exec,
// ->system…) et les identifiants (safeExec) ; on ne vise que l'appel global.
$DANGER_FUNCS = 'eval|assert|create_function|shell_exec|exec|system|passthru|proc_open|popen|pcntl_exec|proc_nice|dl';
$rulesRegex = [
    // rule => [severity, regex, message]
    'exec-sink'      => ['CRITICAL', '/(?<![>\w])(' . $DANGER_FUNCS . ')\s*\(/',
        'Fonction d\'exécution dangereuse (injection de commande / RCE possible).'],
    // NB : l'opérateur backtick shell (`...`) est couvert par semgrep (analyse
    // syntaxique) — un simple regex confondrait avec les identifiants SQL `col`.
    'obfuscation'    => ['CRITICAL', '/(eval|assert)\s*\(\s*(base64_decode|gzinflate|gzuncompress|str_rot13|hex2bin)/i',
        'Code obscurci (décodage puis exécution) — signature typique de malware.'],
    'unserialize'    => ['HIGH', '/(?<![>\w])unserialize\s*\(/',
        'unserialize() — risque d\'injection d\'objet PHP. Préférer json_decode().'],
    'preg-eval'      => ['CRITICAL', '/preg_replace\s*\(\s*[\'"][^\'"]*e[^\'"]*[\'"]/',
        'preg_replace avec modificateur /e (exécute du code).'],
    // Secrets en dur (formats de clés réels → quasi zéro faux positif).
    'secret-stripe'  => ['CRITICAL', '/\b(sk|rk)_live_[0-9a-zA-Z]{16,}/',
        'Clé secrète Stripe EN DUR. Elle doit vivre dans .env (jamais commitée).'],
    'secret-brevo'   => ['CRITICAL', '/\bxkeysib-[0-9a-f]{32,}/',
        'Clé API Brevo EN DUR. À déplacer dans .env.'],
    'secret-aws'     => ['CRITICAL', '/\bAKIA[0-9A-Z]{16}\b/',
        'Clé d\'accès AWS EN DUR. À déplacer dans .env.'],
    'secret-google'  => ['CRITICAL', '/\bAIza[0-9A-Za-z_\-]{35}\b/',
        'Clé API Google EN DUR. À déplacer dans .env.'],
    'secret-privkey' => ['CRITICAL', '/-----BEGIN (RSA |EC |OPENSSH |DSA |PGP )?PRIVATE KEY-----/',
        'Clé privée EN DUR dans le dépôt. À révoquer et déplacer hors du code.'],
    'secret-jwt'     => ['HIGH', '/\beyJ[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}\.[A-Za-z0-9_\-]{10,}/',
        'Jeton JWT en dur possible.'],
    // Restes de débogage (avertissements).
    'debug-leftover' => ['WARN', '/(?<![>\w])(var_dump|var_export|phpinfo|debug_zval_refcount)\s*\(/',
        'Reste de débogage à retirer avant production.'],
];

/* ------------------------------------------------------------------ */
/* Collecte des fichiers                                               */
/* ------------------------------------------------------------------ */
function collectFiles(string $root, array $dirs, array $exclude, array $exts): array
{
    $out = [];
    foreach ($dirs as $d) {
        $base = $root . '/' . $d;
        if (!is_dir($base)) {
            continue;
        }
        $it = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
                static function ($cur) use ($exclude): bool {
                    return !($cur->isDir() && in_array($cur->getFilename(), $exclude, true));
                }
            )
        );
        foreach ($it as $f) {
            if (!$f->isFile()) {
                continue;
            }
            $name = $f->getFilename();
            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (in_array($ext, $exts, true) || $name === '.htaccess') {
                $out[] = $f->getPathname();
            }
        }
    }
    // Fichiers racine sensibles (.htaccess déjà couvert via public/).
    return $out;
}

/* ------------------------------------------------------------------ */
/* Analyse                                                             */
/* ------------------------------------------------------------------ */
$findings = [];
$hostRe   = '#\bhttps?://([a-z0-9.\-]+)#i';

$selfPath = realpath(__FILE__);
$files = collectFiles($ROOT, $INCLUDE_DIRS, $EXCLUDE_DIRS, $SCAN_EXT);
foreach ($files as $path) {
    // Ne pas se scanner soi-même : ce fichier contient, par nature, les motifs
    // recherchés (dans les définitions de règles).
    if (realpath($path) === $selfPath) {
        continue;
    }
    $rel = ltrim(str_replace($ROOT, '', $path), '/');
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        continue;
    }
    foreach ($lines as $i => $line) {
        $lno = $i + 1;
        if (stripos($line, 'security-scan-ignore') !== false) {
            continue; // faux positif justifié + tracé
        }
        foreach ($rulesRegex as $rule => [$sev, $re, $msg]) {
            if (preg_match($re, $line)) {
                $findings[] = [$sev, $rule, $rel, $lno, trim($line), $msg];
            }
        }
        // Intégrations inconnues : hôtes externes non déclarés.
        if (preg_match_all($hostRe, $line, $m)) {
            foreach ($m[1] as $host) {
                $host = strtolower(rtrim($host, '.'));
                if ($host === '' || isset($ALLOWED_HOSTS[$host])) {
                    continue;
                }
                // Ignore les hôtes locaux / d'exemple.
                if (preg_match('/^(localhost|127\.0\.0\.1|0\.0\.0\.0|example\.(com|org|net)|exemple\.com|votre-domaine)$/', $host)) {
                    continue;
                }
                $findings[] = ['HIGH', 'unknown-host', $rel, $lno, $host,
                    'INTÉGRATION INCONNUE : hôte externe non déclaré. Si légitime, l\'ajouter à scripts/allowed_hosts.txt ; sinon, code suspect à retirer.'];
            }
        }
    }
}

/* ------------------------------------------------------------------ */
/* Rapport                                                             */
/* ------------------------------------------------------------------ */
$order = ['CRITICAL' => 0, 'HIGH' => 1, 'WARN' => 2];
usort($findings, static fn ($a, $b) => ($order[$a[0]] <=> $order[$b[0]]) ?: strcmp($a[2], $b[2]));

$counts = ['CRITICAL' => 0, 'HIGH' => 0, 'WARN' => 0];
foreach ($findings as $f) {
    $counts[$f[0]]++;
}
$blocking = $counts['CRITICAL'] + $counts['HIGH'];

fwrite(STDOUT, "🛡️  AfrikaLink — scan de sécurité\n");
fwrite(STDOUT, str_repeat('─', 60) . "\n");
fwrite(STDOUT, sprintf("Fichiers analysés : %d\n", count($files)));

if ($findings === []) {
    fwrite(STDOUT, "✅ Aucun problème détecté.\n");
    exit(0);
}

$icon = ['CRITICAL' => '⛔', 'HIGH' => '🔴', 'WARN' => '🟡'];
foreach ($findings as [$sev, $rule, $rel, $lno, $snippet, $msg]) {
    fwrite(STDOUT, sprintf(
        "%s %-8s [%s] %s:%d\n     %s\n     ↳ %s\n",
        $icon[$sev], $sev, $rule, $rel, $lno, $msg,
        mb_strlen($snippet) > 140 ? mb_substr($snippet, 0, 140) . '…' : $snippet
    ));
}
fwrite(STDOUT, str_repeat('─', 60) . "\n");
fwrite(STDOUT, sprintf("⛔ %d critique · 🔴 %d élevé · 🟡 %d avertissement\n",
    $counts['CRITICAL'], $counts['HIGH'], $counts['WARN']));

if ($blocking > 0 && !$WARN_ONLY) {
    fwrite(STDOUT, "\n❌ Échec : problème(s) bloquant(s). Fusion refusée jusqu'à correction.\n");
    exit(2);
}
fwrite(STDOUT, $blocking > 0 ? "\n(mode avertissement : non bloquant)\n" : "\n✅ Aucun problème bloquant.\n");
exit(0);
