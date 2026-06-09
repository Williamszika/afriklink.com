"""Per-run cost guardrails: a hard cap on API calls and on total tokens.

Every LLM call goes through a RunBudget. `before_call()` is checked *before* each
request (stops once the call cap is hit); `register()` records usage *after* and
raises once the token cap is exceeded, so a runaway agent halts mid-run.
"""
from __future__ import annotations

from dataclasses import dataclass


class BudgetExceeded(RuntimeError):
    """Raised when an agent run hits its call or token ceiling."""


@dataclass
class RunBudget:
    max_calls: int
    max_tokens: int
    calls: int = 0
    input_tokens: int = 0
    output_tokens: int = 0
    web_searches: int = 0
    est_cost_usd: float = 0.0

    @property
    def total_tokens(self) -> int:
        return self.input_tokens + self.output_tokens

    def before_call(self) -> None:
        if self.calls >= self.max_calls:
            raise BudgetExceeded(
                f"call cap reached ({self.calls}/{self.max_calls})"
            )

    def register(
        self,
        *,
        input_tokens: int,
        output_tokens: int,
        web_searches: int = 0,
        est_cost_usd: float = 0.0,
    ) -> None:
        self.calls += 1
        self.input_tokens += input_tokens
        self.output_tokens += output_tokens
        self.web_searches += web_searches
        self.est_cost_usd = round(self.est_cost_usd + est_cost_usd, 6)
        if self.total_tokens > self.max_tokens:
            raise BudgetExceeded(
                f"token cap reached ({self.total_tokens}/{self.max_tokens})"
            )
