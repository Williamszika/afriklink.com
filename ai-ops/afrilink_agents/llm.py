"""Anthropic Messages API wrapper.

Two primitives the agents use:
  * gather()    — research with the server-side web_search / web_fetch tools and
                  adaptive thinking; returns free-form text with sources.
  * structure() — coerce text into a validated Pydantic model via structured
                  outputs (output_config.format). No tools, so no citation conflict.

Every underlying API request is metered: cost is logged to `api_calls` and counted
against the run's RunBudget (which raises BudgetExceeded at the cap).

Model defaults: claude-opus-4-8, adaptive thinking, effort from settings. No
temperature/top_p/budget_tokens (removed on Opus 4.8).
"""
from __future__ import annotations

from typing import TYPE_CHECKING, Any

from .budget import RunBudget
from .pricing import estimate_cost
from .schemas import strict_json_schema

if TYPE_CHECKING:  # avoid importing pydantic/anthropic at module import time
    from pydantic import BaseModel

    from .config import Settings
    from .db import Database

# Server-side tool versions (Messages API).
WEB_SEARCH_TOOL = "web_search_20260209"
WEB_FETCH_TOOL = "web_fetch_20260209"


def _text_of(response: Any) -> str:
    """Concatenate text blocks from a Messages response."""
    parts = []
    for block in getattr(response, "content", []) or []:
        if getattr(block, "type", None) == "text":
            parts.append(block.text)
    return "".join(parts)


class LLMClient:
    def __init__(self, settings: "Settings", db: "Database"):
        self.settings = settings
        self.db = db
        self._client = None

    def _client_or_create(self):
        if self._client is None:
            import anthropic  # lazy: keep import-time light and offline-friendly

            key = self.settings.anthropic_api_key
            if not key:
                import os
                key = os.environ.get("ANTHROPIC_API_KEY")
            if not key:
                raise RuntimeError(
                    "ANTHROPIC_API_KEY manquant — définis-le dans l'environnement ou ai-ops/.env"
                )
            self._client = anthropic.Anthropic(api_key=key)
        return self._client

    # -- accounting ------------------------------------------------------------

    def _account(self, response: Any, *, run_id: int | None, agent: str, model: str,
                 budget: RunBudget) -> None:
        usage = getattr(response, "usage", None)
        inp = int(getattr(usage, "input_tokens", 0) or 0)
        out = int(getattr(usage, "output_tokens", 0) or 0)
        cache_read = int(getattr(usage, "cache_read_input_tokens", 0) or 0)
        cache_write = int(getattr(usage, "cache_creation_input_tokens", 0) or 0)
        web = 0
        server_tool_use = getattr(usage, "server_tool_use", None)
        if server_tool_use is not None:
            web = int(getattr(server_tool_use, "web_search_requests", 0) or 0)

        cost = estimate_cost(model, inp, out, cache_read, cache_write, web)
        self.db.log_api_call(
            run_id=run_id, agent=agent, model=model, input_tokens=inp,
            output_tokens=out, cache_read=cache_read, cache_write=cache_write,
            web_searches=web, est_cost_usd=cost,
        )
        budget.register(input_tokens=inp, output_tokens=out, web_searches=web, est_cost_usd=cost)

    def _create(self, *, model: str, system: str, messages: list[dict[str, Any]],
                budget: RunBudget, run_id: int | None, agent: str, max_tokens: int,
                tools: list[dict[str, Any]] | None = None,
                output_config: dict[str, Any] | None = None,
                thinking: dict[str, Any] | None = None) -> Any:
        budget.before_call()
        client = self._client_or_create()
        kwargs: dict[str, Any] = dict(
            model=model, max_tokens=max_tokens, system=system, messages=messages,
        )
        if tools:
            kwargs["tools"] = tools
        if output_config:
            kwargs["output_config"] = output_config
        if thinking:
            kwargs["thinking"] = thinking
        response = client.messages.create(**kwargs)
        self._account(response, run_id=run_id, agent=agent, model=model, budget=budget)
        return response

    # -- primitives ------------------------------------------------------------

    def gather(self, *, agent: str, run_id: int | None, budget: RunBudget,
               system: str, user: str, model: str | None = None,
               max_tokens: int | None = None) -> str:
        """Research call with live web access. Handles the server-tool pause loop."""
        model = model or self.settings.default_model
        max_tokens = max_tokens or self.settings.gather_max_tokens

        tools = None
        if self.settings.use_web_search:
            tools = [
                {"type": WEB_SEARCH_TOOL, "name": "web_search",
                 "max_uses": self.settings.web_search_max_uses},
                {"type": WEB_FETCH_TOOL, "name": "web_fetch",
                 "max_uses": self.settings.web_search_max_uses},
            ]

        messages: list[dict[str, Any]] = [{"role": "user", "content": user}]
        texts: list[str] = []
        for _ in range(self.settings.max_server_tool_turns):
            response = self._create(
                model=model, system=system, messages=messages, budget=budget,
                run_id=run_id, agent=agent, max_tokens=max_tokens, tools=tools,
                thinking={"type": "adaptive"},
                output_config={"effort": self.settings.effort},
            )
            texts.append(_text_of(response))
            if getattr(response, "stop_reason", None) == "pause_turn":
                # Server tool paused — resend with the assistant turn so it resumes.
                messages.append({"role": "assistant", "content": response.content})
                continue
            break
        return "\n\n".join(t for t in texts if t)

    def structure(self, *, agent: str, run_id: int | None, budget: RunBudget,
                  system: str, user: str, schema_model: "type[BaseModel]",
                  model: str | None = None, max_tokens: int | None = None) -> "BaseModel":
        """Coerce input into a validated Pydantic instance via structured outputs."""
        model = model or self.settings.default_model
        max_tokens = max_tokens or self.settings.structure_max_tokens
        output_config = {
            "format": {"type": "json_schema", "schema": strict_json_schema(schema_model)},
            "effort": "low",
        }

        messages: list[dict[str, Any]] = [{"role": "user", "content": user}]
        last_error: Exception | None = None
        for _ in range(2):  # one repair attempt
            response = self._create(
                model=model, system=system, messages=messages, budget=budget,
                run_id=run_id, agent=agent, max_tokens=max_tokens,
                output_config=output_config, thinking={"type": "disabled"},
            )
            text = _text_of(response)
            try:
                return schema_model.model_validate_json(text)
            except Exception as exc:  # noqa: BLE001 — surface as repair turn
                last_error = exc
                messages = [
                    {"role": "user", "content": user},
                    {"role": "assistant", "content": text},
                    {"role": "user", "content":
                        f"Sortie invalide ({exc}). Renvoie UNIQUEMENT le JSON valide "
                        f"conforme au schéma, sans texte autour."},
                ]
        raise ValueError(f"structure(): JSON invalide après 2 tentatives: {last_error}")
