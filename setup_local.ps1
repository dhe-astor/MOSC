# setup_local.ps1
# PowerShell script to download and extract portable PHP, Composer, and Node.js

$ErrorActionPreference = "Stop"

$projectRoot = Get-Location
$toolsDir = Join-Path $projectRoot "tools"

if (-not (Test-Path $toolsDir)) {
    New-Item -ItemType Directory -Path $toolsDir | Out-Null
    Write-Host "Created tools directory at $toolsDir"
}

# 1. Download & Extract PHP
$phpDest = Join-Path $toolsDir "php"
if (-not (Test-Path $phpDest)) {
    $phpUrl = "https://windows.php.net/downloads/releases/archives/php-8.3.11-nts-Win32-vs16-x64.zip"
    $phpZip = Join-Path $toolsDir "php.zip"
    
    Write-Host "Downloading PHP 8.3..."
    try {
        Invoke-WebRequest -Uri $phpUrl -OutFile $phpZip
    } catch {
        $phpUrlFallback = "https://downloads.php.net/~windows/releases/archives/php-8.3.11-nts-Win32-vs16-x64.zip"
        Write-Host "Primary URL failed. Trying fallback URL..."
        Invoke-WebRequest -Uri $phpUrlFallback -OutFile $phpZip
    }
    
    Write-Host "Extracting PHP..."
    Expand-Archive -Path $phpZip -DestinationPath $phpDest
    Remove-Item $phpZip
    Write-Host "PHP extracted to $phpDest"
} else {
    Write-Host "PHP already exists at $phpDest"
}

# 2. Configure php.ini
$phpIni = Join-Path $phpDest "php.ini"
if (-not (Test-Path $phpIni)) {
    $phpIniDev = Join-Path $phpDest "php.ini-development"
    if (Test-Path $phpIniDev) {
        Copy-Item $phpIniDev $phpIni
        Write-Host "Created php.ini from php.ini-development"
        
        # Modify php.ini to enable extensions
        $content = Get-Content $phpIni
        
        # Uncomment extension_dir
        $content = $content -replace ';extension_dir = "ext"', 'extension_dir = "ext"'
        
        # Enable extensions
        $content = $content -replace ';extension=curl', 'extension=curl'
        $content = $content -replace ';extension=fileinfo', 'extension=fileinfo'
        $content = $content -replace ';extension=gd', 'extension=gd'
        $content = $content -replace ';extension=exif', 'extension=exif'
        $content = $content -replace ';extension=mbstring', 'extension=mbstring'
        $content = $content -replace ';extension=openssl', 'extension=openssl'
        $content = $content -replace ';extension=pdo_sqlite', 'extension=pdo_sqlite'
        $content = $content -replace ';extension=sqlite3', 'extension=sqlite3'
        $content = $content -replace ';extension=zip', 'extension=zip'
        
        Set-Content $phpIni $content
        Write-Host "Configured php.ini with sqlite3, pdo_sqlite, openssl, mbstring, curl, fileinfo, gd, exif, and zip extensions enabled."
    }
}

# 3. Download Composer
$composerPhar = Join-Path $phpDest "composer.phar"
if (-not (Test-Path $composerPhar)) {
    $composerUrl = "https://getcomposer.org/composer.phar"
    Write-Host "Downloading Composer..."
    Invoke-WebRequest -Uri $composerUrl -OutFile $composerPhar
    
    # Create composer.bat
    $composerBat = Join-Path $phpDest "composer.bat"
    '@php "%~dp0composer.phar" %*' | Set-Content $composerBat
    Write-Host "Composer downloaded and helper batch file created."
} else {
    Write-Host "Composer already exists."
}

# 4. Download & Extract Node.js
$nodeDest = Join-Path $toolsDir "node"
if (-not (Test-Path $nodeDest)) {
    $nodeUrl = "https://nodejs.org/dist/v20.11.1/node-v20.11.1-win-x64.zip"
    $nodeZip = Join-Path $toolsDir "node.zip"
    
    Write-Host "Downloading Node.js..."
    Invoke-WebRequest -Uri $nodeUrl -OutFile $nodeZip
    
    Write-Host "Extracting Node.js..."
    Expand-Archive -Path $nodeZip -DestinationPath $toolsDir
    
    # Move and rename folder to tools\node
    $extractedNodeDir = Join-Path $toolsDir "node-v20.11.1-win-x64"
    if (Test-Path $extractedNodeDir) {
        Rename-Item -Path $extractedNodeDir -NewName "node"
    }
    Remove-Item $nodeZip
    Write-Host "Node.js extracted to $nodeDest"
} else {
    Write-Host "Node.js already exists at $nodeDest"
}

Write-Host "Setup complete!"
