# Aadi Marketing OS: local desktop worker bootstrap (Windows 10/11, user-level scheduled task).
# Run in PowerShell in the worker directory after creating a scoped token in the Laravel app.
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$worker = (Resolve-Path $PSScriptRoot).Path
Set-Location $worker
if (-not (Get-Command py -ErrorAction SilentlyContinue)) { throw 'Python launcher missing. Install Python 3.11+ and rerun.' }
$version = & py -3 -c 'import sys; print("%d.%d" % sys.version_info[:2])'
$split = $version.Split('.')
if ([int]$split[0] -lt 3 -or ([int]$split[0] -eq 3 -and [int]$split[1] -lt 11)) { throw 'Python 3.11+ required.' }
$venv = Join-Path $worker '.venv'
if (-not (Test-Path $venv)) { & py -3 -m venv $venv }
$python = Join-Path $venv 'Scripts\python.exe'
& $python -m pip install --disable-pip-version-check -r (Join-Path $worker 'requirements.txt')
if ($LASTEXITCODE -ne 0) { throw 'Worker dependencies failed to install.' }
& $python -m unittest discover -s (Join-Path $worker 'tests') -v
if ($LASTEXITCODE -ne 0) { throw 'Worker tests failed. No scheduled task created.' }
$config = Join-Path $worker '.env.worker'
if (-not (Test-Path $config)) {
    $url = (Read-Host 'HTTPS Laravel app URL').Trim().TrimEnd('/')
    if ($url -notmatch '^https://[^\s/]+') { throw 'A HTTPS app URL is required.' }
    $token = (Read-Host 'Scoped worker API token (input hidden)' -AsSecureString)
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($token)
    try { $plain = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
    if ($plain -notmatch '^amo_[1-9][0-9]*\.[a-f0-9]{64}$') { throw 'Invalid worker credential syntax.' }
    $node = (Read-Host 'Worker node label [desktop-1]').Trim()
    if (-not $node) { $node = 'desktop-1' }
    if ($node -notmatch '^[a-zA-Z0-9._-]{1,64}$') { throw 'Invalid worker node label.' }
    $model = (Read-Host 'Installed Ollama CPU model (optional)').Trim()
    $items = [ordered]@{
        AMO_BASE_URL = $url
        AMO_WORKER_TOKEN = $plain
        AMO_NODE_NAME = $node
        AMO_CAPABILITIES = 'heartbeat,research.fetch,content.generate,media.render,publish.post,ai.catalog_sync'
    }
    if ($model) { $items.OLLAMA_MODEL=$model }
    $content = ($items.GetEnumerator() | ForEach-Object { $_.Key + '=' + (ConvertTo-Json -Compress -InputObject $_.Value) }) -join "`n"
    [IO.File]::WriteAllText($config, $content + "`n", [Text.UTF8Encoding]::new($false))
    # Limit the credential file to the current Windows user. Failure blocks task registration.
    $user = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    $acl = New-Object System.Security.AccessControl.FileSecurity
    $acl.SetAccessRuleProtection($true, $false)
    $rule = New-Object System.Security.AccessControl.FileSystemAccessRule($user,'FullControl','Allow')
    $acl.SetAccessRule($rule)
    Set-Acl -Path $config -AclObject $acl
    $plain = $null
}
$taskName = 'Aadi Marketing OS Worker'
$taskAction = New-ScheduledTaskAction -Execute $python -Argument '-m amo_worker.main --forever' -WorkingDirectory $worker
$taskTrigger = New-ScheduledTaskTrigger -AtLogOn -User ([Security.Principal.WindowsIdentity]::GetCurrent().Name)
try {
    Register-ScheduledTask -TaskName $taskName -Action $taskAction -Trigger $taskTrigger -Description 'Outbound HTTPS polling, CPU-only local AI and media. No inbound ports.' -Force | Out-Null
} catch { throw 'Task registration failed. Run manually: .venv\Scripts\python.exe -m amo_worker.main --forever' }
Write-Host 'Worker installed as user-level startup task. No platform OAuth permissions were granted.'
Write-Host 'Confirm first heartbeat in Laravel aadi:doctor and verify Ollama/OpenRouter configuration.'
