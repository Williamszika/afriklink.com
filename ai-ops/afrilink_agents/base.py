"""BaseAgent: the run lifecycle shared by every agent.

run() wraps execute() with guardrails (RunBudget), persistence (agent_runs row),
cost capture, and error handling. Subclasses implement execute() — the role logic —
and use self.llm for metered calls and self.db to write messages / propositions.
"""
from __future__ import annotations

import logging
import traceback
from dataclasses import dataclass
from typing import TYPE_CHECKING

from .budget import BudgetExceeded, RunBudget
from .util import now_iso

if TYPE_CHECKING:
    from .config import Settings
    from .db import Database
    from .llm import LLMClient

log = logging.getLogger("afrilink.agent")


@dataclass
class RunResult:
    agent: str
    status: str            # ok | error | budget_exceeded | skipped
    run_id: int | None
    calls: int
    tokens: int
    est_cost_usd: float
    error: str | None
    detail: str


class BaseAgent:
    #: unique key, also the agent_state / DB identifier
    name: str = ""
    #: daily | weekly | event | manual
    frequency: str = "manual"
    #: set False on stubs so the orchestrator skips them cleanly
    implemented: bool = True

    def __init__(self, settings: "Settings", db: "Database", llm: "LLMClient"):
        self.settings = settings
        self.db = db
        self.llm = llm

    @property
    def model(self) -> str:
        return self.settings.model_for(self.name)

    def make_budget(self) -> RunBudget:
        return RunBudget(
            max_calls=self.settings.calls_for(self.name),
            max_tokens=self.settings.max_tokens_per_run,
        )

    def execute(self, run_id: int, budget: RunBudget) -> str:  # pragma: no cover
        """Core logic. Return a short human-readable summary string."""
        raise NotImplementedError

    def run(self, trigger: str = "manual") -> RunResult:
        if not self.implemented:
            log.info("agent %s is a stub — skipped", self.name)
            return RunResult(self.name, "skipped", None, 0, 0, 0.0, None, "stub (not implemented)")

        budget = self.make_budget()
        run_id = self.db.start_run(self.name, trigger)
        status, error, detail = "ok", None, ""
        try:
            detail = self.execute(run_id, budget) or ""
        except BudgetExceeded as exc:
            status, error = "budget_exceeded", str(exc)
            log.warning("agent %s hit budget: %s", self.name, exc)
        except Exception as exc:  # noqa: BLE001 — one agent must not crash the run
            status, error = "error", f"{type(exc).__name__}: {exc}"
            log.error("agent %s failed:\n%s", self.name, traceback.format_exc())

        self.db.finish_run(
            run_id, status=status, calls=budget.calls,
            input_tokens=budget.input_tokens, output_tokens=budget.output_tokens,
            est_cost_usd=budget.est_cost_usd, error=error,
        )
        self.db.set_state(self.name, last_run_at=now_iso(), last_status=status)
        return RunResult(
            self.name, status, run_id, budget.calls, budget.total_tokens,
            budget.est_cost_usd, error, detail,
        )
