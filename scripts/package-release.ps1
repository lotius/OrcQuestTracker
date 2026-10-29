<#
.SYNOPSIS
    Builds an upload-ready zip of the tracker for shared hosting (cPanel and similar).

.DESCRIPTION
    Builds the front end, installs production-only PHP packages, and zips the tracked project files
    together with vendor/ and public/build/ into dist\orcquest-tracker-<date>.zip. Your .env, database
    and storage contents are never included. Development packages are restored afterwards.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File scripts\package-release.ps1
#>
param(
    [string] $OutputDirectory = 'dist'
)

$ErrorActionPreference = 'Stop'
$root = Split-Path $PSScriptRoot -Parent
Set-Location $root

$stamp = Get-Date -Format 'yyyy-MM-dd-HHmm'
$stage = Join-Path ([System.IO.Path]::GetTempPath()) "orcquest-release-$stamp"
$zip = Join-Path $root (Join-Path $OutputDirectory "orcquest-tracker-$stamp.zip")

Write-Host 'Building front end...' -ForegroundColor Green
npm run build
if ($LASTEXITCODE -ne 0) { throw 'npm run build failed' }

Write-Host 'Installing production PHP packages...' -ForegroundColor Green
composer install --no-dev --optimize-autoloader --no-interaction --quiet
if ($LASTEXITCODE -ne 0) { throw 'composer install --no-dev failed' }

try {
    Write-Host 'Collecting files...' -ForegroundColor Green
    New-Item -ItemType Directory -Force -Path $stage | Out-Null

    # Tracked files only (no node_modules, .env, database or local storage), minus things a server never needs.
    $files = git ls-files | Where-Object { $_ -notmatch '^(tests|docs|scripts)/' -and $_ -notmatch '^\.(github|editorconfig|gitattributes)' }
    foreach ($file in $files) {
        $target = Join-Path $stage $file
        New-Item -ItemType Directory -Force -Path (Split-Path $target -Parent) | Out-Null
        Copy-Item -LiteralPath $file -Destination $target
    }

    robocopy vendor (Join-Path $stage 'vendor') /E /NFL /NDL /NJH /NJS /NP | Out-Null
    robocopy 'public\build' (Join-Path $stage 'public\build') /E /NFL /NDL /NJH /NJS /NP | Out-Null
    # robocopy exit codes below 8 mean success
    if ($LASTEXITCODE -ge 8) { throw 'Copying vendor/ or public/build/ failed' }

    Write-Host 'Zipping...' -ForegroundColor Green
    New-Item -ItemType Directory -Force -Path (Split-Path $zip -Parent) | Out-Null
    if (Test-Path $zip) { Remove-Item $zip }
    # Windows' own bsdtar writes zip files; a Git-for-Windows tar earlier on PATH cannot.
    & (Join-Path $env:SystemRoot 'System32\tar.exe') -a -c -f $zip -C $stage .
    if ($LASTEXITCODE -ne 0) { throw 'Creating the zip failed' }

    $size = [math]::Round((Get-Item $zip).Length / 1MB, 1)
    Write-Host "Done: $zip ($size MB)" -ForegroundColor Green
}
finally {
    Remove-Item -Recurse -Force $stage -ErrorAction SilentlyContinue
    Write-Host 'Restoring development PHP packages...' -ForegroundColor Green
    composer install --no-interaction --quiet
}
