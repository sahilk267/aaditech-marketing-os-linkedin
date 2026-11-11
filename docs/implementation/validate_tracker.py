#!/usr/bin/env python3
"""Integrity checks for Aadi Marketing OS implementation governance files."""
from __future__ import annotations
import csv,sys,collections
from pathlib import Path

def data(folder,name):
 with (folder/name).open(encoding='utf-8',newline='') as f: return list(csv.DictReader(f))
def fail(message):raise SystemExit('FAIL: '+message)
def ids(items,key,label):
 values=[x[key] for x in items]
 if any(not x for x in values):fail(label+' has blank '+key)
 d=[x for x,v in collections.Counter(values).items() if v!=1]
 if d:fail(label+' duplicate '+key+': '+','.join(d))
 return set(values)
def check(root):
 tasks=data(root,'TASK_TRACKER.csv');gates=data(root,'GATE_TRACKER.csv');mods=data(root,'MODULE_REGISTRY.csv')
 reqs=data(root,'REQUIREMENT_TRACEABILITY.csv');tests=data(root,'E2E_TEST_MATRIX.csv');conf=data(root,'CONFLICT_REGISTER.csv')
 dec=data(root,'DECISION_REGISTER.csv');plat=data(root,'PLATFORM_MATRIX.csv')
 ts=ids(tasks,'task_id','tasks');gs=ids(gates,'gate_id','gates');ms=ids(mods,'module_id','modules')
 rs=ids(reqs,'requirement_id','requirements');xs=ids(tests,'test_id','tests');ids(conf,'id','conflicts');ids(dec,'id','decisions');ids(plat,'platform_capability','platform matrix')
 if len(mods)!=19:fail(f'Expected 19 SRS components, got {len(mods)}')
 if len(tasks)<100 or len(reqs)<40 or len(tests)<40:fail('Baseline coverage unexpectedly shrunk; reviewed scope change required')
 titles=[(x['gate'],x['module_id'],x['title'].casefold()) for x in tasks]
 if len(titles)!=len(set(titles)):fail('duplicate task title in same gate/module')
 statuses={'Not Started','In Progress','Blocked','In Review','Verified','Deferred'}
 gsmap={x['gate_id']:x for x in gates}
 edges={g['gate_id']:list(filter(None,g['prerequisites'].split(';'))) for g in gates}
 for gate,dep in edges.items():
  for d in dep:
   if d not in gs:fail(gate+' missing gate prerequisite '+d)
 seen=set();active=set()
 def visit(n):
  if n in active:fail('cyclic gate dependencies '+n)
  if n in seen:return
  active.add(n)
  for d in edges[n]:visit(d)
  active.remove(n);seen.add(n)
 for g in gs:visit(g)
 for task in tasks:
  t=task['task_id']
  if task['gate'] not in gs:fail(t+' missing gate')
  if task['module_id'] not in ms:fail(t+' missing module')
  if task['status'] not in statuses:fail(t+' invalid status')
  if not task['title'] or not task['acceptance'] or not task['priority'] or not task['test_ref']:fail(t+' incomplete acceptance or priority')
  if task['status']=='Verified' and not all(task.get(x,'') for x in ['evidence','reviewer']):fail(t+' verified without evidence/reviewer')
  if task['status']=='Verified' and any(x['status']!='Verified' for x in tasks if x['task_id'] in task['dependencies'].split(';')):fail(t+' verified with incomplete prerequisite task')
  if task['status']=='Deferred' and not task.get('blocker'):fail(t+' deferred with no owner-approved reason')
  for dep in filter(None,task['dependencies'].split(';')):
   if dep not in ts:fail(t+' missing task dependency '+dep)
   if dep==t:fail(t+' self-referential')
  for req in filter(None,task['requirement_ids'].split(';')):
   if req not in rs:fail(t+' unknown requirement '+req)
 # Tasks cycle detection
 td={t['task_id']:list(filter(None,t['dependencies'].split(';'))) for t in tasks};vis=set();act=set()
 def tv(n):
  if n in act:fail('cyclic task '+n)
  if n in vis:return
  act.add(n)
  for d in td[n]:tv(d)
  act.remove(n);vis.add(n)
 for t in td:tv(t)
 for req in reqs:
  r=req['requirement_id'];mapped=list(filter(None,req['task_ids'].split(';')))
  if not mapped or any(t not in ts for t in mapped):fail(r+' no valid tasks')
  actual={t['task_id'] for t in tasks if r in t['requirement_ids'].split(';')}
  if set(mapped)!=actual:fail(r+' requirement-to-task mapping mismatch')
  for mid in filter(None,req['module_ids'].split(';')):
   if mid not in ms:fail(r+' missing module '+mid)
  for gate in filter(None,req['gate_ids'].split(';')):
   if gate not in gs:fail(r+' missing gate '+gate)
 for test in tests:
  if test['gate'] not in gs or not test['expected_result']:fail(test['test_id']+' invalid gate or outcome')
  if test['status'] not in ['Not Run','Passed','Failed','Blocked']:fail(test['test_id']+' invalid status')
  if test['status']=='Passed' and not test['evidence']:fail(test['test_id']+' pass without evidence')
 for g in gates:
  if g['status'] not in statuses:fail(g['gate_id']+' invalid gate status')
  if g['status']=='Verified':
   if not all(g[x] for x in ('evidence','signoff_owner')):fail(g['gate_id']+' verified without evidence')
   if any(x['status']!='Verified' for x in gates if x['gate_id'] in edges[g['gate_id']]):fail(g['gate_id']+' upstream gate not verified')
   if any(x['status'] not in ('Verified','Deferred') for x in tasks if x['gate']==g['gate_id']):fail(g['gate_id']+' includes unfinished task')
   if any(x['status']!='Passed' for x in tests if x['gate']==g['gate_id']):fail(g['gate_id']+' includes unpassed acceptance test')
 # Ensure no empty module/requirement/gate and no orphan E2E stage.
 for m in ms:
  if not any(t['module_id']==m for t in tasks):fail('orphan module '+m)
 for g in gs:
  if not any(t['gate']==g for t in tasks):fail('gate without tasks '+g)
  if not any(t['gate']==g for t in tests) and g not in ('G00',):fail('gate with no acceptance tests '+g)
 # SRS §14 acceptance classes must have explicit E2E coverage.
 for r in reqs:
  if r['srs_section'].startswith('§14') and not any(t['gate'] in r['gate_ids'].split(';') for t in tests):fail('acceptance coverage missing '+r['requirement_id'])
 print(f'PASS: {len(tasks)} tasks, {len(mods)} modules, {len(gates)} gates, {len(reqs)} requirements, {len(tests)} E2E tests, {len(conf)} conflicts, {len(dec)} decisions, {len(plat)} platforms; IDs/dependencies/coverage OK')
if __name__=='__main__':check(Path(sys.argv[1]) if len(sys.argv)>1 else Path(__file__).parent)
