<#
.SYNOPSIS
    Packages this POSO portal installation into a single transferable ZIP.

.DESCRIPTION
    Run this on the machine that currently HAS the working portal.
    It produces one ZIP containing the application, its PHP dependencies,
    a fresh MySQL dump and a one-click installer for the other machine.

    vendor/ is bundled on purpose, so the target machine needs neither
    Composer nor an internet connection.

.PARAMETER OutputDirectory
    Where to drop the ZIP. Defaults to the Desktop.

.PARAMETER NoDatabase
    Package the application only, without a copy of the live data.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File ops\transfer\Export-Portal.ps1
#>
[CmdletBinding()]
param(
    [string] $OutputDirectory = [Environment]::GetFolderPath('Desktop'),
    [switch] $NoDatabase
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

# The project is the grandparent of ops\transfer\.
$ProjectRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)

function Write-Step ([string] $Text) { Write-Host ''; Write-Host "==> $Text" -ForegroundColor Cyan }
function Write-Note ([string] $Text) { Write-Host "    $Text" -ForegroundColor DarkGray }
function Write-Good ([string] $Text) { Write-Host "    OK  $Text" -ForegroundColor Green }
function Stop-WithMessage ([string] $Text, [string[]] $Hints) {
    Write-Host ''
    Write-Host "FAILED: $Text" -ForegroundColor Red
    if ($Hints) {
        Write-Host ''
        foreach ($hint in $Hints) { Write-Host "  - $hint" -ForegroundColor Yellow }
    }
    Write-Host ''
    if ($script:staging -and (Test-Path $script:staging)) {
        Remove-Item -LiteralPath $script:staging -Recurse -Force -ErrorAction SilentlyContinue
    }
    exit 1
}

# --------------------------------------------------------------- env file --

# Reads a Laravel .env into a hashtable. Tolerates leading whitespace and
# quoted values; this project's .env has indented DB_ lines.
function Read-EnvFile ([string] $Path) {
    $values = @{}
    if (-not (Test-Path $Path)) { return $values }
    foreach ($line in (Get-Content -LiteralPath $Path)) {
        $trimmed = $line.Trim()
        if ($trimmed -eq '' -or $trimmed.StartsWith('#')) { continue }
        $split = $trimmed.IndexOf('=')
        if ($split -lt 1) { continue }
        $key = $trimmed.Substring(0, $split).Trim()
        $value = $trimmed.Substring($split + 1).Trim()
        if ($value.Length -ge 2) {
            $first = $value.Substring(0, 1)
            $last = $value.Substring($value.Length - 1, 1)
            if (($first -eq '"' -and $last -eq '"') -or ($first -eq "'" -and $last -eq "'")) {
                $value = $value.Substring(1, $value.Length - 2)
            }
        }
        $values[$key] = $value
    }
    return $values
}

# ------------------------------------------------------------ locate tools --

function Find-LaragonRoot {
    if ($env:LARAGON_ROOT -and (Test-Path $env:LARAGON_ROOT)) { return $env:LARAGON_ROOT }
    foreach ($candidate in @('C:\laragon', 'D:\laragon', 'E:\laragon')) {
        if (Test-Path (Join-Path $candidate 'bin')) { return $candidate }
    }
    return $null
}

function Test-TcpPort ([string] $TargetHost, [int] $Port) {
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $connect = $client.BeginConnect($TargetHost, $Port, $null, $null)
        $ok = $connect.AsyncWaitHandle.WaitOne(1500, $false)
        if ($ok -and $client.Connected) { $client.EndConnect($connect); return $true }
        return $false
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Find-MysqlTool ([string] $LaragonRoot, [string] $Executable) {
    if ($LaragonRoot) {
        $binRoot = Join-Path $LaragonRoot 'bin\mysql'
        if (Test-Path $binRoot) {
            $found = Get-ChildItem -Path $binRoot -Filter $Executable -Recurse -File -ErrorAction SilentlyContinue |
                     Sort-Object FullName -Descending | Select-Object -First 1
            if ($found) { return $found.FullName }
        }
    }
    $onPath = Get-Command $Executable -ErrorAction SilentlyContinue
    if ($onPath) { return $onPath.Source }
    return $null
}

Write-Host ''
Write-Host '  POSO Portal - export for transfer' -ForegroundColor White
Write-Host '  ---------------------------------' -ForegroundColor DarkGray
Write-Note "Project: $ProjectRoot"

if (-not (Test-Path (Join-Path $ProjectRoot 'artisan'))) {
    Stop-WithMessage "No artisan file under $ProjectRoot - run this script from inside the project."
}

$envFile = Join-Path $ProjectRoot '.env'
if (-not (Test-Path $envFile)) {
    Stop-WithMessage 'No .env found. The portal must be configured here before it can be exported.'
}
$envValues = Read-EnvFile $envFile

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$staging = Join-Path ([System.IO.Path]::GetTempPath()) "poso-export-$stamp"
$payload = Join-Path $staging 'app'
New-Item -ItemType Directory -Path $payload -Force | Out-Null

# ------------------------------------------------------------- 1. database --

$dumpFile = Join-Path $staging 'database.sql'

if ($NoDatabase) {
    Write-Step 'Database: skipped (-NoDatabase)'
} else {
    Write-Step 'Dumping the MySQL database'

    $laragon = Find-LaragonRoot
    $mysqldump = Find-MysqlTool $laragon 'mysqldump.exe'
    if (-not $mysqldump) { Stop-WithMessage 'mysqldump.exe not found. Is Laragon installed with MySQL?' }
    Write-Note "Using $mysqldump"

    $database = $envValues['DB_DATABASE']
    if (-not $database) { Stop-WithMessage 'DB_DATABASE is not set in .env' }

    # Credentials go in a temp options file, never on the command line.
    $optionsFile = Join-Path $staging 'my.cnf'
    $dbHost = $envValues['DB_HOST']; if (-not $dbHost) { $dbHost = '127.0.0.1' }
    $dbPort = $envValues['DB_PORT']; if (-not $dbPort) { $dbPort = '3306' }

    if (-not (Test-TcpPort $dbHost ([int] $dbPort))) {
        Stop-WithMessage "The MySQL server is not running on ${dbHost}:${dbPort}." @(
            'Open Laragon and click "Start All", then run this script again.',
            'To package the application without any data, re-run with: -NoDatabase'
        )
    }
    $optionLines = @(
        '[client]',
        "host=$dbHost",
        "port=$dbPort",
        "user=$($envValues['DB_USERNAME'])",
        "password=$($envValues['DB_PASSWORD'])"
    )
    Set-Content -LiteralPath $optionsFile -Value $optionLines -Encoding ASCII

    try {
        $dumpArgs = @(
            "--defaults-extra-file=$optionsFile",
            '--single-transaction',
            '--skip-lock-tables',
            '--no-tablespaces',
            '--add-drop-table',
            '--routines',
            '--events',
            '--hex-blob',
            '--default-character-set=utf8mb4',
            "--result-file=$dumpFile",
            $database
        )
        & $mysqldump @dumpArgs
        if ($LASTEXITCODE -ne 0) { Stop-WithMessage "mysqldump exited with code $LASTEXITCODE" }
    } finally {
        Remove-Item -LiteralPath $optionsFile -Force -ErrorAction SilentlyContinue
    }

    if (-not (Test-Path $dumpFile)) { Stop-WithMessage 'mysqldump produced no file.' }
    $dumpSize = (Get-Item $dumpFile).Length
    if ($dumpSize -lt 1024) { Stop-WithMessage 'The database dump came out empty. Check that MySQL is running.' }
    Write-Good ("$database dumped, {0:N0} KB" -f ($dumpSize / 1KB))
}

# ------------------------------------------------------------ 2. app files --

Write-Step 'Copying the application'

# Excluded on purpose:
#   node_modules       not needed at runtime; npm build only validates assets
#   public\ordinances  54 MB of scans nothing reads (landing page is hardcoded)
#   added docs         the source PDFs for those scans
#   tmp                scratch space
#   storage caches     machine-specific compiled output, rebuilt on the target
#   bootstrap\cache    package discovery cache, regenerated on the target
#   public\hot         stale Vite dev-server pointer
#   public\storage     a symlink, recreated by artisan storage:link
$excludeDirs = @(
    (Join-Path $ProjectRoot 'node_modules'),
    (Join-Path $ProjectRoot 'public\ordinances'),
    (Join-Path $ProjectRoot 'added docs'),
    (Join-Path $ProjectRoot 'tmp'),
    (Join-Path $ProjectRoot 'storage\logs'),
    (Join-Path $ProjectRoot 'storage\framework\cache'),
    (Join-Path $ProjectRoot 'storage\framework\sessions'),
    (Join-Path $ProjectRoot 'storage\framework\views'),
    (Join-Path $ProjectRoot 'storage\framework\testing'),
    (Join-Path $ProjectRoot 'storage\app\private\backups'),
    (Join-Path $ProjectRoot 'public\storage')
)
$excludeFiles = @(
    (Join-Path $ProjectRoot '.env'),
    (Join-Path $ProjectRoot 'public\hot'),
    (Join-Path $ProjectRoot '.phpunit.result.cache'),
    (Join-Path $ProjectRoot 'bootstrap\cache\packages.php'),
    (Join-Path $ProjectRoot 'bootstrap\cache\services.php')
)

$robocopyArgs = @($ProjectRoot, $payload, '/E', '/NFL', '/NDL', '/NJH', '/NJS', '/NP', '/R:1', '/W:1')
$robocopyArgs += '/XD'
$robocopyArgs += $excludeDirs
$robocopyArgs += '/XF'
$robocopyArgs += $excludeFiles

& robocopy.exe @robocopyArgs | Out-Null
# Robocopy signals success with 0-7; 8 and above are real failures.
if ($LASTEXITCODE -ge 8) { Stop-WithMessage "robocopy failed with code $LASTEXITCODE" }

# Recreate the emptied cache directories so Laravel has somewhere to write.
$writableDirs = @(
    'storage\logs',
    'storage\framework\cache\data',
    'storage\framework\sessions',
    'storage\framework\views',
    'bootstrap\cache'
)
foreach ($dir in $writableDirs) {
    New-Item -ItemType Directory -Path (Join-Path $payload $dir) -Force | Out-Null
    $keep = Join-Path $payload (Join-Path $dir '.gitignore')
    if (-not (Test-Path $keep)) {
        Set-Content -LiteralPath $keep -Value @('*', '!.gitignore') -Encoding ASCII
    }
}

$fileCount = (Get-ChildItem $payload -Recurse -File -Force | Measure-Object).Count
Write-Good "$fileCount files staged"

if (-not (Test-Path (Join-Path $payload 'vendor\autoload.php'))) {
    Write-Note 'Warning: vendor/ is missing here, so the installer will need Composer and internet.'
}

# ----------------------------------------------------- 3. portable env file --

Write-Step 'Preparing the environment file'

# Shipped as env.portable so an extracted copy cannot be mistaken for a live
# .env. The installer writes the real .env once it knows the target's MySQL
# password. APP_KEY travels with it so existing sessions stay readable.
Copy-Item -LiteralPath $envFile -Destination (Join-Path $staging 'env.portable') -Force
if (-not $envValues['APP_KEY']) { Write-Note 'Warning: APP_KEY is empty; the installer will generate one.' }
Write-Good 'env.portable written (carries APP_KEY and the DB credentials)'

# -------------------------------------------------------- 4. installer bits --

Write-Step 'Adding the one-click installer'

foreach ($name in @('INSTALL.bat', 'Install-Portal.ps1', 'READ-ME-FIRST.txt')) {
    $source = Join-Path $PSScriptRoot $name
    if (-not (Test-Path $source)) { Stop-WithMessage "Missing installer file: $source" }
    Copy-Item -LiteralPath $source -Destination (Join-Path $staging $name) -Force
}
Write-Good 'INSTALL.bat, Install-Portal.ps1, READ-ME-FIRST.txt'

# --------------------------------------------------------------- 5. archive --

Write-Step 'Building the ZIP'

if (-not (Test-Path $OutputDirectory)) {
    New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
}
$zipPath = Join-Path $OutputDirectory "poso-portal-transfer-$stamp.zip"

# ZipArchiveMode lives in System.IO.Compression; CreateEntryFromFile lives in
# System.IO.Compression.FileSystem. Both assemblies have to be loaded.
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

# Built entry by entry rather than with CreateFromDirectory, which writes
# Windows backslashes into the entry names. The ZIP format wants forward
# slashes, and some extractors produce one long mangled filename without them.
if (Test-Path $zipPath) { Remove-Item -LiteralPath $zipPath -Force }
$stagingPrefix = $staging.TrimEnd('\') + '\'
$archive = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
try {
    foreach ($file in (Get-ChildItem -LiteralPath $staging -Recurse -File -Force)) {
        $relative = $file.FullName.Substring($stagingPrefix.Length).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
            $archive, $file.FullName, $relative, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
} finally {
    $archive.Dispose()
}

Remove-Item -LiteralPath $staging -Recurse -Force -ErrorAction SilentlyContinue

$zipSize = (Get-Item $zipPath).Length
Write-Good ("{0:N1} MB" -f ($zipSize / 1MB))

Write-Host ''
Write-Host '  Done.' -ForegroundColor Green
Write-Host "  $zipPath" -ForegroundColor White
Write-Host ''
Write-Host '  On the other machine:' -ForegroundColor White
Write-Host '    1. Install Laragon, open it, click Start All.'
Write-Host '    2. Extract this ZIP anywhere.'
Write-Host '    3. Double-click INSTALL.bat.'
Write-Host ''
Write-Host '  This ZIP holds real violator data, the database password and' -ForegroundColor Yellow
Write-Host '  APP_KEY. Hand it over the way you would hand over the database.' -ForegroundColor Yellow
Write-Host ''
