#!/usr/bin/env python3
"""Generate TypeScript interfaces from the bundled ElDokan OpenAPI schema.

Supports the JSON Schema/OpenAPI constructs used by Customer API Contract v1.
No third-party Python packages are required.
"""
from __future__ import annotations
import json
from pathlib import Path
from typing import Any

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "openapi" / "eldokan-customer-api-v1.openapi.json"
TARGET = ROOT / "src" / "generated" / "types.ts"


def literal(value: Any) -> str:
    if value is True:
        return "true"
    if value is False:
        return "false"
    if value is None:
        return "null"
    return json.dumps(value, ensure_ascii=False)


def ref_name(ref: str) -> str:
    return ref.rsplit("/", 1)[-1]


def ts_type(schema: dict[str, Any], indent: int = 0) -> str:
    nullable = bool(schema.get("nullable"))

    if "$ref" in schema:
        result = ref_name(schema["$ref"])
    elif "allOf" in schema:
        parts = [ts_type(part, indent) for part in schema["allOf"]]
        result = " & ".join(parts) if parts else "unknown"
    elif "oneOf" in schema:
        result = " | ".join(ts_type(part, indent) for part in schema["oneOf"])
    elif "enum" in schema:
        result = " | ".join(literal(v) for v in schema["enum"])
    else:
        stype = schema.get("type")
        if stype == "string":
            pattern = schema.get("pattern")
            prefix_patterns = {
                "^prd_[1-9][0-9]*$": "`prd_${number}`",
                "^cus_[a-f0-9]{32}$": "`cus_${string}`",
                "^cat_[1-9][0-9]*$": "`cat_${number}`",
                "^sel_[1-9][0-9]*$": "`sel_${number}`",
                "^brd_[1-9][0-9]*$": "`brd_${number}`",
                "^tag_[1-9][0-9]*$": "`tag_${number}`",
                "^var_[1-9][0-9]*$": "`var_${number}`",
                "^att_[1-9][0-9]*$": "`att_${number}`",
                "^atr_[1-9][0-9]*$": "`atr_${number}`",
                "^hsl_[1-9][0-9]*$": "`hsl_${number}`",
                "^hbn_[1-9][0-9]*$": "`hbn_${number}`",
                "^req_": "`req_${string}`",
            }
            result = prefix_patterns.get(pattern, "string")
        elif stype in ("integer", "number"):
            result = "number"
        elif stype == "boolean":
            result = "boolean"
        elif stype == "array":
            result = f"Array<{ts_type(schema.get('items', {}), indent)}>"
        elif stype == "object" or "properties" in schema:
            props = schema.get("properties", {})
            required = set(schema.get("required", []))
            if not props:
                result = "Record<string, unknown>"
            else:
                pad = " " * indent
                inner = " " * (indent + 2)
                rows = ["{"]
                for key, prop in props.items():
                    optional = "" if key in required else "?"
                    rows.append(f"{inner}{json.dumps(key) if '-' in key else key}{optional}: {ts_type(prop, indent + 2)};")
                rows.append(f"{pad}}}")
                result = "\n".join(rows)
        else:
            result = "unknown"

    if nullable and "null" not in {p.strip() for p in result.split("|")}:  # simple de-dupe
        result = f"{result} | null"
    return result


def jsdoc(text: str | None) -> list[str]:
    if not text:
        return []
    clean = " ".join(text.split())
    return ["/**", f" * {clean}", " */"]


def render_schema(name: str, schema: dict[str, Any]) -> str:
    lines: list[str] = []
    lines.extend(jsdoc(schema.get("description")))
    stype = schema.get("type")
    if (stype == "object" or "properties" in schema) and "$ref" not in schema and "oneOf" not in schema and "allOf" not in schema:
        required = set(schema.get("required", []))
        lines.append(f"export interface {name} {{")
        for key, prop in schema.get("properties", {}).items():
            lines.extend("  " + line for line in jsdoc(prop.get("description")))
            optional = "" if key in required else "?"
            prop_name = json.dumps(key) if "-" in key else key
            lines.append(f"  {prop_name}{optional}: {ts_type(prop, 2)};")
        lines.append("}")
    else:
        lines.append(f"export type {name} = {ts_type(schema)};")
    return "\n".join(lines)


def main() -> None:
    spec = json.loads(SOURCE.read_text(encoding="utf-8"))
    schemas = spec["components"]["schemas"]
    out = [
        "/* eslint-disable */",
        "/**",
        " * AUTO-GENERATED from openapi/eldokan-customer-api-v1.openapi.json.",
        " * Contract: ElDokan Customer API v1.",
        " * Do not edit manually. Run: python scripts/generate-types.py",
        " */",
        "",
    ]
    for name, schema in schemas.items():
        out.append(render_schema(name, schema))
        out.append("")
    TARGET.write_text("\n".join(out).rstrip() + "\n", encoding="utf-8")
    print(f"Generated {len(schemas)} schemas -> {TARGET.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
