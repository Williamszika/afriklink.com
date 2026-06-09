"""Command-line interface.

    python -m afrilink_agents init-db
    python -m afrilink_agents run                 # orchestrator: run all due agents
    python -m afrilink_agents run-agent market_research
    python -m afrilink_agents agents              # list agents + schedule + last run
    python -m afrilink_agents proposals --status pending
    python -m afrilink_agents review 12 --approve --note "go"
    python -m afrilink_agents messages --agent security_audit
    python -m afrilink_agents costs --days 30
"""
from __future__ import annotations

import argparse
import logging
import sys
from datetime import timedelta

from .config import load_settings
from .db import Database
from .llm import LLMClient
from .orchestrator import Orchestrator
from .registry import AGENT_CLASSES
from .util import now_utc


def _context():
    settings = load_settings()
    db = Database(settings.db_path)
    llm = LLMClient(settings, db)
    return settings, db, llm


def _print_results(results) -> None:
    if not results:
        print("Aucun agent à exécuter (rien n'est dû).")
        return
    print(f"{'AGENT':<18} {'STATUT':<16} {'APPELS':>6} {'TOKENS':>9} {'COÛT$':>9}  DÉTAIL")
    for r in results:
        print(f"{r.agent:<18} {r.status:<16} {r.calls:>6} {r.tokens:>9} "
              f"{r.est_cost_usd:>9.4f}  {r.detail or (r.error or '')}")
    total = sum(r.est_cost_usd for r in results)
    print(f"\nCoût total estimé de cette exécution : ${total:.4f}")


def cmd_init_db(args, settings, db, llm) -> int:
    print(f"Base initialisée : {settings.db_path}")
    return 0


def cmd_run(args, settings, db, llm) -> int:
    _print_results(Orchestrator(settings, db, llm).run_due("schedule"))
    return 0


def cmd_run_agent(args, settings, db, llm) -> int:
    if args.name not in AGENT_CLASSES:
        print(f"Agent inconnu : {args.name}. Connus : {', '.join(AGENT_CLASSES)}", file=sys.stderr)
        return 2
    _print_results([Orchestrator(settings, db, llm).run_agent(args.name, args.trigger)])
    return 0


def cmd_agents(args, settings, db, llm) -> int:
    print(f"{'AGENT':<18} {'FRÉQ':<8} {'STATUT':<8} {'MODÈLE':<20} {'DERNIER RUN':<22} {'ÉTAT'}")
    for name, cls in AGENT_CLASSES.items():
        state = db.get_state(name)
        impl = "actif" if cls.implemented else "stub"
        last = (state["last_run_at"] if state and state["last_run_at"] else "—")
        last_status = (state["last_status"] if state and state["last_status"] else "—")
        print(f"{name:<18} {cls.frequency:<8} {impl:<8} {settings.model_for(name):<20} "
              f"{last:<22} {last_status}")
    return 0


def cmd_proposals(args, settings, db, llm) -> int:
    rows = db.fetch_propositions(status=args.status, agent=args.agent, limit=args.limit)
    if not rows:
        print("Aucune proposition.")
        return 0
    for r in rows:
        flag = {"pending": "·", "approved": "✓", "rejected": "✗", "done": "★"}.get(r["status"], "?")
        meta = " ".join(filter(None, [
            r["priority"] or "", r["severity"] or "", r["country"] or "", r["file"] or ""]))
        print(f"[{flag}] #{r['id']:<4} {r['agent']:<16} {r['ptype']:<18} {meta}")
        print(f"      {r['title']}")
    print(f"\n{len(rows)} proposition(s).")
    return 0


def cmd_review(args, settings, db, llm) -> int:
    status = "approved" if args.approve else "rejected"
    if db.update_proposition_status(args.id, status, args.note):
        print(f"Proposition #{args.id} → {status}.")
        return 0
    print(f"Proposition #{args.id} introuvable.", file=sys.stderr)
    return 1


def cmd_messages(args, settings, db, llm) -> int:
    rows = db.fetch_messages(agent=args.agent, limit=args.limit)
    if not rows:
        print("Aucun message.")
        return 0
    for r in rows:
        body = r["content"]
        preview = body if len(body) <= 200 else body[:200] + "…"
        print(f"#{r['id']:<4} {r['created_at']} {r['agent']}/{r['kind']}: {preview}")
    return 0


def cmd_costs(args, settings, db, llm) -> int:
    since = None
    if args.days > 0:
        since = (now_utc() - timedelta(days=args.days)).replace(microsecond=0).isoformat()
    s = db.sum_costs_since(since_iso=since)
    window = f"{args.days} derniers jours" if args.days > 0 else "tout l'historique"
    print(f"Coûts API ({window}) :")
    print(f"  appels        : {s['calls']}")
    print(f"  tokens entrée : {s['itok']}")
    print(f"  tokens sortie : {s['otok']}")
    print(f"  web searches  : {s['web']}")
    print(f"  coût estimé   : ${s['cost']:.4f}")
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="afrilink_agents", description="AfriLink AI-Ops")
    sub = parser.add_subparsers(dest="command", required=True)

    sub.add_parser("init-db", help="créer/mettre à jour la base SQLite")
    sub.add_parser("run", help="exécuter tous les agents dus (orchestrateur)")

    p = sub.add_parser("run-agent", help="exécuter un agent maintenant")
    p.add_argument("name")
    p.add_argument("--trigger", default="manual")

    sub.add_parser("agents", help="lister les agents et leur état")

    p = sub.add_parser("proposals", help="lister les propositions")
    p.add_argument("--status", choices=["pending", "approved", "rejected", "done"])
    p.add_argument("--agent")
    p.add_argument("--limit", type=int, default=100)

    p = sub.add_parser("review", help="valider/rejeter une proposition")
    p.add_argument("id", type=int)
    g = p.add_mutually_exclusive_group(required=True)
    g.add_argument("--approve", action="store_true")
    g.add_argument("--reject", action="store_true")
    p.add_argument("--note")

    p = sub.add_parser("messages", help="lister les messages inter-agents")
    p.add_argument("--agent")
    p.add_argument("--limit", type=int, default=30)

    p = sub.add_parser("costs", help="résumé des coûts API")
    p.add_argument("--days", type=int, default=30)
    return parser


_HANDLERS = {
    "init-db": cmd_init_db, "run": cmd_run, "run-agent": cmd_run_agent,
    "agents": cmd_agents, "proposals": cmd_proposals, "review": cmd_review,
    "messages": cmd_messages, "costs": cmd_costs,
}


def main(argv: list[str] | None = None) -> int:
    logging.basicConfig(level=logging.INFO, format="%(levelname)s %(name)s: %(message)s")
    args = build_parser().parse_args(argv)
    settings, db, llm = _context()
    try:
        return _HANDLERS[args.command](args, settings, db, llm)
    finally:
        db.close()


if __name__ == "__main__":
    raise SystemExit(main())
