"""Run: python -m amo_worker.main --once OR --forever; no inbound ports required."""
from __future__ import annotations
import argparse,logging,time
from .client import Client,ApiError
from .jobs import Jobs
from .ai import NoModelAvailable,ModelOutputInvalid
from .research import UnsafeSource

LOG=logging.getLogger('amo_worker')

def work_once(client:Client,runner:Jobs)->bool:
    job=client.claim()
    if not job:return False
    jid=job['id'];client.start(jid)
    try:
        result=runner.execute(job)
        client.complete(jid,result)
        LOG.info('job_id=%s status=succeeded type=%s',jid,job['job_type'])
    except (UnsafeSource,ValueError,ModelOutputInvalid) as ex:
        code=getattr(ex,'args',['JOB_INVALID'])[0]
        if not isinstance(code,str) or not code.isupper():code='JOB_VALIDATION_FAILED'
        client.fail(jid,code[:96],False)
    except (NoModelAvailable,ApiError) as ex:
        client.fail(jid,'PROVIDER_UNAVAILABLE',True)
    except Exception:
        client.fail(jid,'WORKER_UNEXPECTED_ERROR',True)
        LOG.exception('Job failed id=%s',jid)
    return True

def main():
    p=argparse.ArgumentParser();p.add_argument('--once',action='store_true');p.add_argument('--forever',action='store_true')
    args=p.parse_args()
    logging.basicConfig(level=logging.INFO,format='%(asctime)s %(levelname)s %(message)s')
    client=Client();client.register();runner=Jobs(client)
    if args.once:work_once(client,runner);return
    if not args.forever: p.error('Select --once or --forever')
    last_beat=0
    while True:
        try:
            if time.monotonic()-last_beat>60:
                client.heartbeat();last_beat=time.monotonic()
            found=work_once(client,runner)
            if not found:time.sleep(10)
        except Exception:
            LOG.exception('Worker poll error (credentials are never logged)')
            time.sleep(20)

if __name__=='__main__':main()
