#!/usr/bin/env python3
"""Small CPU-only Ollama structured-output benchmark. No model downloads or external calls."""
from __future__ import annotations

import argparse
import json
import statistics
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

BASE = "http://127.0.0.1:11434"  # Fixed to loopback; never accepts a remote model endpoint.
SCHEMA: dict[str, Any] = {
    "type": "object",
    "properties": {
        "hook": {"type": "string"},
        "body": {"type": "string"},
        "risk_level": {"type": "string", "enum": ["low", "moderate", "high"]},
        "claims": {"type": "array", "items": {"type": "string"}},
    },
    "required": ["hook", "body", "risk_level", "claims"],
    "additionalProperties": False,
}

PROMPTS = [
    ("aadi-tech-solutions", "Draft a cautious IT maintenance tip with no performance or security guarantees."),
    ("overseas-job-placement", "Draft an interview-preparation reminder. Do not invent jobs, salaries, visas or employers."),
    ("toolboxgalaxy", "Draft a generic workflow tip about organizing repetitive file tasks. Do not claim a tool supports any specific feature."),
]


def request_json(path: str, body: dict | None, timeout: int) -> dict:
    payload = None if body is None else json.dumps(body).encode("utf-8")
    req = urllib.request.Request(
        BASE + path,
        data=payload,
        headers={"Content-Type": "application/json"},
        method="POST" if body is not None else "GET",
    )
    with urllib.request.urlopen(req, timeout=timeout) as response:
        return json.load(response)


def validate(data: object) -> bool:
    if not isinstance(data, dict) or set(data) != set(SCHEMA["required"]):
        return False
    return (
        isinstance(data["hook"], str) and bool(data["hook"].strip())
        and isinstance(data["body"], str) and bool(data["body"].strip())
        and data["risk_level"] in ("low", "moderate", "high")
        and isinstance(data["claims"], list)
        and all(isinstance(x, str) for x in data["claims"])
    )


def run_one(model: str, brand: str, request: str, timeout: int) -> dict:
    prompt = (
        "Return JSON matching the requested schema only. No invented factual claims or URLs. "
        f"Brand: {brand}. Task: {request} Keep the body under 60 words, and claims empty."
    )
    started = time.perf_counter()
    try:
        result = request_json("/api/chat", {
            "model": model,
            "messages": [{"role": "user", "content": prompt}],
            "stream": False,
            "format": SCHEMA,
            "options": {"num_predict": 180, "temperature": 0},
            "keep_alive": "0s",
        }, timeout=timeout)
        duration_s = round(time.perf_counter() - started, 3)
        raw = result.get("message", {}).get("content", "")
        try:
            parsed = json.loads(raw)
            valid = validate(parsed)
        except (ValueError, TypeError):
            valid = False
        tokens = result.get("eval_count")
        duration_ns = result.get("eval_duration")
        speed = round(tokens * 1e9 / duration_ns, 2) if isinstance(tokens, (int, float)) and isinstance(duration_ns, (int, float)) and duration_ns > 0 else None
        return {
            "brand": brand, "ok": valid, "total_seconds": duration_s,
            "eval_tokens": tokens, "eval_tokens_per_second": speed,
            "ollama_reported_total_ns": result.get("total_duration"),
            "error": None if valid else "invalid_schema_or_json",
        }
    except (urllib.error.URLError, TimeoutError, ValueError, OSError) as exc:
        return {
            "brand": brand, "ok": False,
            "total_seconds": round(time.perf_counter() - started, 3),
            "error": type(exc).__name__,
        }


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--model", help="Exact installed model ID; no downloading will occur")
    ap.add_argument("--list-models", action="store_true")
    ap.add_argument("--output", type=Path, help="Write summary JSON outside version control")
    ap.add_argument("--timeout", type=int, default=180)
    args = ap.parse_args()
    if args.timeout < 5 or args.timeout > 900:
        ap.error("--timeout must be between 5 and 900 seconds")
    if not args.model and not args.list_models:
        ap.error("use --model or --list-models")
    try:
        models_response = request_json("/api/tags", None, timeout=min(args.timeout, 10))
    except (urllib.error.URLError, TimeoutError, ValueError, OSError) as exc:
        print(json.dumps({"status": "ollama_unreachable", "error_class": type(exc).__name__}, indent=2))
        return 2
    models = sorted({str(m.get("name", "")) for m in models_response.get("models", []) if isinstance(m, dict)})
    if args.list_models:
        print(json.dumps({"installed_models": models}, indent=2))
        return 0
    if args.model not in models:
        print(json.dumps({"status": "model_not_installed", "model": args.model, "installed_models": models}, indent=2))
        return 2
    outcomes = [run_one(args.model, brand, prompt, args.timeout) for brand, prompt in PROMPTS]
    times = [x["total_seconds"] for x in outcomes if x["ok"]]
    speeds = [x["eval_tokens_per_second"] for x in outcomes if x.get("eval_tokens_per_second") is not None]
    report = {
        "tool": "aadi_ollama_cpu_benchmark_v1", "date_utc": datetime.now(timezone.utc).isoformat(),
        "model": args.model, "checks": len(outcomes),
        "schema_passes": sum(1 for x in outcomes if x["ok"]),
        "median_success_latency_seconds": round(statistics.median(times), 3) if times else None,
        "median_eval_tokens_per_second": round(statistics.median(speeds), 2) if speeds else None,
        "outcomes": outcomes,
        "qualification": "This is a quick JSON/latency smoke benchmark; factual, brand and legal claims require independent validation.",
    }
    json_text = json.dumps(report, ensure_ascii=False, indent=2)
    if args.output:
        args.output.write_text(json_text + "\n", encoding="utf-8")
    print(json_text)
    return 0 if report["schema_passes"] == len(outcomes) else 1


if __name__ == "__main__":
    sys.exit(main())
