$ErrorActionPreference = 'Stop'
$posoHostsPath = 'C:/Windows/System32/drivers/etc/hosts'
if (-not (Select-String -LiteralPath $posoHostsPath -Pattern '^\s*127\.0\.0\.1\s+portal\.poso\.test(?:\s|$)' -Quiet)) {
    Add-Content -LiteralPath $posoHostsPath -Value "`r`n127.0.0.1 portal.poso.test # POSO citizen portal" -Encoding ascii
}
