-- AfriLink AI-Ops — shared SQLite database.
-- The agents communicate through `messages` and write actionable `propositions`
-- that the human validates. `agent_runs` + `api_calls` give cost/audit visibility.

PRAGMA journal_mode = WAL;
PRAGMA foreign_keys = ON;

-- Inter-agent message bus: each agent writes its results here.
CREATE TABLE IF NOT EXISTS messages (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    agent      TEXT NOT NULL,
    kind       TEXT NOT NULL,            -- synthesis | audit_summary | note | ...
    topic      TEXT,
    content    TEXT NOT NULL,            -- text or JSON
    run_id     INTEGER,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_messages_agent ON messages(agent);
CREATE INDEX IF NOT EXISTS idx_messages_created ON messages(created_at);

-- Action proposals the human validates manually.
CREATE TABLE IF NOT EXISTS propositions (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    agent       TEXT NOT NULL,
    ptype       TEXT NOT NULL,           -- market_opportunity | security_finding | recommendation | ...
    title       TEXT NOT NULL,
    body        TEXT NOT NULL,
    payload     TEXT,                    -- JSON
    priority    TEXT,                    -- P0..P3
    severity    TEXT,                    -- critical..info (security)
    category    TEXT,
    country     TEXT,
    file        TEXT,
    line        INTEGER,
    source_refs TEXT,                    -- JSON list of proposition/message ids
    dedup_key   TEXT,                    -- prevents re-proposing the same item
    status      TEXT NOT NULL DEFAULT 'pending',   -- pending | approved | rejected | done
    note        TEXT,
    run_id      INTEGER,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME
);
CREATE INDEX IF NOT EXISTS idx_props_status ON propositions(status);
CREATE INDEX IF NOT EXISTS idx_props_agent ON propositions(agent);
CREATE INDEX IF NOT EXISTS idx_props_created ON propositions(created_at);

-- One row per agent execution.
CREATE TABLE IF NOT EXISTS agent_runs (
    id            INTEGER PRIMARY KEY AUTOINCREMENT,
    agent         TEXT NOT NULL,
    trigger       TEXT NOT NULL,         -- schedule | manual | event | commit
    status        TEXT NOT NULL,         -- running | ok | error | budget_exceeded | skipped
    calls         INTEGER NOT NULL DEFAULT 0,
    input_tokens  INTEGER NOT NULL DEFAULT 0,
    output_tokens INTEGER NOT NULL DEFAULT 0,
    est_cost_usd  REAL NOT NULL DEFAULT 0,
    error         TEXT,
    started_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finished_at   DATETIME
);
CREATE INDEX IF NOT EXISTS idx_runs_agent ON agent_runs(agent);

-- One row per Claude API call (mandatory cost log).
CREATE TABLE IF NOT EXISTS api_calls (
    id                 INTEGER PRIMARY KEY AUTOINCREMENT,
    run_id             INTEGER,
    agent              TEXT NOT NULL,
    model              TEXT NOT NULL,
    input_tokens       INTEGER NOT NULL DEFAULT 0,
    output_tokens      INTEGER NOT NULL DEFAULT 0,
    cache_read_tokens  INTEGER NOT NULL DEFAULT 0,
    cache_write_tokens INTEGER NOT NULL DEFAULT 0,
    web_searches       INTEGER NOT NULL DEFAULT 0,
    est_cost_usd       REAL NOT NULL DEFAULT 0,
    created_at         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS idx_apicalls_run ON api_calls(run_id);
CREATE INDEX IF NOT EXISTS idx_apicalls_created ON api_calls(created_at);

-- Per-agent scheduling + loop state.
CREATE TABLE IF NOT EXISTS agent_state (
    agent          TEXT PRIMARY KEY,
    last_run_at    TEXT,
    last_status    TEXT,
    relaunch_count INTEGER NOT NULL DEFAULT 0,
    updated_at     TEXT
);
