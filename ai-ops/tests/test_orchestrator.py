from datetime import timedelta

from afrilink_agents.orchestrator import Orchestrator
from afrilink_agents.util import now_utc


def _iso(dt):
    return dt.replace(microsecond=0).isoformat()


def test_is_due_never_run(settings, db):
    orch = Orchestrator(settings, db, llm=None)
    assert orch.is_due("market_research") is True   # weekly, no state yet
    assert orch.is_due("security_audit") is True     # daily, no state yet


def test_is_due_recent_vs_stale(settings, db):
    orch = Orchestrator(settings, db, llm=None)

    # security_audit (daily): ran 2h ago -> not due; 2 days ago -> due
    db.set_state("security_audit", last_run_at=_iso(now_utc() - timedelta(hours=2)))
    assert orch.is_due("security_audit") is False
    db.set_state("security_audit", last_run_at=_iso(now_utc() - timedelta(days=2)))
    assert orch.is_due("security_audit") is True

    # market_research (weekly): ran 3 days ago -> not due
    db.set_state("market_research", last_run_at=_iso(now_utc() - timedelta(days=3)))
    assert orch.is_due("market_research") is False


def test_event_and_stub_agents_not_scheduled(settings, db):
    orch = Orchestrator(settings, db, llm=None)
    # senior_dev is event-driven; the others are stubs (implemented=False)
    assert orch.is_due("senior_dev") is False
    assert orch.is_due("competitor_watch") is False


def test_run_due_skips_when_nothing_due(settings, db):
    orch = Orchestrator(settings, db, llm=None)
    db.set_state("market_research", last_run_at=_iso(now_utc()))
    db.set_state("security_audit", last_run_at=_iso(now_utc()))
    # senior_dev stub returns nothing; no implemented producers are due -> empty
    assert orch.run_due() == []
