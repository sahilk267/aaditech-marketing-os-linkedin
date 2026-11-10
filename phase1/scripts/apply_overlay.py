#!/usr/bin/env python3
"""Copy Phase 1 overlay into a NEW Laravel skeleton, preserving backups.

Refuses to touch a missing/composerless target. On conflicts a --confirm flag
is required, and every overwritten original is backed up with a timestamp.
"""
from __future__ import annotations
import argparse
import datetime
import json
import shutil
from pathlib import Path

SRC = Path(__file__).resolve().parent.parent / "overlay"

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--target", required=True, type=Path)
    parser.add_argument("--confirm", action="store_true", help="Explicitly apply to a freshly generated, inspected Laravel skeleton")
    args = parser.parse_args()
    target = args.target.resolve()
    manifest = target / "composer.json"
    if not manifest.is_file():
        parser.error("Target has no composer.json; create/inspect a fresh Laravel app first")
    contents = json.loads(manifest.read_text())
    if "laravel/framework" not in contents.get("require", {}):
        parser.error("Target is not a Laravel framework application")
    files = sorted(x for x in SRC.rglob("*") if x.is_file())
    conflicts = [x.relative_to(SRC) for x in files if (target / x.relative_to(SRC)).exists()]
    print(f"Target: {target}; files to install: {len(files)}; existing paths: {len(conflicts)}")
    for conflict in conflicts:
        print("  overwrite (backed up):", conflict)
    if not args.confirm:
        print("DRY RUN ONLY. Review conflicts and rerun with --confirm on a new skeleton.")
        return 0
    timestamp = datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    backups = target / ".phase1-backups" / timestamp
    for file in files:
        rel = file.relative_to(SRC); dest = target / rel
        if dest.exists():
            bak = backups / rel; bak.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(dest, bak)
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(file, dest)
    print(f"Applied. Backups of overwritten paths: {backups}")
    return 0

if __name__ == "__main__":
    raise SystemExit(main())
