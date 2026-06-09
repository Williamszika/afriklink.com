def test_message_roundtrip(db):
    mid = db.insert_message(agent="market_research", kind="synthesis", content="hello", topic="t")
    assert mid > 0
    rows = db.fetch_messages(agent="market_research")
    assert len(rows) == 1
    assert rows[0]["content"] == "hello"


def test_proposition_insert_and_status(db):
    pid = db.insert_proposition(
        agent="security_audit", ptype="security_finding", title="SQLi",
        body="bad", severity="high", priority="P1", file="app/x.php", line=10,
        payload={"confidence": "high"}, dedup_key="app/x.php:SQLi",
    )
    assert pid is not None
    pending = db.fetch_propositions(status="pending")
    assert len(pending) == 1

    assert db.update_proposition_status(pid, "approved", "go") is True
    assert len(db.fetch_propositions(status="pending")) == 0
    assert len(db.fetch_propositions(status="approved")) == 1


def test_proposition_dedup(db):
    kw = dict(agent="security_audit", ptype="security_finding", title="XSS",
              body="x", dedup_key="app/y.php:XSS")
    first = db.insert_proposition(**kw)
    second = db.insert_proposition(**kw)
    assert first is not None
    assert second is None  # deduplicated
    assert len(db.fetch_propositions()) == 1


def test_rejected_does_not_block_dedup(db):
    pid = db.insert_proposition(agent="a", ptype="t", title="x", body="b", dedup_key="k")
    db.update_proposition_status(pid, "rejected")
    again = db.insert_proposition(agent="a", ptype="t", title="x", body="b", dedup_key="k")
    assert again is not None  # a rejected item may be re-proposed


def test_runs_and_costs(db):
    run_id = db.start_run("market_research", "manual")
    db.log_api_call(run_id=run_id, agent="market_research", model="claude-opus-4-8",
                    input_tokens=1000, output_tokens=500, web_searches=2, est_cost_usd=0.0325)
    db.finish_run(run_id, status="ok", calls=1, input_tokens=1000, output_tokens=500,
                  est_cost_usd=0.0325)
    summary = db.sum_costs_since(since_iso=None)
    assert summary["calls"] == 1
    assert abs(summary["cost"] - 0.0325) < 1e-9
    assert summary["web"] == 2


def test_agent_state_upsert(db):
    assert db.get_state("market_research") is None
    db.set_state("market_research", last_run_at="2026-06-01T00:00:00+00:00", last_status="ok")
    db.set_state("market_research", relaunch_count=2)
    state = db.get_state("market_research")
    assert state["last_status"] == "ok"          # preserved by COALESCE
    assert state["relaunch_count"] == 2
