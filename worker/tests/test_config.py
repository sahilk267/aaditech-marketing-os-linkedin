import os,stat,tempfile,unittest
from pathlib import Path
from unittest.mock import patch
from amo_worker.config import load_worker_env

class WorkerConfigTests(unittest.TestCase):
    def test_safe_parse_and_inherited_override(self):
        with tempfile.TemporaryDirectory() as d:
            f=Path(d)/'.env.worker'
            f.write_text('AMO_BASE_URL="https://example.org"\nAMO_NODE_NAME=local-1\n')
            f.chmod(0o600)
            with patch.dict(os.environ,{'AMO_NODE_NAME':'from-service'},clear=True):
                load_worker_env(f)
                self.assertEqual(os.environ['AMO_NODE_NAME'],'from-service')
                self.assertEqual(os.environ['AMO_BASE_URL'],'https://example.org')

    def test_reject_unknown_key_and_world_readable_secret(self):
        with tempfile.TemporaryDirectory() as d:
            f=Path(d)/'.env.worker';f.write_text('EVIL_KEY=value\n');f.chmod(0o600)
            with self.assertRaises(ValueError):load_worker_env(f)
            if os.name=='posix':
                f.write_text('AMO_WORKER_TOKEN=amo_1.'+'a'*64+'\n');f.chmod(0o644)
                with self.assertRaises(ValueError):load_worker_env(f)
