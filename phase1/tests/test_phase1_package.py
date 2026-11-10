from pathlib import Path
import ast
import unittest

ROOT = Path(__file__).resolve().parents[1]
OVERLAY = ROOT / "overlay"

class OverlayTests(unittest.TestCase):
    def test_phase1_files_present(self):
        required = [
            "bootstrap/app.php", "routes/api.php", "routes/web.php",
            "app/Policies/BrandPolicy.php", "app/Models/Brand.php", "app/Models/User.php",
            "app/Http/Middleware/AuthenticateWorker.php", "database/seeders/BrandSeeder.php",
            "tests/Feature/Phase1/BrandAccessTest.php", "tests/Feature/Phase1/WorkerAccessTest.php",
        ]
        self.assertTrue(all((OVERLAY / f).is_file() for f in required))
    def test_brand_profile_seeds(self):
        text=(OVERLAY / "database/seeders/BrandSeeder.php").read_text()
        for slug in ("aadi-tech-solutions", "overseas-job-placement", "aadipro", "toolboxgalaxy"):
            self.assertIn(slug,text)
        self.assertIn("'verified_catalog'=>[]",text)
        self.assertIn("'requires_official_evidence'=>true",text)
    def test_auth_and_brand_isolation_guards(self):
        self.assertIn("Gate::authorize('view', $brand)", (OVERLAY / "app/Http/Controllers/BrandController.php").read_text())
        self.assertIn("visibleTo($request->user())", (OVERLAY / "app/Http/Controllers/DashboardController.php").read_text())
        self.assertIn("'is_active'=>true", (OVERLAY / "app/Http/Controllers/AuthController.php").read_text())
    def test_worker_security_features(self):
        worker=(OVERLAY / "app/Http/Middleware/AuthenticateWorker.php").read_text()
        for text in ("hash_equals", "X-Worker-Timestamp", "X-Worker-Nonce", "worker_request_nonces", "revoked_at", "isSecure"):
            self.assertIn(text,worker)
    def test_worker_client_parses(self):
        ast.parse((ROOT / "worker/heartbeat_client.py").read_text())
    def test_copy_script_parses(self):
        ast.parse((ROOT / "scripts/apply_overlay.py").read_text())

if __name__=="__main__": unittest.main()
