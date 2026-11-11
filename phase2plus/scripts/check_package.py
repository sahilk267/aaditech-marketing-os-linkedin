"""Static source package checks; distinct from Laravel/MySQL feature tests."""
from pathlib import Path
import re,sys
ROOT=Path(__file__).resolve().parents[1]
files=list((ROOT/'overlay').rglob('*.php'))
assert len(files)>=40,f'Expected Laravel PHP source set, got {len(files)}'
required=[
'overlay/routes/api.php','overlay/routes/web.php',
'overlay/app/Domain/Workflow/JobEngine.php',
'overlay/app/Domain/Publishing/PublishService.php',
'overlay/app/Domain/AI/FreeModelGuard.php',
'overlay/app/Domain/Research/SourcePolicy.php',
'worker/amo_worker/main.py','worker/amo_worker/ai.py','worker/amo_worker/research.py',
'worker/tests/test_security.py','docs/PHASE2PLUS_STATUS.md'
]
missing=[f for f in required if not (ROOT/f).is_file()]
assert not missing,missing
assert '$this->manual' in (ROOT/'overlay/app/Domain/Publishing/PlatformRegistry.php').read_text()
assert 'needs_reconciliation' in (ROOT/'overlay/app/Domain/Publishing/PublishService.php').read_text()
assert 'price_is_zero' in (ROOT/'worker/amo_worker/ai.py').read_text()
assert 'robots' in (ROOT/'worker/amo_worker/research.py').read_text()
for path in ROOT.rglob('*'):
    if path.is_file() and (path.name=='.env' or path.suffix=='.sqlite3'):
        raise AssertionError('Unsafe runtime file in package: '+str(path))
print('PACKAGE CHECKS PASS —',len(files),'PHP files;',len(required),'required paths')
