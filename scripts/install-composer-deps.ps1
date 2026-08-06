# Installs PhpSpreadsheet via Composer using Local WP's bundled PHP.
# Works from regular PowerShell (php/composer do not need to be on PATH).

$ErrorActionPreference = 'Stop'

$pluginRoot = Split-Path -Parent $PSScriptRoot
Set-Location $pluginRoot

function Get-LocalSitePath {
	$dir = $pluginRoot
	for ($i = 0; $i -lt 8; $i++) {
		if ((Split-Path -Leaf $dir) -eq 'public' -and (Split-Path -Leaf (Split-Path -Parent $dir)) -eq 'app') {
			return Split-Path -Parent (Split-Path -Parent $dir)
		}
		$parent = Split-Path -Parent $dir
		if ([string]::IsNullOrEmpty($parent) -or $parent -eq $dir) {
			break
		}
		$dir = $parent
	}
	return $null
}

function Find-LocalPhpIni {
	$sitePath = Get-LocalSitePath
	if (-not $sitePath) {
		return $null
	}

	$needle = ($sitePath -replace '\\', '/').ToLowerInvariant()
	$runRoot  = Join-Path $env:APPDATA 'Local\run'
	if (-not (Test-Path $runRoot)) {
		return $null
	}

	foreach ($ini in Get-ChildItem -Path $runRoot -Recurse -Filter 'php.ini' -ErrorAction SilentlyContinue) {
		$content = Get-Content -Path $ini.FullName -Raw -ErrorAction SilentlyContinue
		if ($content -and ($content.ToLowerInvariant().Contains($needle) -or $content.ToLowerInvariant().Contains($needle.Replace('/', '\')))) {
			return $ini.FullName
		}
	}

	return $null
}

function Find-LocalPhp {
	$servicesRoot = Join-Path $env:APPDATA 'Local\lightning-services'
	if (-not (Test-Path $servicesRoot)) {
		return $null
	}

	$phpDirs = Get-ChildItem -Path $servicesRoot -Directory -Filter 'php-*' -ErrorAction SilentlyContinue |
		Sort-Object Name -Descending

	foreach ($dir in $phpDirs) {
		$phpExe = Join-Path $dir.FullName 'bin\win64\php.exe'
		if (Test-Path $phpExe) {
			return @{
				Exe = $phpExe
				Ext = Join-Path $dir.FullName 'bin\win64\ext'
			}
		}
	}

	return $null
}

$php = Find-LocalPhp
if (-not $php) {
	Write-Error @"
Could not find Local WP PHP.

Option A (recommended): In Local, right-click your site -> Open Site Shell, then run:
  composer install --no-dev

Option B: Install PHP 8+ and Composer globally, add them to PATH, then run:
  composer install --no-dev
"@
}

$phpIni = Find-LocalPhpIni
$phpConfigArgs = @()
if ($phpIni) {
	$phpConfigArgs = @('-c', $phpIni)
	Write-Host "Using site php.ini: $phpIni"
} else {
	$phpConfigArgs = @(
		'-d', "extension_dir=$($php.Ext)",
		'-d', 'extension=php_openssl.dll',
		'-d', 'extension=php_zip.dll',
		'-d', 'extension=php_mbstring.dll',
		'-d', 'extension=php_fileinfo.dll',
		'-d', 'extension=php_gd.dll'
	)
	Write-Host 'Using bundled PHP extensions (site php.ini not found).'
}

$composerPhar = Join-Path $pluginRoot 'composer.phar'
if (-not (Test-Path $composerPhar)) {
	Write-Host 'Downloading Composer...'
	Invoke-WebRequest -Uri 'https://getcomposer.org/download/latest-stable/composer.phar' -OutFile $composerPhar -UseBasicParsing
}

$phpArgs = @(
	$phpConfigArgs
	$composerPhar,
	'install',
	'--no-dev',
	'--no-interaction'
)

Write-Host "Using PHP: $($php.Exe)"
Write-Host 'Running: composer install --no-dev'
& $php.Exe @phpArgs

if ($LASTEXITCODE -ne 0) {
	exit $LASTEXITCODE
}

Write-Host ''
Write-Host 'Done. PhpSpreadsheet is installed in vendor/. Excel export should work now.'
