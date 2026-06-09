import pytest

from afrilink_agents.budget import BudgetExceeded, RunBudget
from afrilink_agents.pricing import estimate_cost


def test_call_cap():
    budget = RunBudget(max_calls=2, max_tokens=10_000)
    budget.before_call(); budget.register(input_tokens=1, output_tokens=1)
    budget.before_call(); budget.register(input_tokens=1, output_tokens=1)
    with pytest.raises(BudgetExceeded):
        budget.before_call()  # third call refused


def test_token_cap():
    budget = RunBudget(max_calls=100, max_tokens=1_000)
    with pytest.raises(BudgetExceeded):
        budget.register(input_tokens=900, output_tokens=200)  # 1100 > 1000
    assert budget.calls == 1  # the offending call is still counted


def test_estimate_cost_opus():
    # 1M input + 1M output on Opus 4.8 = $5 + $25
    assert estimate_cost("claude-opus-4-8", 1_000_000, 1_000_000) == pytest.approx(30.0)


def test_estimate_cost_web_and_cache():
    cost = estimate_cost("claude-haiku-4-5", input_tokens=0, output_tokens=0,
                         cache_read_tokens=0, cache_write_tokens=0, web_searches=10)
    assert cost == pytest.approx(0.10)  # 10 searches * $0.01


def test_unknown_model_falls_back_to_opus_pricing():
    assert estimate_cost("mystery", 1_000_000, 0) == pytest.approx(5.0)
