"""Free-only OpenRouter routing, CPU Ollama fallback, persistent usage cap."""
from __future__ import annotations
from datetime import datetime,timezone
from decimal import Decimal,InvalidOperation
import json, os, sqlite3, threading, time
from pathlib import Path
import requests

class NoModelAvailable(Exception):pass
class ModelOutputInvalid(Exception):pass

def price_is_zero(row:dict)->bool:
    try:
        pricing=row['pricing']
        return all(Decimal(str(pricing[key]))==0 and not isinstance(pricing[key],bool) for key in ('prompt','completion'))
    except (KeyError,ValueError,TypeError,InvalidOperation):return False

def select_verified_free(registry:list[dict], catalog:list[dict])->str|None:
    pricing={r.get('id'):r for r in catalog if isinstance(r,dict)}
    for model in registry:
        mid=model.get('model_id')
        if isinstance(mid,str) and mid in pricing and price_is_zero(pricing[mid]):return mid
    return None

class DailyCap:
    def __init__(self,path:Path|str,limit:int):
        self.path=str(path);self.limit=max(1,min(limit,100000));self._lock=threading.Lock()
        Path(self.path).parent.mkdir(parents=True,exist_ok=True)
        with sqlite3.connect(self.path) as db:
            db.execute('CREATE TABLE IF NOT EXISTS requests (day TEXT PRIMARY KEY, count INTEGER NOT NULL)')
    def take(self)->bool:
        day=datetime.now(timezone.utc).strftime('%Y-%m-%d')
        with self._lock,sqlite3.connect(self.path) as db:
            db.execute('BEGIN IMMEDIATE')
            count=db.execute('SELECT count FROM requests WHERE day=?',(day,)).fetchone()
            if count and count[0]>=self.limit:return False
            db.execute('INSERT INTO requests(day,count) VALUES(?,1) ON CONFLICT(day) DO UPDATE SET count=count+1',(day,))
            return True

class AI:
    def __init__(self,session=None,cap=None):
        self.http=session or requests.Session()
        self.cap=cap or DailyCap(Path.home()/'.aadi-marketing-os'/'openrouter-quota.sqlite3',int(os.getenv('OPENROUTER_DAILY_HARD_CAP','40')))
        self.last_used=None
    def catalog(self)->list[dict]:
        r=self.http.get('https://openrouter.ai/api/v1/models',timeout=25)
        r.raise_for_status()
        data=r.json().get('data',[])
        if not isinstance(data,list):raise ValueError('OpenRouter catalog invalid')
        return [dict(id=m['id'],pricing=m.get('pricing'),context_length=m.get('context_length')) for m in data if isinstance(m,dict) and isinstance(m.get('id'),str)][:1000]
    def generate(self, messages:list[dict], registry:list[dict],catalog:list[dict])->dict:
        key=os.getenv('OPENROUTER_API_KEY')
        model=select_verified_free(registry,catalog)
        if key and model and self.cap.take():
            try:
                response=self.http.post('https://openrouter.ai/api/v1/chat/completions',
                    json={'model':model,'messages':messages,'temperature':0.3},
                    headers={'Authorization':'Bearer '+key},timeout=60)
                if response.status_code in (401,403):raise NoModelAvailable('OPENROUTER_AUTH')
                if response.status_code==429:raise NoModelAvailable('OPENROUTER_QUOTA')
                response.raise_for_status()
                text=response.json()['choices'][0]['message']['content']
                result=parse_model_json(text)
                self.last_used=('openrouter',model)
                return result
            except (requests.RequestException,KeyError,IndexError,TypeError,ValueError,ModelOutputInvalid,NoModelAvailable):
                # All failures go to local Ollama, never a paid OpenRouter model.
                pass
        return self.local(messages)
    def local(self,messages:list[dict])->dict:
        model=os.getenv('OLLAMA_MODEL')
        base=os.getenv('OLLAMA_BASE_URL','http://127.0.0.1:11434').rstrip('/')
        if not model or base not in ('http://127.0.0.1:11434','http://localhost:11434'):
            raise NoModelAvailable('OLLAMA_UNCONFIGURED')
        prompt='\n'.join(m['role']+': '+m['content'] for m in messages)
        try:
            r=self.http.post(base+'/api/generate',json={'model':model,'prompt':prompt,'stream':False,
                'format':'json','options':{'num_predict':1200,'temperature':0.3}},timeout=300)
            r.raise_for_status()
            out=parse_model_json(r.json()['response'])
        except (requests.RequestException,ValueError,KeyError,ModelOutputInvalid) as ex:
            raise NoModelAvailable('OLLAMA_UNAVAILABLE') from ex
        self.last_used=('ollama',model)
        return out

def parse_model_json(raw)->dict:
    if not isinstance(raw,str) or len(raw)>25000:raise ModelOutputInvalid('INVALID_MODEL_SIZE')
    # Reject markdown fences/partial JSON instead of evaluating or executing any content.
    try:out=json.loads(raw)
    except (ValueError,TypeError) as ex:raise ModelOutputInvalid('MODEL_NOT_JSON') from ex
    if not isinstance(out,dict):raise ModelOutputInvalid('MODEL_NOT_OBJECT')
    return out
