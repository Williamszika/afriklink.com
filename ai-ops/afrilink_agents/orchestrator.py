"""Orchestrator: reads each agent's schedule, triggers the due ones, and triggers
the event-driven SeniorDevAgent when new propositions have arrived.

Designed to be invoked frequently (e.g. hourly by cron). Each agent's `frequency`
plus its `agent_state.last_run_at` decides whether it is due, so the schedule lives
in the DB, not in the cron line.
"""
from __future__ import annotations

import logging
from typing import TYPE_CHECKING

from .registry import AGENT_CLASSES, build_agent
from .util import now_utc, parse_iso

if TYPE_CHECKING:
    from .base import RunResult
    from .config import Settings
    from .db import Database
    from .llm import LLMClient

log = logging.getLogger("afrilink.orchestrator")

FREQUENCY_SECONDS = {"daily": 86_400, "weekly": 604_800}


class Orchestrator:
    def __init__(self, settings: "Settings", db: "Database", llm: "LLMClient"):
        self.settings = settings
        self.db = db
        self.llm = llm

    def is_due(self, name: str) -> bool:
        cls = AGENT_CLASSES[name]
        if not cls.implemented or cls.frequency not in FREQUENCY_SECONDS:
            return False
        state = self.db.get_state(name)
        if state is None or not state["last_run_at"]:
            return True
        elapsed = (now_utc() - parse_iso(state["last_run_at"])).total_seconds()
        return elapsed >= FREQUENCY_SECONDS[cls.frequency]

    def run_agent(self, name: str, trigger: str = "manual") -> "RunResult":
        return build_agent(name, self.settings, self.db, self.llm).run(trigger)

    def run_due(self, trigger: str = "schedule") -> list["RunResult"]:
        results: list[RunResult] = []
        for name, cls in AGENT_CLASSES.items():
            if cls.frequency in FREQUENCY_SECONDS and self.is_due(name):
                log.info("running due agent: %s", name)
                results.append(self.run_agent(name, trigger))
        results.extend(self._maybe_run_senior_dev(trigger))
        return results

    def _maybe_run_senior_dev(self, trigger: str) -> list["RunResult"]:
        """Trigger the SeniorDevAgent when producers created new propositions since its
        last run — capped by MAX_AGENT_RELAUNCHES so it can't loop on its own output."""
        cls = AGENT_CLASSES.get("senior_dev")
        if cls is None or not cls.implemented:
            return []

        state = self.db.get_state("senior_dev")
        last_run = state["last_run_at"] if state else None
        relaunches = int(state["relaunch_count"]) if state else 0
        new_props = self.db.count_propositions_since(since_iso=last_run)

        if new_props <= 0:
            if relaunches:
                self.db.set_state("senior_dev", relaunch_count=0)  # reset when idle
            return []
        if relaunches >= self.settings.max_agent_relaunches:
            log.warning("senior_dev relaunch cap reached (%s) — skipping", relaunches)
            return []

        result = self.run_agent("senior_dev", "event")
        self.db.set_state("senior_dev", relaunch_count=relaunches + 1)
        return [result]
