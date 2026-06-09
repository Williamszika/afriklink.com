"""SQLite access layer. All queries are parameterised (no string interpolation)."""
from __future__ import annotations

import json
import sqlite3
from pathlib import Path
from typing import Any

from .util import now_iso

SCHEMA_PATH = Path(__file__).resolve().parent / "schema.sql"


class Database:
    def __init__(self, path: str | Path):
        self.path = Path(path)
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.conn = sqlite3.connect(str(self.path))
        self.conn.row_factory = sqlite3.Row
        self.conn.execute("PRAGMA foreign_keys = ON")
        self.init()

    def init(self) -> None:
        self.conn.executescript(SCHEMA_PATH.read_text(encoding="utf-8"))
        self.conn.commit()

    def close(self) -> None:
        self.conn.close()

    # -- messages --------------------------------------------------------------

    def insert_message(
        self, *, agent: str, kind: str, content: str,
        topic: str | None = None, run_id: int | None = None,
    ) -> int:
        cur = self.conn.execute(
            "INSERT INTO messages (agent, kind, topic, content, run_id) "
            "VALUES (?, ?, ?, ?, ?)",
            (agent, kind, topic, content, run_id),
        )
        self.conn.commit()
        return int(cur.lastrowid)

    def fetch_messages(self, *, agent: str | None = None, limit: int = 50) -> list[sqlite3.Row]:
        if agent:
            rows = self.conn.execute(
                "SELECT * FROM messages WHERE agent = ? ORDER BY id DESC LIMIT ?",
                (agent, limit),
            )
        else:
            rows = self.conn.execute(
                "SELECT * FROM messages ORDER BY id DESC LIMIT ?", (limit,)
            )
        return rows.fetchall()

    # -- propositions ----------------------------------------------------------

    def proposition_exists(self, *, agent: str, dedup_key: str) -> bool:
        row = self.conn.execute(
            "SELECT 1 FROM propositions "
            "WHERE agent = ? AND dedup_key = ? AND status != 'rejected' LIMIT 1",
            (agent, dedup_key),
        ).fetchone()
        return row is not None

    def insert_proposition(
        self, *, agent: str, ptype: str, title: str, body: str,
        payload: dict[str, Any] | None = None, priority: str | None = None,
        severity: str | None = None, category: str | None = None,
        country: str | None = None, file: str | None = None, line: int | None = None,
        source_refs: list[Any] | None = None, dedup_key: str | None = None,
        run_id: int | None = None,
    ) -> int | None:
        """Insert a proposition. Returns the new id, or None if deduplicated."""
        if dedup_key and self.proposition_exists(agent=agent, dedup_key=dedup_key):
            return None
        cur = self.conn.execute(
            "INSERT INTO propositions "
            "(agent, ptype, title, body, payload, priority, severity, category, "
            " country, file, line, source_refs, dedup_key, run_id) "
            "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            (
                agent, ptype, title, body,
                json.dumps(payload, ensure_ascii=False) if payload is not None else None,
                priority, severity, category, country, file, line,
                json.dumps(source_refs, ensure_ascii=False) if source_refs is not None else None,
                dedup_key, run_id,
            ),
        )
        self.conn.commit()
        return int(cur.lastrowid)

    def fetch_propositions(
        self, *, status: str | None = None, agent: str | None = None, limit: int = 100,
    ) -> list[sqlite3.Row]:
        clauses, params = [], []
        if status:
            clauses.append("status = ?"); params.append(status)
        if agent:
            clauses.append("agent = ?"); params.append(agent)
        where = (" WHERE " + " AND ".join(clauses)) if clauses else ""
        params.append(limit)
        return self.conn.execute(
            f"SELECT * FROM propositions{where} ORDER BY id DESC LIMIT ?", params
        ).fetchall()

    def update_proposition_status(self, prop_id: int, status: str, note: str | None = None) -> bool:
        cur = self.conn.execute(
            "UPDATE propositions SET status = ?, note = ?, reviewed_at = ? WHERE id = ?",
            (status, note, now_iso(), prop_id),
        )
        self.conn.commit()
        return cur.rowcount > 0

    def count_propositions_since(self, *, since_iso: str | None) -> int:
        if since_iso:
            row = self.conn.execute(
                "SELECT COUNT(*) AS n FROM propositions WHERE created_at > ?", (since_iso,)
            ).fetchone()
        else:
            row = self.conn.execute("SELECT COUNT(*) AS n FROM propositions").fetchone()
        return int(row["n"])

    # -- runs / api calls ------------------------------------------------------

    def start_run(self, agent: str, trigger: str) -> int:
        cur = self.conn.execute(
            "INSERT INTO agent_runs (agent, trigger, status) VALUES (?, ?, 'running')",
            (agent, trigger),
        )
        self.conn.commit()
        return int(cur.lastrowid)

    def finish_run(
        self, run_id: int, *, status: str, calls: int, input_tokens: int,
        output_tokens: int, est_cost_usd: float, error: str | None = None,
    ) -> None:
        self.conn.execute(
            "UPDATE agent_runs SET status = ?, calls = ?, input_tokens = ?, "
            "output_tokens = ?, est_cost_usd = ?, error = ?, finished_at = ? "
            "WHERE id = ?",
            (status, calls, input_tokens, output_tokens, est_cost_usd, error, now_iso(), run_id),
        )
        self.conn.commit()

    def log_api_call(
        self, *, run_id: int | None, agent: str, model: str, input_tokens: int,
        output_tokens: int, cache_read: int = 0, cache_write: int = 0,
        web_searches: int = 0, est_cost_usd: float = 0.0,
    ) -> None:
        self.conn.execute(
            "INSERT INTO api_calls (run_id, agent, model, input_tokens, output_tokens, "
            "cache_read_tokens, cache_write_tokens, web_searches, est_cost_usd) "
            "VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            (run_id, agent, model, input_tokens, output_tokens,
             cache_read, cache_write, web_searches, est_cost_usd),
        )
        self.conn.commit()

    def sum_costs_since(self, *, since_iso: str | None) -> dict[str, Any]:
        if since_iso:
            row = self.conn.execute(
                "SELECT COUNT(*) AS calls, COALESCE(SUM(est_cost_usd),0) AS cost, "
                "COALESCE(SUM(input_tokens),0) AS itok, COALESCE(SUM(output_tokens),0) AS otok, "
                "COALESCE(SUM(web_searches),0) AS web FROM api_calls WHERE created_at > ?",
                (since_iso,),
            ).fetchone()
        else:
            row = self.conn.execute(
                "SELECT COUNT(*) AS calls, COALESCE(SUM(est_cost_usd),0) AS cost, "
                "COALESCE(SUM(input_tokens),0) AS itok, COALESCE(SUM(output_tokens),0) AS otok, "
                "COALESCE(SUM(web_searches),0) AS web FROM api_calls"
            ).fetchone()
        return dict(row)

    # -- agent state -----------------------------------------------------------

    def get_state(self, agent: str) -> sqlite3.Row | None:
        return self.conn.execute(
            "SELECT * FROM agent_state WHERE agent = ?", (agent,)
        ).fetchone()

    def set_state(
        self, agent: str, *, last_run_at: str | None = None,
        last_status: str | None = None, relaunch_count: int | None = None,
    ) -> None:
        existing = self.get_state(agent)
        if existing is None:
            self.conn.execute(
                "INSERT INTO agent_state (agent, last_run_at, last_status, relaunch_count, updated_at) "
                "VALUES (?, ?, ?, ?, ?)",
                (agent, last_run_at, last_status, relaunch_count or 0, now_iso()),
            )
        else:
            self.conn.execute(
                "UPDATE agent_state SET "
                "last_run_at = COALESCE(?, last_run_at), "
                "last_status = COALESCE(?, last_status), "
                "relaunch_count = COALESCE(?, relaunch_count), "
                "updated_at = ? WHERE agent = ?",
                (last_run_at, last_status, relaunch_count, now_iso(), agent),
            )
        self.conn.commit()
