import base64,io,unittest
from unittest.mock import Mock
from amo_worker.media import media_result
from amo_worker.main import work_once,KEEPALIVE_SECONDS
from amo_worker.client import REQUESTED_LEASE_SECONDS,ApiError

class WorkerRuntimeTests(unittest.TestCase):
    def test_lease_keepalive_margin(self):
        self.assertLess(KEEPALIVE_SECONDS*3,REQUESTED_LEASE_SECONDS)

    def test_creative_contains_svg_png_and_carousel(self):
        r=media_result('Brand','Important update','A short editorial note')
        self.assertTrue(base64.b64decode(r['svg_base64']).startswith(b'<svg'))
        self.assertTrue(base64.b64decode(r['png_base64']).startswith(b'\x89PNG\r\n\x1a\n'))
        self.assertEqual(3,len(r['carousel_png_base64']))
    def test_job_completion_renews_lease_first(self):
        client=Mock();client.claim.return_value={'id':7,'attempt_count':2,'job_type':'analytics.collect','payload':{}}
        runner=Mock();runner.execute.return_value={'status':'manual_data_only'}
        self.assertTrue(work_once(client,runner))
        names=[x[0] for x in client.method_calls]
        self.assertLess(names.index('renew'),names.index('complete'))

    def test_revoked_source_does_not_retry_permanent_server_denial(self):
        client=Mock()
        client.claim.return_value={'id':7,'attempt_count':2,'job_type':'research.fetch','payload':{}}
        runner=Mock()
        runner.execute.side_effect=ApiError(403,'request_rejected')
        self.assertTrue(work_once(client,runner))
        client.fail.assert_called_once_with(7,2,'WORKER_POLICY_REJECTED',False)

    def test_stale_generation_never_attempts_to_fail_new_lease(self):
        client=Mock()
        client.claim.return_value={'id':7,'attempt_count':2,'job_type':'research.fetch','payload':{}}
        runner=Mock()
        runner.execute.side_effect=ApiError(409,'request_rejected')
        self.assertTrue(work_once(client,runner))
        client.fail.assert_not_called()

if __name__=='__main__':unittest.main()
