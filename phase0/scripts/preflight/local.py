#!/usr/bin/env python3
"""Non-sensitive read-only diagnostics for the machine on which this is run."""
from __future__ import annotations

import argparse
import json
import platform
import shutil
import subprocess
from datetime import datetime, timezone
from pathlib import Path

TOOLS = ("php", "composer", "mysql", "python", "git", "ollama", "ffmpeg", "node")


def version_for(name: str) -> str | None:
    exe = shutil.which(name)
    if not exe:
        return None
    args = {
        "php": ["-v"], "composer": ["--version"], "mysql": ["--version"],
        "python": ["--version"], "git": ["--version"], "ollama": ["--version"],
        "ffmpeg": ["-version"], "node": ["--version"],
    }[name]
    try:
        cp = subprocess.run([exe, *args], capture_output=True, text=True, timeout=5, check=False)
        # Do not print full environment or CLI paths; extract only first line.
        return (cp.stdout or cp.stderr).splitlines()[0][:180]
    except (OSError, subprocess.TimeoutExpired, IndexError):
        return "detected_but_version_unknown"


def collect() -> dict:
    return {
        "tool": "aadi_local_preflight_v1",
        "time_utc": datetime.now(timezone.utc).isoformat(),
        "machine_note": "Describes only the computer that ran this command; not Hostinger or another desktop.",
        "platform": platform.platform(),
        "architecture": platform.machine(),
        "python_version": platform.python_version(),
        "commands": {name: {"present": bool(shutil.which(name)), "version": version_for(name)} for name in TOOLS},
        "disk_free_bytes_working_dir": shutil.disk_usage(Path.cwd()).free,
    }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--output", type=Path, help="Optional diagnostic JSON file path")
    opts = parser.parse_args()
    data = json.dumps(collect(), indent=2, ensure_ascii=False)
    if opts.output:
        opts.output.write_text(data + "\n", encoding="utf-8")
    else:
        print(data)


if __name__ == "__main__":
    main()
