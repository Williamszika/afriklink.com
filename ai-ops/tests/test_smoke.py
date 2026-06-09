import importlib

from afrilink_agents.cli import build_parser
from afrilink_agents.registry import AGENT_CLASSES


def test_all_modules_import():
    for mod in (
        "afrilink_agents.config", "afrilink_agents.db", "afrilink_agents.llm",
        "afrilink_agents.base", "afrilink_agents.orchestrator", "afrilink_agents.cli",
        "afrilink_agents.models", "afrilink_agents.schemas", "afrilink_agents.pricing",
        "afrilink_agents.budget",
    ):
        importlib.import_module(mod)


def test_registry_has_six_agents():
    assert set(AGENT_CLASSES) == {
        "market_research", "security_audit", "competitor_watch",
        "regulatory_watch", "growth", "senior_dev",
    }


def test_mvp_agents_implemented_others_are_stubs():
    assert AGENT_CLASSES["market_research"].implemented is True
    assert AGENT_CLASSES["security_audit"].implemented is True
    for stub in ("competitor_watch", "regulatory_watch", "growth", "senior_dev"):
        assert AGENT_CLASSES[stub].implemented is False


def test_frequencies():
    assert AGENT_CLASSES["market_research"].frequency == "weekly"
    assert AGENT_CLASSES["security_audit"].frequency == "daily"
    assert AGENT_CLASSES["senior_dev"].frequency == "event"


def test_cli_parser_builds():
    parser = build_parser()
    args = parser.parse_args(["run-agent", "market_research", "--trigger", "manual"])
    assert args.name == "market_research"
