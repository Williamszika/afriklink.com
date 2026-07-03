#!/usr/bin/env bash
# Active les hooks Git de sécurité du dépôt (à lancer UNE fois après clonage).
#   bash scripts/install-hooks.sh
set -euo pipefail

ROOT="$(git rev-parse --show-toplevel)"
git -C "$ROOT" config core.hooksPath scripts/git-hooks
chmod +x "$ROOT"/scripts/git-hooks/* 2>/dev/null || true

echo "✅ Hooks Git de sécurité activés (core.hooksPath = scripts/git-hooks)."
echo "   → Le scanner de sécurité s'exécutera automatiquement avant chaque 'git push'."
