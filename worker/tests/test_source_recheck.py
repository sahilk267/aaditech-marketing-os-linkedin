"""The desktop worker must validate source approval immediately before fetching."""
import unittest
from unittest.mock import Mock,patch
from amo_worker.jobs import Jobs

class ResearchClaimAuthorizationTests(unittest.TestCase):
    def test_research_rechecks_server_approval_before_http_fetch(self):
        client=Mock()
        runner=Jobs(client,ai=Mock())
        with patch('amo_worker.jobs.fetch_public_notes',return_value={'summary':'sample'}) as fetch:
            value=runner.execute({
                'id':22,'attempt_count':3,'job_type':'research.fetch',
                'payload':{'url':'https://docs.example.org/example'},
            })
        client.context.assert_called_once_with(22,3)
        fetch.assert_called_once_with('https://docs.example.org/example')
        self.assertEqual('sample',value['summary'])

    def test_denied_context_never_calls_fetcher(self):
        client=Mock()
        client.context.side_effect=RuntimeError('SOURCE_REVOKED')
        runner=Jobs(client,ai=Mock())
        with patch('amo_worker.jobs.fetch_public_notes') as fetch:
            with self.assertRaises(RuntimeError):
                runner.execute({'id':22,'attempt_count':3,'job_type':'research.fetch',
                               'payload':{'url':'https://docs.example.org/example'}})
        fetch.assert_not_called()

if __name__=='__main__':unittest.main()
