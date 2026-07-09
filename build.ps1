#Requires -Version 5.1
<#
.SYNOPSIS
    Compila localmente el .zip del plugin Consentia.
.DESCRIPTION
    Genera dist\consentia.zip, listo para subir en Plugins -> Subir plugin.
    Reproduce lo que hace el workflow de GitHub Actions.
.EXAMPLE
    .\build.ps1
#>

$ErrorActionPreference = 'Stop'
$root    = $PSScriptRoot
$slug    = 'consentia'
$dist    = Join-Path $root 'dist'
$work    = Join-Path $root 'build'
$exclude = @('.git', '.github', 'build', 'dist', '.gitignore', '.gitattributes', 'build.ps1')

foreach ($d in @($dist, $work)) {
    if (Test-Path $d) { Remove-Item $d -Recurse -Force }
    New-Item -ItemType Directory -Path $d | Out-Null
}

$pluginDir = Join-Path $work $slug
New-Item -ItemType Directory -Path $pluginDir | Out-Null
Get-ChildItem -Path $root -Force | Where-Object { $exclude -notcontains $_.Name } | ForEach-Object {
    if ($_.PSIsContainer) {
        Copy-Item $_.FullName -Destination (Join-Path $pluginDir $_.Name) -Recurse -Force
    } else {
        Copy-Item $_.FullName -Destination $pluginDir -Force
    }
}

$zip = Join-Path $dist "$slug.zip"
Compress-Archive -Path $pluginDir -DestinationPath $zip -CompressionLevel Optimal
Write-Host "OK  plugin -> $zip" -ForegroundColor Green
Write-Host "`nSube $slug.zip en  Plugins -> Anadir nuevo -> Subir plugin" -ForegroundColor Cyan
