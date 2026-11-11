import base64,io,unittest
from unittest.mock import Mock
from amo_worker.media import media_result
from amo_worker.main import work_once

class WorkerRuntimeTests(unittest.TestCase):
    def test_creative_contains_svg_png_and_carousel(self):
        r=media_result('Brand','Important update','A short editorial note')
        self.assertTrue(base64.b64decode(r['svg_base64']).startswith(b'<svg'))
        self.assertTrue(base64.b64decode(r['png_base64']).startswith(b'\x89PNG\r\n\x1a\n'))
        self.assertEqual(3,len(r['carousel_png_base64']))
    def test_job_completion_renews_lease_first(self):
        client=Mock();client.claim.return_value={'id':7,'job_type':'analytics.collect','payload':{}}
        runner=Mock();runner.execute.return_value={'status':'manual_data_only'}
        self.assertTrue(work_once(client,runner))
        names=[x[0] for x in client.method_calls]
        self.assertLess(names.index('renew'),names.index('complete'))

if __name__=='__main__':unittest.main()
