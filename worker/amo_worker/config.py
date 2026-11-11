"""Strict, non-executable local worker environment file support.

The worker never receives account OAuth tokens or private customer credentials.
The .env.worker file is a local owner-provided secret store and must not enter Git.
"""
from __future__ import annotations
import json, os, re, stat
from pathlib import Path

ALLOWED={
    'AMO_BASE_URL','AMO_WORKER_TOKEN','AMO_NODE_NAME','AMO_CAPABILITIES',
    'OPENROUTER_API_KEY','OPENROUTER_DAILY_HARD_CAP',
    'OLLAMA_BASE_URL','OLLAMA_MODEL',
}

def load_worker_env(path:Path|str|None=None)->None:
    file=Path(path) if path is not None else Path(__file__).resolve().parents[1]/'.env.worker'
    if not file.exists():return
    if not file.is_file() or file.stat().st_size>16384:
        raise ValueError('WORKER_ENV_INVALID')
    if os.name=='posix' and file.stat().st_mode & (stat.S_IRWXG|stat.S_IRWXO):
        raise ValueError('WORKER_ENV_PERMISSIONS')
    for line in file.read_text(encoding='utf-8').splitlines():
        line=line.strip()
        if not line or line.startswith('#'):continue
        match=re.fullmatch(r'([A-Z][A-Z0-9_]*)=(.*)',line)
        if not match or match.group(1) not in ALLOWED:
            raise ValueError('WORKER_ENV_KEY_NOT_ALLOWED')
        name,value=match.groups()
        if value.startswith('"'):
            try:value=json.loads(value)
            except (ValueError,TypeError) as e:raise ValueError('WORKER_ENV_VALUE_INVALID') from e
        if not isinstance(value,str) or any(c in value for c in ('\r','\n','\0')):
            raise ValueError('WORKER_ENV_VALUE_INVALID')
        # Inherited service environment always takes precedence.
        os.environ.setdefault(name,value)
