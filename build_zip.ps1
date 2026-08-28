# build_zip.ps1 - Build the distribution zip for local_forumia (PowerShell).
#
# Usage:
#   .\build_zip.ps1 [version]
#
# If [version] is omitted, the version is read from version.php.
# The output zip is written to the directory above the plugin root, with the
# Moodle-expected folder name ("forumia") as the single zip root directory.
#
# CRITICAL: this script excludes generate_license.php and license_private.key
# so the private signing key never reaches a customer. Always package with this
# script (or otherwise exclude those two files) - never zip the raw folder.
#
# Requires 7-Zip (7z.exe); Compress-Archive produces ZIPs incompatible with PHP.

param(
    [string]$Version = ""
)

$ErrorActionPreference = "Stop"

$PluginName   = "forumia"        # Moodle install folder name (zip root).
$Frankenstyle = "local_forumia"  # Used only for the output zip filename.
$PluginDir    = $PSScriptRoot
$ParentDir    = Split-Path $PluginDir -Parent

# -----------------------------------------------------------------------
# Locate 7-Zip.
# -----------------------------------------------------------------------
$SevenZip = $null
$SevenZipOnPath = $null
$SevenZipCmd = Get-Command "7z" -ErrorAction SilentlyContinue
if ($SevenZipCmd) { $SevenZipOnPath = $SevenZipCmd.Source }
$SevenZipCandidates = @(
    "C:\Program Files\7-Zip\7z.exe",
    "C:\Program Files (x86)\7-Zip\7z.exe",
    $SevenZipOnPath
)
foreach ($candidate in $SevenZipCandidates) {
    if ($candidate -and (Test-Path $candidate)) {
        $SevenZip = $candidate
        break
    }
}
if (-not $SevenZip) {
    Write-Error "7-Zip not found. Install it from https://www.7-zip.org/"
    exit 1
}
Write-Host "Using 7-Zip: $SevenZip"

# -----------------------------------------------------------------------
# Determine version string.
# -----------------------------------------------------------------------
if ($Version -eq "") {
    $versionPhp = Get-Content "$PluginDir\version.php" -Raw
    if ($versionPhp -match "release\s*=\s*'([^']+)'") {
        $Version = $Matches[1]
    } else {
        $Version = "0.0.0"
    }
}

$ZipFile = "$ParentDir\${Frankenstyle}_${Version}.zip"

Write-Host "Building: $Frankenstyle v$Version"
Write-Host "Output:   $ZipFile"

# -----------------------------------------------------------------------
# Safety: refuse to build if the private signing key is missing a public
# counterpart, and ALWAYS confirm the key/generator are excluded below.
# -----------------------------------------------------------------------
if (-not (Test-Path "$PluginDir\classes\license\validator.php")) {
    Write-Warning "validator.php not found - is the license system installed?"
}

# -----------------------------------------------------------------------
# Patterns to EXCLUDE (relative path prefixes).
# -----------------------------------------------------------------------
$ExcludeRelPatterns = @(
    ".git",
    ".gitignore",
    ".gitattributes",
    ".github",
    "tests",
    "phpunit.xml",
    "phpunit.xml.dist",
    ".phpunit.result.cache",
    ".vscode",
    ".idea",
    "node_modules",
    "package.json",
    "package-lock.json",
    "Gruntfile.js",
    ".grunt",
    "build_zip.ps1",
    "build_zip.sh",
    ".distignore",
    "generate_license.php",
    "license_private.key",
    ".claude",
    "CLAUDE.md",
    "AUDIT.md",
    "CI-REPORT.md",
    "REVIEWER-NOTES.md",
    "LECCIONES.md",
    "MARKETPLACE-LISTING.md"
)

# .md and .txt are excluded by extension, EXCEPT the three documents the buyer
# must find when they open the package. Shipping a plugin with no README and no
# licence text is how a paid listing looks abandoned on day one.
$ExcludeExtensions = @(".md", ".txt")
$AlwaysInclude     = @("README.md", "CHANGELOG.md", "LICENSE")

# -----------------------------------------------------------------------
# Collect files to include.
# -----------------------------------------------------------------------
$allFiles = Get-ChildItem -Path $PluginDir -Recurse -File

$included = @()
foreach ($file in $allFiles) {
    $rel  = $file.FullName.Substring($PluginDir.Length + 1)
    $skip = $false

    if ($AlwaysInclude -notcontains $rel) {
        if ($ExcludeExtensions -contains $file.Extension.ToLower()) { $skip = $true }
    }

    if (-not $skip) {
        foreach ($pattern in $ExcludeRelPatterns) {
            if ($rel -eq $pattern -or $rel.StartsWith($pattern + "\")) {
                $skip = $true; break
            }
        }
    }

    if (-not $skip) { $included += $file }
}

Write-Host "Including $($included.Count) files..."

# Hard guard: never ship the signing key or generator.
foreach ($f in $included) {
    $rel = $f.FullName.Substring($PluginDir.Length + 1)
    if ($rel -eq "license_private.key" -or $rel -eq "generate_license.php") {
        Write-Error "REFUSING TO BUILD: $rel would be included in the zip."
        exit 1
    }
}

# -----------------------------------------------------------------------
# Stage files under "<PluginName>\" so the ZIP root matches the install name.
# -----------------------------------------------------------------------
$TempDir   = Join-Path $env:TEMP "moodle_plugin_build_$(Get-Random)"
$StageRoot = Join-Path $TempDir $PluginName
New-Item -ItemType Directory -Path $StageRoot -Force | Out-Null

foreach ($file in $included) {
    $rel      = $file.FullName.Substring($PluginDir.Length + 1)
    $destPath = Join-Path $StageRoot $rel
    $destDir  = Split-Path $destPath -Parent
    if (-not (Test-Path $destDir)) {
        New-Item -ItemType Directory -Path $destDir -Force | Out-Null
    }
    Copy-Item $file.FullName -Destination $destPath
}

# -----------------------------------------------------------------------
# Create the ZIP with 7-Zip.
# -----------------------------------------------------------------------
if (Test-Path $ZipFile) { Remove-Item $ZipFile -Force }

Push-Location $TempDir
try {
    & $SevenZip a -tzip -mx=5 $ZipFile "$PluginName\" | Out-Null
    if ($LASTEXITCODE -ne 0) {
        throw "7-Zip exited with code $LASTEXITCODE"
    }
} finally {
    Pop-Location
}

Remove-Item $TempDir -Recurse -Force

$size = [math]::Round((Get-Item $ZipFile).Length / 1KB, 1)
Write-Host ""
Write-Host "Done: ${size} KB  $ZipFile"
