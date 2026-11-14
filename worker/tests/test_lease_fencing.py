"""Wire protocol checks: lease attempt count must survive HTTP transport."""
import unittest
from unittest.mock import Mock
from amo_worker.client import Client
from amo_worker.jobs import Jobs

class WorkerLeaseWireTests(unittest.TestCase):
    def test_worker_api_request_carries_attempt_on_mutations_and_reads(self):
        session=Mock()
        response=Mock(status_code=200)
        response.json.return_value={'job':{'status':'running'}}
        session.request.return_value=response
        client=Client(url='https://example.invalid',token='amo_1.'+'a'*64,session=session)
        client.start(19,2)
        client.renew(19,2)
        client.complete(19,2,{'done':True})
        client.fail(19,2,'WORKER_FAILED',True)
        client.context(19,2)
        client.publish(19,2)
        observed=session.request.call_args_list
        self.assertEqual(6,len(observed))
        for call in observed:
            kwargs=call.kwargs
            payload=kwargs.get('json',kwargs.get('params'))
            self.assertEqual(2,payload['attempt_count'])
        self.assertNotIn('json',observed[4].kwargs)  # GET uses URL query

    def test_job_runner_passes_attempt_to_publish(self):
        client=Mock()
        client.publish.return_value={'status':'manual_ready'}
        runner=Jobs(client)
        result=runner.execute({'id':10,'attempt_count':4,'job_type':'publish.post','payload':{}})
        client.publish.assert_called_once_with(10,4)
        self.assertEqual('manual_ready',result['status'])

if __name__=='__main__': unittest.main()
