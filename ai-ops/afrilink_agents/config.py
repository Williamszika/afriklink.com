"""Environment-driven configuration. Nothing secret is hard-coded; the API key is
read from the environment only when a call is actually made.
"""
from __future__ import annotations

import os
from dataclasses import dataclass, field
from pathlib import Path

try:  # optional; .env is convenient in dev, not required
    from dotenv import load_dotenv
except Exception:  # pragma: no cover
    load_dotenv = None

PACKAGE_DIR = Path(__file__).resolve().parent
AI_OPS_DIR = PACKAGE_DIR.parent
REPO_ROOT = AI_OPS_DIR.parent  # the afriklink.com site repo

DEFAULT_MODEL = "claude-opus-4-8"

# West-African countries AfriLink connects with Europe (canonical list).
WEST_AFRICA_COUNTRIES = [
    "Nigeria", "Ghana", "Sénégal", "Côte d'Ivoire", "Mali",
    "Bénin", "Togo", "Burkina Faso", "Guinée",
]

# Agents whose model can be overridden via AGENT_MODEL_<NAME>.
KNOWN_AGENTS = [
    "market_research", "security_audit", "competitor_watch",
    "regulatory_watch", "growth", "senior_dev",
]


def _env(name: str, default: str | None = None) -> str | None:
    value = os.environ.get(name)
    return value if value not in (None, "") else default


def _env_int(name: str, default: int) -> int:
    raw = os.environ.get(name)
    try:
        return int(raw) if raw not in (None, "") else default
    except ValueError:
        return default


def _env_bool(name: str, default: bool) -> bool:
    raw = os.environ.get(name)
    if raw in (None, ""):
        return default
    return raw.strip().lower() in ("1", "true", "yes", "on")


def _env_list(name: str, default: list[str]) -> list[str]:
    raw = os.environ.get(name)
    if raw in (None, ""):
        return list(default)
    return [item.strip() for item in raw.split(",") if item.strip()]


@dataclass(frozen=True)
class Settings:
    # Auth / models
    anthropic_api_key: str | None
    default_model: str
    effort: str                      # low | medium | high | xhigh | max
    agent_models: dict[str, str]

    # Storage
    db_path: Path
    storage_dir: Path
    audit_repo_path: Path

    # Web search (server tool)
    use_web_search: bool
    web_search_max_uses: int

    # Token sizing
    gather_max_tokens: int
    structure_max_tokens: int

    # Guardrails
    max_api_calls_per_run: int
    max_tokens_per_run: int
    max_server_tool_turns: int
    max_agent_relaunches: int
    agent_max_calls: dict[str, int]

    # Market research
    market_countries: list[str] = field(default_factory=lambda: list(WEST_AFRICA_COUNTRIES))

    def model_for(self, agent_name: str) -> str:
        return self.agent_models.get(agent_name, self.default_model)

    def calls_for(self, agent_name: str) -> int:
        return self.agent_max_calls.get(agent_name, self.max_api_calls_per_run)


def load_settings() -> Settings:
    if load_dotenv is not None:
        load_dotenv(AI_OPS_DIR / ".env")

    storage_dir = Path(_env("AFRILINK_STORAGE_DIR", str(AI_OPS_DIR / "storage")))
    db_path = Path(_env("AFRILINK_DB_PATH", str(storage_dir / "afrilink.db")))
    audit_repo = Path(_env("AUDIT_REPO_PATH", str(REPO_ROOT)))

    default_model = _env("AFRILINK_DEFAULT_MODEL", DEFAULT_MODEL) or DEFAULT_MODEL
    agent_models = {
        name: os.environ[key]
        for name in KNOWN_AGENTS
        if (key := f"AGENT_MODEL_{name.upper()}") in os.environ and os.environ[key]
    }

    countries = _env_list("MARKET_COUNTRIES", WEST_AFRICA_COUNTRIES)

    global_max_calls = _env_int("MAX_API_CALLS_PER_RUN", 8)
    # Per-agent call caps (market scales with the number of countries: one gather
    # per country + a structuring call + headroom).
    agent_max_calls = {
        "market_research": _env_int("MARKET_MAX_CALLS", len(countries) + 3),
        "security_audit": _env_int("SECURITY_MAX_CALLS", 6),
        "senior_dev": _env_int("SENIOR_DEV_MAX_CALLS", 4),
    }

    return Settings(
        anthropic_api_key=_env("ANTHROPIC_API_KEY"),
        default_model=default_model,
        effort=_env("AFRILINK_EFFORT", "medium") or "medium",
        agent_models=agent_models,
        db_path=db_path,
        storage_dir=storage_dir,
        audit_repo_path=audit_repo,
        use_web_search=_env_bool("USE_WEB_SEARCH", True),
        web_search_max_uses=_env_int("WEB_SEARCH_MAX_USES", 5),
        gather_max_tokens=_env_int("GATHER_MAX_TOKENS", 10000),
        structure_max_tokens=_env_int("STRUCTURE_MAX_TOKENS", 6000),
        max_api_calls_per_run=global_max_calls,
        max_tokens_per_run=_env_int("MAX_TOKENS_PER_RUN", 200_000),
        max_server_tool_turns=_env_int("MAX_SERVER_TOOL_TURNS", 6),
        max_agent_relaunches=_env_int("MAX_AGENT_RELAUNCHES", 3),
        agent_max_calls=agent_max_calls,
        market_countries=countries,
    )
