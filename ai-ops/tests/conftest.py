from __future__ import annotations

from pathlib import Path

import pytest

from afrilink_agents.config import Settings, WEST_AFRICA_COUNTRIES
from afrilink_agents.db import Database


def make_settings(tmp_path: Path, **overrides) -> Settings:
    defaults = dict(
        anthropic_api_key=None,
        default_model="claude-opus-4-8",
        effort="medium",
        agent_models={},
        db_path=tmp_path / "afrilink.db",
        storage_dir=tmp_path,
        audit_repo_path=tmp_path,
        use_web_search=True,
        web_search_max_uses=5,
        gather_max_tokens=10000,
        structure_max_tokens=6000,
        max_api_calls_per_run=8,
        max_tokens_per_run=200_000,
        max_server_tool_turns=6,
        max_agent_relaunches=3,
        agent_max_calls={"market_research": 12, "security_audit": 6, "senior_dev": 4},
        market_countries=list(WEST_AFRICA_COUNTRIES),
    )
    defaults.update(overrides)
    return Settings(**defaults)


@pytest.fixture
def settings(tmp_path) -> Settings:
    return make_settings(tmp_path)


@pytest.fixture
def db(tmp_path):
    database = Database(tmp_path / "afrilink.db")
    yield database
    database.close()
