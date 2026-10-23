<#
.SYNOPSIS
    Makes an already-copied POSO portal folder run on THIS machine.

.DESCRIPTION
    Use this when you copied the project folder across by hand (USB, network
    share, cloud drive) instead of building a transfer ZIP.

    Copying the folder does not copy the MySQL database, and it drags along
    compiled files that point at the old machine's paths. This script fixes
    both: it repairs .env for this machine's MySQL, loads the database from
    the newest backup inside storage\app\private\backups, and clears the
    stale compiled output.

    It leaves an existing database alone unless you ask for it to be
    replaced, so it is safe to run more than once.

.PARAMETER DumpFile
    A specific .sql file to load. Otherwise the newest backup in the project
    is used.

.PARAMETER ImportData
    Load the dump even if the database already has tables. This REPLACES
    the data currently in that database.

.PARAMETER FreshDatabase
    Ignore any dump. Run migrations and the demo seeder instead.

.PARAMETER SkipDatabase
    Only repair configuration and caches; do not touch the database.

.PARAMETER DbPassword
    This machine's MySQL password. Supply it to avoid the prompt. Use ""
    for a stock Laragon, which leaves the root password empty.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File ops\transfer\Setup-Here.ps1
#>
[CmdletBinding()]
param(
    [string] $DumpFile,
    [string] $LaragonRoot,
    [string] $AppUrl,
    [string] $DbPassword,
    [switch] $ImportData,
    [switch] $FreshDatabase,
    [switch] $SkipDatabase
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'

# The project is the grandparent of ops\transfer\.
$ProjectRoot = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$TempDir = Join-Path ([System.IO.Path]::GetTempPath()) ('poso-setup-' + (Get-Date -Format 'yyyyMMddHHmmss'))
New-Item -ItemType Directory -Path $TempDir -Force | Out-Null

function Write-Step ([string] $Text) { Write-Host ''; Write-Host "==> $Text" -ForegroundColor Cyan }
function Write-Note ([string] $Text) { Write-Host "    $Text" -ForegroundColor DarkGray }
function Write-Good ([string] $Text) { Write-Host "    OK  $Text" -ForegroundColor Green }
function Write-Warn ([string] $Text) { Write-Host "    !   $Text" -ForegroundColor Yellow }

function Stop-WithMessage ([string] $Text, [string[]] $Hints) {
    Write-Host ''
    Write-Host '  ---------------------------------------------------------' -ForegroundColor Red
    Write-Host "  FAILED: $Text" -ForegroundColor Red
    if ($Hints) {
        Write-Host ''
        foreach ($hint in $Hints) { Write-Host "  - $hint" -ForegroundColor Yellow }
    }
    Write-Host '  ---------------------------------------------------------' -ForegroundColor Red
    Write-Host ''
    Remove-Item -LiteralPath $TempDir -Recurse -Force -ErrorAction SilentlyContinue
    exit 1
}

function Read-EnvFile ([string] $Path) {
    $values = [ordered]@{}
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

# Rewrites KEY=VALUE lines in place, preserving comments and order.
function Write-EnvFile ([string] $SourcePath, [string] $DestinationPath, [hashtable] $Overrides) {
    $output = New-Object System.Collections.Generic.List[string]
    $seen = @{}
    foreach ($line in (Get-Content -LiteralPath $SourcePath)) {
        $trimmed = $line.Trim()
        $split = $trimmed.IndexOf('=')
        $replaced = $false
        if ($trimmed -ne '' -and -not $trimmed.StartsWith('#') -and $split -ge 1) {
            $key = $trimmed.Substring(0, $split).Trim()
            if ($Overrides.ContainsKey($key)) {
                $value = [string] $Overrides[$key]
                if ($value -match '\s') { $value = '"' + $value + '"' }
                $output.Add("$key=$value")
                $seen[$key] = $true
                $replaced = $true
            }
        }
        if (-not $replaced) { $output.Add($line) }
    }
    foreach ($key in $Overrides.Keys) {
        if (-not $seen.ContainsKey($key)) {
            $value = [string] $Overrides[$key]
            if ($value -match '\s') { $value = '"' + $value + '"' }
            $output.Add("$key=$value")
        }
    }
    Set-Content -LiteralPath $DestinationPath -Value $output.ToArray() -Encoding ASCII
}

function New-MysqlOptionsFile ([string] $MysqlHost, [string] $Port, [string] $User, [string] $Password) {
    $file = Join-Path $TempDir ('my-' + [guid]::NewGuid().ToString('N') + '.cnf')
    Set-Content -LiteralPath $file -Encoding ASCII -Value @(
        '[client]', "host=$MysqlHost", "port=$Port", "user=$User", "password=$Password")
    return $file
}

function Invoke-Mysql {
    param(
        [string] $MysqlExe,
        [string] $OptionsFile,
        [string] $Query,
        [string] $Database,
        [string] $InputFile
    )
    $arguments = @("--defaults-extra-file=$OptionsFile")
    if ($Query) { $arguments += @('-e', $Query) }
    if ($Database) { $arguments += $Database }

    $outFile = Join-Path $TempDir ('out-' + [guid]::NewGuid().ToString('N') + '.txt')
    $errFile = Join-Path $TempDir ('err-' + [guid]::NewGuid().ToString('N') + '.txt')

    $startArgs = @{
        FilePath               = $MysqlExe
        ArgumentList           = $arguments
        NoNewWindow            = $true
        Wait                   = $true
        PassThru               = $true
        RedirectStandardOutput = $outFile
        RedirectStandardError  = $errFile
    }
    if ($InputFile) { $startArgs['RedirectStandardInput'] = $InputFile }

    $process = Start-Process @startArgs

    $stdout = ''
    $stderr = ''
    if (Test-Path $outFile) { $stdout = (Get-Content -LiteralPath $outFile -Raw) }
    if (Test-Path $errFile) { $stderr = (Get-Content -LiteralPath $errFile -Raw) }

    return [pscustomobject]@{ ExitCode = $process.ExitCode; StdOut = $stdout; StdErr = $stderr }
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

# =====================================================================  go  ==

Write-Host ''
Write-Host '  ===========================================================' -ForegroundColor White
Write-Host '   POSO Portal - set up this copy on this machine' -ForegroundColor White
Write-Host '  ===========================================================' -ForegroundColor DarkGray
Write-Note "Folder: $ProjectRoot"

if (-not (Test-Path (Join-Path $ProjectRoot 'artisan'))) {
    Stop-WithMessage 'This script is not inside a POSO portal folder.' @(
        'It belongs at <project>\ops\transfer\Setup-Here.ps1',
        "Looked for: $ProjectRoot\artisan"
    )
}

# --------------------------------------------------------------- 1. .env ---

Write-Step 'Checking the configuration file'

$envPath = Join-Path $ProjectRoot '.env'
$envSource = $envPath
if (-not (Test-Path $envPath)) {
    foreach ($fallback in @('env.portable', '.env.example')) {
        $candidate = Join-Path $ProjectRoot $fallback
        if (Test-Path $candidate) { $envSource = $candidate; break }
    }
    if ($envSource -eq $envPath) {
        Stop-WithMessage 'No .env, env.portable or .env.example in this folder.' @(
            'Copy the .env file across from the original machine.'
        )
    }
    Write-Warn "No .env here; starting from $(Split-Path -Leaf $envSource)"
} else {
    Write-Good '.env came across with the folder'
}
$envValues = Read-EnvFile $envSource

# ------------------------------------------------------------ 2. find PHP --

Write-Step 'Looking for PHP 8.3 or newer'

if (-not $LaragonRoot) {
    if ($env:LARAGON_ROOT -and (Test-Path $env:LARAGON_ROOT)) {
        $LaragonRoot = $env:LARAGON_ROOT
    } else {
        foreach ($candidate in @('C:\laragon', 'D:\laragon', 'E:\laragon', "$env:USERPROFILE\laragon")) {
            if (Test-Path (Join-Path $candidate 'bin')) { $LaragonRoot = $candidate; break }
        }
    }
}

$phpCandidates = New-Object System.Collections.Generic.List[string]
if ($LaragonRoot) {
    $phpRoot = Join-Path $LaragonRoot 'bin\php'
    if (Test-Path $phpRoot) {
        foreach ($dir in (Get-ChildItem -Path $phpRoot -Directory | Sort-Object Name -Descending)) {
            $exe = Join-Path $dir.FullName 'php.exe'
            if (Test-Path $exe) { $phpCandidates.Add($exe) }
        }
    }
}
$onPath = Get-Command 'php.exe' -ErrorAction SilentlyContinue
if ($onPath) { $phpCandidates.Add($onPath.Source) }

$php = $null
$phpVersion = $null
foreach ($candidate in $phpCandidates) {
    try { $reported = (& $candidate -r "echo PHP_VERSION;" 2>$null) } catch { continue }
    if (-not $reported) { continue }
    if ($reported -match '^(\d+)\.(\d+)\.(\d+)') {
        $parsed = [version]("$($Matches[1]).$($Matches[2]).$($Matches[3])")
        if ($parsed -ge [version]'8.3.0') { $php = $candidate; $phpVersion = $reported; break }
    }
}
if (-not $php) {
    Stop-WithMessage 'No PHP 8.3 or newer was found.' @(
        'This application requires PHP 8.3 (composer.json: "php": "^8.3").',
        'In Laragon: Menu > PHP > Version. Pick 8.3 or newer, then run this again.'
    )
}
Write-Good "PHP $phpVersion"
Write-Note $php

# --------------------------------------------------------- 3. dependencies --

Write-Step 'Checking PHP dependencies'

if (Test-Path (Join-Path $ProjectRoot 'vendor\autoload.php')) {
    Write-Good 'vendor/ came across with the folder'
} else {
    Write-Warn 'vendor/ is missing; running composer install (needs internet).'
    $composer = $null
    if ($LaragonRoot) {
        $candidate = Join-Path $LaragonRoot 'bin\composer\composer.bat'
        if (Test-Path $candidate) { $composer = $candidate }
    }
    if (-not $composer) {
        $onPath = Get-Command 'composer' -ErrorAction SilentlyContinue
        if ($onPath) { $composer = $onPath.Source }
    }
    if (-not $composer) {
        Stop-WithMessage 'vendor/ is missing and Composer was not found.' @(
            'Copy the vendor folder across from the original machine, or install Composer.'
        )
    }
    Push-Location $ProjectRoot
    try {
        & $composer install --no-interaction --optimize-autoloader
        if ($LASTEXITCODE -ne 0) { Stop-WithMessage 'composer install failed.' }
    } finally { Pop-Location }
    Write-Good 'Dependencies installed'
}

# -------------------------------------------------------- 4. stale leftovers --

Write-Step 'Clearing files that still point at the old machine'

# Compiled Blade templates, the config cache and the package manifest all
# store absolute paths. Carried across unchanged they produce blank pages or
# "file not found" errors that look nothing like a path problem.
$removed = 0
$stalePatterns = @(
    'bootstrap\cache\*.php',
    'storage\framework\views\*.php',
    'storage\framework\cache\data\*',
    'storage\framework\sessions\*'
)
foreach ($pattern in $stalePatterns) {
    $full = Join-Path $ProjectRoot $pattern
    foreach ($item in (Get-ChildItem -Path $full -Force -ErrorAction SilentlyContinue)) {
        Remove-Item -LiteralPath $item.FullName -Recurse -Force -ErrorAction SilentlyContinue
        $removed++
    }
}
# A leftover Vite hot file makes Laravel look for a dev server that is not there.
$hotFile = Join-Path $ProjectRoot 'public\hot'
if (Test-Path $hotFile) { Remove-Item -LiteralPath $hotFile -Force; $removed++ }
$phpunitCache = Join-Path $ProjectRoot '.phpunit.result.cache'
if (Test-Path $phpunitCache) { Remove-Item -LiteralPath $phpunitCache -Force; $removed++ }

# Make sure everything Laravel writes to exists.
foreach ($dir in @('storage\logs', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\framework\views', 'bootstrap\cache')) {
    New-Item -ItemType Directory -Path (Join-Path $ProjectRoot $dir) -Force | Out-Null
}
Write-Good "$removed stale files removed"

# ---------------------------------------------------------- 5. find MySQL --

$dbHost = $envValues['DB_HOST']; if (-not $dbHost) { $dbHost = '127.0.0.1' }
$dbPort = $envValues['DB_PORT']; if (-not $dbPort) { $dbPort = '3306' }
$dbUser = $envValues['DB_USERNAME']; if (-not $dbUser) { $dbUser = 'root' }
$dbName = $envValues['DB_DATABASE']; if (-not $dbName) { $dbName = 'poso_portal' }

$mysqlExe = $null
$mysqldumpExe = $null

if (-not $SkipDatabase) {
    Write-Step 'Looking for MySQL'

    if ($LaragonRoot) {
        $mysqlRoot = Join-Path $LaragonRoot 'bin\mysql'
        if (Test-Path $mysqlRoot) {
            $found = Get-ChildItem -Path $mysqlRoot -Filter 'mysql.exe' -Recurse -File -ErrorAction SilentlyContinue |
                     Sort-Object FullName -Descending | Select-Object -First 1
            if ($found) {
                $mysqlExe = $found.FullName
                $sibling = Join-Path $found.Directory.FullName 'mysqldump.exe'
                if (Test-Path $sibling) { $mysqldumpExe = $sibling }
            }
        }
    }
    if (-not $mysqlExe) {
        $onPath = Get-Command 'mysql.exe' -ErrorAction SilentlyContinue
        if ($onPath) { $mysqlExe = $onPath.Source }
    }
    if (-not $mysqlExe) {
        Stop-WithMessage 'mysql.exe was not found.' @(
            'Install Laragon Full (the lite edition ships without MySQL).',
            'Or re-run with -SkipDatabase to fix only the configuration.'
        )
    }
    Write-Good "MySQL client: $mysqlExe"

    Write-Step "Waiting for the MySQL server on ${dbHost}:${dbPort}"
    $serverUp = $false
    for ($attempt = 1; $attempt -le 20; $attempt++) {
        if (Test-TcpPort $dbHost ([int] $dbPort)) { $serverUp = $true; break }
        if ($attempt -eq 1) {
            Write-Warn 'Not reachable yet. Open Laragon and click "Start All".'
            Write-Note 'Waiting up to 60 seconds...'
        }
        Start-Sleep -Seconds 3
    }
    if (-not $serverUp) {
        Stop-WithMessage "Nothing is listening on ${dbHost}:${dbPort}." @(
            'Open Laragon and click "Start All", then run this again.',
            'If another MySQL already uses port 3306, stop that one first.'
        )
    }
    Write-Good 'MySQL is accepting connections'
}

# ----------------------------------------------------- 6. MySQL password ----

$resolvedPassword = $envValues['DB_PASSWORD']
$optionsFile = $null

if (-not $SkipDatabase) {
    Write-Step "Signing in to MySQL as '$dbUser'"

    # The old machine's password is unlikely to match a fresh Laragon, which
    # leaves root blank. Try what we have, then the usual defaults, then ask.
    $passwordCandidates = New-Object System.Collections.Generic.List[string]
    if ($PSBoundParameters.ContainsKey('DbPassword')) { $passwordCandidates.Add([string] $DbPassword) }
    $passwordCandidates.Add([string] $envValues['DB_PASSWORD'])
    foreach ($stock in @('', 'root', 'password')) {
        if (-not $passwordCandidates.Contains($stock)) { $passwordCandidates.Add($stock) }
    }

    $resolvedPassword = $null
    foreach ($candidate in $passwordCandidates) {
        $attemptFile = New-MysqlOptionsFile $dbHost $dbPort $dbUser $candidate
        $probe = Invoke-Mysql -MysqlExe $mysqlExe -OptionsFile $attemptFile -Query 'SELECT 1'
        if ($probe.ExitCode -eq 0) { $resolvedPassword = $candidate; $optionsFile = $attemptFile; break }
        Remove-Item -LiteralPath $attemptFile -Force -ErrorAction SilentlyContinue
    }

    if ($null -eq $resolvedPassword) {
        Write-Warn "None of the usual passwords worked for '$dbUser'."
        for ($tries = 1; $tries -le 3; $tries++) {
            $secure = Read-Host -Prompt "    MySQL password for '$dbUser'" -AsSecureString
            $plain = [System.Runtime.InteropServices.Marshal]::PtrToStringAuto(
                [System.Runtime.InteropServices.Marshal]::SecureStringToBSTR($secure))
            $attemptFile = New-MysqlOptionsFile $dbHost $dbPort $dbUser $plain
            $probe = Invoke-Mysql -MysqlExe $mysqlExe -OptionsFile $attemptFile -Query 'SELECT 1'
            if ($probe.ExitCode -eq 0) { $resolvedPassword = $plain; $optionsFile = $attemptFile; break }
            Remove-Item -LiteralPath $attemptFile -Force -ErrorAction SilentlyContinue
            Write-Warn 'Rejected.'
        }
    }
    if ($null -eq $resolvedPassword) {
        Stop-WithMessage 'Could not sign in to MySQL.' @(
            'Laragon normally leaves the root password empty.',
            'Check it in Laragon: Menu > MySQL > Change root password.'
        )
    }
    if ($resolvedPassword -eq '') { Write-Good 'Connected (no password, Laragon default)' }
    else { Write-Good 'Connected' }
}

# --------------------------------------------------------- 7. write .env ----

Write-Step 'Updating .env for this machine'

$overrides = @{
    'DB_CONNECTION' = 'mysql'
    'DB_HOST'       = $dbHost
    'DB_PORT'       = $dbPort
    'DB_DATABASE'   = $dbName
    'DB_USERNAME'   = $dbUser
}
if (-not $SkipDatabase) { $overrides['DB_PASSWORD'] = $resolvedPassword }
if ($AppUrl) { $overrides['APP_URL'] = $AppUrl }
# The backup command hard-codes the old machine's mysqldump path by default.
if ($mysqldumpExe) { $overrides['POSO_MYSQLDUMP'] = $mysqldumpExe }

Write-EnvFile $envSource $envPath $overrides
Write-Good ".env updated for database '$dbName'"

Push-Location $ProjectRoot
try {
    if (-not $envValues['APP_KEY']) {
        Write-Note 'No APP_KEY found; generating one.'
        & $php artisan key:generate --force --no-interaction | Out-Null
    } else {
        Write-Note 'APP_KEY kept from the original machine'
    }
} finally { Pop-Location }

# -------------------------------------------------------- 8. the database ---

$restoredFromDump = $false

if ($SkipDatabase) {
    Write-Step 'Database: skipped (-SkipDatabase)'
} else {
    Write-Step "Preparing the database '$dbName'"

    $create = Invoke-Mysql -MysqlExe $mysqlExe -OptionsFile $optionsFile `
        -Query "CREATE DATABASE IF NOT EXISTS ``$dbName`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    if ($create.ExitCode -ne 0) { Stop-WithMessage "Could not create the database: $($create.StdErr)" }

    $tableProbe = Invoke-Mysql -MysqlExe $mysqlExe -OptionsFile $optionsFile `
        -Query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '$dbName';"
    $existingTables = 0
    if ($tableProbe.ExitCode -eq 0 -and $tableProbe.StdOut -match '(\d+)\s*$') { $existingTables = [int] $Matches[1] }

    if ($existingTables -gt 0) { Write-Note "'$dbName' already holds $existingTables tables" }
    else { Write-Good "'$dbName' created, empty" }

    # Pick the dump. A backup inside the project travelled with the folder.
    if (-not $DumpFile -and -not $FreshDatabase) {
        $searchPaths = @(
            (Join-Path $ProjectRoot 'database.sql'),
            (Join-Path $PSScriptRoot 'database.sql')
        )
        foreach ($candidate in $searchPaths) {
            if (Test-Path $candidate) { $DumpFile = $candidate; break }
        }
        if (-not $DumpFile) {
            $backupDir = Join-Path $ProjectRoot 'storage\app\private\backups'
            if (Test-Path $backupDir) {
                $newest = Get-ChildItem -Path $backupDir -Filter '*.sql' -File -ErrorAction SilentlyContinue |
                          Sort-Object LastWriteTime -Descending | Select-Object -First 1
                if ($newest) { $DumpFile = $newest.FullName }
            }
        }
    }

    if ($FreshDatabase) {
        Write-Step 'Building a fresh database (-FreshDatabase)'
    } elseif (-not $DumpFile) {
        Write-Warn 'No .sql backup found in this folder.'
        Write-Note 'The schema will be created empty; there will be no records and no staff accounts.'
        Write-Note 'To bring your data across, run this on the ORIGINAL machine:'
        Write-Note '    php artisan poso:backup'
        Write-Note 'then copy storage\app\private\backups\*.sql into this folder and run this again.'
    } else {
        $dumpInfo = Get-Item $DumpFile
        Write-Step 'Loading the data'
        Write-Note ("From {0} ({1:N0} KB, {2})" -f $dumpInfo.Name, ($dumpInfo.Length / 1KB), $dumpInfo.LastWriteTime)

        $proceed = $true
        if ($existingTables -gt 0 -and -not $ImportData) {
            Write-Warn "'$dbName' already has data on this machine."
            Write-Host ''
            Write-Host '    Loading the backup REPLACES it. Say no to keep what is there.' -ForegroundColor DarkGray
            $answer = Read-Host '    Replace it with the backup? [y/N]'
            if ($answer -notmatch '^(y|yes)$') {
                $proceed = $false
                Write-Note 'Kept the existing data.'
            }
        }

        if ($proceed) {
            $import = Invoke-Mysql -MysqlExe $mysqlExe -OptionsFile $optionsFile -Database $dbName -InputFile $DumpFile
            if ($import.ExitCode -ne 0) { Stop-WithMessage "Loading the backup failed: $($import.StdErr)" }
            $restoredFromDump = $true
            Write-Good 'Data loaded'
        }
    }
}

# ------------------------------------------------------- 9. warm up Laravel --

Write-Step 'Starting the application up'

Push-Location $ProjectRoot
try {
    & $php artisan package:discover --no-interaction | Out-Null
    & $php artisan config:clear --no-interaction | Out-Null
    & $php artisan cache:clear --no-interaction | Out-Null
    & $php artisan view:clear --no-interaction | Out-Null
    & $php artisan route:clear --no-interaction | Out-Null
    Write-Good 'Caches rebuilt for this machine'

    if (-not $SkipDatabase) {
        & $php artisan migrate --force --no-interaction
        if ($LASTEXITCODE -ne 0) { Stop-WithMessage 'php artisan migrate failed.' }

        if ($FreshDatabase) {
            & $php artisan db:seed --force --no-interaction
            if ($LASTEXITCODE -ne 0) { Write-Warn 'Seeding failed; the database is empty but usable.' }
        }
    }

    if (-not (Test-Path (Join-Path $ProjectRoot 'public\storage'))) {
        & $php artisan storage:link --no-interaction 2>$null | Out-Null
        if (-not (Test-Path (Join-Path $ProjectRoot 'public\storage'))) {
            Write-Warn 'storage:link needs admin or Developer Mode. Nothing in this app uses it yet.'
        }
    }
} finally { Pop-Location }

# ------------------------------------------------------------ 10. verify ----

$accounts = $null
if (-not $SkipDatabase) {
    Write-Step 'Verifying'

    $counts = Invoke-Mysql -MysqlExe $mysqlExe -OptionsFile $optionsFile -Database $dbName `
        -Query "SELECT (SELECT COUNT(*) FROM users), (SELECT COUNT(*) FROM violations WHERE deleted_at IS NULL), (SELECT COUNT(*) FROM violators WHERE deleted_at IS NULL);"
    if ($counts.ExitCode -ne 0) {
        Stop-WithMessage "The database is not readable: $($counts.StdErr)"
    }
    $numbers = @()
    foreach ($match in [regex]::Matches($counts.StdOut, '\d+')) { $numbers += $match.Value }
    if ($numbers.Count -ge 3) {
        Write-Good "$($numbers[0]) staff accounts, $($numbers[1]) violations, $($numbers[2]) violators"
        if ([int] $numbers[0] -eq 0) {
            Write-Warn 'There are no staff accounts, so nobody can sign in.'
            Write-Note 'Load a backup from the original machine, or re-run with -FreshDatabase for demo accounts.'
        }
    }

    $accounts = Invoke-Mysql -MysqlExe $mysqlExe -OptionsFile $optionsFile -Database $dbName `
        -Query "SELECT username, role FROM users WHERE is_active = 1 ORDER BY role, username;"
}

Remove-Item -LiteralPath $TempDir -Recurse -Force -ErrorAction SilentlyContinue

# ------------------------------------------------------------- 11. done ----

$finalEnv = Read-EnvFile $envPath
$citizenDomain = $finalEnv['CITIZEN_DOMAIN']
if (-not $citizenDomain) { $citizenDomain = 'portal.poso.test' }

$siteName = Split-Path -Leaf $ProjectRoot

Write-Host ''
Write-Host '  ===========================================================' -ForegroundColor Green
Write-Host '   Ready.' -ForegroundColor Green
Write-Host '  ===========================================================' -ForegroundColor DarkGray
Write-Host ''
Write-Host "   Folder   : $ProjectRoot" -ForegroundColor White
Write-Host "   Database : $dbName on ${dbHost}:${dbPort}" -ForegroundColor White
Write-Host ''
Write-Host '   Open it' -ForegroundColor White
Write-Host '   -------' -ForegroundColor DarkGray
Write-Host '   The surest way, works anywhere:' -ForegroundColor DarkGray
Write-Host ''
Write-Host "     cd $ProjectRoot" -ForegroundColor Cyan
Write-Host '     php artisan serve' -ForegroundColor Cyan
Write-Host '     http://127.0.0.1:8000'
Write-Host ''
if ($ProjectRoot -match '\\www\\') {
    Write-Host '   Or through Laragon, with Apache running:' -ForegroundColor DarkGray
    Write-Host "     http://$siteName.test" -ForegroundColor White
    Write-Host "     http://$siteName.test/login" -ForegroundColor White
    Write-Host '   If that name does not resolve, right-click Laragon > Apache > Reload.' -ForegroundColor DarkGray
} else {
    Write-Host '   This folder is not under Laragon''s www directory, so Laragon' -ForegroundColor Yellow
    Write-Host '   will not publish a .test address for it. Move it there, or use' -ForegroundColor Yellow
    Write-Host '   php artisan serve as above.' -ForegroundColor Yellow
}
Write-Host ''

if ($accounts -and $accounts.ExitCode -eq 0 -and $accounts.StdOut.Trim()) {
    Write-Host '   Sign in with' -ForegroundColor White
    Write-Host '   ------------' -ForegroundColor DarkGray
    Write-Host '   The same usernames and passwords as the original machine:'
    Write-Host ''
    foreach ($line in ($accounts.StdOut -split "`r?`n")) {
        if ($line.Trim() -eq '' -or $line -match '^\s*username\s') { continue }
        Write-Host "     $($line.Trim())" -ForegroundColor White
    }
    Write-Host ''
}

Write-Host '   Citizen settlement search is at /search, and at' -ForegroundColor DarkGray
Write-Host "   http://$citizenDomain once that host is set up." -ForegroundColor DarkGray
Write-Host '   See ops\laragon\README.md for that.' -ForegroundColor DarkGray
Write-Host ''
