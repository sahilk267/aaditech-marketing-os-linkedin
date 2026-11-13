#!/usr/bin/env python3
"""Offline structural checks; not a substitute for full Laravel tests."""
from pathlib import Path
import ast,json,re,subprocess,sys
root=Path(__file__).resolve().parents[1]
required=['composer.json','phpunit.database.xml','artisan','bootstrap/app.php','bootstrap/providers.php',
    'public/index.php','routes/api.php','routes/web.php','routes/console.php',
    'database/seeders/BrandSeeder.php','scripts/deploy.php','app/Console/Commands/AadiDoctor.php',
    'app/Console/Commands/AadiTick.php','.github/workflows/app-ci.yml',
    'worker/install_windows.ps1','worker/amo_worker/config.py',
    'app/Domain/Publishing/LinkedInImageUploader.php','tests/Feature/Phase2Plus/LinkedInImagePublishingTest.php']
for p in required:
    assert (root/p).is_file(),f'Missing canonical file: {p}'
conf=json.loads((root/'composer.json').read_text())
assert conf['require']['laravel/framework'].startswith('^13.')
assert conf['autoload']['psr-4']['App\\']=='app/'
assert conf['autoload-dev']['psr-4']['Tests\\']=='tests/'
migrations=list((root/'database/migrations').glob('*.php'))
assert len(migrations)==11 and len({p.name for p in migrations})==len(migrations),'Duplicate or missing migrations'
api=(root/'routes/api.php').read_text();web=(root/'routes/web.php').read_text()
assert api.count("Route::post('/register'")==1 and api.count("Route::post('/jobs/claim'")==1
assert web.count("Route::resource('brands'")==1
assert (root/'routes/console.php').read_text().count('aadi:tick')==1
assert not (root/'.env').exists(),'.env must not be included in distributable source'
# Runtime dependency directories are expected in CI/release, but never in source control.
if (root/'.git').exists():
    tracked=subprocess.run(['git','ls-files','vendor'],cwd=root,capture_output=True,text=True,check=True).stdout.strip()
    assert not tracked,'vendor must not be committed'
for p in root.glob('app/**/*.php'):
    if 'env(' in p.read_text():
        # Services should consume config() values once config caching is enabled.
        assert p.name not in {'OfficialLinkedInAdapter.php'},f'Illegal env access in {p}'
# Live ownership and route duplicate safeguards. Historical phase overlays are excluded.
ownership=(root/'docs/implementation/CANONICAL_OWNERSHIP.md').read_text(encoding='utf-8')
owners={}
for line in ownership.splitlines():
    match=re.match(r'^\|\s*(M\d{2})\s*\|\s*([^|]+)\|',line)
    if not match:continue
    mid,locations=match.groups()
    assert mid not in owners, f'Duplicate canonical ownership row: {mid}'
    anchors=[x.strip() for x in locations.split(';') if x.strip()]
    assert anchors, f'Empty runtime owner for {mid}'
    for anchor in anchors:
        assert (root/anchor).exists(),f'Invalid canonical runtime owner {mid}: {anchor}'
        assert not anchor.startswith(('phase0/','phase1/','phase2plus/')),f'Archived overlay owner: {mid}'
    owners[mid]=anchors
assert set(owners)=={f'M{n:02}' for n in range(1,20)},'All 19 SRS components need exactly one registry row'
route_re=re.compile(r'''Route::(get|post|put|patch|delete)\(\s*['"]([^'"]+)['"]''')
for name in ('routes/web.php','routes/api.php'):
    paths=route_re.findall((root/name).read_text(encoding='utf-8'))
    assert len(paths)==len(set(paths)),f'Duplicate canonical route method/path in {name}'
print('PASS: 19 canonical owner rows and non-duplicate route declarations')

print(f'PASS: canonical Laravel manifest, {len(migrations)} unique migrations, merged routes and tracked command dependencies')

# Fail CI for accidental duplicate Python module functions/classes or class methods.
for file in (root/'worker').rglob('*.py'):
    tree=ast.parse(file.read_text(encoding='utf-8'),filename=str(file))
    def reject_duplicates(body, scope):
        seen=set()
        for item in body:
            if isinstance(item,(ast.FunctionDef,ast.AsyncFunctionDef,ast.ClassDef)):
                if item.name in seen:raise AssertionError(f'Duplicate definition {scope}.{item.name}: {file}')
                seen.add(item.name)
                if isinstance(item,ast.ClassDef):reject_duplicates(item.body,scope+'.'+item.name)
    reject_duplicates(tree.body,file.stem)
print('PASS: no duplicate worker Python definitions')

# MariaDB 11.4 official image provides healthcheck.sh; mysqladmin is not a portable readiness probe.
app_ci=(root/'.github/workflows/app-ci.yml').read_text()
release_ci=(root/'.github/workflows/build-release.yml').read_text()
for label, content in [('application CI',app_ci),('release CI',release_ci)]:
    assert 'healthcheck.sh --connect --innodb_initialized' in content, f'{label} must use MariaDB healthcheck.sh'
assert 'mysqladmin ping' not in release_ci, 'Release builder has only MariaDB, never use mysqladmin healthcheck'
assert 'phpunit.database.xml' in app_ci, 'DB integration config must trigger application CI'
assert 'Smoke test the actual deployable bundle' in release_ci, 'Release must be smoke-installed from extracted artifact'
assert release_ci.index('Smoke test the actual deployable bundle') < release_ci.index('actions/upload-artifact@v4'), 'Never upload an untested release'
print('PASS: MariaDB/MySQL container health checks and DB CI trigger')
