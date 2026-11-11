#!/usr/bin/env python3
"""Dry-run-first Phase2+ installer for an inspected Laravel app, backing up collisions."""
from __future__ import annotations
import argparse,datetime,json,shutil,sys
from pathlib import Path
SOURCE=Path(__file__).resolve().parents[1]/'overlay'

def main():
    p=argparse.ArgumentParser();p.add_argument('--target',type=Path,required=True)
    p.add_argument('--confirm',action='store_true');args=p.parse_args()
    target=args.target.resolve();manifest=target/'composer.json'
    if not manifest.is_file():p.error('Target must be an inspected Laravel skeleton with composer.json')
    composer=json.loads(manifest.read_text())
    if 'laravel/framework' not in composer.get('require',{}):p.error('Target is not Laravel')
    paths=sorted(x for x in SOURCE.rglob('*') if x.is_file())
    collisions=[str(x.relative_to(SOURCE)) for x in paths if (target/x.relative_to(SOURCE)).exists()]
    print(json.dumps({'overlay_files':len(paths),'overwrites':collisions,'confirmed':args.confirm},indent=2))
    if not args.confirm:return 0
    backup=target/'.aadi-phase2plus-backups'/datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')
    for file in paths:
        rel=file.relative_to(SOURCE);dest=target/rel
        if dest.is_file():
            saved=backup/rel;saved.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(dest,saved)
        dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(file,dest)
    print('Install finished. Backups:',backup)
    return 0
if __name__=='__main__':sys.exit(main())
