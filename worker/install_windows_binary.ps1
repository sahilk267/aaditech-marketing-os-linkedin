# Portable Windows binary worker bootstrap (no Python or pip installation)
$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest
$root = (Resolve-Path $PSScriptRoot).Path
$exe = Join-Path $root 'aadi-worker.exe'
if (-not (Test-Path $exe)) { throw 'Worker executable missing from bundle' }
& $exe --help | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Worker binary smoke check failed' }
$config = Join-Path $root '.env.worker'
if (-not (Test-Path $config)) {
    $url = (Read-Host 'HTTPS Marketing OS base URL').Trim().TrimEnd('/')
    if ($url -notmatch '^https://[^/\s]+$') { throw 'HTTPS origin required' }
    $secure = Read-Host 'Scoped worker token' -AsSecureString
    $pointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure)
    try { $token = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($pointer) }
    finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($pointer) }
    if ($token -notmatch '^amo_[1-9][0-9]*\.[a-f0-9]{64}$') { throw 'Worker token format invalid' }
    $node = (Read-Host 'Worker label [desktop-1]').Trim()
    if (-not $node) { $node = 'desktop-1' }
    if ($node -notmatch '^[a-zA-Z0-9._-]{1,64}$') { throw 'Invalid worker node label' }
    $vars = [ordered]@{
        AMO_BASE_URL = $url
        AMO_WORKER_TOKEN = $token
        AMO_NODE_NAME = $node
        AMO_CAPABILITIES = 'heartbeat,research.fetch,content.generate,media.render,publish.post,ai.catalog_sync'
    }
    $lines = ($vars.GetEnumerator() | ForEach-Object { $_.Key + '=' + (ConvertTo-Json -Compress -InputObject $_.Value) })
    [IO.File]::WriteAllLines($config, [string[]]$lines, [Text.UTF8Encoding]::new($false))
    $user = [Security.Principal.WindowsIdentity]::GetCurrent().Name
    $acl = New-Object System.Security.AccessControl.FileSecurity
    $acl.SetAccessRuleProtection($true, $false)
    $acl.SetAccessRule([System.Security.AccessControl.FileSystemAccessRule]::new($user,'FullControl','Allow'))
    Set-Acl -Path $config -AclObject $acl
    $token = $null
}
$task = 'Aadi Marketing OS Worker'
$action = New-ScheduledTaskAction -Execute $exe -Argument '--forever' -WorkingDirectory $root
$trigger = New-ScheduledTaskTrigger -AtLogOn -User ([Security.Principal.WindowsIdentity]::GetCurrent().Name)
Register-ScheduledTask -TaskName $task -Action $action -Trigger $trigger -Force | Out-Null
try { Start-ScheduledTask -TaskName $task } catch { Write-Warning 'Task registered but did not start; check Task Scheduler.' }
Write-Host 'Worker startup configured; verify first heartbeat in Aadi dashboard.'
