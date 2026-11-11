"""Regression tests for structure, duplicate, evidence and dependency gates."""
from __future__ import annotations
import csv, shutil, subprocess, sys, tempfile, unittest
from pathlib import Path
ROOT=Path(__file__).resolve().parents[1]
FILES=[
 'TASK_TRACKER.csv','GATE_TRACKER.csv','MODULE_REGISTRY.csv',
 'REQUIREMENT_TRACEABILITY.csv','E2E_TEST_MATRIX.csv','CONFLICT_REGISTER.csv',
 'DECISION_REGISTER.csv','PLATFORM_MATRIX.csv',
]

class IntegrityTests(unittest.TestCase):
 def setUp(self):
  self.tmp=tempfile.TemporaryDirectory()
  self.root=Path(self.tmp.name)
  for f in FILES:shutil.copy2(ROOT/f,self.root/f)
 def tearDown(self):self.tmp.cleanup()
 def check(self,expected):
  p=subprocess.run([sys.executable,str(ROOT/'validate_tracker.py'),str(self.root)],capture_output=True,text=True)
  self.assertEqual(p.returncode,0 if expected else 1,p.stdout+p.stderr)
 def edit(self,filename,mutation):
  p=self.root/filename
  with p.open(encoding='utf-8',newline='') as f:
   reader=csv.DictReader(f);fields=reader.fieldnames;items=list(reader)
  mutation(items)
  with p.open('w',encoding='utf-8',newline='') as f:
   w=csv.DictWriter(f,fieldnames=fields);w.writeheader();w.writerows(items)
 def test_valid_baseline(self):self.check(True)
 def test_duplicate_task_id_rejected(self):
  self.edit('TASK_TRACKER.csv',lambda r:r[1].update(task_id=r[0]['task_id']))
  self.check(False)
 def test_dangling_dependency_rejected(self):
  self.edit('TASK_TRACKER.csv',lambda r:r[1].update(dependencies='T999'))
  self.check(False)
 def test_cyclic_dependency_rejected(self):
  self.edit('TASK_TRACKER.csv',lambda r:(r[0].update(dependencies='T002'),r[1].update(dependencies='T001')))
  self.check(False)
 def test_verified_without_evidence_rejected(self):
  self.edit('TASK_TRACKER.csv',lambda r:r[0].update(status='Verified'))
  self.check(False)
 def test_requirement_reverse_link_mismatch_rejected(self):
  self.edit('REQUIREMENT_TRACEABILITY.csv',lambda r:r[0].update(task_ids='T001'))
  self.check(False)
 def test_gate_verified_with_incomplete_tasks_rejected(self):
  self.edit('GATE_TRACKER.csv',lambda r:r[0].update(status='Verified',evidence='https://example.invalid/test'))
  self.check(False)

if __name__=='__main__':unittest.main()
