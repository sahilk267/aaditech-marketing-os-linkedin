# Aadi Marketing OS: read-only local Windows desktop preflight.
# Run only on the actual worker machine. No credentials or environment variables printed.
$ErrorActionPreference = 'Stop'
if ($env:OS -ne 'Windows_NT') {
    throw 'This diagnostic is intended for a Windows worker desktop.'
}

function Command-Version([string]$Name, [string[]]$Arguments) {
    $cmd = Get-Command $Name -ErrorAction SilentlyContinue
    if (-not $cmd) { return @{ installed = $false; version = $null } }
    try {
        $result = & $cmd.Source @Arguments 2>&1 | Select-Object -First 1
        return @{ installed = $true; version = [string]$result }
    } catch {
        return @{ installed = $true; version = 'not_detected' }
    }
}

$os = Get-CimInstance Win32_OperatingSystem
$cpu = Get-CimInstance Win32_Processor | Select-Object -First 1
$drives = @(Get-CimInstance Win32_LogicalDisk -Filter 'DriveType=3' |
    Select-Object DeviceID, Size, FreeSpace)
$report = [ordered]@{
    tool = 'aadi_windows_desktop_preflight_v1'
    time_utc = (Get-Date).ToUniversalTime().ToString('o')
    host_role = 'worker_desktop_only'
    windows_caption = $os.Caption
    windows_version = $os.Version
    windows_build = $os.BuildNumber
    cpu_name = $cpu.Name
    logical_processors = $cpu.NumberOfLogicalProcessors
    physical_ram_bytes = [int64]$os.TotalVisibleMemorySize * 1024
    drives = $drives
    python = Command-Version 'python' @('--version')
    git = Command-Version 'git' @('--version')
    ollama = Command-Version 'ollama' @('--version')
    ffmpeg = Command-Version 'ffmpeg' @('-version')
    ollama_process_running = [bool](Get-Process -Name 'ollama*' -ErrorAction SilentlyContinue)
    # Process presence does not prove Ollama API availability or model quality.
    ollama_api_status = 'run_python_benchmark_to_verify'
    hosting_php_mysql_cron = 'not_measured_by_this_script'
}
$report | ConvertTo-Json -Depth 6
