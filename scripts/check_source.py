#!/usr/bin/env python3
"""Offline structural checks; not a substitute for full Laravel tests."""
from pathlib import Path
import ast,json,re,subprocess,sys
root=Path(__file__).resolve().parents[1]
required=['composer.json','artisan','bootstrap/app.php','bootstrap/providers.php',
    'public/index.php','routes/api.php','routes/web.php','routes/console.php',
    'database/seeders/BrandSeeder.php','scripts/deploy.php','app/Console/Commands/AadiDoctor.php',
    'app/Console/Commands/AadiTick.php','.github/workflows/app-ci.yml',
    'worker/install_windows.ps1','worker/amo_worker/config.py']
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
