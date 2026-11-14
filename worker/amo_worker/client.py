"""Scoped worker client; the Laravel API remains the source of truth for jobs."""
from __future__ import annotations
import os, secrets, time
from urllib.parse import urlparse
import requests

class ApiError(Exception):
    def __init__(self, status:int, code:str):
        self.status=status;self.code=code
        super().__init__(f'WORKER_HTTP_{status}_{code}')

REQUESTED_LEASE_SECONDS=900
WORKER_VERSION='0.3.0'

class Client:
    def __init__(self, url:str|None=None, token:str|None=None, session=None):
        self.url=(url or os.environ['AMO_BASE_URL']).rstrip('/')
        if urlparse(self.url).scheme!='https':
            raise ValueError('Worker API requires HTTPS')
        self.token=token or os.environ['AMO_WORKER_TOKEN']
        if not self.token.startswith('amo_'):
            raise ValueError('Worker token format invalid')
        self.session=session or requests.Session()

    def request(self, method:str, path:str, data:dict|None=None):
        if not path.startswith('/api/v1/worker/'):
            raise ValueError('Unapproved worker endpoint')
        headers={
            'Authorization':'Bearer '+self.token,
            'X-Worker-Timestamp':str(int(time.time())),
            'X-Worker-Nonce':secrets.token_urlsafe(24),
            'Accept':'application/json',
        }
        # A new nonce is used on every invocation, including retries.
        try:
            response=self.session.request(method,self.url+path,**({'params':data} if method=='GET' else {'json':data}),headers=headers,timeout=35)
        except requests.RequestException as ex:
            raise ApiError(0,'network_error') from ex
        if not 200<=response.status_code<300:
            raise ApiError(response.status_code,'request_rejected')
        try:return response.json()
        except ValueError as ex:raise ApiError(response.status_code,'bad_json') from ex

    def register(self):
        return self.request('POST','/api/v1/worker/register',{
            'node_name':os.getenv('AMO_NODE_NAME','desktop-1'),
            'platform':'windows','worker_version':WORKER_VERSION,
            'capabilities':[x.strip() for x in os.getenv('AMO_CAPABILITIES','heartbeat,research.fetch,content.generate,media.render,publish.post,ai.catalog_sync').split(',') if x.strip()],
        })
    def heartbeat(self):return self.request('POST','/api/v1/worker/heartbeat',{'worker_version':WORKER_VERSION})
    def tick(self):return self.request('POST','/api/v1/worker/tick')
    def claim(self):return self.request('POST','/api/v1/worker/jobs/claim',{'lease_seconds':REQUESTED_LEASE_SECONDS}).get('job')
    def start(self,jid,attempt):return self.request('POST',f'/api/v1/worker/jobs/{jid}/start',{'attempt_count':attempt})
    def renew(self,jid,attempt):return self.request('POST',f'/api/v1/worker/jobs/{jid}/renew',{'lease_seconds':REQUESTED_LEASE_SECONDS,'attempt_count':attempt})
    def complete(self,jid,attempt,result):return self.request('POST',f'/api/v1/worker/jobs/{jid}/complete',{'attempt_count':attempt,'result':result})
    def fail(self,jid,attempt,code,retryable):return self.request('POST',f'/api/v1/worker/jobs/{jid}/fail',{'attempt_count':attempt,'error_code':code,'retryable':retryable})
    def context(self,jid,attempt):return self.request('GET',f'/api/v1/worker/jobs/{jid}/context',{'attempt_count':attempt})
    def models(self):return self.request('GET','/api/v1/worker/ai/models').get('models',[])
    def usage(self,provider,model,task,status):
        return self.request('POST','/api/v1/worker/ai/usage',{'provider':provider,'model_id':model,'task_type':task,'status':status})
    def catalog(self,models):return self.request('POST','/api/v1/worker/ai/catalog',{'models':models})
    def publish(self,jid,attempt):return self.request('POST',f'/api/v1/worker/jobs/{jid}/publish',{'attempt_count':attempt})
