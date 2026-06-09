"""Agent registry: name -> class, preserving a stable run order."""
from __future__ import annotations

from typing import TYPE_CHECKING

from .agents.competitor_watch import CompetitorWatchAgent
from .agents.growth import GrowthAgent
from .agents.market_research import MarketResearchAgent
from .agents.regulatory_watch import RegulatoryWatchAgent
from .agents.security_audit import SecurityAuditAgent
from .agents.senior_dev import SeniorDevAgent
from .base import BaseAgent

if TYPE_CHECKING:
    from .config import Settings
    from .db import Database
    from .llm import LLMClient

# Order = orchestrator run order (producers before the senior dev consumer).
_AGENT_LIST: list[type[BaseAgent]] = [
    MarketResearchAgent,
    SecurityAuditAgent,
    CompetitorWatchAgent,
    RegulatoryWatchAgent,
    GrowthAgent,
    SeniorDevAgent,
]

AGENT_CLASSES: dict[str, type[BaseAgent]] = {cls.name: cls for cls in _AGENT_LIST}


def build_agent(name: str, settings: "Settings", db: "Database", llm: "LLMClient") -> BaseAgent:
    if name not in AGENT_CLASSES:
        raise KeyError(f"agent inconnu: {name!r} (connus: {', '.join(AGENT_CLASSES)})")
    return AGENT_CLASSES[name](settings, db, llm)
