# AfriLink AI-Ops

A Python multi-agent system that drives continuous improvement of **AfriLink** (the
PHP/MySQL marketplace connecting West Africa and Europe). It is **separate from the
site**: it runs on its own schedule, calls the Claude API, and writes **action
proposals** into a shared SQLite database that **you validate manually**.

```
orchestrator ──> agents ──(messages + propositions)──> SQLite ──> you review/approve
     ▲                                                   │
     └──────────────── cron / git hook ─────────────────┘
```

## Agents

| Agent | Frequency | Status | Output |
|---|---|---|---|
| `market_research` | weekly | ✅ MVP | e-commerce trends, payments, behaviours & opportunities per country |
| `security_audit` | daily + on-commit | ✅ MVP | vulnerability tickets on **your** code |
| `competitor_watch` | daily | 🚧 stub | gaps in competitors' **public** offering (no probing/scanning) |
| `regulatory_watch` | weekly | 🚧 stub | data-protection / e-commerce / tax changes + impact |
| `growth` | weekly | 🚧 stub | acquisition channels & segments (EU + Africa) |
| `senior_dev` | event | 🚧 stub | prioritized technical recommendations from other agents' proposals |

The orchestrator runs producers when due, then triggers `senior_dev` when new
proposals have arrived (relaunch-capped).

## Model & cost posture

- Default model: **`claude-opus-4-8`** for every agent (override per agent via
  `AGENT_MODEL_<NAME>`), adaptive thinking, effort `medium`.
- Research agents use the Anthropic **web search** server tool for fresh, cited data.
- **Guardrails (mandatory):** per-run caps on API **calls** and **tokens**
  (`RunBudget`), every call logged to `api_calls` with an estimated cost, an
  event-relaunch cap, and the API key only ever read from the environment.

## Layout

```
ai-ops/
├── afrilink_agents/
│   ├── config.py          # env-driven settings, model & guardrail config
│   ├── budget.py          # RunBudget: per-run call/token caps
│   ├── pricing.py         # cost estimation
│   ├── llm.py             # Anthropic wrapper: gather() web + structure() JSON, metered
│   ├── schemas.py         # Pydantic -> strict JSON schema for structured outputs
│   ├── models.py          # validated output models
│   ├── db.py / schema.sql # SQLite: messages, propositions, agent_runs, api_calls, state
│   ├── base.py            # BaseAgent run lifecycle (guardrails + logging)
│   ├── orchestrator.py    # schedule + event triggering
│   ├── registry.py        # name -> agent class
│   ├── cli.py             # operator CLI
│   └── agents/            # market_research, security_audit (MVP) + 4 stubs
├── scripts/
│   ├── crontab.example
│   └── git-hooks/post-commit
└── tests/
```

## Setup

```bash
cd ai-ops
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt

cp .env.example .env          # set ANTHROPIC_API_KEY (and tune guardrails)
python -m afrilink_agents init-db
```

## Usage

```bash
# Orchestrator — runs only the agents that are due (this is the cron entrypoint)
python -m afrilink_agents run

# Run one agent now
python -m afrilink_agents run-agent security_audit
python -m afrilink_agents run-agent market_research

# Review the proposals you need to validate
python -m afrilink_agents proposals --status pending
python -m afrilink_agents review 12 --approve --note "go"
python -m afrilink_agents review 13 --reject

# Inter-agent messages, agent status, and cost report
python -m afrilink_agents messages --agent security_audit
python -m afrilink_agents agents
python -m afrilink_agents costs --days 30
```

### Scheduling

```bash
crontab scripts/crontab.example          # hourly orchestrator (edit paths first)
```

### On-commit security audit

```bash
ln -s ../../ai-ops/scripts/git-hooks/post-commit .git/hooks/post-commit
```

## Guardrails reference (`.env`)

| Variable | Default | Purpose |
|---|---|---|
| `MAX_API_CALLS_PER_RUN` | 8 | global per-run call cap |
| `MAX_TOKENS_PER_RUN` | 200000 | per-run input+output token cap |
| `MAX_SERVER_TOOL_TURNS` | 6 | web-search pause/resume turns per gather |
| `MAX_AGENT_RELAUNCHES` | 3 | event-driven relaunch cap (senior_dev) |
| `WEB_SEARCH_MAX_USES` | 5 | searches per gather call |
| `AFRILINK_EFFORT` | medium | thinking/effort level |
| `MARKET_COUNTRIES` | (9 countries) | trim to cut cost while testing |

## Tests

```bash
pip install pytest && pytest          # no API key or network required
```

## Safety & scope

- `security_audit` analyses **your own** code only (authorized), is **defensive
  only** (never emits exploits), and never reads secret files (`.env`, keys).
- `competitor_watch` uses **public information only** — it must never scan, probe,
  or test the security of any third-party site (enforced in its system prompt).
- Proposals are advice for a human to validate — nothing is auto-applied to the site.
