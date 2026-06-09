from typing import Literal

from pydantic import BaseModel

from afrilink_agents.models import MarketResearchOutput, SecurityAuditOutput
from afrilink_agents.schemas import strict_json_schema


def _all_objects(node):
    """Yield every object-typed subschema in a JSON schema."""
    if isinstance(node, dict):
        if node.get("type") == "object":
            yield node
        for value in node.values():
            yield from _all_objects(value)
    elif isinstance(node, list):
        for item in node:
            yield from _all_objects(item)


def test_objects_are_strict_and_fully_required():
    schema = strict_json_schema(MarketResearchOutput)
    objects = list(_all_objects(schema))
    assert objects, "expected nested objects"
    for obj in objects:
        assert obj["additionalProperties"] is False
        if "properties" in obj:
            assert set(obj["required"]) == set(obj["properties"])


def test_unsupported_keywords_stripped():
    class Model(BaseModel):
        name: str
        kind: Literal["a", "b"]

    # Pydantic emits "title"; Literal emits "enum" (allowed) but no constraints here.
    schema = strict_json_schema(Model)
    dumped = repr(schema)
    for bad in ("minLength", "maxLength", "minimum", "maximum", "pattern", "title"):
        assert bad not in dumped


def test_security_schema_has_enum_for_severity():
    schema = strict_json_schema(SecurityAuditOutput)
    text = repr(schema)
    assert "critical" in text and "info" in text  # severity enum preserved
