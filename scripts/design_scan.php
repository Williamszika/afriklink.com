<?php
declare(strict_types=1);

/**
 * AfrikaLink — vérificateur DESIGN (compagnon du skill afrikalink-design).
 *
 * Mode RAPPORT (informatif, ne bloque pas) : c'est une aide, pas un mur. Signale :
 *   • A11Y     — <img> sans attribut alt ;
 *   • CSP      — gestionnaires d'événements inline (onclick=…) ou <script> inline
 *                dans une vue → cassés par la CSP stricte (à corriger) ;
 *   • RTL      — propriété CSS physique (left/right/padding-left…) dans une règle
 *                .authx → risque d'affichage en arabe (préférer les propriétés logiques).
 *
 * Usage : php scripts/design_scan.php        (toujours exit 0 — purement indicatif)
 * Ignorer une ligne : y ajouter le commentaire « design-scan-ignore ».
 */

$ROOT = rtrim((string) (getopt('', ['root::'])['root'] ?? getcwd()), '/');
$VIEWS = $ROOT . '/app/Views';
$CSS   = $ROOT . '/public/assets/css/app.css';

$a11y = [];   // [$file, $line, $snippet]
$csp  = [];
$rtl  = [];

/* ---- Vues : A11Y (img sans alt) + CSP (handlers / scripts inline) ---- */
if (is_dir($VIEWS)) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($VIEWS, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') {
            continue;
        }
        $rel = ltrim(str_replace($ROOT, '', $f->getPathname()), '/');
        $content = (string) file_get_contents($f->getPathname());
        $lines = explode("\n", $content);

        // <img …> sans alt (les balises tiennent sur une ligne dans ce projet).
        foreach ($lines as $i => $line) {
            if (stripos($line, 'design-scan-ignore') !== false) {
                continue;
            }
            if (preg_match_all('/<img\b[^>]*>/i', $line, $m)) {
                foreach ($m[0] as $tag) {
                    if (!preg_match('/\balt\s*=/i', $tag)) {
                        $a11y[] = [$rel, $i + 1, trim($tag)];
                    }
                }
            }
            // Gestionnaire d'événement inline (onclick=, onsubmit=…) → interdit par
            // la CSP. Liste explicite d'attributs pour éviter les faux positifs.
            if (preg_match('/\son(click|submit|change|input|load|error|mouse[a-z]+|key[a-z]+|focus|blur|scroll|toggle|drag[a-z]*|touch[a-z]+|reset|select|paste)\s*=\s*["\']/i', $line)) {
                $csp[] = [$rel, $i + 1, 'gestionnaire inline', trim($line)];
            }
            // <script> INLINE (sans src) → bloqué par la CSP. Les <script src="…">
            // (app.js self-hosté, Turnstile) sont régis par l'allowlist CSP, pas ici ;
            // le JSON-LD est un bloc de données autorisé.
            if (preg_match('/<script\b(?![^>]*\bsrc\s*=)(?![^>]*application\/ld\+json)/i', $line)) {
                $csp[] = [$rel, $i + 1, 'script inline', trim($line)];
            }
        }
    }
}

/* ---- CSS : propriété physique dans une règle .authx (risque RTL) ---- */
if (is_file($CSS)) {
    $physical = '/(padding-left|padding-right|margin-left|margin-right|(?<![-\w])left\s*:|(?<![-\w])right\s*:|text-align\s*:\s*(left|right))/i';
    foreach (explode("\n", (string) file_get_contents($CSS)) as $i => $line) {
        if (stripos($line, 'design-scan-ignore') !== false) {
            continue;
        }
        if (str_contains($line, '.authx') && preg_match($physical, $line) && !str_contains($line, 'dir=rtl')) {
            $rtl[] = ['public/assets/css/app.css', $i + 1, trim($line)];
        }
    }
}

/* ---- Rapport ---- */
fwrite(STDOUT, "🎨 AfrikaLink — vérificateur design (rapport, non bloquant)\n");
fwrite(STDOUT, str_repeat('─', 60) . "\n");

if ($csp !== []) {
    fwrite(STDOUT, "\n⛔ CSP — à corriger (ne fonctionnera pas avec la CSP stricte) :\n");
    foreach ($csp as [$file, $line, $kind, $snip]) {
        fwrite(STDOUT, sprintf("   %s:%d — %s\n       %s\n", $file, $line, $kind, mb_substr($snip, 0, 120)));
    }
} else {
    fwrite(STDOUT, "\n✅ CSP : aucun script/handler inline dans les vues.\n");
}

if ($rtl !== []) {
    fwrite(STDOUT, "\n🔄 RTL — propriétés physiques dans .authx (préférer les logiques) :\n");
    foreach ($rtl as [$file, $line, $snip]) {
        fwrite(STDOUT, sprintf("   %s:%d\n       %s\n", $file, $line, mb_substr($snip, 0, 120)));
    }
} else {
    fwrite(STDOUT, "\n✅ RTL : le système .authx utilise des propriétés logiques.\n");
}

if ($a11y !== []) {
    // Backlog volumineux (héritage) : on résume par fichier plutôt qu'un mur de lignes.
    $byFile = [];
    foreach ($a11y as [$file]) {
        $byFile[$file] = ($byFile[$file] ?? 0) + 1;
    }
    arsort($byFile);
    fwrite(STDOUT, sprintf("\n♿ A11Y — %d <img> sans « alt » (accessibilité, à compléter progressivement) :\n", count($a11y)));
    $n = 0;
    foreach ($byFile as $file => $cnt) {
        fwrite(STDOUT, sprintf("   %3d × %s\n", $cnt, $file));
        if (++$n >= 12) {
            fwrite(STDOUT, sprintf("   … (+%d autres fichiers)\n", count($byFile) - 12));
            break;
        }
    }
    fwrite(STDOUT, "   ⇒ Ajouter alt=\"\" (image décorative) ou un alt descriptif.\n");
} else {
    fwrite(STDOUT, "\n✅ A11Y : toutes les <img> ont un alt.\n");
}

fwrite(STDOUT, "\n" . str_repeat('─', 60) . "\n");
fwrite(STDOUT, "Rapport indicatif — voir .claude/skills/afrikalink-design/SKILL.md.\n");
exit(0);
