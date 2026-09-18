# Setup do SisREAd no Windows (sem Docker). Idempotente: pode rodar várias vezes.
$ErrorActionPreference = 'Stop'
Set-Location (Split-Path $PSScriptRoot -Parent)

if (-not (Get-Command php -ErrorAction SilentlyContinue)) {
    winget install --id PHP.PHP.8.3 -e --accept-source-agreements --accept-package-agreements --disable-interactivity
    $env:Path = [Environment]::GetEnvironmentVariable('Path','User') + ';' + [Environment]::GetEnvironmentVariable('Path','Machine')
}
$phpDir = Split-Path (Get-Command php).Source
# winget registra um alias; resolve o diretório real do pacote
$pkg = Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Directory -Filter 'PHP.PHP.8.3*' -ErrorAction SilentlyContinue | Select-Object -First 1
if ($pkg) { $phpDir = $pkg.FullName }

$ini = Join-Path $phpDir 'php.ini'
if (-not (Test-Path $ini)) {
    $c = Get-Content (Join-Path $phpDir 'php.ini-development')
    $c = $c -replace '^;extension_dir = "ext"', 'extension_dir = "ext"'
    foreach ($e in 'curl','fileinfo','mbstring','openssl','pdo_sqlite','sqlite3','zip','intl','sodium') {
        $c = $c -replace "^;extension=$e$", "extension=$e"
    }
    $c = $c -replace '^memory_limit = .*', 'memory_limit = 1G'
    Set-Content $ini $c -Encoding ascii
}

$composer = Join-Path $phpDir 'composer.phar'
if (-not (Test-Path $composer)) {
    Invoke-WebRequest https://getcomposer.org/download/latest-stable/composer.phar -OutFile $composer
}

# pcntl/posix não existem no Windows; só o Horizon precisa deles (não usado localmente)
php $composer install --no-interaction --ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix

if (-not (Test-Path .env)) {
    Copy-Item .env.example .env
    (Get-Content .env) -replace '^APP_NAME=.*','APP_NAME=SisREAd' -replace '^APP_URL=.*','APP_URL=http://127.0.0.1:8000' | Set-Content .env
    php artisan key:generate
}
php artisan migrate --force
npm install
npm run build
Write-Host "Setup concluído. Rode scripts\start.ps1"
