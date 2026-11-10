#!/usr/bin/env python3
"""Phase 1 registered desktop heartbeat prototype. Python 3.11+, stdlib only.

Environment: AADI_API_BASE_URL (HTTPS), AADI_WORKER_TOKEN (secret),
AADI_WORKER_NAME, AADI_WORKER_PLATFORM, AADI_WORKER_VERSION.
No publishing, AI requests, or job claiming is implemented in this prototype.
"""
from __future__ import annotations

import json
import os
import platform
import secrets
import sys
import time
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


def require_env() -> tuple[str, str]:
    base = os.environ.get("AADI_API_BASE_URL", "").rstrip("/")
    token = os.environ.get("AADI_WORKER_TOKEN", "")
    if not base.startswith("https://") or not token.startswith("amo_"):
        raise ValueError("Set an HTTPS AADI_API_BASE_URL and valid worker token in environment variables")
    return base, token


def request_json(base: str, token: str, endpoint: str, data: dict) -> dict:
    timestamp = str(int(time.time()))
    nonce = secrets.token_urlsafe(24)
    body = json.dumps(data, separators=(",", ":")).encode("utf-8")
    request = Request(
        base + "/api/v1/worker/" + endpoint,
        data=body,
        headers={
            "Authorization": "Bearer " + token,
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-Worker-Timestamp": timestamp,
            "X-Worker-Nonce": nonce,
        },
        method="POST",
    )
    with urlopen(request, timeout=15) as response:
        return json.load(response)


def main() -> int:
    try:
        base, token = require_env()
    except ValueError as exc:
        print(str(exc), file=sys.stderr)
        return 2
    worker_version = os.environ.get("AADI_WORKER_VERSION", "0.1.0")
    worker_name = os.environ.get("AADI_WORKER_NAME", platform.node() or "aadi-desktop")
    worker_platform = os.environ.get("AADI_WORKER_PLATFORM", platform.system().lower())
    payload = {
        "node_name": worker_name,
        "platform": worker_platform,
        "worker_version": worker_version,
        "capabilities": ["heartbeat"],
    }
    try:
        result = request_json(base, token, "register", payload)
        print(f"Worker registered; ID={result.get('worker_id')}")
    except (HTTPError, URLError, OSError, ValueError) as exc:
        # No request or token contents should enter the diagnostic stream.
        print(f"Registration failed ({type(exc).__name__}); check server and credential", file=sys.stderr)
        return 1

    # Single heartbeat by default; set AADI_WORKER_CONTINUOUS=1 to enable loop after deployment testing.
    continuous = os.environ.get("AADI_WORKER_CONTINUOUS") == "1"
    while True:
        try:
            request_json(base, token, "heartbeat", {"worker_version": worker_version})
            print("Heartbeat acknowledged")
        except (HTTPError, URLError, OSError, ValueError) as exc:
            print(f"Heartbeat failed ({type(exc).__name__})", file=sys.stderr)
            if not continuous:
                return 1
        if not continuous:
            break
        time.sleep(60)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
