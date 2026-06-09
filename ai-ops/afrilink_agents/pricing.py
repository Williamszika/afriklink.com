"""Model pricing + cost estimation (for the per-call cost log / guardrails).

Prices are USD per 1,000,000 tokens (input, output), per the Claude model catalog.
Cache reads bill at ~0.1x input; cache writes (5-min TTL) at ~1.25x input.
Web search bills per search. All figures are estimates for budgeting, not invoices.
"""
from __future__ import annotations

# (input_per_1M, output_per_1M) in USD
PRICING: dict[str, tuple[float, float]] = {
    "claude-opus-4-8": (5.00, 25.00),
    "claude-opus-4-7": (5.00, 25.00),
    "claude-opus-4-6": (5.00, 25.00),
    "claude-sonnet-4-6": (3.00, 15.00),
    "claude-haiku-4-5": (1.00, 5.00),
}

_FALLBACK = PRICING["claude-opus-4-8"]

# Anthropic web search: ~$10 per 1,000 searches.
WEB_SEARCH_COST_PER_SEARCH = 10.0 / 1000.0


def estimate_cost(
    model: str,
    input_tokens: int = 0,
    output_tokens: int = 0,
    cache_read_tokens: int = 0,
    cache_write_tokens: int = 0,
    web_searches: int = 0,
) -> float:
    """Estimated USD cost of a single API call."""
    price_in, price_out = PRICING.get(model, _FALLBACK)
    cost = (
        input_tokens * price_in
        + output_tokens * price_out
        + cache_read_tokens * price_in * 0.10
        + cache_write_tokens * price_in * 1.25
    ) / 1_000_000.0
    cost += web_searches * WEB_SEARCH_COST_PER_SEARCH
    return round(cost, 6)
