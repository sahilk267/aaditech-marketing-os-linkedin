import os,sys,unittest,tempfile
from pathlib import Path
from unittest.mock import patch
sys.path.insert(0,str(Path(__file__).resolve().parents[1]))
from amo_worker.ai import DailyCap,price_is_zero,select_verified_free,parse_model_json,ModelOutputInvalid
from amo_worker.research import validate_url,UnsafeSource,resolve_public_ip
from amo_worker.media import render_svg
from amo_worker.jobs import validate_draft

class WorkerSafetyTests(unittest.TestCase):
    def test_free_price_fail_closed(self):
        self.assertTrue(price_is_zero({'pricing':{'prompt':'0','completion':'0.0000'}}))
        for m in ({'pricing':{'prompt':'0','completion':'0.1'}},{'pricing':{'prompt':'0'}},{'pricing':{'prompt':'NaN','completion':'0'}},{'pricing':{}}):
            self.assertFalse(price_is_zero(m))
    def test_no_paid_fallback(self):
        reg=[{'model_id':'paid'},{'model_id':'free'}]
        cat=[{'id':'paid','pricing':{'prompt':'0.1','completion':'0'}},{'id':'free','pricing':{'prompt':'0','completion':'0'}}]
        self.assertEqual(select_verified_free(reg,cat),'free')
        self.assertIsNone(select_verified_free(reg,cat[:1]))
    def test_cap_durable(self):
        with tempfile.TemporaryDirectory() as d:
            path=Path(d)/'quota.sqlite3'
            self.assertTrue(DailyCap(path,2).take())
            self.assertTrue(DailyCap(path,2).take())
            self.assertFalse(DailyCap(path,2).take())
    def test_ssrf_schemes_hosts_and_dns(self):
        for url in ('http://example.com','https://localhost/admin','https://127.0.0.1',
                    'https://internal.test/a','https://user:pass@example.com/a','https://example.com:8080'):
            with self.subTest(url=url):
                with self.assertRaises(UnsafeSource):validate_url(url)
        with patch('amo_worker.research.socket.getaddrinfo',return_value=[(2,1,6,'',('127.0.0.1',443))]):
            with self.assertRaises(UnsafeSource):resolve_public_ip('example.com')
    def test_svg_escaped(self):
        s=render_svg('<script>alert(1)</script>','Hello <script> world','body')
        self.assertNotIn('<script>',s)
        self.assertIn('&lt;script&gt;',s)
    def test_json_is_not_python(self):
        with self.assertRaises(ModelOutputInvalid):parse_model_json('__import__("os").system("id")')
    def test_metadata_consistency(self):
        draft={'title':'Hi','topic':'Help','body':'Example','content_type':'text','language':'en'}
        wanted={'language':'en','content_type':'text','platform':'linkedin','source_ids':[],'brand_slug':'toolboxgalaxy'}
        self.assertIn('linkedin',validate_draft(draft,wanted)['platform_variants'])
        wanted['language']='fr'
        with self.assertRaises(ValueError):validate_draft(draft,wanted)

if __name__=='__main__':unittest.main()

class AIProviderFallbackTests(unittest.TestCase):
    def test_no_paid_openrouter_request_when_catalog_paid(self):
        from amo_worker.ai import AI,NoModelAvailable
        from unittest.mock import Mock
        session=Mock()
        ai=AI(session=session,cap=Mock())
        with patch.dict(os.environ,{'OPENROUTER_API_KEY':'fake','OLLAMA_MODEL':''}):
            with self.assertRaises(NoModelAvailable):
                ai.generate([{'role':'user','content':'hello'}],[{'model_id':'paid'}],[{'id':'paid','pricing':{'prompt':'0.1','completion':'0'}}])
        session.post.assert_not_called()

    def test_paid_price_underflow_is_not_accepted(self):
        self.assertFalse(price_is_zero({'pricing':{'prompt':'1e-999','completion':'0'}}))
