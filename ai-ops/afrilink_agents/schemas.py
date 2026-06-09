"""Turn a Pydantic model into a strict JSON Schema accepted by the Messages API
structured-output format (`output_config.format`).

The API requires `additionalProperties: false` on every object and does not support
constraint keywords (minLength, minimum, pattern, format, ...). We design our models
with all-required fields and no constraints, then this cleaner enforces those rules
(and lists every property in `required`, so the model must emit a complete object).
"""
from __future__ import annotations

from typing import Any

from pydantic import BaseModel

# Keywords the structured-output schema validator does not support — strip them.
_UNSUPPORTED_KEYS = {
    "minLength", "maxLength", "minimum", "maximum", "exclusiveMinimum",
    "exclusiveMaximum", "multipleOf", "minItems", "maxItems", "uniqueItems",
    "pattern", "format", "title", "default", "examples",
}


def strict_json_schema(model: type[BaseModel]) -> dict[str, Any]:
    return _clean(model.model_json_schema())


def _clean(node: Any) -> Any:
    if isinstance(node, dict):
        cleaned: dict[str, Any] = {
            key: _clean(value)
            for key, value in node.items()
            if key not in _UNSUPPORTED_KEYS
        }
        if cleaned.get("type") == "object":
            cleaned["additionalProperties"] = False
            properties = cleaned.get("properties")
            if isinstance(properties, dict):
                # All fields are required for reliable structured output.
                cleaned["required"] = list(properties.keys())
        return cleaned
    if isinstance(node, list):
        return [_clean(item) for item in node]
    return node
