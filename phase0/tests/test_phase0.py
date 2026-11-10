"""Phase 0 static contract checks; not a substitute for Laravel integration tests."""
from pathlib import Path
import importlib.util
import json
import subprocess
import sys
import unittest

ROOT = Path(__file__).resolve().parents[1]


class Phase0ContractTests(unittest.TestCase):
    def test_required_docs_exist(self):
        for name in ("SRS", "ARCHITECTURE", "DATABASE", "SECURITY", "API_CONTRACTS", "ROADMAP", "RUNBOOK", "PHASE0_PREFLIGHT_REPORT", "PHASE1_IMPLEMENTATION_PLAN"):
            with self.subTest(name=name):
                self.assertTrue((ROOT / "docs" / f"{name}.md").is_file())

    def test_original_srs_maintained(self):
        srs = (ROOT / "docs" / "SRS.md").read_text(encoding="utf-8")
        self.assertIn("# Aadi Marketing OS", srs)
        for brand in ("Aadi Tech Solutions", "Overseas Job Placement", "AadiPro", "ToolboxGalaxy"):
            self.assertIn(brand, srs)
        self.assertIn("no n8n", srs.lower())

    def test_gitignore_excludes_sensitive_artifacts(self):
        ignore = (ROOT / ".gitignore").read_text(encoding="utf-8")
        self.assertIn(".env\n", ignore)
        self.assertIn("/preflight-results/", ignore)
        self.assertIn("*.log", ignore)

    def test_benchmark_schema_validator(self):
        path = ROOT / "worker" / "benchmark_ollama.py"
        spec = importlib.util.spec_from_file_location("benchmark_ollama", path)
        module = importlib.util.module_from_spec(spec)
        assert spec and spec.loader
        spec.loader.exec_module(module)
        self.assertTrue(module.validate({"hook": "H", "body": "B", "risk_level": "low", "claims": []}))
        self.assertFalse(module.validate({"hook": "H", "body": "B", "risk_level": "verified", "claims": []}))
        self.assertFalse(module.validate({"hook": "", "body": "B", "risk_level": "low", "claims": []}))
        self.assertEqual(module.BASE, "http://127.0.0.1:11434")

    def test_hostinger_script_syntax_when_php_available(self):
        import shutil
        if not shutil.which("php"):
            self.skipTest("PHP not available on this test host")
        cp = subprocess.run(["php", "-l", str(ROOT / "scripts" / "preflight" / "hostinger.php")], capture_output=True, text=True, timeout=8)
        self.assertEqual(cp.returncode, 0, cp.stdout + cp.stderr)

    def test_hostinger_script_outputs_json_when_php_available(self):
        import shutil
        if not shutil.which("php"):
            self.skipTest("PHP not available on this test host")
        cp = subprocess.run(["php", str(ROOT / "scripts" / "preflight" / "hostinger.php")], capture_output=True, text=True, timeout=8)
        self.assertEqual(cp.returncode, 0, cp.stderr)
        data = json.loads(cp.stdout)
        self.assertIn("required_extensions", data)
        self.assertIn("db", data)
        self.assertEqual(data["cron"]["status"], "manual_hpanel_check_required")


if __name__ == "__main__":
    unittest.main()
