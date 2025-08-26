param(
    [string]$DbHost,
    [string]$DbPort,
    [string]$Db,
    [string]$DbUser,
    [string]$DbPass
)

$ErrorActionPreference = 'Stop'

if (-not $DbHost) { $DbHost = $env:DB_HOST; if (-not $DbHost) { $DbHost = '127.0.0.1' } }
if (-not $DbPort) { $DbPort = $env:DB_PORT; if (-not $DbPort) { $DbPort = '5432' } }
if (-not $Db)    { $Db     = $env:DB_NAME; if (-not $Db)     { $Db     = 'amp' } }
if (-not $DbUser) { $DbUser = $env:DB_USER; if (-not $DbUser) { $DbUser = 'postgres' } }
if (-not $DbPass) { $DbPass = $env:DB_PASS; if (-not $DbPass) { $DbPass = 'Karanja8' } }

$env:PGPASSWORD = $DbPass
$psql = 'psql'

Write-Host ("Applying migrations to {0}@{1}:{2}" -f $Db, $DbHost, $DbPort)
& $psql -h $DbHost -p $DbPort -U $DbUser -d $Db -v ON_ERROR_STOP=1 -f (Resolve-Path './db/migrations/001_init.sql') | Write-Host
Write-Host 'Migrations completed.'


