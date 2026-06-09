"""SecurityAuditAgent (MVP, functional).

Audits the user's OWN code (authorized, defensive only) at AUDIT_REPO_PATH, detects
vulnerabilities, and writes a security ticket per finding into `propositions`.
Daily + on-commit (see scripts/git-hooks/post-commit).

Defensive posture: never produces offensive code, never reads secret files (.env,
keys), and instructs the model to reference — not echo — any secret it spots.
"""
from __future__ import annotations

from pathlib import Path

from ..base import BaseAgent
from ..budget import RunBudget
from ..models import SecurityAuditOutput

SYSTEM = (
    "Tu es un auditeur de sécurité applicative défensif. Le code fourni appartient à "
    "l'utilisateur et l'audit est autorisé. Identifie les vulnérabilités : injection SQL, "
    "XSS, contrôle d'accès/IDOR, secrets en clair, CSRF manquant, uploads non sûrs, "
    "mauvaise configuration, dépendances vulnérables. Aligne-toi sur l'OWASP Top 10. "
    "Pour chaque finding : titre, sévérité, catégorie, fichier, ligne si possible, "
    "description, recommandation concrète, niveau de confiance. "
    "RÈGLES : ne produis JAMAIS de code offensif ou d'exploit ; ne recopie JAMAIS la "
    "valeur d'un secret (référence seulement son emplacement) ; ne signale que ce qui "
    "est réellement visible dans le code. Réponds uniquement avec le JSON du schéma."
)

INCLUDE_EXT = {".php", ".js", ".sql", ".htaccess", ".sh", ".py", ".html"}
INCLUDE_NAMES = {".htaccess"}
EXCLUDE_DIRS = {
    ".git", "vendor", "node_modules", "storage", "cache", ".claude",
    "ai-ops", "dist", "build", "__pycache__",
}
# Never read secret-bearing files.
EXCLUDE_NAMES = {".env"}
EXCLUDE_SUFFIXES = {".key", ".pem", ".p12", ".lock"}

MAX_FILE_BYTES = 60_000
MAX_FILES = 24
MAX_LINES_PER_FILE = 500
BATCH_CHAR_BUDGET = 45_000  # ~ keep each audit call bounded


def _priority_for(severity: str) -> str:
    return {"critical": "P0", "high": "P1", "medium": "P2"}.get(severity, "P3")


class SecurityAuditAgent(BaseAgent):
    name = "security_audit"
    frequency = "daily"

    def execute(self, run_id: int, budget: RunBudget) -> str:
        repo = Path(self.settings.audit_repo_path)
        files = self._collect_files(repo)
        if not files:
            return f"aucun fichier à auditer sous {repo}"

        batches = self._batch(files)
        total_findings = 0
        for batch in batches:
            blob = self._render(repo, batch)
            output: SecurityAuditOutput = self.llm.structure(  # type: ignore[assignment]
                agent=self.name, run_id=run_id, budget=budget, system=SYSTEM,
                user="Analyse ce code et renvoie les findings au format JSON.\n\n" + blob,
                schema_model=SecurityAuditOutput, model=self.model,
                max_tokens=max(self.settings.structure_max_tokens, 8000),
            )
            self.db.insert_message(
                agent=self.name, kind="audit_summary", topic="security",
                content=output.summary, run_id=run_id,
            )
            for finding in output.findings:
                created = self.db.insert_proposition(
                    agent=self.name, ptype="security_finding",
                    title=f"[{finding.severity}] {finding.title[:120]}",
                    body=f"{finding.description}\n\nRecommandation : {finding.recommendation}",
                    severity=finding.severity, category=finding.category,
                    priority=_priority_for(finding.severity),
                    file=finding.file, line=finding.line,
                    payload={"confidence": finding.confidence},
                    dedup_key=f"{finding.file}:{finding.title[:80]}",
                    run_id=run_id,
                )
                if created is not None:
                    total_findings += 1

        return f"{len(files)} fichiers, {len(batches)} lot(s), {total_findings} findings"

    # -- file selection --------------------------------------------------------

    def _collect_files(self, repo: Path) -> list[Path]:
        candidates: list[Path] = []
        for path in repo.rglob("*"):
            if not path.is_file():
                continue
            if any(part in EXCLUDE_DIRS for part in path.relative_to(repo).parts):
                continue
            if path.name in EXCLUDE_NAMES or path.suffix in EXCLUDE_SUFFIXES:
                continue
            if path.suffix not in INCLUDE_EXT and path.name not in INCLUDE_NAMES:
                continue
            try:
                if path.stat().st_size > MAX_FILE_BYTES:
                    continue
            except OSError:
                continue
            candidates.append(path)

        candidates.sort(key=lambda p: (self._priority_rank(p), str(p)))
        return candidates[:MAX_FILES]

    @staticmethod
    def _priority_rank(path: Path) -> int:
        """Audit the security-sensitive code first."""
        text = str(path).lower()
        for rank, needle in enumerate((
            "auth", "login", "password", "session", "csrf", "db", "database",
            "sql", "upload", "payment", "webhook", "controller", "middleware",
            "config", ".htaccess", "index.php",
        )):
            if needle in text:
                return rank
        return 99

    def _batch(self, files: list[Path]) -> list[list[Path]]:
        batches: list[list[Path]] = []
        current: list[Path] = []
        size = 0
        for path in files:
            try:
                file_size = path.stat().st_size
            except OSError:
                file_size = 0
            if current and size + file_size > BATCH_CHAR_BUDGET:
                batches.append(current)
                current, size = [], 0
            current.append(path)
            size += file_size
        if current:
            batches.append(current)
        return batches

    def _render(self, repo: Path, batch: list[Path]) -> str:
        chunks: list[str] = []
        for path in batch:
            try:
                lines = path.read_text(encoding="utf-8", errors="replace").splitlines()
            except OSError:
                continue
            rel = path.relative_to(repo)
            numbered = "\n".join(
                f"{i:>4}\t{line}" for i, line in enumerate(lines[:MAX_LINES_PER_FILE], start=1)
            )
            truncated = "" if len(lines) <= MAX_LINES_PER_FILE else "\n… (tronqué)"
            chunks.append(f"===== FICHIER: {rel} =====\n{numbered}{truncated}")
        return "\n\n".join(chunks)
