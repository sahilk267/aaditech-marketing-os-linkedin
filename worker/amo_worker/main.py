"""Run: python -m amo_worker.main --once OR --forever; no inbound ports required."""
from __future__ import annotations
import argparse,logging,time,threading
from .client import Client,ApiError,REQUESTED_LEASE_SECONDS
from .jobs import Jobs
from .ai import NoModelAvailable,ModelOutputInvalid
from .research import UnsafeSource
from .config import load_worker_env

LOG=logging.getLogger('amo_worker')
KEEPALIVE_SECONDS=60  # Renew and refresh node heartbeat well before the worker's 900s lease.
assert KEEPALIVE_SECONDS * 3 < REQUESTED_LEASE_SECONDS

def work_once(client:Client,runner:Jobs)->bool:
    job=client.claim()
    if not job:return False
    jid=job['id'];attempt=job['attempt_count'];client.start(jid,attempt)
    stop=threading.Event()
    # Long-running CPU inference may outlive the lease. Renew on a separate
    # HTTP session to avoid sharing a requests.Session across threads.
    def keepalive():
        while not stop.wait(KEEPALIVE_SECONDS):
            try:
                keepalive_client=Client(url=client.url,token=client.token)
                keepalive_client.renew(jid,attempt)
                # A long CPU-only render must not mark a healthy worker offline.
                keepalive_client.heartbeat()
            except Exception:
                LOG.warning('Lease/heartbeat renewal failed job_id=%s; completion will be checked by server',jid)
    renewer=threading.Thread(target=keepalive,name=f'job-lease-{jid}',daemon=True)
    renewer.start()
    try:
        result=runner.execute(job)
        client.renew(jid,attempt)  # server is authoritative about lease generation
        client.complete(jid,attempt,result)
        LOG.info('job_id=%s status=succeeded type=%s',jid,job['job_type'])
    except (UnsafeSource,ValueError,ModelOutputInvalid) as ex:
        code=getattr(ex,'args',['JOB_INVALID'])[0]
        if not isinstance(code,str) or not code.isupper():code='JOB_VALIDATION_FAILED'
        client.fail(jid,attempt,code[:96],False)
    except NoModelAvailable:
        client.fail(jid,attempt,'PROVIDER_UNAVAILABLE',True)
    except ApiError as ex:
        if ex.status == 409:
            # Another generation owns the lease; no stale failure mutation.
            LOG.warning('Lease generation no longer active job_id=%s',jid)
        else:
            permanent = ex.status in (403,404,422,426)
            client.fail(jid,attempt,
                        'WORKER_POLICY_REJECTED' if permanent else 'PROVIDER_UNAVAILABLE',
                        not permanent)
    except Exception:
        client.fail(jid,attempt,'WORKER_UNEXPECTED_ERROR',True)
        LOG.exception('Job failed id=%s',jid)
    finally:
        stop.set()
        renewer.join(timeout=1)
    return True

def main():
    p=argparse.ArgumentParser();p.add_argument('--once',action='store_true');p.add_argument('--forever',action='store_true')
    args=p.parse_args()
    load_worker_env()
    logging.basicConfig(level=logging.INFO,format='%(asctime)s %(levelname)s %(message)s')
    client=Client();client.register();runner=Jobs(client)
    if args.once:work_once(client,runner);return
    if not args.forever: p.error('Select --once or --forever')
    last_beat=0
    while True:
        try:
            if time.monotonic()-last_beat>60:
                client.heartbeat();last_beat=time.monotonic()
                try:client.tick()  # deterministic scheduling without hosting cron
                except ApiError:LOG.warning('Scheduler tick unavailable; configure host cron as fallback')
            found=work_once(client,runner)
            if not found:time.sleep(10)
        except Exception:
            LOG.exception('Worker poll error (credentials are never logged)')
            time.sleep(20)

if __name__=='__main__':main()
