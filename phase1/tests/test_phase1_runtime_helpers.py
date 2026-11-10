"""Test stdlib helper scripts without a Laravel runtime or internet calls."""
import importlib.util
import io
import json
import os
import subprocess
import sys
import tempfile
from pathlib import Path
from unittest import TestCase
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[1]

def load_module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)
    return module

CLIENT = load_module("heartbeat_client", ROOT/"worker/heartbeat_client.py")

class HelperTests(TestCase):
    def test_require_env_rejects_plain_http(self):
        with patch.dict(os.environ,{"AADI_API_BASE_URL":"http://localhost:8000", "AADI_WORKER_TOKEN":"amo_1."+"a"*64}):
            with self.assertRaisesRegex(ValueError,"HTTPS"):
                CLIENT.require_env()

    def test_header_builder_emits_fresh_nonce_and_correct_path(self):
        seen = []
        class FakeResponse:
            def __enter__(self): return self
            def __exit__(self,*args): return False
            def read(self): return b'{"status":"online"}'
        def fake_open(request, timeout):
            seen.append(request)
            self.assertEqual(timeout,15)
            return FakeResponse()
        with patch.object(CLIENT, "urlopen", fake_open):
            result = CLIENT.request_json("https://example.com", "amo_1."+"a"*64, "heartbeat", {"worker_version":"0.1.0"})
            CLIENT.request_json("https://example.com", "amo_1."+"a"*64, "heartbeat", {})
        self.assertEqual(result["status"],"online")
        self.assertEqual(seen[0].full_url,"https://example.com/api/v1/worker/heartbeat")
        self.assertEqual(seen[0].get_method(),"POST")
        self.assertEqual(seen[0].headers['Authorization'],"Bearer amo_1."+"a"*64)
        self.assertNotEqual(seen[0].headers['X-worker-nonce'],seen[1].headers['X-worker-nonce'])

    def test_overlay_dry_run_preserves_existing_files(self):
        with tempfile.TemporaryDirectory() as td:
            repo=Path(td)
            (repo/"composer.json").write_text(json.dumps({"require":{"laravel/framework":"^13.0"}}))
            (repo/"bootstrap").mkdir()
            (repo/"bootstrap/app.php").write_text("ORIGINAL CONTENT")
            result = subprocess.run([sys.executable,str(ROOT/"scripts/apply_overlay.py"),"--target",str(repo)],capture_output=True,text=True)
            self.assertEqual(result.returncode,0,result.stderr)
            self.assertIn('DRY RUN ONLY',result.stdout)
            self.assertEqual((repo/"bootstrap/app.php").read_text(),"ORIGINAL CONTENT")

    def test_overlay_confirm_creates_backup(self):
        with tempfile.TemporaryDirectory() as td:
            repo=Path(td)
            (repo/"composer.json").write_text(json.dumps({"require":{"laravel/framework":"^13.0"}}))
            (repo/"bootstrap").mkdir()
            (repo/"bootstrap/app.php").write_text("ORIGINAL CONTENT")
            result = subprocess.run([sys.executable,str(ROOT/"scripts/apply_overlay.py"),"--target",str(repo),"--confirm"],capture_output=True,text=True)
            self.assertEqual(result.returncode,0,result.stderr)
            self.assertTrue((repo/"app/Models/Brand.php").exists())
            self.assertNotEqual((repo/"bootstrap/app.php").read_text(),"ORIGINAL CONTENT")
            backups=list((repo/".phase1-backups").glob('*/bootstrap/app.php'))
            self.assertEqual(len(backups),1)
            self.assertEqual(backups[0].read_text(),"ORIGINAL CONTENT")
